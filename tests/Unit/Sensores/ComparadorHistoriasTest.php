<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Sensores;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Sensores\ComparadorHistorias;
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\RelacionHistoria;
use Pluma\Sensores\TendenciaDetectada;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Huella semántica del Radar (Libro Cap. 3.4, "dos golpes").
 *
 * @covers \Pluma\Sensores\ComparadorHistorias
 */
final class ComparadorHistoriasTest extends CasoDePruebaUnitario {

	private function tendenciaNueva( string $termino = 'tendencia nueva' ): TendenciaDetectada {
		return new TendenciaDetectada(
			$termino,
			PuntuacionOportunidad::calcular( 80, 80 ),
			new DateTimeImmutable( '2026-07-23T12:00:00+00:00' ),
			array(
				array(
					'titulo' => 'Un artículo',
					'url'    => 'https://example.com',
					'fuente' => 'Ejemplo',
				),
			),
			'google_trends'
		);
	}

	/**
	 * @return list<array{id: int, termino: string, articulosRelacionados: list<array{titulo: string, url: string, fuente: string}>}>
	 */
	private function candidatas(): array {
		return array(
			array(
				'id'                    => 42,
				'termino'               => 'historia ya cubierta',
				'articulosRelacionados' => array(),
			),
		);
	}

	private function presupuestoDisponible(): PresupuestoLenguaje {
		Functions\when( 'get_option' )->justReturn( false );

		return new PresupuestoLenguaje( new RelojFijo() );
	}

	public function test_relacion_identica_no_incluye_tendencia_relacionada(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "identica", "candidatoId": 42}' );

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::Identica, $resultado->relacion );
	}

	public function test_relacion_evoluciona_con_candidato_valido(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "evoluciona", "candidatoId": 42}' );

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::Evoluciona, $resultado->relacion );
		self::assertSame( 42, $resultado->tendenciaRelacionadaId );
	}

	public function test_relacion_evoluciona_con_candidato_id_desconocido_se_trata_como_sin_relacion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "evoluciona", "candidatoId": 999}' );

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::SinRelacion, $resultado->relacion );
		self::assertNull( $resultado->tendenciaRelacionadaId );
	}

	public function test_sin_relacion_no_requiere_candidato_id(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "sin_relacion", "candidatoId": null}' );

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::SinRelacion, $resultado->relacion );
		self::assertNull( $resultado->tendenciaRelacionadaId );
	}

	public function test_sin_candidatas_no_llama_al_proveedor(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "identica", "candidatoId": null}' );

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), array() );

		self::assertSame( RelacionHistoria::SinRelacion, $resultado->relacion );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_sin_presupuesto_disponible_devuelve_sin_relacion_sin_llamar_al_proveedor(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => PresupuestoLenguaje::OPCION_LIMITE_DIARIO === $opcion ? 0.0 : $defecto
		);

		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "identica", "candidatoId": 42}' );

		$resultado = ( new ComparadorHistorias( $proveedor, new PresupuestoLenguaje( new RelojFijo() ) ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::SinRelacion, $resultado->relacion );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_proveedor_caido_devuelve_sin_relacion_en_vez_de_lanzar(): void {
		$proveedor = new class() implements \Pluma\Proveedores\LenguajeInterface {
			public function completar( \Pluma\Proveedores\PeticionLenguaje $peticion ): \Pluma\Proveedores\RespuestaLenguaje {
				throw new ProveedorLenguajeException( 'proveedor caído' );
			}

			public function tieneCredenciales(): bool {
				return true;
			}

			public function familiaDe( string $modelo ): string {
				return $modelo;
			}
		};

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::SinRelacion, $resultado->relacion );
	}

	public function test_respuesta_no_interpretable_devuelve_sin_relacion_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso( 'no es JSON en absoluto' );

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::SinRelacion, $resultado->relacion );
	}

	public function test_respuesta_truncada_devuelve_sin_relacion_en_vez_de_lanzar(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "identica", "candidatoId": 42}', truncada: true );

		$resultado = ( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertSame( RelacionHistoria::SinRelacion, $resultado->relacion );
	}

	public function test_el_material_enviado_incluye_el_termino_nuevo_y_las_candidatas(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"relacion": "sin_relacion", "candidatoId": null}' );

		( new ComparadorHistorias( $proveedor, $this->presupuestoDisponible() ) )->comparar( $this->tendenciaNueva(), $this->candidatas() );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'tendencia nueva', $proveedor->ultimaPeticion->material );
		self::assertStringContainsString( 'historia ya cubierta', $proveedor->ultimaPeticion->material );
	}
}
