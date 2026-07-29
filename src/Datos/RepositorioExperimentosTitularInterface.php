<?php

declare(strict_types=1);

namespace Pluma\Datos;

use DateTimeImmutable;
use Pluma\Seo\ExperimentoTitular;

/**
 * Contrato del repositorio de experimentos de titular (Nivel Cuatro Y.2).
 */
interface RepositorioExperimentosTitularInterface {

	public function crear( int $piezaId, int $postId, string $tituloA, string $tituloB, DateTimeImmutable $ahora ): int;

	public function obtenerPorPostId( int $postId ): ?ExperimentoTitular;

	public function incrementarImpresion( int $id, string $variante ): void;

	public function incrementarClic( int $id, string $variante ): void;

	/**
	 * Experimentos todavía sin consolidar, creados antes de `$limiteCreacion`
	 * — listos para decidir un ganador.
	 *
	 * @return list<ExperimentoTitular>
	 */
	public function obtenerListosParaConsolidar( DateTimeImmutable $limiteCreacion, int $limite ): array;

	public function consolidar( int $id, string $tituloGanador, DateTimeImmutable $ahora ): bool;
}
