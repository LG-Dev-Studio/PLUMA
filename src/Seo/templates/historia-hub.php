<?php
/**
 * Plantilla del hub público de una Historia (Nivel Cuatro U.2).
 *
 * Vista pura: solo escapa y muestra. Los datos ya llegan resueltos desde
 * `Pluma\Seo\HistoriaHub::datosParaPlantilla()` — ninguna consulta ni
 * lógica de negocio ocurre aquí (CLAUDE.md § Ley de Arquitectura). Se
 * integra con el tema activo vía `get_header()`/`get_footer()`.
 *
 * @package Pluma\Seo
 */

declare(strict_types=1);

use Pluma\Seo\HistoriaHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$datos = HistoriaHub::datosParaPlantilla();

if ( null === $datos ) {
	return;
}

$historia          = $datos['historia'];
$periodistaTitular = $datos['periodistaTitular'];

get_header();
?>

<main class="pluma-historia-hub">
	<article>
		<header class="pluma-historia-hub__cabecera">
			<h1 class="pluma-historia-hub__titulo"><?php echo esc_html( $historia->titulo ); ?></h1>
			<?php if ( null !== $periodistaTitular ) : ?>
				<p class="pluma-historia-hub__titular">
					<?php
					printf(
						/* translators: %s: nombre del periodista titular de la historia */
						esc_html__( 'Sigue esta historia: %s', 'pluma-engine' ),
						esc_html( $periodistaTitular->nombre )
					);
					?>
				</p>
			<?php endif; ?>
		</header>

		<section class="pluma-historia-hub__conocimiento">
			<h2><?php esc_html_e( 'Lo que sabemos', 'pluma-engine' ); ?></h2>
			<?php if ( array() === $datos['bloqueConocimiento']->sabemos ) : ?>
				<p><?php esc_html_e( 'Todavía no hay hechos con sustento suficiente en esta historia.', 'pluma-engine' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $datos['bloqueConocimiento']->sabemos as $hecho ) : ?>
						<li><?php echo esc_html( $hecho ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( array() !== $datos['bloqueConocimiento']->noSabemos ) : ?>
				<h2><?php esc_html_e( 'Lo que no sabemos todavía', 'pluma-engine' ); ?></h2>
				<ul>
					<?php foreach ( $datos['bloqueConocimiento']->noSabemos as $hecho ) : ?>
						<li><?php echo esc_html( $hecho ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>

		<section class="pluma-historia-hub__cronologia">
			<h2><?php esc_html_e( 'Cronología de la cobertura', 'pluma-engine' ); ?></h2>
			<ol>
				<?php foreach ( $datos['cronologia'] as $entrada ) : ?>
					<li class="pluma-historia-hub__entrada pluma-historia-hub__entrada--<?php echo esc_attr( $entrada['tipo'] ); ?>">
						<time datetime="<?php echo esc_attr( $entrada['fecha'] ); ?>"><?php echo esc_html( $entrada['fecha'] ); ?></time>
						<a href="<?php echo esc_url( $entrada['url'] ); ?>"><?php echo esc_html( $entrada['titulo'] ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>

		<section class="pluma-historia-hub__buzon-pistas">
			<h2><?php esc_html_e( '¿Sabes algo más sobre esta historia?', 'pluma-engine' ); ?></h2>
			<p><?php esc_html_e( 'Toda pista la revisa un editor humano antes de usarse — nunca se publica directamente.', 'pluma-engine' ); ?></p>
			<form id="pluma-form-pista" data-historia-id="<?php echo esc_attr( (string) $historia->id ); ?>">
				<label for="pluma-pista-contenido"><?php esc_html_e( 'Cuéntanos qué sabes', 'pluma-engine' ); ?></label>
				<textarea id="pluma-pista-contenido" name="contenido" required></textarea>
				<label for="pluma-pista-email"><?php esc_html_e( 'Tu correo (opcional, para que podamos contactarte)', 'pluma-engine' ); ?></label>
				<input type="email" id="pluma-pista-email" name="contactoEmail" />
				<button type="submit"><?php esc_html_e( 'Enviar pista', 'pluma-engine' ); ?></button>
				<p id="pluma-pista-mensaje" role="status"></p>
			</form>
			<script>
			( function () {
				var formulario = document.getElementById( 'pluma-form-pista' );
				if ( ! formulario ) {
					return;
				}
				formulario.addEventListener( 'submit', function ( evento ) {
					evento.preventDefault();
					var mensaje = document.getElementById( 'pluma-pista-mensaje' );
					fetch( <?php echo wp_json_encode( esc_url_raw( rest_url( 'pluma/v1/pistas' ) ) ); ?>, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify( {
							historiaId: parseInt( formulario.dataset.historiaId, 10 ),
							contenido: document.getElementById( 'pluma-pista-contenido' ).value,
							contactoEmail: document.getElementById( 'pluma-pista-email' ).value,
						} ),
					} )
						.then( function ( respuesta ) {
							if ( ! respuesta.ok ) {
								throw new Error( 'respuesta no OK' );
							}
							formulario.reset();
							mensaje.textContent = <?php echo wp_json_encode( __( 'Gracias, un editor revisará tu pista.', 'pluma-engine' ) ); ?>;
						} )
						.catch( function () {
							mensaje.textContent = <?php echo wp_json_encode( __( 'No se pudo enviar. Inténtalo de nuevo.', 'pluma-engine' ) ); ?>;
						} );
				} );
			} )();
			</script>
		</section>
	</article>
</main>

<?php
get_footer();
