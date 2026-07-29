<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioDerivadosSociales;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\EstadoDerivadoSocial;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_derivados_sociales` contra tablas reales — Nivel
 * Cuatro W.2 (Etapa 9, Porción 4).
 *
 * @covers \Pluma\Datos\RepositorioDerivadosSociales
 */
final class RepositorioDerivadosSocialesTest extends WP_UnitTestCase {

	public function test_crear_persiste_pendiente_y_obtener_por_id_lo_recupera(): void {
		global $wpdb;
		$repo  = new RepositorioDerivadosSociales( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 42, 'Extracto social de prueba', 'Titular Discover de prueba', $reloj->ahora() );

		$derivado = $repo->obtenerPorId( $id );

		self::assertNotNull( $derivado );
		self::assertSame( 42, $derivado->piezaId );
		self::assertSame( EstadoDerivadoSocial::Pendiente, $derivado->estado );
	}

	public function test_actualizar_estado_persiste_la_transicion(): void {
		global $wpdb;
		$repo  = new RepositorioDerivadosSociales( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 43, 'Extracto', 'Titular', $reloj->ahora() );

		self::assertTrue( $repo->actualizarEstado( $id, EstadoDerivadoSocial::Aprobado ) );
		self::assertSame( EstadoDerivadoSocial::Aprobado, $repo->obtenerPorId( $id )->estado );
	}

	public function test_obtener_por_estado_solo_devuelve_ese_estado(): void {
		global $wpdb;
		$repo  = new RepositorioDerivadosSociales( $wpdb );
		$reloj = new RelojSistema();

		$pendienteId = $repo->crear( 44, 'Pendiente', 'Titular', $reloj->ahora() );
		$aprobadoId  = $repo->crear( 45, 'Aprobado', 'Titular', $reloj->ahora() );
		$repo->actualizarEstado( $aprobadoId, EstadoDerivadoSocial::Aprobado );

		$pendientes = $repo->obtenerPorEstado( EstadoDerivadoSocial::Pendiente, 50 );
		$ids        = array_map( static fn ( $d ): int => $d->id, $pendientes );

		self::assertContains( $pendienteId, $ids );
		self::assertNotContains( $aprobadoId, $ids );
	}
}
