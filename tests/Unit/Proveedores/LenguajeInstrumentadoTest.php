<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Pluma\Kernel\ContextoEjecucion;
use Pluma\Proveedores\EnrutadorModelos;
use Pluma\Proveedores\LenguajeInstrumentado;
use Pluma\Proveedores\OrigenLlamada;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Proveedores\ResultadoLlamada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeQueFalla;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use Pluma\Tests\Unit\Dobles\RepositorioLlamadasModeloEnMemoria;

/**
 * NCP-1 (`ADR 0010`). Contrato de esta porción: CERO cambio de
 * comportamiento — cada test verifica primero que la respuesta/excepción
 * real pasa intacta, y solo después que el registro es correcto.
 *
 * @covers \Pluma\Proveedores\LenguajeInstrumentado
 */
final class LenguajeInstrumentadoTest extends CasoDePruebaUnitario {

	private function peticion( PropositoLenguaje $proposito = PropositoLenguaje::Redactar ): PeticionLenguaje {
		return new PeticionLenguaje( $proposito, 'directrices', 'material', 500 );
	}

	public function test_el_camino_feliz_devuelve_la_respuesta_real_intacta_y_registra_ok(): void {
		Functions\when( 'get_option' )->justReturn( 'anthropic/claude-sonnet-5' );

		$interno     = new ProveedorLenguajeFalso( 'texto redactado' );
		$repositorio = new RepositorioLlamadasModeloEnMemoria();
		$contexto    = new ContextoEjecucion();
		$contexto->declarar( OrigenLlamada::Cron );

		$decorador = new LenguajeInstrumentado(
			$interno,
			new EnrutadorModelos(),
			$repositorio,
			$contexto,
			new RelojFijo(),
			'openrouter'
		);

		$respuesta = $decorador->completar( $this->peticion() );

		self::assertSame( 'texto redactado', $respuesta->contenido );
		self::assertSame( 100, $respuesta->tokensEntrada );

		self::assertCount( 1, $repositorio->registros );
		$registro = $repositorio->registros[0]['registro'];

		self::assertSame( 'redactar', $registro->proposito );
		self::assertSame( 'openrouter', $registro->proveedor );
		self::assertSame( 'modelo-falso', $registro->modelo );
		self::assertSame( 'modelo-falso', $registro->familia );
		self::assertSame( OrigenLlamada::Cron, $registro->origen );
		self::assertSame( ResultadoLlamada::Ok, $registro->resultado );
		self::assertSame( 100, $registro->tokensEntrada );
		self::assertSame( 50, $registro->tokensSalida );
		self::assertSame( 0.001, $registro->costeUsd );
		self::assertFalse( $registro->truncada );
		self::assertGreaterThanOrEqual( 0, $registro->latenciaMs );
	}

	/**
	 * @return list<array{0: ProveedorLenguajeException, 1: ResultadoLlamada}>
	 */
	public static function excepcionesYResultadoEsperado(): array {
		return array(
			array( new ProveedorLenguajeException( 'presupuesto agotado', presupuestoAgotado: true ), ResultadoLlamada::PresupuestoAgotado ),
			array( new ProveedorLenguajeException( 'sin credenciales', sinCredenciales: true ), ResultadoLlamada::SinCredenciales ),
			array( new ProveedorLenguajeException( 'circuito abierto', circuitoAbierto: true ), ResultadoLlamada::CircuitoAbierto ),
			array( new ProveedorLenguajeException( 'fallo de red' ), ResultadoLlamada::Error ),
		);
	}

	/**
	 * @dataProvider excepcionesYResultadoEsperado
	 */
	public function test_cada_camino_de_excepcion_relanza_intacta_y_registra_el_resultado_correcto(
		ProveedorLenguajeException $excepcion,
		ResultadoLlamada $resultadoEsperado
	): void {
		Functions\when( 'get_option' )->justReturn( 'anthropic/claude-sonnet-5' );

		$repositorio = new RepositorioLlamadasModeloEnMemoria();
		$contexto    = new ContextoEjecucion();
		$contexto->declarar( OrigenLlamada::Panel );

		$decorador = new LenguajeInstrumentado(
			new ProveedorLenguajeQueFalla( $excepcion ),
			new EnrutadorModelos(),
			$repositorio,
			$contexto,
			new RelojFijo(),
			'openrouter'
		);

		try {
			$decorador->completar( $this->peticion() );
			self::fail( 'se esperaba que completar() relanzara la excepción del proveedor interno' );
		} catch ( ProveedorLenguajeException $capturada ) {
			// La MISMA excepción, no una envoltura: el contrato de
			// `LenguajeInterface::completar()` no cambia para el llamador.
			self::assertSame( $excepcion, $capturada );
		}

		self::assertCount( 1, $repositorio->registros );
		$registro = $repositorio->registros[0]['registro'];

		self::assertSame( $resultadoEsperado, $registro->resultado );
		self::assertSame( OrigenLlamada::Panel, $registro->origen );
		self::assertSame( 0, $registro->tokensEntrada );
		self::assertSame( 0, $registro->tokensSalida );
		self::assertNull( $registro->costeUsd );
		// El modelo se resuelve vía EnrutadorModelos ANTES de intentar la
		// llamada real: disponible también cuando `RespuestaLenguaje` nunca
		// llegó a existir.
		self::assertSame( 'anthropic/claude-sonnet-5', $registro->modelo );
	}

	public function test_un_fallo_del_repositorio_no_propaga_y_dispara_la_degradacion_una_sola_vez(): void {
		Functions\when( 'get_option' )->justReturn( 'anthropic/claude-sonnet-5' );
		Functions\expect( 'do_action' )->once()->with( 'pluma/registro_llamadas_degradado' );

		$decorador = new LenguajeInstrumentado(
			new ProveedorLenguajeFalso( 'texto' ),
			new EnrutadorModelos(),
			new RepositorioLlamadasModeloEnMemoria( fallaAlRegistrar: true ),
			new ContextoEjecucion(),
			new RelojFijo(),
			'openrouter'
		);

		// Dos llamadas: el evento de degradación debe dispararse una sola vez
		// (instancia, no por llamada) — `Functions\expect(...)->once()` de
		// arriba ya lo verifica.
		$primera = $decorador->completar( $this->peticion() );
		$segunda = $decorador->completar( $this->peticion() );

		self::assertSame( 'texto', $primera->contenido );
		self::assertSame( 'texto', $segunda->contenido );
	}

	public function test_familiade_y_tienecredenciales_delegan_sin_registrar_nada(): void {
		$repositorio = new RepositorioLlamadasModeloEnMemoria();

		$decorador = new LenguajeInstrumentado(
			new ProveedorLenguajeFalso( 'texto' ),
			new EnrutadorModelos(),
			$repositorio,
			new ContextoEjecucion(),
			new RelojFijo(),
			'openrouter'
		);

		self::assertTrue( $decorador->tieneCredenciales() );
		self::assertSame( 'anthropic', $decorador->familiaDe( 'anthropic/claude-sonnet-5' ) );
		self::assertSame( array(), $repositorio->registros );
	}
}
