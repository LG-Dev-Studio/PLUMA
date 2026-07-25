<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Mockery;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\EnrutadorModelos;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\ProveedorOpenRouter;
use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use WP_Error;

/**
 * Fixture real (anonimizada) de una respuesta de
 * `POST https://openrouter.ai/api/v1/chat/completions`, verificada contra la
 * documentación y el catálogo real de OpenRouter antes de escribir el
 * proveedor (GOVERNANCE §4.4: la suite jamás llama a la API real).
 *
 * @covers \Pluma\Proveedores\ProveedorOpenRouter
 */
final class ProveedorOpenRouterTest extends CasoDePruebaUnitario {

	private const OPCION_FALLOS        = 'pluma_proveedor_lenguaje_fallos';
	private const OPCION_ABIERTO_HASTA = 'pluma_proveedor_lenguaje_abierto_hasta';

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	private function fixtureDecodificada(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de un fixture local del repo, no una URL remota.
		$contenido = file_get_contents( __DIR__ . '/../../Fixtures/openrouter-respuesta-2026-07-23.json' );
		self::assertIsString( $contenido );

		$datos = json_decode( $contenido, true );
		self::assertIsArray( $datos );

		return $datos;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function mockearOpciones( array $overrides = array() ): void {
		$valores = array_merge(
			array(
				ProveedorOpenRouter::OPCION_LLAVE_CIFRADA => Cifrado::cifrar( 'sk-or-v1-llave-de-prueba' ),
				self::OPCION_FALLOS                       => 0,
				self::OPCION_ABIERTO_HASTA                => 0,
				PresupuestoLenguaje::OPCION_LIMITE_DIARIO => 5.0,
				PresupuestoLenguaje::OPCION_GASTO         => array(
					'dia'   => ( new RelojFijo() )->ahora()->format( 'Y-m-d' ),
					'gasto' => 0.0,
				),
				EnrutadorModelos::OPCION_MODELO_ECONOMICO => 'anthropic/claude-haiku-4.5',
				EnrutadorModelos::OPCION_MODELO_PREMIUM   => 'anthropic/claude-sonnet-5',
			),
			$overrides
		);

		Functions\when( 'get_option' )->alias(
			static fn ( string $opcion, $defecto = false ) => array_key_exists( $opcion, $valores ) ? $valores[ $opcion ] : $defecto
		);
	}

	private function proveedor(): ProveedorOpenRouter {
		$reloj = new RelojFijo();

		return new ProveedorOpenRouter( new EnrutadorModelos(), new PresupuestoLenguaje( $reloj ), $reloj );
	}

	private function peticionDePrueba(): PeticionLenguaje {
		return new PeticionLenguaje(
			PropositoLenguaje::Redactar,
			'Eres un periodista sintético de prueba.',
			'Extracto del expediente de investigación.',
			1024
		);
	}

	public function test_completar_devuelve_una_respuesta_exitosa_a_partir_de_la_fixture_real(): void {
		$this->mockearOpciones();

		Functions\when( 'home_url' )->justReturn( 'https://cliente-de-prueba.test' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de un fixture local del repo, no una URL remota.
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( file_get_contents( __DIR__ . '/../../Fixtures/openrouter-respuesta-2026-07-23.json' ) );

		$gastoCapturado = null;
		Functions\when( 'update_option' )->alias(
			static function ( string $opcion, $valor ) use ( &$gastoCapturado ): bool {
				if ( PresupuestoLenguaje::OPCION_GASTO === $opcion ) {
					$gastoCapturado = $valor;
				}

				return true;
			}
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );

		$respuesta = $this->proveedor()->completar( $this->peticionDePrueba() );

		self::assertStringContainsString( 'El mercado reacciona ante el nuevo dato', $respuesta->contenido );
		self::assertSame( 512, $respuesta->tokensEntrada );
		self::assertSame( 128, $respuesta->tokensSalida );
		self::assertSame( 0.0034, $respuesta->costeUsd );
		self::assertSame( 'openrouter', $respuesta->proveedor );
		self::assertSame( 'anthropic/claude-sonnet-5', $respuesta->modelo );
		self::assertFalse( $respuesta->truncada );
		self::assertGreaterThanOrEqual( 0, $respuesta->latenciaMs );

		self::assertIsArray( $gastoCapturado );
		self::assertSame( 0.0034, $gastoCapturado['gasto'] );
	}

	public function test_marca_truncada_cuando_el_finish_reason_es_length(): void {
		$this->mockearOpciones();

		$datos                                = $this->fixtureDecodificada();
		$datos['choices'][0]['finish_reason'] = 'length';

		Functions\when( 'home_url' )->justReturn( 'https://cliente-de-prueba.test' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- prepara el cuerpo simulado de wp_remote_post con codificación estándar, deliberadamente independiente del alias de wp_json_encode ya simulado en este test.
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( (string) json_encode( $datos ) );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );

		$respuesta = $this->proveedor()->completar( $this->peticionDePrueba() );

		self::assertTrue( $respuesta->truncada );
	}

	public function test_lanza_excepcion_sin_credenciales_si_no_hay_llave_guardada(): void {
		$this->mockearOpciones( array( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA => '' ) );

		try {
			$this->proveedor()->completar( $this->peticionDePrueba() );
			self::fail( 'Se esperaba ProveedorLenguajeException.' );
		} catch ( ProveedorLenguajeException $excepcion ) {
			self::assertTrue( $excepcion->sinCredenciales );
			self::assertFalse( $excepcion->presupuestoAgotado );
		}
	}

	public function test_lanza_excepcion_sin_credenciales_si_las_salts_rotaron_y_el_sobre_ya_no_descifra(): void {
		// Un sobre cifrado con otra clave (simulación de rotación de salts):
		// Cifrado::descifrar() devuelve null y el proveedor lo trata igual que
		// "sin credenciales", nunca como un error opaco distinto.
		$claveVieja = sodium_crypto_generichash( 'otra-app-keyotra-secure-key', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cifrado    = sodium_crypto_secretbox( 'sk-or-v1-vieja', $nonce, $claveVieja );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- codificación binario-a-texto para el caso de prueba, no ofuscación de código.
		$sobre = 'pluma_v1:' . base64_encode( $nonce . $cifrado );

		$this->mockearOpciones( array( ProveedorOpenRouter::OPCION_LLAVE_CIFRADA => $sobre ) );

		$this->expectException( ProveedorLenguajeException::class );

		$this->proveedor()->completar( $this->peticionDePrueba() );
	}

	public function test_lanza_excepcion_de_presupuesto_agotado_si_no_queda_presupuesto_disponible(): void {
		$this->mockearOpciones(
			array(
				PresupuestoLenguaje::OPCION_LIMITE_DIARIO => 5.0,
				PresupuestoLenguaje::OPCION_GASTO         => array(
					'dia'   => ( new RelojFijo() )->ahora()->format( 'Y-m-d' ),
					'gasto' => 5.0,
				),
			)
		);

		Functions\expect( 'wp_remote_post' )->never();

		try {
			$this->proveedor()->completar( $this->peticionDePrueba() );
			self::fail( 'Se esperaba ProveedorLenguajeException.' );
		} catch ( ProveedorLenguajeException $excepcion ) {
			self::assertTrue( $excepcion->presupuestoAgotado );
		}
	}

	public function test_lanza_excepcion_y_registra_el_fallo_si_wp_remote_post_devuelve_wp_error(): void {
		$this->mockearOpciones();

		Functions\when( 'home_url' )->justReturn( 'https://cliente-de-prueba.test' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_post' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $valor ): bool => $valor instanceof WP_Error );

		Functions\expect( 'update_option' )
			->once()
			->with( self::OPCION_FALLOS, 1, false )
			->andReturn( true );

		$this->expectException( ProveedorLenguajeException::class );

		$this->proveedor()->completar( $this->peticionDePrueba() );
	}

	public function test_lanza_excepcion_si_el_codigo_http_no_es_200(): void {
		$this->mockearOpciones();

		Functions\when( 'home_url' )->justReturn( 'https://cliente-de-prueba.test' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 503 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 503 );
		Functions\when( 'update_option' )->justReturn( true );

		$this->expectException( ProveedorLenguajeException::class );

		$this->proveedor()->completar( $this->peticionDePrueba() );
	}

	public function test_el_tercer_fallo_consecutivo_abre_el_circuito(): void {
		$this->mockearOpciones( array( self::OPCION_FALLOS => 2 ) );

		Functions\when( 'home_url' )->justReturn( 'https://cliente-de-prueba.test' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_post' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $valor ): bool => $valor instanceof WP_Error );

		Functions\expect( 'update_option' )
			->once()
			->with( self::OPCION_FALLOS, 3, false )
			->andReturn( true );
		Functions\expect( 'update_option' )
			->once()
			->with( self::OPCION_ABIERTO_HASTA, ( new RelojFijo() )->ahora()->getTimestamp() + 300, false )
			->andReturn( true );

		try {
			$this->proveedor()->completar( $this->peticionDePrueba() );
			self::fail( 'Se esperaba ProveedorLenguajeException.' );
		} catch ( ProveedorLenguajeException $excepcion ) {
			// El fallo de red se propaga sin marcar presupuesto ni credenciales;
			// lo que interesa de este test es la apertura del circuito, verificada
			// arriba mediante las expectativas de update_option.
			self::assertFalse( $excepcion->presupuestoAgotado );
			self::assertFalse( $excepcion->sinCredenciales );
		}
	}

	public function test_circuito_abierto_impide_una_nueva_llamada_de_red(): void {
		$this->mockearOpciones(
			array(
				self::OPCION_ABIERTO_HASTA => ( new RelojFijo() )->ahora()->getTimestamp() + 100,
			)
		);

		Functions\expect( 'wp_remote_post' )->never();

		$this->expectException( ProveedorLenguajeException::class );
		$this->expectExceptionMessageMatches( '/[Cc]ircuito abierto/' );

		$this->proveedor()->completar( $this->peticionDePrueba() );
	}

	public function test_circuito_abierto_expone_el_mismo_estado_para_la_sala_de_maquinas(): void {
		$this->mockearOpciones( array( self::OPCION_ABIERTO_HASTA => ( new RelojFijo() )->ahora()->getTimestamp() + 100 ) );

		self::assertTrue( $this->proveedor()->circuitoAbierto() );
	}

	public function test_circuito_cerrado_cuando_no_hay_enfriamiento_activo(): void {
		$this->mockearOpciones( array( self::OPCION_ABIERTO_HASTA => 0 ) );

		self::assertFalse( $this->proveedor()->circuitoAbierto() );
	}

	public function test_probar_llave_devuelve_verdadero_si_openrouter_responde_200(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				'https://openrouter.ai/api/v1/key',
				Mockery::on( static fn ( array $args ): bool => 'Bearer sk-or-v1-a-probar' === ( $args['headers']['Authorization'] ?? null ) )
			)
			->andReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );

		self::assertTrue( $this->proveedor()->probarLlave( 'sk-or-v1-a-probar' ) );
	}

	public function test_probar_llave_devuelve_falso_si_openrouter_rechaza_la_llave(): void {
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 401 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 401 );

		self::assertFalse( $this->proveedor()->probarLlave( 'sk-or-v1-invalida' ) );
	}

	public function test_probar_llave_devuelve_falso_si_hay_un_fallo_de_red(): void {
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'Timeout' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $valor ): bool => $valor instanceof WP_Error );

		self::assertFalse( $this->proveedor()->probarLlave( 'sk-or-v1-cualquiera' ) );
	}

	/**
	 * Nivel Tres J.2: OpenRouter nombra sus modelos `{proveedor}/{modelo}` —
	 * el prefijo ya es la familia real, no una taxonomía inventada.
	 */
	public function test_familia_de_deriva_el_prefijo_del_proveedor_en_el_slug(): void {
		self::assertSame( 'anthropic', $this->proveedor()->familiaDe( 'anthropic/claude-sonnet-5' ) );
		self::assertSame( 'openai', $this->proveedor()->familiaDe( 'openai/gpt-5' ) );
	}

	public function test_familia_de_sin_barra_devuelve_el_slug_completo(): void {
		self::assertSame( 'modelo-sin-barra', $this->proveedor()->familiaDe( 'modelo-sin-barra' ) );
	}
}
