<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Proveedores;

use Brain\Monkey\Functions;
use Pluma\Kernel\ContextoEjecucion;
use Pluma\Proveedores\EmbeddingsInstrumentado;
use Pluma\Proveedores\EnrutadorModelos;
use Pluma\Proveedores\OrigenLlamada;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\RegistroLlamada;
use Pluma\Proveedores\ResultadoLlamada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;
use Pluma\Tests\Unit\Dobles\EmbeddingsQueFalla;
use Pluma\Tests\Unit\Dobles\RelojFijo;
use Pluma\Tests\Unit\Dobles\RepositorioLlamadasModeloEnMemoria;

/**
 * NCP-1 (`ADR 0010`). Contrato de esta porción: CERO cambio de
 * comportamiento — cada test verifica primero que el vector real pasa
 * intacto, y solo después que el registro es correcto.
 *
 * @covers \Pluma\Proveedores\EmbeddingsInstrumentado
 */
final class EmbeddingsInstrumentadoTest extends CasoDePruebaUnitario {

	public function test_el_camino_feliz_devuelve_el_vector_real_intacto_y_registra_ok_bajo_el_bucket_embeddings(): void {
		Functions\when( 'get_option' )->justReturn( 'openai/text-embedding-3-small' );

		$repositorio = new RepositorioLlamadasModeloEnMemoria();
		$contexto    = new ContextoEjecucion();
		$contexto->declarar( OrigenLlamada::Visitante );

		$decorador = new EmbeddingsInstrumentado(
			new EmbeddingsFalso(),
			new EnrutadorModelos(),
			$repositorio,
			$contexto,
			new RelojFijo(),
			'openrouter'
		);

		$vector = $decorador->embed( 'texto de prueba' );

		self::assertSame( array( 1.0, 0.0, 0.0 ), $vector );

		self::assertCount( 1, $repositorio->registros );
		$registro = $repositorio->registros[0]['registro'];

		self::assertSame( RegistroLlamada::PROPOSITO_EMBEDDINGS, $registro->proposito );
		self::assertSame( 'openrouter', $registro->proveedor );
		self::assertSame( 'openai/text-embedding-3-small', $registro->modelo );
		self::assertSame( 'openai', $registro->familia );
		self::assertSame( OrigenLlamada::Visitante, $registro->origen );
		self::assertSame( ResultadoLlamada::Ok, $registro->resultado );
		// Limitación declarada (docs/deuda.md): `embed()` no expone coste ni
		// uso de tokens — el decorador no puede inventarlos.
		self::assertNull( $registro->costeUsd );
		self::assertSame( 0, $registro->tokensEntrada );
		self::assertSame( 0, $registro->tokensSalida );
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
		Functions\when( 'get_option' )->justReturn( 'openai/text-embedding-3-small' );

		$repositorio = new RepositorioLlamadasModeloEnMemoria();

		$decorador = new EmbeddingsInstrumentado(
			new EmbeddingsQueFalla( $excepcion ),
			new EnrutadorModelos(),
			$repositorio,
			new ContextoEjecucion(),
			new RelojFijo(),
			'openrouter'
		);

		try {
			$decorador->embed( 'texto' );
			self::fail( 'se esperaba que embed() relanzara la excepción del proveedor interno' );
		} catch ( ProveedorLenguajeException $capturada ) {
			self::assertSame( $excepcion, $capturada );
		}

		self::assertCount( 1, $repositorio->registros );
		self::assertSame( $resultadoEsperado, $repositorio->registros[0]['registro']->resultado );
	}

	public function test_un_fallo_del_repositorio_no_propaga_y_dispara_la_degradacion_una_sola_vez(): void {
		Functions\when( 'get_option' )->justReturn( 'openai/text-embedding-3-small' );
		Functions\expect( 'do_action' )->once()->with( 'pluma/registro_llamadas_degradado' );

		$decorador = new EmbeddingsInstrumentado(
			new EmbeddingsFalso(),
			new EnrutadorModelos(),
			new RepositorioLlamadasModeloEnMemoria( fallaAlRegistrar: true ),
			new ContextoEjecucion(),
			new RelojFijo(),
			'openrouter'
		);

		$primero = $decorador->embed( 'a' );
		$segundo = $decorador->embed( 'b' );

		self::assertSame( array( 1.0, 0.0, 0.0 ), $primero );
		self::assertSame( array( 1.0, 0.0, 0.0 ), $segundo );
	}
}
