<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioCorrecciones;
use Pluma\Kernel\RelojSistema;
use Pluma\Publicacion\EstadoCorreccion;
use WP_UnitTestCase;

/**
 * Repositorio `pluma_correcciones` contra tablas reales — Nivel Cuatro X.4
 * (Etapa 9, Porción 5).
 *
 * @covers \Pluma\Datos\RepositorioCorrecciones
 */
final class RepositorioCorreccionesTest extends WP_UnitTestCase {

	public function test_crear_persiste_pendiente_y_obtener_por_id_lo_recupera(): void {
		global $wpdb;
		$repo  = new RepositorioCorrecciones( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 42, 'la cifra citada es incorrecta', 'fuente oficial con la cifra real', 'lector@example.test', 'Lector Uno', true, $reloj->ahora() );

		$correccion = $repo->obtenerPorId( $id );

		self::assertNotNull( $correccion );
		self::assertSame( 42, $correccion->piezaId );
		self::assertSame( EstadoCorreccion::Pendiente, $correccion->estado );
		self::assertTrue( $correccion->creditoOptIn );
	}

	public function test_resolver_marca_verificada_con_nota_y_fecha(): void {
		global $wpdb;
		$repo  = new RepositorioCorrecciones( $wpdb );
		$reloj = new RelojSistema();

		$id = $repo->crear( 43, 'afirmación', 'evidencia', null, null, false, $reloj->ahora() );

		self::assertTrue( $repo->resolver( $id, EstadoCorreccion::Verificada, 'confirmado con la fuente', $reloj->ahora() ) );

		$correccion = $repo->obtenerPorId( $id );
		self::assertSame( EstadoCorreccion::Verificada, $correccion->estado );
		self::assertSame( 'confirmado con la fuente', $correccion->notaEditor );
		self::assertNotNull( $correccion->resueltoEn );
	}

	public function test_obtener_por_estado_y_verificadas_recientes(): void {
		global $wpdb;
		$repo  = new RepositorioCorrecciones( $wpdb );
		$reloj = new RelojSistema();

		$pendienteId  = $repo->crear( 44, 'a', 'b', null, null, false, $reloj->ahora() );
		$verificadaId = $repo->crear( 45, 'a', 'b', null, null, false, $reloj->ahora() );
		$repo->resolver( $verificadaId, EstadoCorreccion::Verificada, null, $reloj->ahora() );

		$pendientes = $repo->obtenerPorEstado( EstadoCorreccion::Pendiente, 50 );
		self::assertContains( $pendienteId, array_map( static fn ( $c ): int => $c->id, $pendientes ) );

		$verificadas = $repo->obtenerVerificadasRecientes( 50 );
		self::assertContains( $verificadaId, array_map( static fn ( $c ): int => $c->id, $verificadas ) );
	}
}
