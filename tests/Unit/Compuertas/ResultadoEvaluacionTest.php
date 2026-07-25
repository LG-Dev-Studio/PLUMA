<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Compuertas;

use Brain\Monkey\Functions;
use Pluma\Compuertas\DiagnosticoCalidad;
use Pluma\Compuertas\DiagnosticoOriginalidad;
use Pluma\Compuertas\DiagnosticoRiesgo;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\ResultadoEvaluacion;
use Pluma\Compuertas\TemaRegulado;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * `RepositorioPiezas::actualizarResultadoCompuertas()` persiste el
 * `ResultadoEvaluacion` completo como JSON (`diagnostico_compuertas`) y lo
 * rehidrata en cada lectura — si el ida-y-vuelta pierde un campo, la Sala de
 * Revisión mostraría un diagnóstico incompleto o incorrecto sin que ningún
 * error lo delate.
 *
 * @covers \Pluma\Compuertas\ResultadoEvaluacion
 * @covers \Pluma\Compuertas\DiagnosticoCalidad
 * @covers \Pluma\Compuertas\DiagnosticoRiesgo
 * @covers \Pluma\Compuertas\DiagnosticoOriginalidad
 */
final class ResultadoEvaluacionTest extends CasoDePruebaUnitario {

	private function resultado(): ResultadoEvaluacion {
		return new ResultadoEvaluacion(
			false,
			true,
			array( 'riesgoDifamacion' ),
			ModoOperacion::Copiloto,
			new DiagnosticoCalidad( 82, 70, true, true, array( 'estructura completa' ) ),
			new DiagnosticoRiesgo( true, false, false, true, true, 'acusación sin atribución', false, TemaRegulado::Legal ),
			new DiagnosticoOriginalidad( false, true, 0.55, 0.4 )
		);
	}

	public function test_ida_y_vuelta_por_array_preserva_todos_los_diagnosticos(): void {
		$original     = $this->resultado();
		$reconstruida = ResultadoEvaluacion::desdeArray( $original->aArray() );

		self::assertSame( $original->aprobada, $reconstruida->aprobada );
		self::assertSame( $original->retenida, $reconstruida->retenida );
		self::assertSame( $original->motivos, $reconstruida->motivos );
		self::assertSame( $original->modoEfectivo, $reconstruida->modoEfectivo );
		self::assertSame( $original->calidad->puntuacionTotal, $reconstruida->calidad->puntuacionTotal );
		self::assertSame( $original->calidad->sustentoAprobado, $reconstruida->calidad->sustentoAprobado );
		self::assertSame( $original->riesgo->temaRegulado, $reconstruida->riesgo->temaRegulado );
		self::assertSame( $original->riesgo->detalleDifamacion, $reconstruida->riesgo->detalleDifamacion );
		self::assertSame( $original->originalidad->ratioGananciaInformacion, $reconstruida->originalidad->ratioGananciaInformacion );
	}

	public function test_ida_y_vuelta_preserva_un_temaRegulado_nulo(): void {
		$original = new ResultadoEvaluacion(
			true,
			false,
			array(),
			ModoOperacion::Autonomo,
			new DiagnosticoCalidad( 90, 70, true, true, array() ),
			new DiagnosticoRiesgo( false, false, false, false, false, '', false, null ),
			new DiagnosticoOriginalidad( false, false, 0.8, 0.4 )
		);

		$reconstruida = ResultadoEvaluacion::desdeArray( $original->aArray() );

		self::assertNull( $reconstruida->riesgo->temaRegulado );
	}

	public function test_el_json_encode_de_aArray_produce_un_string_valido(): void {
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$json = wp_json_encode( $this->resultado()->aArray() );

		self::assertIsString( $json );
		self::assertSame( $this->resultado()->aArray(), json_decode( $json, true ) );
	}
}
