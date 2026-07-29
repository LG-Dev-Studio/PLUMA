<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Datos\RepositorioDerivadosSociales;
use Pluma\Datos\RepositorioPeriodistas;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Datos\RepositorioTendencias;
use Pluma\Kernel\Activador;
use Pluma\Kernel\Nucleo;
use Pluma\Kernel\RelojSistema;
use Pluma\Pipeline\EstadoPieza;
use Pluma\Publicacion\EstadoDerivadoSocial;
use Pluma\Publicacion\GestorDerivadosSociales;
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
use Pluma\Sensores\PuntuacionOportunidad;
use Pluma\Sensores\TendenciaDetectada;
use WP_UnitTestCase;

/**
 * Nivel Cuatro W.2 — se engancha al evento real `pluma/pieza_publicada`.
 * Sin credenciales del proveedor de lenguaje configuradas (caso real de
 * wp-env de test), el fallo se absorbe con normalidad — mejor esfuerzo,
 * nunca rompe la publicación ya ocurrida ni inventa un derivado.
 *
 * @covers \Pluma\Publicacion\GestorDerivadosSociales
 */
final class GestorDerivadosSocialesTest extends WP_UnitTestCase {

	public function test_publicar_una_pieza_sin_credenciales_de_proveedor_no_crea_derivado_ni_lanza(): void {
		Activador::activar( new RelojSistema(), '0.9.0' );

		$nucleo = new Nucleo();
		$nucleo->contenedor()->obtener( GestorDerivadosSociales::class )->registrar();

		global $wpdb;
		$reloj = new RelojSistema();

		$diales       = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas       = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz       = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$periodistaId = ( new RepositorioPeriodistas( $wpdb ) )->crear( 'Periodista de derivado ' . uniqid(), null, 'Bio.', RolPeriodista::Columnista, array(), EstadoPeriodista::Activo, $diales, $reglas, $matriz, $reloj->ahora() );

		$tendenciaId = ( new RepositorioTendencias( $wpdb ) )->guardar(
			new TendenciaDetectada( 'tendencia derivado ' . uniqid(), PuntuacionOportunidad::calcular( 80, 60 ), $reloj->ahora(), array(), 'google_trends' ),
			$reloj->ahora()
		);

		$repoPiezas = new RepositorioPiezas( $wpdb );
		$piezaId    = $repoPiezas->crear( $tendenciaId, $reloj->ahora() );
		$postId     = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Pieza real de prueba',
			)
		);
		$repoPiezas->asignarPeriodista( $piezaId, $periodistaId, 1, $reloj->ahora() );
		$repoPiezas->actualizarPostId( $piezaId, $postId, $reloj->ahora() );
		$repoPiezas->actualizarEstado( $piezaId, EstadoPieza::Detectada, EstadoPieza::Publicada, $reloj->ahora() );

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- convención real del proyecto (CLAUDE.md § Ley de Arquitectura: "pluma/pieza_{estado}"), igual que Transicionador::transitar().
		do_action( 'pluma/pieza_publicada', $piezaId, EstadoPieza::Detectada, 'publicada por el test' );

		$pendientes        = ( new RepositorioDerivadosSociales( $wpdb ) )->obtenerPorEstado( EstadoDerivadoSocial::Pendiente, 50 );
		$piezasConDerivado = array_column( $pendientes, 'piezaId' );

		self::assertNotContains( $piezaId, $piezasConDerivado );
	}
}
