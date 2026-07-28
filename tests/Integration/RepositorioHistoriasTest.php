<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use DateTimeImmutable;
use Pluma\Datos\RepositorioHistorias;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoHistoria;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\TipoPieza;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_historias` + `pluma_piezas.historia_id`/`tipo` contra
 * tablas reales — Nivel Cuatro U.1/U.4 (Etapa 9, Porción 1).
 *
 * @covers \Pluma\Datos\RepositorioHistorias
 * @covers \Pluma\Datos\RepositorioPiezas
 */
final class RepositorioHistoriasTest extends WP_UnitTestCase {

	public function test_crear_persiste_y_obtener_por_id_recupera_la_historia(): void {
		global $wpdb;
		$repo  = new RepositorioHistorias( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 'saga de prueba ' . uniqid(), $reloj->ahora() );

		$historia = $repo->obtenerPorId( $id );

		self::assertNotNull( $historia );
		self::assertSame( EstadoHistoria::Abierta, $historia->estado );
		self::assertNull( $historia->periodistaTitularId );
		// RepositorioHistorias no toca pluma_piezas — piezaIds llega vacío
		// desde aquí; GestorHistorias es quien lo hidrata de verdad.
		self::assertSame( array(), $historia->piezaIds );
	}

	public function test_actualizar_estado_persiste_la_transicion(): void {
		global $wpdb;
		$repo  = new RepositorioHistorias( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 'saga en seguimiento ' . uniqid(), $reloj->ahora() );

		self::assertTrue( $repo->actualizarEstado( $id, EstadoHistoria::EnSeguimiento, $reloj->ahora() ) );
		self::assertSame( EstadoHistoria::EnSeguimiento, $repo->obtenerPorId( $id )->estado );

		self::assertTrue( $repo->actualizarEstado( $id, EstadoHistoria::Cerrada, $reloj->ahora() ) );
		self::assertSame( EstadoHistoria::Cerrada, $repo->obtenerPorId( $id )->estado );
	}

	public function test_asignar_periodista_titular_persiste_el_vinculo(): void {
		global $wpdb;
		$repoHistorias   = new RepositorioHistorias( $wpdb );
		$repoPeriodistas = new \Pluma\Datos\RepositorioPeriodistas( $wpdb );
		$reloj           = new RelojSistema();

		$diales = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);

		$periodistaId = $repoPeriodistas->crear(
			'Titular de la historia ' . uniqid(),
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$diales,
			$reglas,
			$matriz,
			$reloj->ahora()
		);

		$historiaId = $repoHistorias->crear( 'saga con titular ' . uniqid(), $reloj->ahora() );

		self::assertTrue( $repoHistorias->asignarPeriodistaTitular( $historiaId, $periodistaId, $reloj->ahora() ) );
		self::assertSame( $periodistaId, $repoHistorias->obtenerPorId( $historiaId )->periodistaTitularId );
	}

	public function test_obtener_abiertas_sin_actividad_desde_excluye_las_recientes_y_las_cerradas(): void {
		global $wpdb;
		$repo  = new RepositorioHistorias( $wpdb );
		$reloj = new RelojSistema();

		$antigua = $repo->crear( 'saga antigua ' . uniqid(), $reloj->ahora()->modify( '-30 days' ) );
		$wpdb->update( $wpdb->prefix . 'pluma_historias', array( 'actualizada_en' => $reloj->ahora()->modify( '-30 days' )->format( 'Y-m-d H:i:s' ) ), array( 'id' => $antigua ) );

		$reciente = $repo->crear( 'saga reciente ' . uniqid(), $reloj->ahora() );

		$cerradaAntigua = $repo->crear( 'saga cerrada antigua ' . uniqid(), $reloj->ahora() );
		$wpdb->update( $wpdb->prefix . 'pluma_historias', array( 'actualizada_en' => $reloj->ahora()->modify( '-30 days' )->format( 'Y-m-d H:i:s' ) ), array( 'id' => $cerradaAntigua ) );
		$repo->actualizarEstado( $cerradaAntigua, EstadoHistoria::Cerrada, $reloj->ahora()->modify( '-30 days' ) );

		$vencidas = $repo->obtenerAbiertasSinActividadDesde( $reloj->ahora()->modify( '-7 days' ) );

		self::assertContains( $antigua, $vencidas );
		self::assertNotContains( $reciente, $vencidas );
		self::assertNotContains( $cerradaAntigua, $vencidas );
	}

	public function test_vincular_historia_y_obtener_por_historia_en_piezas_reales(): void {
		global $wpdb;
		$repoHistorias = new RepositorioHistorias( $wpdb );
		$repoPiezas    = new RepositorioPiezas( $wpdb );
		$reloj         = new RelojSistema();

		$historiaId = $repoHistorias->crear( 'saga con piezas reales ' . uniqid(), $reloj->ahora() );

		$tendenciaId     = 1;
		$piezaOriginalId = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );
		$piezaNuevaId    = $repoPiezas->crearComoActualizacion( $tendenciaId, $piezaOriginalId, $reloj->ahora() );

		self::assertTrue( $repoPiezas->vincularHistoria( $piezaOriginalId, $historiaId, TipoPieza::Original, $reloj->ahora() ) );
		self::assertTrue( $repoPiezas->vincularHistoria( $piezaNuevaId, $historiaId, TipoPieza::Actualizacion, $reloj->ahora() ) );

		$piezas = $repoPiezas->obtenerPorHistoria( $historiaId );

		self::assertCount( 2, $piezas );
		self::assertSame( $piezaOriginalId, $piezas[0]->id );
		self::assertSame( TipoPieza::Original, $piezas[0]->tipo );
		self::assertSame( $historiaId, $piezas[0]->historiaId );
		self::assertSame( $piezaNuevaId, $piezas[1]->id );
		self::assertSame( TipoPieza::Actualizacion, $piezas[1]->tipo );
	}

	public function test_pieza_sin_vincular_no_tiene_historia_ni_tipo_distinto_de_original(): void {
		global $wpdb;
		$repoPiezas = new RepositorioPiezas( $wpdb );
		$reloj      = new RelojSistema();

		$piezaId = $repoPiezas->crear( 1, $reloj->ahora() );
		$pieza   = $repoPiezas->obtenerPorId( $piezaId );

		self::assertNotNull( $pieza );
		self::assertNull( $pieza->historiaId );
		self::assertSame( TipoPieza::Original, $pieza->tipo );
		self::assertSame( EstadoPieza::Detectada, $pieza->estado );
	}
}
