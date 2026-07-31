<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Lista global de vocabulario prohibido del sitio (Libro Cap. 5.3,
 * pl-periodistas §Vocabulario prohibido): clichés del medio + muletillas
 * típicas de texto generado por IA. "Esta lista es crítica y se mantiene
 * actualizada" — revisar y ampliar cada release (Libro Cap. 5.3).
 *
 * Se combina con el vocabulario prohibido propio de cada periodista
 * ({@see ReglasConducta::$vocabularioProhibido}); ninguna de las dos listas
 * sustituye a la otra.
 *
 * Nivel Tres Q.1 (Etapa 8, Porción 10): la lista es un artefacto localizado,
 * no traducido — una muletilla de IA en un registro puede ser lenguaje
 * natural en otro. `'es-ES'` es el único locale curado hoy (`ADR 0012`); un
 * locale sin catálogo devuelve una lista vacía en vez de heredar en
 * silencio el catálogo de otro idioma — el borde REST (`RestPeriodistas`)
 * ya rechaza de entrada cualquier `localeEditorial` sin cobertura
 * (`ResolutorPerfilIdioma`), así que este `default` vacío es un cinturón de
 * seguridad, no la ruta esperada.
 */
final class VocabularioProhibidoGlobal {

	/**
	 * @return list<string>
	 */
	public static function muletillasDeTextoIa( string $locale = 'es-ES' ): array {
		return match ( $locale ) {
			'es-ES' => array(
				'es importante destacar',
				'es importante señalar',
				'cabe destacar',
				'cabe señalar',
				'en el mundo actual',
				'en la era digital',
				'a medida que avanza la tecnología',
				'sin duda alguna',
				'no cabe duda de que',
				'en conclusión',
				'en resumen',
				'es fundamental',
				'juega un papel crucial',
				'juega un papel fundamental',
				'desempeña un papel clave',
				'representa un hito',
				'marca un antes y un después',
				'en un mundo cada vez más',
				'la clave está en',
				'no hay que olvidar que',
				'como periodista sintética',
				'como modelo de lenguaje',
				'espero que esta información sea de utilidad',
			),
			default => array(),
		};
	}

	/**
	 * @param list<string> $vocabularioPropio
	 * @return list<string>
	 */
	public static function combinarCon( array $vocabularioPropio, string $locale = 'es-ES' ): array {
		return array_values( array_unique( array_merge( self::muletillasDeTextoIa( $locale ), $vocabularioPropio ) ) );
	}
}
