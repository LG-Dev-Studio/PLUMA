<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Los 8 roles del Plano 1 Semántico (`docs/CEREBRO_PLUMA_v2.md` Parte 1.3,
 * `ADR 0014` §2): cada órgano es un rol, no un modelo concreto — el registro
 * (`RegistroModelos`) mapea rol → artefacto real en uso.
 */
enum RolModelo: string {

	case Enc = 'enc';
	case Ner = 'ner';
	case Seg = 'seg';
	case Lid = 'lid';
	case Nli = 'nli';
	case Rrk = 'rrk';
	case Cls = 'cls';
	case Tox = 'tox';
}
