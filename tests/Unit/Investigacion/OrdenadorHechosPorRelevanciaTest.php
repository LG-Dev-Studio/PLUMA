<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use DateTimeImmutable;
use Pluma\Investigacion\DimensionEncuadre;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\OrdenadorHechosPorRelevancia;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\ProveedorRerankLexico;
use Pluma\Proveedores\ResultadoRerank;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RerankFalso;
use Pluma\Tests\Unit\Dobles\RerankQueFalla;

/**
 * @covers \Pluma\Investigacion\OrdenadorHechosPorRelevancia
 */
final class OrdenadorHechosPorRelevanciaTest extends CasoDePruebaUnitario {

	private function expediente( int $cantidadHechos ): Expediente {
		$hechos = array();

		for ( $i = 0; $i < $cantidadHechos; $i++ ) {
			$hechos[] = new HechoFuente( "hecho {$i}", "https://example.com/{$i}", new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		}

		return new Expediente( 'una tendencia', $hechos, array() );
	}

	public function test_expediente_con_menos_de_dos_hechos_no_llama_a_nada(): void {
		$rerank   = new RerankQueFalla( new ProveedorLenguajeException( 'no debería llamarse' ) );
		$original = $this->expediente( 1 );

		self::assertSame( $original, ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original ) );
	}

	public function test_el_proveedor_real_reordena_los_hechos_por_relevancia(): void {
		$original = new Expediente(
			'capital de Francia',
			array(
				new HechoFuente( 'El clima en Madrid es cálido en verano.', 'https://example.com/0', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
				new HechoFuente( 'París es la capital de Francia.', 'https://example.com/1', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
				new HechoFuente( 'Francia es un país de Europa occidental.', 'https://example.com/2', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
			),
			array()
		);

		$reordenado = ( new OrdenadorHechosPorRelevancia( new ProveedorRerankLexico() ) )->ordenar( $original );

		self::assertSame( 'capital de Francia', $reordenado->tendenciaOrigen );
		self::assertSame( 'París es la capital de Francia.', $reordenado->hechos[0]->extracto );
		self::assertCount( 3, $reordenado->hechos );
	}

	public function test_fallo_del_proveedor_devuelve_el_expediente_original_sin_lanzar(): void {
		$rerank    = new RerankQueFalla( new ProveedorLenguajeException( 'fallo simulado' ) );
		$original  = $this->expediente( 2 );
		$resultado = ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original );

		self::assertSame( $original, $resultado );
		self::assertCount( 2, $resultado->hechos );
	}

	public function test_respuesta_con_menos_resultados_que_hechos_devuelve_el_original(): void {
		$rerank = new RerankFalso(
			static fn (): array => array( new ResultadoRerank( 0, 0.9 ) )
		);

		$original  = $this->expediente( 3 );
		$resultado = ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original );

		self::assertSame( $original, $resultado );
		self::assertCount( 3, $resultado->hechos );
	}

	public function test_respuesta_con_indices_duplicados_devuelve_el_original(): void {
		$rerank = new RerankFalso(
			static fn (): array => array(
				new ResultadoRerank( 0, 0.9 ),
				new ResultadoRerank( 0, 0.5 ),
			)
		);

		$original  = $this->expediente( 2 );
		$resultado = ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original );

		self::assertSame( $original, $resultado );
	}

	public function test_respuesta_con_indice_fuera_de_rango_devuelve_el_original(): void {
		$rerank = new RerankFalso(
			static fn (): array => array(
				new ResultadoRerank( 0, 0.9 ),
				new ResultadoRerank( 5, 0.5 ),
			)
		);

		$original  = $this->expediente( 2 );
		$resultado = ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original );

		self::assertSame( $original, $resultado );
	}

	public function test_huecos_detectados_se_preservan_al_reordenar(): void {
		$rerank = new RerankFalso(
			static fn (): array => array(
				new ResultadoRerank( 1, 0.9 ),
				new ResultadoRerank( 0, 0.1 ),
			)
		);

		$original = new Expediente(
			'una tendencia',
			array(
				new HechoFuente( 'hecho 0', 'https://example.com/0', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
				new HechoFuente( 'hecho 1', 'https://example.com/1', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
			),
			array( DimensionEncuadre::Legal )
		);

		$reordenado = ( new OrdenadorHechosPorRelevancia( $rerank ) )->ordenar( $original );

		self::assertSame( array( DimensionEncuadre::Legal ), $reordenado->huecosDetectados );
		self::assertSame( 'hecho 1', $reordenado->hechos[0]->extracto );
	}
}
