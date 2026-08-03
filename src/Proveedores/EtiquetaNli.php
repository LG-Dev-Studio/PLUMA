<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Las 3 etiquetas de inferencia de lenguaje natural (NLI). Valores string
 * verificados contra la respuesta real del endpoint `/predict` de Hugging
 * Face Text Embeddings Inference sirviendo un modelo XLM-RoBERTa de
 * clasificación de secuencias (`ADR 0020`).
 */
enum EtiquetaNli: string {

	case Entailment    = 'entailment';
	case Neutral       = 'neutral';
	case Contradiccion = 'contradiction';
}
