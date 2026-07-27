<?php

declare(strict_types=1);

namespace Pluma\Investigacion;

use Pluma\Kernel\RelojInterface;

/**
 * Construye el expediente a partir de los artículos que el propio Sensor ya
 * trae agregados (Google Trends cita medios reales por tendencia). Cada
 * hecho es el título real de un artículo real, con su URL — cero texto
 * inventado. Nivel `Atribuido`: es la curaduría de una sola fuente
 * (Google), no una triangulación independiente entre 2+ medios (Cap. 4.2).
 *
 * Nivel Tres L.1: cada hecho pasa por el Protocolo de Verificación de
 * Procedencia de la Declaración (Fase 2.5) antes de entrar al expediente.
 */
final class InvestigadorMecanico implements InvestigadorInterface {

	public function __construct(
		private readonly RelojInterface $reloj,
		private readonly VerificadorProcedenciaDeclaracion $verificadorProcedencia,
	) {
	}

	public function investigar( string $termino, array $articulosRelacionados ): Expediente {
		$ahora = $this->reloj->ahora();

		$hechos = array_map(
			fn ( array $articulo ): HechoFuente => new HechoFuente(
				$articulo['titulo'],
				$articulo['url'],
				$ahora,
				NivelVerificacion::Atribuido,
				$this->verificadorProcedencia->detectar( $articulo['titulo'], $articulo['url'] )
			),
			$articulosRelacionados
		);

		return new Expediente( $termino, $hechos );
	}
}
