<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Datos\RepositorioPiezasInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Pieza;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Redaccion\AgrupadorTemasSinCobertura;
use Pluma\Redaccion\CreadorAutomaticoPeriodistas;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * Trabajo posterior a la Etapa 9 (creación automática de periodistas): las
 * cinco guardas deben impedir la llamada a la IA por sí solas, sin depender
 * del criterio del proveedor de lenguaje.
 *
 * @covers \Pluma\Redaccion\CreadorAutomaticoPeriodistas
 */
final class CreadorAutomaticoPeriodistasTest extends CasoDePruebaUnitario {

	private function pieza( int $id, string $tema, string $extracto = 'un extracto real' ): Pieza {
		$reloj      = new RelojFijo();
		$expediente = new Expediente(
			'una tendencia',
			array( new HechoFuente( $extracto, 'https://example.com', $reloj->ahora(), NivelVerificacion::Atribuido ) )
		);

		return new Pieza(
			$id,
			100,
			EstadoPieza::SinPeriodistaIdoneo,
			$expediente,
			null,
			$reloj->ahora(),
			$reloj->ahora(),
			temaSinCubrir: $tema
		);
	}

	/**
	 * @param list<Pieza> $piezasElegibles
	 */
	private function construir(
		array $piezasElegibles,
		ProveedorLenguajeFalso $proveedor,
		int $automaticosActivos = 0,
		?RepositorioPeriodistasInterface $repoPeriodistasCompartido = null
	): array {
		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->method( 'obtenerPorEstadoEntre' )->willReturn( $piezasElegibles );

		$repoPeriodistas = $repoPeriodistasCompartido ?? $this->createMock( RepositorioPeriodistasInterface::class );

		if ( null === $repoPeriodistasCompartido ) {
			$repoPeriodistas->method( 'contarAutomaticosActivos' )->willReturn( $automaticosActivos );
		}

		$presupuesto = new PresupuestoLenguaje( new RelojFijo() );
		$agrupador   = new AgrupadorTemasSinCobertura( $proveedor, $presupuesto );

		$creador = new CreadorAutomaticoPeriodistas( $repoPiezas, $repoPeriodistas, $agrupador, new RelojFijo() );

		return array( $creador, $repoPeriodistas );
	}

	public function test_interruptor_apagado_no_llama_al_proveedor_ni_al_repositorio(): void {
		Functions\when( 'get_option' )->justReturn( false ); // OPCION_ACTIVADA por defecto false.

		$proveedor       = new ProveedorLenguajeFalso( '{"crearPeriodista": true, "vertical": "deportes", "nombre": "X", "biografia": "X", "rol": "cronista", "nivelDominio": 4}' );
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->expects( self::never() )->method( 'contarAutomaticosActivos' );
		$repoPeriodistas->expects( self::never() )->method( 'crear' );

		[$creador] = $this->construir(
			array( $this->pieza( 1, 'deportes' ), $this->pieza( 2, 'deportes' ), $this->pieza( 3, 'deportes' ) ),
			$proveedor,
			repoPeriodistasCompartido: $repoPeriodistas
		);

		$errores = $creador->evaluarYProponer();

		self::assertSame( array(), $errores );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_cooldown_no_cumplido_no_llama_al_proveedor(): void {
		$reloj = new RelojFijo();
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) use ( $reloj ) {
				return match ( $opcion ) {
					CreadorAutomaticoPeriodistas::OPCION_ACTIVADA => true,
					'pluma_creacion_automatica_ultimo_intento' => $reloj->ahora()->getTimestamp() - 3600, // hace 1h, cooldown por defecto son 24h.
					default => $defecto,
				};
			}
		);

		$proveedor       = new ProveedorLenguajeFalso( '{"crearPeriodista": true, "vertical": "deportes", "nombre": "X", "biografia": "X", "rol": "cronista", "nivelDominio": 4}' );
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->expects( self::never() )->method( 'crear' );

		$repoPiezas = $this->createMock( RepositorioPiezasInterface::class );
		$repoPiezas->expects( self::never() )->method( 'obtenerPorEstadoEntre' );

		$presupuesto = new PresupuestoLenguaje( new RelojFijo() );
		$creador     = new CreadorAutomaticoPeriodistas( $repoPiezas, $repoPeriodistas, new AgrupadorTemasSinCobertura( $proveedor, $presupuesto ), $reloj );

		$errores = $creador->evaluarYProponer();

		self::assertSame( array(), $errores );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_tope_de_periodistas_automaticos_alcanzado_no_llama_al_proveedor(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => CreadorAutomaticoPeriodistas::OPCION_ACTIVADA === $opcion ? true : $defecto
		);

		$proveedor = new ProveedorLenguajeFalso( '{"crearPeriodista": true, "vertical": "deportes", "nombre": "X", "biografia": "X", "rol": "cronista", "nivelDominio": 4}' );

