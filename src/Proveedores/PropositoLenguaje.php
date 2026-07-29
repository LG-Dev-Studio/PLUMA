<?php

declare(strict_types=1);

namespace Pluma\Proveedores;

/**
 * Propósitos de una petición al proveedor de lenguaje
 * (pl-proveedor-ia, `references/contrato-lenguaje.md`). El Enrutador asigna
 * modelo por propósito: clasificar barato, redactar premium.
 */
enum PropositoLenguaje: string {

	case Clasificar   = 'clasificar';
	case Angulos      = 'angulos';
	case Redactar     = 'redactar';
	case Corregir     = 'corregir';
	case Titulares    = 'titulares';
	case BloqueEditor = 'bloque_editor';
	/**
	 * Vista previa en vivo del Estudio de Conducta (Libro Cap. 10.2): un
	 * párrafo corto que se re-redacta al mover un dial. Deliberadamente NO
	 * premium — es una demostración, no una Pieza real, y consume del mismo
	 * presupuesto diario compartido (decisión del propietario, 2026-07-23).
	 */
	case VistaPrevia = 'vista_previa';
	/**
	 * Huella semántica del Radar (Libro Cap. 3.4, Etapa 5): ¿una tendencia
	 * nueva es la misma historia que una ya procesada, o su evolución?
	 * Económico y determinista, igual que `Clasificar` — es una comparación
	 * estructurada, no redacción.
	 */
	case CompararHistorias = 'comparar_historias';
	/**
	 * Memoria de audiencia (Libro Cap. 5.7, Etapa 5): extrae un aprendizaje
	 * breve de un comentario sustantivo real. Económico y determinista,
	 * mismo criterio que `CompararHistorias` — es análisis, no creación.
	 */
	case AnalizarAudiencia = 'analizar_audiencia';
	/**
	 * Respuestas asistidas a comentarios (Libro Cap. 5.7, Etapa 5): borrador
	 * de respuesta del periodista a un comentario real, en su propia voz.
	 * Premium, mismo criterio que `BloqueEditor` — es voz pública del
	 * periodista, no una clasificación interna.
	 */
	case RespuestaComentario = 'respuesta_comentario';
	/**
	 * Fase 3.5 del Algoritmo de Decisión Editorial (Nivel Tres O.1): pasada
	 * adversarial acotada — "usando exclusivamente el expediente, construye
	 * el caso más fuerte posible en contra de esta tesis exacta". Mecanismo
	 * deliberadamente barato: económico, no premium.
	 */
	case Falsear = 'falsear';
	/**
	 * Compuertas de comentarios (Nivel Cuatro X.1, Etapa 9): "la misma
	 * filosofía del Capítulo 8, aplicada a la entrada" — clasifica cada
	 * comentario entrante en tiempo real, síncrono con el envío del
	 * visitante. Económico y determinista, mismo criterio que `Clasificar`.
	 */
	case ClasificarComentario = 'clasificar_comentario';
	/**
	 * El boletín como producto del periodista (Nivel Cuatro W.1, Etapa 9):
	 * párrafo de apertura redactado en la voz del periodista, "mismo
	 * pipeline, propósito boletin, mismas compuertas de tono" — literal del
	 * texto fuente. Premium, mismo criterio que `RespuestaComentario`: es
	 * voz pública del periodista, no una clasificación interna.
	 */
	case Boletin = 'boletin';
	/**
	 * Derivados por canal (Nivel Cuatro W.2, Etapa 9): extracto social +
	 * titular de Discover derivados de una Pieza ya redactada. Premium
	 * (voz pública del periodista, el anti-clickbait del Corrector aplica
	 * igual que a un titular normal — "un derivado clickbait envenena la
	 * marca igual que un titular clickbait", literal del texto fuente).
	 */
	case DerivadoSocial = 'derivado_social';
	/**
	 * Experimento de titular (Nivel Cuatro Y.2, Etapa 9): un segundo titular
	 * EDITORIAL candidato — nunca el title SEO (`Titulares`), "dos
	 * superficies, dos reglas" literal del texto fuente. Premium: es voz
	 * pública del periodista, mismo criterio que `Boletin`.
	 */
	case TitularAlternativo = 'titular_alternativo';

	public function esPremium(): bool {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- falso positivo: $this en un método de enum (PHP 8.1) es válido; el sniff aún no reconoce enums.
		return match ( $this ) {
			self::Redactar, self::Corregir, self::BloqueEditor, self::RespuestaComentario, self::Boletin, self::DerivadoSocial, self::TitularAlternativo => true,
			self::Clasificar, self::Angulos, self::Titulares, self::VistaPrevia, self::CompararHistorias, self::AnalizarAudiencia, self::Falsear, self::ClasificarComentario => false,
		};
	}

	/**
	 * Temperatura por propósito: tareas de clasificación deterministas,
	 * redacción con espacio creativo (contrato-lenguaje.md: "limites...
	 * temperatura por propósito").
	 */
	public function temperatura(): float {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- falso positivo: $this en un método de enum (PHP 8.1) es válido; el sniff aún no reconoce enums.
		return match ( $this ) {
			self::Clasificar, self::CompararHistorias, self::AnalizarAudiencia, self::ClasificarComentario => 0.0,
			self::Corregir, self::Falsear => 0.2,
			self::Angulos, self::Titulares => 0.8,
			self::Redactar, self::BloqueEditor, self::VistaPrevia, self::RespuestaComentario, self::Boletin, self::DerivadoSocial, self::TitularAlternativo => 0.7,
		};
	}
}
