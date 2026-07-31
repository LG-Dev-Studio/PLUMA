<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Kernel\RelojInterface;

/**
 * Exportación completa del banco de periodistas y su memoria (pl-periodistas
 * §8: "es API pública del producto"; Libro Cap. 11: "el activo más valioso
 * del usuario debe ser portable"). Incluye el historial completo de
 * versiones de Conducta, no solo la actual — el import reconstruye la
 * trazabilidad exacta.
 */
final class ExportadorBancoPeriodistas {

	// Campos nuevos y opcionales (p. ej. `respuestasHabilitadas` en la Etapa
	// 5, `localeEditorial` en la Etapa 8 Porción 10) se añaden de forma
	// retrocompatible SIN bumpear esta versión — `ImportadorBancoPeriodistas`
	// defaultea el campo ausente en exportaciones antiguas, nunca falla el
	// import por su ausencia. Bumpear rompería la importación de todo
	// archivo exportado antes del cambio.
	public const VERSION_FORMATO = '1.0';

	public function __construct(
		private readonly RepositorioPeriodistasInterface $repoPeriodistas,
		private readonly RepositorioMemoriaEditorialInterface $repoMemoria,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * @return array{version: string, exportadoEn: string, periodistas: list<array{nombre: string, avatarUrl: ?string, biografia: string, rol: string, especialidades: list<array{vertical: string, nivelDominio: int}>, estado: string, localeEditorial: string, versionesConducta: list<array{diales: array<string, mixed>, reglasConducta: array<string, mixed>, matrizTonos: array<string, mixed>}>, memoria: list<array{tipo: string, tema: string, contenido: array<string, mixed>}>}>}
	 */
	public function exportar(): array {
		$periodistas = array();

		foreach ( $this->repoPeriodistas->obtenerTodos() as $periodista ) {
			$versiones = array_map(
				static fn ( ConductaVersion $v ): array => array(
					'diales'         => $v->diales->aArray(),
					'reglasConducta' => $v->reglas->aArray(),
					'matrizTonos'    => $v->matrizTonos->aArray(),
				),
				$this->repoPeriodistas->obtenerHistorialVersiones( $periodista->id )
			);

			$memoria = array_map(
				static fn ( EntradaMemoria $m ): array => array(
					'tipo'      => $m->tipo->value,
					'tema'      => $m->tema,
					'contenido' => $m->contenido,
				),
				$this->repoMemoria->obtenerTodoPorPeriodista( $periodista->id )
			);

			$periodistas[] = array(
				'nombre'            => $periodista->nombre,
				'avatarUrl'         => $periodista->avatarUrl,
				'biografia'         => $periodista->biografia,
				'rol'               => $periodista->rol->value,
				'especialidades'    => array_map( static fn ( Especialidad $e ): array => $e->aArray(), $periodista->especialidades ),
				'estado'            => $periodista->estado->value,
				'localeEditorial'   => $periodista->localeEditorial,
				'versionesConducta' => $versiones,
				'memoria'           => $memoria,
			);
		}

		return array(
			'version'     => self::VERSION_FORMATO,
			'exportadoEn' => $this->reloj->ahora()->format( DATE_ATOM ),
			'periodistas' => $periodistas,
		);
	}
}