		[$creador] = $this->construir(
			array( $this->pieza( 1, 'deportes' ), $this->pieza( 2, 'deportes' ), $this->pieza( 3, 'deportes' ) ),
			$proveedor,
			automaticosActivos: 5 // igual al tope por defecto (5).
		);

		$errores = $creador->evaluarYProponer();

		self::assertSame( array(), $errores );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_menos_piezas_que_el_minimo_no_llama_al_proveedor(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => CreadorAutomaticoPeriodistas::OPCION_ACTIVADA === $opcion ? true : $defecto
		);

		$proveedor = new ProveedorLenguajeFalso( '{"crearPeriodista": true, "vertical": "deportes", "nombre": "X", "biografia": "X", "rol": "cronista", "nivelDominio": 4}' );

		// Mínimo por defecto es 3; solo 2 Piezas elegibles.
		[$creador] = $this->construir(
			array( $this->pieza( 1, 'deportes' ), $this->pieza( 2, 'deportes' ) ),
			$proveedor
		);

		$errores = $creador->evaluarYProponer();

		self::assertSame( array(), $errores );
		self::assertNull( $proveedor->ultimaPeticion );
	}

	public function test_crea_un_periodista_propuesto_cuando_el_agrupador_encuentra_un_grupo_coherente(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => CreadorAutomaticoPeriodistas::OPCION_ACTIVADA === $opcion ? true : $defecto
		);
		Functions\when( 'update_option' )->justReturn( true );
		Functions\expect( 'do_action' )->once()->with( 'pluma/periodista_propuesto_automaticamente', 42, 'deportes' );

		$proveedor       = new ProveedorLenguajeFalso( '{"crearPeriodista": true, "vertical": "Deportes", "nombre": "Renata Solís", "biografia": "Cubre deportes.", "rol": "cronista", "nivelDominio": 4}' );
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'contarAutomaticosActivos' )->willReturn( 0 );
		$repoPeriodistas->expects( self::once() )
			->method( 'crear' )
			->with(
				'Renata Solís',
				null,
				'Cubre deportes.',
				\Pluma\Redaccion\RolPeriodista::Cronista,
				self::callback(
					static function ( array $especialidades ): bool {
						return 1 === count( $especialidades )
							&& 'deportes' === $especialidades[0]->vertical
							&& 4 === $especialidades[0]->nivelDominio;
					}
				),
				EstadoPeriodista::Propuesto,
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				true
			)
			->willReturn( 42 );

		[$creador] = $this->construir(
			array( $this->pieza( 1, 'deportes' ), $this->pieza( 2, 'deportes' ), $this->pieza( 3, 'deportes' ) ),
			$proveedor,
			repoPeriodistasCompartido: $repoPeriodistas
		);

		$errores = $creador->evaluarYProponer();

		self::assertSame( array(), $errores );
		self::assertNotNull( $proveedor->ultimaPeticion );
	}

	public function test_no_crea_nada_cuando_el_agrupador_no_encuentra_un_grupo_coherente(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => CreadorAutomaticoPeriodistas::OPCION_ACTIVADA === $opcion ? true : $defecto
		);
		Functions\when( 'update_option' )->justReturn( true );

		$proveedor       = new ProveedorLenguajeFalso( '{"crearPeriodista": false}' );
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'contarAutomaticosActivos' )->willReturn( 0 );
		$repoPeriodistas->expects( self::never() )->method( 'crear' );

		[$creador] = $this->construir(
			array( $this->pieza( 1, 'deportes' ), $this->pieza( 2, 'deportes' ), $this->pieza( 3, 'deportes' ) ),
			$proveedor,
			repoPeriodistasCompartido: $repoPeriodistas
		);

		$errores = $creador->evaluarYProponer();

		self::assertSame( array(), $errores );
		self::assertNotNull( $proveedor->ultimaPeticion );
	}

	public function test_piezas_con_el_mismo_tema_normalizado_se_deduplican_en_una_sola_muestra(): void {
		Functions\when( 'get_option' )->alias(
			// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition -- falso positivo: es una arrow function con `=>`, no una asignación.
			static fn ( string $opcion, $defecto = false ) => CreadorAutomaticoPeriodistas::OPCION_ACTIVADA === $opcion ? true : $defecto
		);
		Functions\when( 'update_option' )->justReturn( true );

		$proveedor       = new ProveedorLenguajeFalso( '{"crearPeriodista": false}' );
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'contarAutomaticosActivos' )->willReturn( 0 );

		[$creador] = $this->construir(
			array(
				$this->pieza( 1, 'Deportes', 'extracto A' ),
				$this->pieza( 2, 'deportes', 'extracto B' ),
				$this->pieza( 3, '  DEPORTES  ', 'extracto C' ),
			),
			$proveedor,
			repoPeriodistasCompartido: $repoPeriodistas
		);

		$creador->evaluarYProponer();

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertSame( 1, substr_count( $proveedor->ultimaPeticion->material, 'TEMA: deportes' ) );
	}
}
