<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use DateTimeImmutable;
use Pluma\Proveedores\LenguajeInterface;
use Pluma\Proveedores\PeticionLenguaje;
use Pluma\Proveedores\PropositoLenguaje;

/**
 * El Estudio de Conducta (Libro Cap. 10.2): "un párrafo de muestra que se
 * re-redacta al mover un dial — la función que enamora en las demos". Nunca
 * escribe sobre una Pieza real; redacta un hecho neutro fijo con la
 * conducta CANDIDATA (todavía sin guardar) para que el editor sienta el
 * efecto de un dial antes de confirmarlo.
 *
 * El presupuesto se respeta sin excepción ni bypass (decisión del
 * propietario, 2026-07-23): `LenguajeInterface::completar()` ya verifica
 * `PresupuestoLenguaje::disponible()` antes de cada llamada — esta clase no
 * duplica ni rodea esa verificación.
 */
final class GeneradorVistaPrevia {

	private const MAX_TOKENS_RESPUESTA = 220;

	/**
	 * Hecho neutro fijo, el mismo para todo periodista, para que el efecto
	 * de mover un dial sea comparable — nunca una Pieza ni un expediente
	 * real (esto es una demostración de conducta, no redacción de verdad).
	 */
	private const HECHO_MUESTRA = <<<'TEXTO'
	El banco central subió la tasa de referencia medio punto porcentual, la
	tercera subida consecutiva este año. El comunicado oficial atribuye la
	decisión a la inflación de alimentos, que se mantiene por encima del
	objetivo desde hace cuatro meses. Analistas del sector financiero se
	dividen: unos esperan que el consumo se enfríe en el corto plazo, otros
	sostienen que el efecto tardará al menos dos trimestres en notarse.
	TEXTO;

	public function __construct( private readonly LenguajeInterface $proveedor ) {
	}

	/**
	 * @throws \Pluma\Proveedores\ProveedorLenguajeException presupuesto agotado, sin credenciales, o fallo del proveedor.
	 */
	public function generar(
		Periodista $periodista,
		Diales $dialesCandidatos,
		ReglasConducta $reglasCandidatas,
		MatrizTonos $matrizCandidata,
	): string {
		$tipoNoticiaMuestra = TipoNoticia::DatoEconomico;

		try {
			$fila = $matrizCandidata->paraTipo( $tipoNoticiaMuestra );
		} catch ( MatrizTonosIncompletaException ) {
			$fila = new EntradaMatrizTono( $tipoNoticiaMuestra, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No );
		}

		$periodistaConConductaCandidata = new Periodista(
			$periodista->id,
			$periodista->nombre,
			$periodista->avatarUrl,
			$periodista->biografia,
			$periodista->rol,
			$periodista->especialidades,
			$periodista->estado,
			new ConductaVersion( 0, $periodista->id, $dialesCandidatos, $reglasCandidatas, $matrizCandidata, false, new DateTimeImmutable() ),
			$periodista->creadoEn,
			$periodista->actualizadoEn,
			$periodista->localeEditorial
		);

		$directrices = ( new CompiladorDirectrices() )->compilar(
			$periodistaConConductaCandidata,
			$fila->tonoDominante,
			$fila->tonoApoyo,
			$fila->nivelSatira
		);

		$directrices .= "\n\nEscribe un único párrafo corto (60-90 palabras) a partir del hecho de muestra. Sin titular, sin firma, solo el párrafo.";

		$respuesta = $this->proveedor->completar(
			new PeticionLenguaje( PropositoLenguaje::VistaPrevia, $directrices, self::HECHO_MUESTRA, self::MAX_TOKENS_RESPUESTA )
		);

		return trim( $respuesta->contenido );
	}
}
