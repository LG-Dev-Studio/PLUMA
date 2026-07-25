<?php

declare(strict_types=1);

namespace Pluma\Compuertas;

/**
 * Perfil de jurisdicción de fábrica (Nivel Tres N.1): "un umbral único
 * calibrado para el régimen más permisivo es insuficiente para el más
 * severo". En régimen `Penal` (frecuente en varios países hispanohablantes),
 * toda afirmación fáctica negativa sobre persona identificable exige
 * retención humana obligatoria y excluye el modo Autónomo, sin excepción
 * configurable — "un perfil de jurisdicción no es un dial que el cliente
 * pueda relajar: relajarlo no protege al producto de una demanda, protege al
 * vendedor del plugin de la ficción de haber advertido".
 *
 * Default de fábrica `Civil` (decisión del propietario, Etapa 7): el cliente
 * activa `Penal` explícitamente si su jurisdicción real lo exige.
 */
enum RegimenResponsabilidad: string {

	case Civil = 'civil';
	case Penal = 'penal';
}
