<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Nivel Cuatro X.1 — "la misma filosofía del Capítulo 8, aplicada a la
 * entrada": clasificación automática de cada comentario entrante (spam /
 * odio-ataque personal / afirmación fáctica riesgosa sobre terceros /
 * crítica legítima / aporte con información). Económico y determinista,
 * mismo patrón que `Pluma\Redaccion\AnalizadorAudiencia`.
 *
 * **Fail-safe obligatorio**: sin presupuesto, con el proveedor caído, o con
 * una respuesta no interpretable, `clasificar()` devuelve `null` — el
 * llamador (`CompuertaComentarios`) respeta el criterio nativo de WordPress
 * en ese caso, nunca bloquea ni aprueba a ciegas un envío de comentario en
 * vivo.
 */
final class ClasificadorComentarios {

	private const MAX_TOKENS_RESPUESTA = 60;

	public function __construct(
		private readonly LenguajeInterface $proveedor,
		private readonly PresupuestoLenguaje $presupuesto,
	) {
	}

	public function clasificar( string $comentarioTexto ): ?CategoriaComentario {
		if ( ! $this->presupuesto->disponible() ) {
			return null;
		}

		try {
			return $this->clasificarConProveedor( $comentarioTexto );
		} catch ( ProveedorLenguajeException | CompuertaException ) {
			return null;
		}
	}

	/**
	 * @throws ProveedorLenguajeException
	 * @throws CompuertaException
	 */
	private function clasificarConProveedor( string $comentarioTexto ): CategoriaComentario {
		$directrices = implode(
			"\n",
			array(
				'Eres el clasificador de comentarios de un medio digital de noticias con opinión vehemente y sátira.',
				'Clasifica el comentario de un lector en EXACTAMENTE una de estas cinco categorías:',
				'"spam": publicidad, enlaces sin relación, contenido automatizado o sin sentido.',
				'"odio_ataque_personal": insulto, acoso o ataque dirigido a una persona, no a sus ideas o argumentos.',
				'"afirmacion_riesgosa": afirma como hecho algo negativo y específico sobre un tercero identificable (persona u organización) sin que el propio comentario aporte respaldo — riesgo de difamación.',
				'"critica_legitima": desacuerdo, opinión o crítica dirigida a argumentos, ideas o hechos — no a personas.',
				'"aporte_informativo": añade un dato, fuente o contexto verificable relevante para el tema.',
				'Responde ÚNICAMENTE con un objeto JSON, sin texto adicional, con esta forma exacta:',
				'{"categoria": "spam" o "odio_ataque_personal" o "afirmacion_riesgosa" o "critica_legitima" o "aporte_informativo"}',
			)
		);

		$peticion  = new PeticionLenguaje( PropositoLenguaje::ClasificarComentario, $directrices, $comentarioTexto, self::MAX_TOKENS_RESPUESTA );
		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		return $this->aCategoria( $datos );
	}

	/**
	 * @param array<string, mixed> $datos
	 *
	 * @throws CompuertaException
	 */
	private function aCategoria( array $datos ): CategoriaComentario {
		if ( ! isset( $datos['categoria'] ) || ! is_string( $datos['categoria'] ) ) {
			throw new CompuertaException( 'La clasificación de comentario no trae categoría.' );
		}

		$categoria = CategoriaComentario::tryFrom( $datos['categoria'] );

		if ( null === $categoria ) {
			throw new CompuertaException( 'La clasificación de comentario usó una categoría desconocida.' );
		}

		return $categoria;
	}
}
