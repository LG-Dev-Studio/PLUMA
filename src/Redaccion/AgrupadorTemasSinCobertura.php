<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PresupuestoLenguaje;
use Pluma\Proveedores\ProveedorLenguajeException;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * Trabajo posterior a la Etapa 9 (creación automática de periodistas): ¿un
 * grupo de temas que hoy ningún periodista activo cubre forma UN tema
 * coherente que justifica un periodista nuevo, o son casos sueltos sin
 * relación entre sí? Modelo económico y determinista
 * (`PropositoLenguaje::AgruparTemasSinCobertura`), mismo patrón que
 * `Pluma\Sensores\ComparadorHistorias`.
 *
 * **Fail-safe obligatorio**: sin presupuesto, con el proveedor caído, o con
 * una respuesta no interpretable, `evaluar()` devuelve `null` en vez de
 * lanzar — la guarda real contra "un periodista por cada noticia" vive en
 * `CreadorAutomaticoPeriodistas` (tamaño mínimo del clúster), no aquí; esta
 * clase nunca debe ser la única defensa, y un fallo suyo jamás bloquea el
 * tick del Orquestador.
 */
final class AgrupadorTemasSinCobertura {

	private const MAX_TOKENS_RESPUESTA = 300;
	private const MAX_MUESTRAS         = 20;
	private const LONGITUD_EXTRACTO    = 300;

	public function __construct(
		private readonly LenguajeInterface $proveedor,
		private readonly PresupuestoLenguaje $presupuesto,
	) {
	}

	/**
	 * @param list<array{tema: string, extracto: string}> $muestras ya deduplicadas por tema.
	 */
	public function evaluar( array $muestras ): ?PropuestaPeriodistaAutomatico {
		if ( array() === $muestras || ! $this->presupuesto->disponible() ) {
			return null;
		}

		try {
			return $this->evaluarConProveedor( array_slice( $muestras, 0, self::MAX_MUESTRAS ) );
		} catch ( ProveedorLenguajeException | DecisionEditorialException $excepcion ) {
			return null;
		}
	}

	/**
	 * @param list<array{tema: string, extracto: string}> $muestras
	 *
	 * @throws ProveedorLenguajeException
	 * @throws DecisionEditorialException
	 */
	private function evaluarConProveedor( array $muestras ): ?PropuestaPeriodistaAutomatico {
		$roles       = implode( ', ', array_map( static fn ( RolPeriodista $r ): string => $r->value, RolPeriodista::cases() ) );
		$directrices = implode(
			"\n",
			array(
				'Eres el director editorial de un medio digital que necesita decidir si le hace falta un periodista nuevo.',
				'Te doy una lista de temas que el banco de periodistas actual NO puede cubrir (ningún periodista activo domina lo suficiente ese tema), cada uno con un extracto real de una noticia relacionada.',
				'Decide si estos temas forman UN tema editorial coherente que justifica dedicar un periodista nuevo específicamente a él, o si son casos sueltos sin relación real entre sí (en ese caso, no crear nada).',
				'Si decides crear: propone un vertical corto en minúsculas (ej. "deportes", "tecnologia"), un nombre humano plausible para el periodista, una biografía breve (1-2 frases) coherente con ese vertical, un rol de entre: ' . $roles . ', y un nivel de dominio entero de 1 a 5 que refleje qué tan central es ese vertical para el periodista (normalmente 4).',
				'Responde ÚNICAMENTE con un objeto JSON, sin texto adicional, con esta forma exacta:',
				'{"crearPeriodista": boolean, "vertical": string, "nombre": string, "biografia": string, "rol": string, "nivelDominio": integer}',
				'Si "crearPeriodista" es false, los demás campos pueden ir vacíos.',
			)
		);

		$peticion = new PeticionLenguaje(
			PropositoLenguaje::AgruparTemasSinCobertura,
			$directrices,
			$this->material( $muestras ),
			self::MAX_TOKENS_RESPUESTA
		);

		$respuesta = $this->proveedor->completar( $peticion );
		VerificadorTruncamiento::asegurar( $respuesta );
		$datos = ExtractorJsonLlm::extraer( $respuesta->contenido );

		return $this->aPropuesta( $datos );
	}

	/**
	 * @param list<array{tema: string, extracto: string}> $muestras
	 */
	private function material( array $muestras ): string {
		$lineas = array();

		foreach ( $muestras as $muestra ) {
			$lineas[] = 'TEMA: ' . $muestra['tema'];
			$lineas[] = '  Extracto: ' . mb_substr( $muestra['extracto'], 0, self::LONGITUD_EXTRACTO );
			$lineas[] = '';
		}

		return implode( "\n", $lineas );
	}

	/**
	 * @param array<string, mixed> $datos
	 */
	private function aPropuesta( array $datos ): ?PropuestaPeriodistaAutomatico {
		if ( ! isset( $datos['crearPeriodista'] ) || true !== $datos['crearPeriodista'] ) {
			return null;
		}

		$vertical     = $datos['vertical'] ?? null;
		$nombre       = $datos['nombre'] ?? null;
		$biografia    = $datos['biografia'] ?? null;
		$rolTexto     = $datos['rol'] ?? null;
		$nivelDominio = $datos['nivelDominio'] ?? null;

		if ( ! is_string( $vertical ) || '' === trim( $vertical )
			|| ! is_string( $nombre ) || '' === trim( $nombre )
			|| ! is_string( $biografia ) || '' === trim( $biografia )
			|| ! is_string( $rolTexto )
			|| ! is_numeric( $nivelDominio ) ) {
			// Respuesta con "crearPeriodista": true pero forma inválida — la
			// misma disciplina de "no inventar" que el resto del pipeline: no
			// se rellenan huecos con valores por defecto inventados, se trata
			// como "no crear" (fail-safe, nunca fail-open).
			return null;
		}

		$rol = RolPeriodista::tryFrom( $rolTexto );

		if ( null === $rol ) {
			return null;
		}

		$nivel = (int) $nivelDominio;

		if ( $nivel < 1 || $nivel > 5 ) {
			return null;
		}

		return new PropuestaPeriodistaAutomatico(
			mb_strtolower( trim( $vertical ) ),
			trim( $nombre ),
			trim( $biografia ),
			$rol,
			$nivel
		);
	}
}
