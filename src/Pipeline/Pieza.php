<?php

declare(strict_types=1);

namespace Pluma\Pipeline;

use DateTimeImmutable;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Investigacion\Expediente;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Seo\DatosSeo;
use Pluma\Taxonomia\ResultadoTaxonomia;

/**
 * Instantánea inmutable de una Pieza (GOVERNANCE §1.2): cada lectura del
 * repositorio devuelve un objeto nuevo. La persistencia y las transiciones
 * las gestiona `Transicionador` + `Pluma\Datos\RepositorioPiezas`, jamás
 * mutando esta instancia.
 */
final readonly class Pieza {

	public function __construct(
		public int $id,
		public int $tendenciaId,
		public EstadoPieza $estado,
		public ?Expediente $expediente,
		public ?int $postId,
		public DateTimeImmutable $creadaEn,
		public DateTimeImmutable $actualizadaEn,
		public ?int $periodistaId = null,
		public ?int $periodistaVersionId = null,
		public ?FichaDecisionEditorial $fichaDecisionEditorial = null,
		public ?ResultadoEvaluacion $resultadoCompuertas = null,
		public ?DatosSeo $datosSeo = null,
		public ?ResultadoTaxonomia $resultadoTaxonomia = null,
		public ?int $piezaOriginalId = null,
		// Nivel Cuatro U.1/U.4 (Etapa 9): agrupación en Historia (saga) +
		// tipo de Pieza dentro de esa saga. `historiaId` nulo = la Pieza
		// todavía no pertenece a ninguna Historia (caso normal, la mayoría
		// de las Piezas nunca lo necesitan). `tipo` por defecto `Original`.
		public ?int $historiaId = null,
		public TipoPieza $tipo = TipoPieza::Original,
		// Trabajo posterior a la Etapa 9 (creación automática de periodistas):
		// el `$clasificacion->tema` que `NingunPeriodistaIdoneoException` ya
		// calculaba y descartaba — nulo salvo cuando el estado es
		// SIN_PERIODISTA_IDONEO (ver `conTemaSinCubrir()`).
		public ?string $temaSinCubrir = null,
	) {
	}

	public function conEstado( EstadoPieza $nuevoEstado, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$nuevoEstado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	public function conExpediente( Expediente $expediente, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	public function conPostId( int $postId, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	public function conPeriodistaAsignado( int $periodistaId, int $periodistaVersionId, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$periodistaId,
			$periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	public function conFichaDecisionEditorial( FichaDecisionEditorial $ficha, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$ficha,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	public function conResultadoCompuertas( ResultadoEvaluacion $resultado, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$resultado,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	public function conDatosSeo( DatosSeo $datosSeo, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	public function conResultadoTaxonomia( ResultadoTaxonomia $resultado, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$resultado,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$this->temaSinCubrir
		);
	}

	/**
	 * Nivel Cuatro U.1 (Etapa 9): vincula la Pieza a una Historia — llamado
	 * por `GestorHistorias` cuando se confirma que esta Pieza forma parte
	 * de una saga (creación o actualización de una Historia existente).
	 */
	public function conHistoria( int $historiaId, TipoPieza $tipo, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$historiaId,
			$tipo,
			$this->temaSinCubrir
		);
	}

	/**
	 * Trabajo posterior a la Etapa 9 (creación automática de periodistas):
	 * registra el tema que ningún periodista activo pudo cubrir, en el
	 * momento exacto en que la Pieza cae a SIN_PERIODISTA_IDONEO.
	 */
	public function conTemaSinCubrir( string $tema, DateTimeImmutable $ahora ): self {
		return new self(
			$this->id,
			$this->tendenciaId,
			$this->estado,
			$this->expediente,
			$this->postId,
			$this->creadaEn,
			$ahora,
			$this->periodistaId,
			$this->periodistaVersionId,
			$this->fichaDecisionEditorial,
			$this->resultadoCompuertas,
			$this->datosSeo,
			$this->resultadoTaxonomia,
			$this->piezaOriginalId,
			$this->historiaId,
			$this->tipo,
			$tema
		);
	}
}
