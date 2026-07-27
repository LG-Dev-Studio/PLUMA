<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EntradaMemoria;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\DecisionEditorialException;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\PosturaColectiva;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SelectorAngulo;
use Pluma\Redaccion\TipoMemoria;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * Paso 3 del Algoritmo de Decisión Editorial (Libro Cap. 5.5) y
 * pl-periodistas §3 "memoria antes de tesis".
 *
 * @covers \Pluma\Redaccion\SelectorAngulo
 */
final class SelectorAnguloTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'Escéptica del poder.', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, false, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Valentina Ruiz',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'un hecho verificado', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function clasificacion(): ClasificacionNoticia {
		return new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico );
	}

	public function test_devuelve_los_candidatos_que_superan_el_umbral_de_sustento(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": ['
				. '{"tesis": "tesis fuerte", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 90, "puntuacionConversacional": 60},'
				. '{"tesis": "tesis sin datos", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 10, "puntuacionConversacional": 60}'
				. ']}'
		);

		$candidatos = ( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array() );

		self::assertCount( 1, $candidatos );
		self::assertSame( 'tesis fuerte', $candidatos[0]->tesis );
	}

	public function test_lanza_excepcion_si_ningun_candidato_supera_el_umbral(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": [{"tesis": "tesis débil", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 80, "puntuacionSustento": 5, "puntuacionConversacional": 60}]}'
		);

		$this->expectException( DecisionEditorialException::class );

		( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array() );
	}

	public function test_elegir_ganadora_devuelve_el_indice_de_mayor_puntuacion_total(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": ['
				. '{"tesis": "floja", "puntuacionOriginalidad": 40, "puntuacionCompatibilidadLinea": 40, "puntuacionSustento": 50, "puntuacionConversacional": 40},'
				. '{"tesis": "fuerte", "puntuacionOriginalidad": 90, "puntuacionCompatibilidadLinea": 90, "puntuacionSustento": 90, "puntuacionConversacional": 90}'
				. ']}'
		);

		$selector   = new SelectorAngulo( $proveedor );
		$candidatos = $selector->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array() );

		self::assertSame( 'fuerte', $candidatos[ $selector->elegirGanadora( $candidatos ) ]->tesis );
	}

	/**
	 * Nivel Tres K.1: el sustento ya se aplicó como piso eliminatorio (ambos
	 * candidatos superan `UMBRAL_SUSTENTO_MINIMO`) — a partir de ahí, un
	 * sustento más alto NO debe inclinar la balanza. La ganadora se decide
	 * solo por originalidad/compatibilidad/conversacional.
	 */
	public function test_puntuacion_total_ignora_el_sustento_una_vez_superado_el_piso(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": ['
				. '{"tesis": "menos sustento pero mejor en lo demás", "puntuacionOriginalidad": 90, "puntuacionCompatibilidadLinea": 90, "puntuacionSustento": 45, "puntuacionConversacional": 90},'
				. '{"tesis": "mucho más sustento pero peor en lo demás", "puntuacionOriginalidad": 40, "puntuacionCompatibilidadLinea": 40, "puntuacionSustento": 100, "puntuacionConversacional": 40}'
				. ']}'
		);

		$selector   = new SelectorAngulo( $proveedor );
		$candidatos = $selector->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array() );

		self::assertSame( 'menos sustento pero mejor en lo demás', $candidatos[ $selector->elegirGanadora( $candidatos ) ]->tesis );
	}

	public function test_lanza_excepcion_si_la_respuesta_llego_truncada(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 70, "puntuacionSustento": 70, "puntuacionConversacional": 70}]}',
			truncada: true
		);

		$this->expectException( DecisionEditorialException::class );

		( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array() );
	}

	public function test_las_posturas_previas_viajan_en_el_material_para_la_memoria_antes_de_tesis(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 70, "puntuacionSustento": 70, "puntuacionConversacional": 70}]}'
		);

		$posturaPrevia = new EntradaMemoria(
			1,
			1,
			TipoMemoria::Postura,
			'economia',
			array( 'postura' => 'La inflación bajará este trimestre.' ),
			null,
			new DateTimeImmutable( '2026-04-01T00:00:00+00:00' )
		);

		( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array( $posturaPrevia ) );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'La inflación bajará este trimestre.', $proveedor->ultimaPeticion->material );
	}

	public function test_sin_posturas_previas_el_material_lo_indica_explicitamente(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 70, "puntuacionSustento": 70, "puntuacionConversacional": 70}]}'
		);

		( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array() );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'no tiene posturas previas', $proveedor->ultimaPeticion->material );
	}

	/**
	 * Nivel Dos E.2, memoria colectiva del sitio: una postura de un colega
	 * ACTIVO se atribuye por nombre — nunca como si fuera la propia postura
	 * anterior del periodista actual.
	 */
	public function test_una_postura_colectiva_de_periodista_activo_se_atribuye_por_nombre(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 70, "puntuacionSustento": 70, "puntuacionConversacional": 70}]}'
		);

		$posturaColectiva = new PosturaColectiva(
			new EntradaMemoria( 2, 2, TipoMemoria::Postura, 'economia', array( 'postura' => 'La reforma fiscal es necesaria.' ), null, new DateTimeImmutable( '2026-04-01T00:00:00+00:00' ) ),
			'Otro Periodista',
			true
		);

		( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array(), array( $posturaColectiva ) );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'un colega de esta redacción, Otro Periodista, sostuvo: La reforma fiscal es necesaria.', $proveedor->ultimaPeticion->material );
	}

	/**
	 * Nivel Dos E.2: una postura de un periodista JUBILADO se atribuye a la
	 * redacción como voz colectiva, no al individuo — el propio texto fuente
	 * exige este cambio de forma exacto.
	 */
	public function test_una_postura_colectiva_de_periodista_jubilado_se_atribuye_a_la_redaccion(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 70, "puntuacionSustento": 70, "puntuacionConversacional": 70}]}'
		);

		$posturaColectiva = new PosturaColectiva(
			new EntradaMemoria( 3, 3, TipoMemoria::Postura, 'economia', array( 'postura' => 'La reforma fiscal era innecesaria.' ), null, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) ),
			'Periodista Jubilado',
			false
		);

		( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array(), array( $posturaColectiva ) );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'esta redacción sostuvo antes', $proveedor->ultimaPeticion->material );
		self::assertStringNotContainsString( 'Periodista Jubilado, sostuvo', $proveedor->ultimaPeticion->material );
	}

	public function test_sin_posturas_colectivas_el_material_lo_indica_explicitamente(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"candidatos": [{"tesis": "x", "puntuacionOriginalidad": 70, "puntuacionCompatibilidadLinea": 70, "puntuacionSustento": 70, "puntuacionConversacional": 70}]}'
		);

		( new SelectorAngulo( $proveedor ) )->generarCandidatos( $this->periodista(), $this->expediente(), $this->clasificacion(), array() );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'Ningún otro periodista de esta redacción tiene posturas previas', $proveedor->ultimaPeticion->material );
	}
}
