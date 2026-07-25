<?php
/**
 * Plantilla de la página de autor por periodista sintético.
 *
 * Vista pura: solo escapa y muestra. Los datos ya llegan resueltos desde
 * `Pluma\Seo\PaginaAutorPeriodista::datosParaPlantilla()` — ninguna consulta
 * ni lógica de negocio ocurre aquí (CLAUDE.md § Ley de Arquitectura).
 * Se integra con el tema activo vía `get_header()`/`get_footer()`.
 *
 * @package Pluma\Seo
 */

declare(strict_types=1);

use Pluma\Seo\PaginaAutorPeriodista;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$datos = PaginaAutorPeriodista::datosParaPlantilla();

if ( null === $datos ) {
	return;
}

$periodista = $datos['periodista'];

get_header();
?>

<main class="pluma-pagina-autor">
	<article class="pluma-pagina-autor__perfil">
		<header class="pluma-pagina-autor__cabecera">
			<?php if ( null !== $periodista->avatarUrl ) : ?>
				<img
					class="pluma-pagina-autor__avatar"
					src="<?php echo esc_url( $periodista->avatarUrl ); ?>"
					alt="<?php echo esc_attr( $periodista->nombre ); ?>"
					width="96"
					height="96"
				/>
			<?php endif; ?>
			<h1 class="pluma-pagina-autor__nombre"><?php echo esc_html( $periodista->nombre ); ?></h1>
			<p class="pluma-pagina-autor__rol"><?php echo esc_html( ucfirst( $periodista->rol->value ) ); ?></p>
		</header>

		<?php echo wp_kses_post( $datos['declaracionHtml'] ); ?>

		<div class="pluma-pagina-autor__biografia">
			<?php echo wp_kses_post( wpautop( esc_html( $periodista->biografia ) ) ); ?>
		</div>
	</article>
</main>

<?php
get_footer();
