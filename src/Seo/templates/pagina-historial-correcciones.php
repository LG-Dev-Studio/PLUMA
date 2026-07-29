<?php
/**
 * Plantilla del historial público de correcciones (Nivel Cuatro, Capítulo Z).
 *
 * Vista pura: solo escapa y muestra. Los datos ya llegan resueltos desde
 * `Pluma\Seo\PaginaHistorialCorrecciones::datosParaPlantilla()` — ninguna
 * consulta ni lógica de negocio ocurre aquí (CLAUDE.md § Ley de
 * Arquitectura). Se integra con el tema activo vía
 * `get_header()`/`get_footer()`.
 *
 * @package Pluma\Seo
 */

declare(strict_types=1);

use Pluma\Seo\PaginaHistorialCorrecciones;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entradas = PaginaHistorialCorrecciones::datosParaPlantilla();

if ( null === $entradas ) {
	return;
}

get_header();
?>

<main class="pluma-pagina-historial-correcciones">
	<article>
		<header class="pluma-pagina-historial-correcciones__cabecera">
			<h1 class="pluma-pagina-historial-correcciones__titulo"><?php esc_html_e( 'Historial público de correcciones', 'pluma-engine' ); ?></h1>
			<p><?php esc_html_e( 'Cada corrección verificada de este sitio, con fecha y — cuando el lector lo autoriza — el crédito de quien la reportó.', 'pluma-engine' ); ?></p>
		</header>

		<?php if ( array() === $entradas ) : ?>
			<p><?php esc_html_e( 'Todavía no hay correcciones verificadas registradas.', 'pluma-engine' ); ?></p>
		<?php else : ?>
			<ul class="pluma-pagina-historial-correcciones__lista">
				<?php foreach ( $entradas as $entrada ) : ?>
					<li class="pluma-pagina-historial-correcciones__entrada">
						<time datetime="<?php echo esc_attr( $entrada['corregidaEn']->format( DATE_ATOM ) ); ?>">
							<?php echo esc_html( (string) mysql2date( get_option( 'date_format', 'j F Y' ), $entrada['corregidaEn']->format( 'Y-m-d H:i:s' ) ) ); ?>
						</time>
						<a href="<?php echo esc_url( $entrada['urlPieza'] ); ?>"><?php echo esc_html( $entrada['tituloPieza'] ); ?></a>
						<?php if ( null !== $entrada['notaEditor'] && '' !== $entrada['notaEditor'] ) : ?>
							<p class="pluma-pagina-historial-correcciones__nota"><?php echo esc_html( $entrada['notaEditor'] ); ?></p>
						<?php endif; ?>
						<?php if ( null !== $entrada['creditoLector'] && '' !== $entrada['creditoLector'] ) : ?>
							<p class="pluma-pagina-historial-correcciones__credito">
								<?php
								printf(
									/* translators: %s: nombre del lector acreditado */
									esc_html__( 'Gracias a %s por señalarlo.', 'pluma-engine' ),
									esc_html( $entrada['creditoLector'] )
								);
								?>
							</p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</article>
</main>

<?php
get_footer();
