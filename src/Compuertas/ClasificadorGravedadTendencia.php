<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;
use Pluma\Sensores\TendenciaDetectada;

/**
 * Clasifica gravedad + campo temático/geográfico de una tendencia justo
 * cuando entra al pipeline (Nivel Dos F.1-F.2) — el Radar nunca calculó esto
 * hasta ahora. Llamada económica (`PropositoLenguaje::Clasificar`), mismo
 * costo que `ClasificadorNoticia` — el eje de gravedad es el mismo concepto
 * (0 = viral ligero, 100 = tragedia), solo que evaluado antes, sobre la
 * tendencia cruda, no sobre el expediente ya investigado.
 */
final class ClasificadorGravedadTendencia {

	private const MAX_TOKENS_RESPUESTA = 300;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @throws GravedadTendenciaException si la respuesta no trae los campos esperados.
	 * @throws CompuertaException si el proveedor devolvió una respuesta truncada o sin JSON reconocible.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function clasificar( TendenciaDetectada $tendencia ): GravedadTendencia {
		$directrices = implode(
			"\n",
			array(
				'Eres el editor de guardia de un medio digital, evaluando una tendencia recién detectada ANTES de asignarla a redacción.',
				'Clasifica exactamente tres campos sobre esta tendencia.',
				'Responde ÚNICAMENTE con un objeto JSON, sin texto adicional, con esta forma exacta:',
				'{"gravedad": integer 0-100 (0 = viral ligero, 100 = tragedia de gravedad excepcional), '
					. '"campoTematico": string (categoría amplia del evento, ej. "atentado", "desastre_natural", "accidente_industrial", "politica"), '
					. '"campoGeografico": string o null (país/región identificable del evento; null si no hay uno claro)}',
			)
		);

		$peticion = new PeticionLenguaje(
			PropositoLenguaje::Clasificar,
			$directrices,
			$this->materialDeTendencia( $tendencia ),
			self::MAX_TOKENS_RESPUESTA
		);

		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		return $this->aGravedad( $datos );
	}

	private function materialDeTendencia( TendenciaDetectada $tendencia ): string {
		$lineas = array( "Término: {$tendencia->termino}" );

		foreach ( $tendencia->articulosRelacionados as $articulo ) {
			$lineas[] = "- {$articulo['titulo']} ({$articulo['fuente']})";
		}

		return implode( "\n", $lineas );
	}

	/**
	 * @param array<string, mixed> $datos
	 */
	private function aGravedad( array $datos ): GravedadTendencia {
		if (
			! isset( $datos['gravedad'], $datos['campoTematico'] )
			|| ! array_key_exists( 'campoGeografico', $datos )
			|| ! is_numeric( $datos['gravedad'] )
			|| ! is_string( $datos['campoTematico'] )
			|| ( null !== $datos['campoGeografico'] && ! is_string( $datos['campoGeografico'] ) )
		) {
			throw new GravedadTendenciaException( 'La clasificación de gravedad no trae los tres campos esperados.' );
		}

		return new GravedadTendencia(
			max( 0, min( 100, (int) $datos['gravedad'] ) ),
			$datos['campoTematico'],
			$datos['campoGeografico']
		);
	}
}
