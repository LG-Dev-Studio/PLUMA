<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Nivel Cuatro W.2 — la adaptación por canal, no la republicación: "extracto
 * social con gancho en la voz del periodista... y metadatos de Discover
 * (imagen grande, titular editorial)". Regla de sistema del propio texto
 * fuente: "los derivados JAMÁS contradicen ni exageran la pieza — un
 * derivado clickbait envenena la marca igual que un titular clickbait" —
 * las directrices se lo piden explícitamente al proveedor; la verificación
 * determinista completa (mismo rigor que `CorrectorInterno`) queda como
 * `PLUMA-E9-6` (no forzar `CorrectorInterno::revisar()`, que exige un
 * `Expediente` completo que un derivado no tiene).
 */
final class GeneradorDerivadoSocial {

	private const MAX_TOKENS_DERIVADO = 200;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @return array{extractoSocial: string, titularDiscover: string}
	 *
	 * @throws DecisionEditorialException si el derivado no trae los dos campos, o llegó truncado.
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException
	 */
	public function generar( Periodista $periodista, string $tituloPieza, string $extractoPieza ): array {
		$reglas = $periodista->conductaActual->reglas;

		$directrices = implode(
			"\n",
			array(
				"Eres {$periodista->nombre}, adaptando tu propia pieza ya publicada para redes sociales y Google Discover.",
				"Tu línea editorial: {$reglas->lineaEditorial}.",
				'`extractoSocial`: 1-2 líneas con gancho, en tu voz, para acompañar el enlace en redes.',
				'`titularDiscover`: un titular editorial (no clickbait) pensado para la tarjeta de Discover.',
				'Regla estricta: ninguno de los dos campos puede contradecir ni exagerar la pieza original — un derivado que exagera daña la marca igual que un titular clickbait.',
				'Nunca cruces tus líneas rojas: ' . implode( ', ', $reglas->lineasRojas ) . '.',
				'Responde ÚNICAMENTE con un objeto JSON de esta forma exacta: {"extractoSocial": string, "titularDiscover": string}',
			)
		);

		$material = sprintf( "Título original: %s\nExtracto de la pieza: %s", $tituloPieza, $extractoPieza );

		$peticion  = new PeticionLenguaje( PropositoLenguaje::DerivadoSocial, $directrices, $material, self::MAX_TOKENS_DERIVADO );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		if ( ! isset( $datos['extractoSocial'], $datos['titularDiscover'] ) || ! is_string( $datos['extractoSocial'] ) || ! is_string( $datos['titularDiscover'] ) || '' === trim( $datos['extractoSocial'] ) || '' === trim( $datos['titularDiscover'] ) ) {
			throw new DecisionEditorialException( 'El derivado social no trae extracto y titular de Discover.' );
		}

		return array(
			'extractoSocial'  => $datos['extractoSocial'],
			'titularDiscover' => $datos['titularDiscover'],
		);
	}
}
