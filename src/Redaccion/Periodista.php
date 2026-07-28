<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use DateTimeImmutable;

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
	) {
	}

	/**
	 * Dominio del vertical de `$tema` (Paso 2 del Algoritmo de Decisión
	 * Editorial: peso alto en la asignación). 0 si no tiene esa especialidad.
	 */
	public function dominioDe( string $vertical ): int {
		foreach ( $this->especialidades as $especialidad ) {
			if ( $especialidad->vertical === $vertical ) {
				return $especialidad->nivelDominio;
			}
		}

		return 0;
	}
}
