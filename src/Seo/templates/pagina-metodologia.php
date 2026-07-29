<?php
/**
 * Plantilla de la página pública de metodología (Nivel Cuatro, Capítulo Z).
 *
 * Vista pura: solo escapa y muestra. Los datos ya llegan resueltos desde
 * `Pluma\Seo\PaginaMetodologia::datosParaPlantilla()` — generados desde la
 * configuración real del sistema, nunca prosa de marketing desincronizada
 * de la operación (CLAUDE.md § Ley de Arquitectura). Se integra con el
 * tema activo vía `get_header()`/`get_footer()`.
 *
 * @package Pluma\Seo
 */

declare(strict_types=1);

use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\RegimenResponsabilidad;
use Pluma\Seo\PaginaHistorialCorrecciones;
use Pluma\Seo\PaginaMetodologia;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$datos = PaginaMetodologia::datosParaPlantilla();

if ( null === $datos ) {
	return;
}

$modo    = $datos['modoOperacion'];
$regimen = $datos['regimenResponsabilidad'];
$respeto = $datos['modoRespetoActivo'];

$etiquetasModo = array(
	ModoOperacion::Piloto->value   => __( 'Piloto: todo el contenido se publica como borrador, ningún artículo sale sin revisión humana previa.', 'pluma-engine' ),
	ModoOperacion::Copiloto->value => __( 'Copiloto: el contenido se publica con una ventana de veto humano antes de quedar visible.', 'pluma-engine' ),
	ModoOperacion::Autonomo->value => __( 'Autónomo: el contenido se publica sin revisión previa, sujeto a compuertas automáticas y degradación por sensibilidad.', 'pluma-engine' ),
);

$etiquetasRegimen = array(
	RegimenResponsabilidad::Civil->value => __( 'un régimen de responsabilidad civil', 'pluma-engine' ),
	RegimenResponsabilidad::Penal->value => __( 'un régimen de responsabilidad penal, con retención humana obligatoria ante cualquier afirmación fáctica negativa sobre una persona identificable', 'pluma-engine' ),
);

get_header();
?>

<main class="pluma-pagina-metodologia">
	<article>
		<header class="pluma-pagina-metodologia__cabecera">
			<h1 class="pluma-pagina-metodologia__titulo"><?php esc_html_e( 'Cómo trabaja esta redacción', 'pluma-engine' ); ?></h1>
			<p><?php esc_html_e( 'Esta página describe, a partir de la configuración real del sistema en este momento, cómo se producen los contenidos de este sitio.', 'pluma-engine' ); ?></p>
		</header>

		<section class="pluma-pagina-metodologia__redaccion-sintetica">
			<h2><?php esc_html_e( 'Redacción sintética', 'pluma-engine' ); ?></h2>
			<p><?php esc_html_e( 'Los artículos de este sitio los redactan periodistas sintéticos: identidades editoriales con un tono y un enfoque definidos, operando bajo un sistema de compuertas de calidad, riesgo y originalidad. Ningún artículo llega al público sin superar esas compuertas.', 'pluma-engine' ); ?></p>
		</section>

		<section class="pluma-pagina-metodologia__modo-operacion">
			<h2><?php esc_html_e( 'Nivel de supervisión humana actual', 'pluma-engine' ); ?></h2>
			<p>
				<?php
				echo esc_html( $etiquetasModo[ $modo->value ] ?? $modo->value );
				?>
			</p>
		</section>

		<section class="pluma-pagina-metodologia__responsabilidad">
			<h2><?php esc_html_e( 'Régimen de responsabilidad editorial', 'pluma-engine' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: descripción del régimen de responsabilidad activo */
					esc_html__( 'Este sitio opera bajo %s.', 'pluma-engine' ),
					esc_html( $etiquetasRegimen[ $regimen->value ] ?? $regimen->value )
				);
				?>
			</p>
		</section>

		<section class="pluma-pagina-metodologia__modo-respeto">
			<h2><?php esc_html_e( 'Modo respeto', 'pluma-engine' ); ?></h2>
			<?php if ( $respeto ) : ?>
				<p><?php esc_html_e( 'El modo respeto está activo en este momento: el tono de todo el sitio se ha ajustado ante un evento de gravedad y la publicación no relacionada está en pausa.', 'pluma-engine' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'El modo respeto no está activo en este momento. Se activa automáticamente ante eventos de gravedad (tragedia, salud, violencia) o manualmente por un editor, y ajusta el tono de todo el sitio mientras dura.', 'pluma-engine' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="pluma-pagina-metodologia__correcciones">
			<h2><?php esc_html_e( 'Correcciones', 'pluma-engine' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: URL del historial público de correcciones */
					wp_kses(
						/* translators: %s: URL del historial público de correcciones */
						__( 'Cualquier lector puede reportar un error. Las correcciones verificadas se marcan en el propio artículo y quedan también en el <a href="%s">historial público de correcciones</a>.', 'pluma-engine' ),
						array( 'a' => array( 'href' => array() ) )
					),
					esc_url( PaginaHistorialCorrecciones::url() )
				);
				?>
			</p>
		</section>

		<section class="pluma-pagina-metodologia__presencia-ia">
			<h2><?php esc_html_e( 'Presencia en superficies de inteligencia artificial', 'pluma-engine' ); ?></h2>
			<p><?php esc_html_e( 'Este sitio no fabrica ni simula presencia, autoridad o engagement en plataformas de IA, buscadores generativos o redes sociales. Cualquier presencia futura en superficies de IA se limitará a la sindicación legítima del contenido ya publicado y verificado, nunca a técnicas de manipulación de visibilidad.', 'pluma-engine' ); ?></p>
		</section>
	</article>
</main>

<?php
get_footer();
