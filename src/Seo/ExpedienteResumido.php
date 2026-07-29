<?php

declare(strict_types=1);

namespace Pluma\Seo;

use Pluma\Compuertas\DiagnosticoRiesgo;
use Pluma\Datos\RepositorioPiezasInterface;

/**
 * Nivel Cuatro Z — "Cómo se hizo esta pieza": expediente resumido opcional
 * por pieza, vía `the_content` (mismo mecanismo que `BannerCorreccion`).
 *
 * Solo muestra hechos que ya existen en datos reales y persistidos
 * (CLAUDE.md "cero invención"): número de fuentes (`Pieza::$expediente`),
 * última actualización real de la Pieza (`Pieza::$actualizadaEn` — no existe
 * un campo "fecha de verificación" distinto, así que se etiqueta con
 * honestidad como última actualización, nunca como verificación), y si se
 * buscó la postura del señalado — solo cuando la pregunta aplica
 * (`DiagnosticoRiesgo::$afirmacionNegativaSobrePersonaIdentificable`), leído
 * de `Pieza::$resultadoCompuertas` (Nivel Tres M.1).
 */
final class ExpedienteResumido {

	public function __construct( private readonly RepositorioPiezasInterface $piezas ) {
	}

	public function registrar(): void {
		add_filter( 'the_content', array( $this, 'anteponerExpediente' ) );
	}

	public function anteponerExpediente( string $contenido ): string {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $contenido;
		}

		$postId = get_the_ID();

		if ( false === $postId ) {
			return $contenido;
		}

		$pieza = $this->piezas->obtenerPorPostId( $postId );

		if ( null === $pieza || null === $pieza->expediente ) {
			return $contenido;
		}

		$numeroFuentes = count( $pieza->expediente->hechos );

		$lineas   = array();
		$lineas[] = sprintf(
			/* translators: %d: número de fuentes consultadas */
			esc_html( _n( 'Basada en %d fuente.', 'Basada en %d fuentes.', $numeroFuentes, 'pluma-engine' ) ),
			absint( $numeroFuentes )
		);
		$lineas[] = sprintf(
			/* translators: %s: fecha de última actualización de la pieza */
			esc_html__( 'Última actualización editorial: %s.', 'pluma-engine' ),
			esc_html( (string) mysql2date( get_option( 'date_format', 'j F Y' ), $pieza->actualizadaEn->format( 'Y-m-d H:i:s' ) ) )
		);

		$diagnosticoRiesgo = $pieza->resultadoCompuertas?->riesgo;

		if ( $diagnosticoRiesgo instanceof DiagnosticoRiesgo && $diagnosticoRiesgo->afirmacionNegativaSobrePersonaIdentificable ) {
			$lineas[] = $diagnosticoRiesgo->posturaSenaladoAusente
				? esc_html__( 'No se pudo confirmar que la parte señalada haya sido consultada antes de la publicación.', 'pluma-engine' )
				: esc_html__( 'Se buscó la postura de la parte señalada antes de la publicación.', 'pluma-engine' );
		}

		$html = '<aside class="pluma-expediente-resumido">'
			. '<p class="pluma-expediente-resumido__titulo">' . esc_html__( 'Cómo se hizo esta pieza', 'pluma-engine' ) . '</p>'
			. '<ul>';

		foreach ( $lineas as $linea ) {
			$html .= '<li>' . $linea . '</li>';
		}

		$html .= '</ul></aside>';

		return $html . $contenido;
	}
}
