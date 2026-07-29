<?php

declare(strict_types=1);

namespace Pluma\Tests\Integration;

use Pluma\Compuertas\DiagnosticoCalidad;
use Pluma\Compuertas\DiagnosticoOriginalidad;
use Pluma\Compuertas\DiagnosticoRiesgo;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Datos\RepositorioPiezas;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Kernel\RelojSistema;
use Pluma\Seo\ExpedienteResumido;
use WP_UnitTestCase;

/**
 * Nivel Cuatro Z — expediente resumido ("Cómo se hizo esta pieza"), contra
 * el bucle real de WordPress. Solo lee datos reales y persistidos de la
 * Pieza (expediente, resultado de compuertas) — nunca inventa.
 *
 * @covers \Pluma\Seo\ExpedienteResumido
 */
final class ExpedienteResumidoTest extends WP_UnitTestCase {

	private function repositorio(): RepositorioPiezas {
		global $wpdb;

		return new RepositorioPiezas( $wpdb );
	}

	private function crearPiezaConExpedientePublicada( int $numeroFuentes, ?DiagnosticoRiesgo $riesgo = null, string $contenido = 'Contenido real de la pieza.' ): int {
		$repo  = $this->repositorio();
		$reloj = new RelojSistema();

		$piezaId = $repo->crear( 1, $reloj->ahora() );

		$hechos = array();
		for ( $i = 0; $i < $numeroFuentes; $i++ ) {
			$hechos[] = new HechoFuente( "hecho {$i}", "https://example.com/{$i}", $reloj->ahora(), NivelVerificacion::Verificado );
		}

		$repo->actualizarExpediente( $piezaId, new Expediente( 'tendencia de prueba', $hechos ), $reloj->ahora() );

		if ( null !== $riesgo ) {
			$resultado = new ResultadoEvaluacion(
				true,
				false,
				array(),
				ModoOperacion::Piloto,
				new DiagnosticoCalidad( 90, 70, true, true, array() ),
				$riesgo,
				new DiagnosticoOriginalidad( false, false, 0.8, 0.4 )
			);
			$repo->actualizarResultadoCompuertas( $piezaId, $resultado, $reloj->ahora() );
		}

		$postId = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => $contenido,
			)
		);
		$repo->actualizarPostId( $piezaId, $postId, $reloj->ahora() );

		return $postId;
	}

	public function test_muestra_el_numero_real_de_fuentes_y_la_ultima_actualizacion(): void {
		( new ExpedienteResumido( $this->repositorio() ) )->registrar();

		$postId = $this->crearPiezaConExpedientePublicada( 3 );

		$this->go_to( get_permalink( $postId ) );

		self::assertTrue( have_posts() );
		the_post();
		$html = apply_filters( 'the_content', get_the_content() );

		self::assertStringContainsString( 'pluma-expediente-resumido', $html );
		self::assertStringContainsString( 'Basada en 3 fuentes', $html );
		self::assertStringContainsString( 'Contenido real de la pieza.', $html );
	}

	public function test_no_muestra_el_expediente_si_la_pieza_no_tiene_uno_persistido(): void {
		( new ExpedienteResumido( $this->repositorio() ) )->registrar();

		$postId = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'Un post ajeno al pipeline de PLUMA.',
			)
		);

		$this->go_to( get_permalink( $postId ) );

		self::assertTrue( have_posts() );
		the_post();
		$html = apply_filters( 'the_content', get_the_content() );

		self::assertStringNotContainsString( 'pluma-expediente-resumido', $html );
	}

	public function test_cuando_aplica_la_pregunta_de_replica_indica_que_no_se_confirmo_la_postura(): void {
		( new ExpedienteResumido( $this->repositorio() ) )->registrar();

		$riesgo = new DiagnosticoRiesgo( false, false, false, false, false, '', false, null, true, true );
		$postId = $this->crearPiezaConExpedientePublicada( 2, $riesgo );

		$this->go_to( get_permalink( $postId ) );

		self::assertTrue( have_posts() );
		the_post();
		$html = apply_filters( 'the_content', get_the_content() );

		self::assertStringContainsString( 'No se pudo confirmar', $html );
	}

	public function test_cuando_la_pregunta_de_replica_no_aplica_no_menciona_al_senalado(): void {
		( new ExpedienteResumido( $this->repositorio() ) )->registrar();

		$riesgo = new DiagnosticoRiesgo( false, false, false, false, false, '', false, null, false, false );
		$postId = $this->crearPiezaConExpedientePublicada( 1, $riesgo );

		$this->go_to( get_permalink( $postId ) );

		self::assertTrue( have_posts() );
		the_post();
		$html = apply_filters( 'the_content', get_the_content() );

		self::assertStringContainsString( 'pluma-expediente-resumido', $html );
		self::assertStringNotContainsString( 'postura', $html );
	}
}
