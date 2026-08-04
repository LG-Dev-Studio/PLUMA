<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Registro formal de modelos (`docs/CEREBRO_PLUMA_v2.md` Parte 5.1, regla 5;
 * `docs/decisiones/0019-ncp2-porcion-5-registro-modelos-formal.md`). Solo
 * lista modelos REALMENTE en uso por PLUMA hoy — no candidatos investigados
 * que todavía no se construyeron (inventar entradas "planeadas" violaría
 * cero-invención).
 *
 * `ADR 0024` retiró las 3 entradas T3 (ENC/NLI/RRK vía cerebro remoto,
 * `ADR 0016`/`ADR 0020`) — ninguna sobrevive: ENC nunca tuvo consumidor real
 * (los 2 consumidores reales de `EmbeddingsInterface` siempre estuvieron
 * ligados a `ProveedorOpenRouter`, API de pago, no a T3); NLI/RRK se
 * reemplazan por las entradas pure-PHP de abajo — la primera vez que este
 * registro tiene un `checksum` real (no `null`): el artefacto ahora vive
 * dentro del propio plugin, no en un servicio remoto que nunca se descarga.
 */
final class RegistroModelos {

	/**
	 * @return list<ModeloRegistrado>
	 */
	public function todos(): array {
		return array(
			new ModeloRegistrado(
				RolModelo::Nli,
				'Clasificador propio (Rubix ML: RandomForest sobre ClassificationTree balanceado), entrenado offline por `tools/entrenamiento-nli/entrenar.php`',
				'entrenado 2026-08-03, exactitud real 49,8% sobre split de prueba de 1.612 ejemplos (ver ADR 0024 para el desglose completo por clase)',
				'MIT (Rubix ML) + CC-BY-4.0 (dataset de entrenamiento: InferES, Kovatchev & Taulé 2022, atribución obligatoria)',
				'español (peninsular) — cobertura del dataset InferES, dialectal más allá de España sin verificar',
				'091b7a51fabc14721eb7f581f02a76f2e47627aa890ea7d4c2608279ec0e34c4',
				null,
				'recursos/modelos/nli-es.rbx + nli-es-vocab.json, dentro del propio plugin — https://huggingface.co/datasets/venelin/inferes (dataset de entrenamiento, licencia y contenido verificados en ADR 0024)'
			),
			new ModeloRegistrado(
				RolModelo::Rrk,
				'TF-IDF + similitud de coseno (técnica léxica, sin modelo entrenado ni descargado — `Pluma\\Proveedores\\ProveedorRerankLexico`)',
				'n/a — técnica determinista, no versionada como artefacto',
				'n/a — código propio del plugin, sin dependencia de terceros para este rol',
				'n/a — técnica léxica, no depende de idioma entrenado',
				null,
				'No aplica: no hay artefacto que descargar ni ejecutar, es cálculo puro sobre el texto de cada petición (ADR 0024)',
				'docs/CEREBRO_PLUMA_v2.md §1.2 (Plano 0: "BM25/TF-IDF sobre el archivo propio" ya listado como léxico puro)'
			),
		);
	}

	/**
	 * @return list<ModeloRegistrado>
	 */
	public function porRol( RolModelo $rol ): array {
		return array_values(
			array_filter(
				$this->todos(),
				static fn ( ModeloRegistrado $entrada ): bool => $entrada->rol === $rol
			)
		);
	}
}
