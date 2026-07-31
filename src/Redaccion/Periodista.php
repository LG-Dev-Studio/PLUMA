<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use DateTimeImmutable;
use Pluma\Idioma\PlegadorDiacriticos;

/**
 * Periodista sintético (Libro Cap. 5.1): identidad persistente + conducta
 * versionada + memoria + repertorio. Es EL activo del cliente
 * (CLAUDE.md § IDENTIDAD): su portabilidad (export/import) es de primera clase.
 */
final readonly class Periodista {

	/**
	 * @param list<Especialidad> $especialidades
	 */
	public function __construct(
		public int $id,
		public string $nombre,
		public ?string $avatarUrl,
		public string $biografia,
		public RolPeriodista $rol,
		public array $especialidades,
		public EstadoPeriodista $estado,
		public ConductaVersion $conductaActual,
		public DateTimeImmutable $creadoEn,
		public DateTimeImmutable $actualizadoEn,
		// Nivel Tres Q.1 (Etapa 8, Porción 10): "los catálogos de
		// vocabulario prohibido y ejemplos-ancla son artefactos
		// localizados, no traducidos" — determina qué catálogo aplica al
		// compilar directrices. Con valor por defecto para no romper
		// ningún constructor posicional existente ("un solo locale
		// poblado inicialmente es aceptable").
		public string $localeEditorial = 'es-ES',
		// Trabajo posterior a la Etapa 9 (creación automática de
		// periodistas): distingue un periodista sembrado por
		// `CreadorAutomaticoPeriodistas` (siempre nace `Propuesto`) de uno
		// creado a mano — usado por el tope
		// `pluma_creacion_automatica_max_periodistas`, que solo cuenta a los
		// automáticos.
		public bool $creadoAutomaticamente = false,
	) {
	}

	/**
	 * Dominio del vertical de `$tema` (Paso 2 del Algoritmo de Decisión
	 * Editorial: peso alto en la asignación). 0 si no tiene esa especialidad.
	 *
	 * Normaliza (minúsculas + recorte de espacios + plegado de diacríticos,
	 * `PLUMA-E9-21`) antes de comparar: el `tema` lo genera la IA en texto
	 * libre (`ClasificadorNoticia` no usa una lista fija), así que "Economía"
	 * y "economia " deben calzar igual que "economia".
	 *
	 * Un periodista generalista declara una Especialidad con
	 * `Especialidad::VERTICAL_COMODIN` en vez de (o además de) filas por
	 * tema. El comodín SOLO responde si ningún vertical declarado calza
	 * exactamente: un match exacto siempre gana sobre el comodín, aunque el
	 * comodín tenga un nivel de dominio más alto — así "generalista, pero
	 * especialmente fuerte en economía" sigue funcionando como se espera.
	 */
	public function dominioDe( string $vertical ): int {
		$temaNormalizado = $this->normalizarVertical( $vertical );
		$nivelComodin    = null;

		foreach ( $this->especialidades as $especialidad ) {
			if ( Especialidad::VERTICAL_COMODIN === $especialidad->vertical ) {
				$nivelComodin = $especialidad->nivelDominio;

				continue;
			}

			if ( $this->normalizarVertical( $especialidad->vertical ) === $temaNormalizado ) {
				return $especialidad->nivelDominio;
			}
		}

		return $nivelComodin ?? 0;
	}

	private function normalizarVertical( string $vertical ): string {
		return PlegadorDiacriticos::plegar( mb_strtolower( trim( $vertical ) ) );
	}
}
