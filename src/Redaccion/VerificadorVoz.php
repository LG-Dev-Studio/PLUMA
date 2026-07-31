<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Punto 4 del Corrector Interno (Libro Cap. 5.6): "¿Suena a la voz del
 * periodista?" — pl-periodistas §6 "Voz medible: rasgos estilísticos
 * presentes con frecuencia controlada, vocabulario prohibido ausente...
 * Es un test, no una aspiración." Verificación determinista, sin proveedor
 * de lenguaje.
 */
final class VerificadorVoz {

	public function verificar( Periodista $periodista, string $cuerpo ): AnotacionCorrector {
		$reglas            = $periodista->conductaActual->reglas;
		$cuerpoNormalizado = mb_strtolower( $cuerpo );

		foreach ( VocabularioProhibidoGlobal::combinarCon( $reglas->vocabularioProhibido, $periodista->localeEditorial ) as $frase ) {
			if ( str_contains( $cuerpoNormalizado, mb_strtolower( $frase ) ) ) {
				return new AnotacionCorrector( PuntoCorrector::Voz, false, "Vocabulario prohibido detectado en el texto: «{$frase}»." );
			}
		}

		if ( array() === $reglas->muletillas ) {
			return new AnotacionCorrector( PuntoCorrector::Voz, true, 'Sin vocabulario prohibido; el periodista no tiene rasgos de voz definidos.' );
		}

		$presentes = 0;

		foreach ( $reglas->muletillas as $muletilla ) {
			if ( str_contains( $cuerpoNormalizado, mb_strtolower( $muletilla ) ) ) {
				++$presentes;
			}
		}

		if ( 0 === $presentes ) {
			return new AnotacionCorrector( PuntoCorrector::Voz, false, 'Ningún rasgo de voz característico del periodista está presente en el texto.' );
		}

		if ( count( $reglas->muletillas ) === $presentes && count( $reglas->muletillas ) > 1 ) {
			return new AnotacionCorrector( PuntoCorrector::Voz, false, 'Todos los rasgos de voz aparecen a la vez: suena paródico, no natural (frecuencia no controlada).' );
		}

		return new AnotacionCorrector( PuntoCorrector::Voz, true, 'Voz reconocible con frecuencia controlada; sin vocabulario prohibido.' );
	}
}
