<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Registro formal de modelos (`docs/CEREBRO_PLUMA_v2.md` Parte 5.1, regla 5;
 * `docs/decisiones/0019-ncp2-porcion-5-registro-modelos-formal.md`). Solo
 * lista modelos REALMENTE en uso por PLUMA hoy — no candidatos investigados
 * en `ADR 0014` que todavía no se construyeron (inventar entradas
 * "planeadas" violaría cero-invención).
 *
 * Consolida lo que antes era `ProveedorEmbeddingsCerebroRemoto::MODELO_REFERENCIA`
 * (una constante suelta, retirada en esta porción) como única fuente de
 * verdad. No aplica ningún enforcement runtime ("sin registro, no carga")
 * todavía — no existe hoy ningún transporte que descargue/cargue un
 * artefacto local (T1/T2 sin construir) al que enganchar ese gate.
 */
final class RegistroModelos {

	/**
	 * @return list<ModeloRegistrado>
	 */
	public function todos(): array {
		return array(
			new ModeloRegistrado(
				RolModelo::Enc,
				'intfloat/multilingual-e5-small',
				'sin versión de release semántica publicada por el autor — Hugging Face no versiona por release, identificado por su repositorio (ver procedencia)',
				'MIT',
				'multilingüe (100+ idiomas, ver ficha del modelo)',
				null,
				'T3 remoto: el modelo vive en el servicio remoto (Hugging Face Text Embeddings Inference), PLUMA nunca lo descarga ni lo ejecuta localmente (ADR 0016)',
				'https://huggingface.co/intfloat/multilingual-e5-small — licencia y transporte verificados en ADR 0014/ADR 0016'
			),
			new ModeloRegistrado(
				RolModelo::Nli,
				'MoritzLaurer/xlm-v-base-mnli-xnli',
				'sin versión de release semántica publicada por el autor — identificado por su repositorio (ver procedencia)',
				'MIT',
				'16 idiomas (entrenado en multi_nli + xnli, ver ficha del modelo)',
				null,
				'T3 remoto: el modelo vive en el servicio remoto (Hugging Face Text Embeddings Inference), PLUMA nunca lo descarga ni lo ejecuta localmente (ADR 0020)',
				'https://huggingface.co/MoritzLaurer/xlm-v-base-mnli-xnli — licencia, arquitectura y transporte verificados en ADR 0020 (sustituye al candidato original de ADR 0014, incompatible con TEI)'
			),
			new ModeloRegistrado(
				RolModelo::Rrk,
				'BAAI/bge-reranker-base',
				'sin versión de release semántica publicada por el autor — identificado por su repositorio (ver procedencia)',
				'MIT',
				'chino e inglés (ver ficha del modelo — no multilingüe pese al ecosistema BGE ser conocido como tal)',
				null,
				'T3 remoto: el modelo vive en el servicio remoto (Hugging Face Text Embeddings Inference), PLUMA nunca lo descarga ni lo ejecuta localmente (ADR 0020)',
				'https://huggingface.co/BAAI/bge-reranker-base — licencia, arquitectura y transporte verificados en ADR 0020 (sustituye a los 2 candidatos originales de ADR 0014, ninguno directamente verificable con TEI)'
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
