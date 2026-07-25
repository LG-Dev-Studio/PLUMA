<?php

declare(strict_types=1);

namespace Pluma\Tests\Invariantes;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Compuertas\CompuertaCalidad;
use Pluma\Compuertas\CompuertaOriginalidad;
use Pluma\Compuertas\CompuertaRiesgo;
use Pluma\Compuertas\EvaluadorCompuertas;
use Pluma\Compuertas\GestorDegradacion;
use Pluma\Compuertas\ModoOperacion;
use Pluma\Compuertas\VerificadorLegibilidad;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Redaccion\AnotacionCorrector;
use Pluma\Redaccion\Borrador;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\PuntoCorrector;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * GOVERNANCE §2.3 — "Afirmación fáctica negativa sobre persona identificable
 * sin doble fuente 'verificada' → estado RETENIDA."
 *
 * Si este test se pone en rojo, una pieza podría publicarse en modo Autónomo
 * acusando a una persona identificable sin el respaldo que la ley exige — el
 * escenario que existe para impedir la difamación automatizada.
 *
 * @covers \Pluma\Compuertas\EvaluadorCompuertas
 * @covers \Pluma\Compuertas\CompuertaRiesgo
 */
final class DifamacionRetenidaInvarianteTest extends CasoDePruebaUnitario {

	public function test_una_afirmacion_negativa_sin_doble_fuente_fuerza_retencion_incluso_en_modo_autonomo(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$expediente = new Expediente(
			'x',
			array( new HechoFuente( 'un hecho atribuido a una sola fuente, sin confirmación independiente', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ) )
		);

		$proveedor = new ProveedorLenguajeFalso(
			'{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": true, '
				. '"detalleDifamacion": "la pieza acusa a un funcionario identificable de corrupción con una sola fuente atribuida, no verificada", '
				. '"hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": false, "posturaSenaladoAusente": false}'
		);

		$evaluador = new EvaluadorCompuertas(
			new CompuertaCalidad( new VerificadorLegibilidad() ),
			new CompuertaRiesgo( $proveedor ),
			new CompuertaOriginalidad(),
			new GestorDegradacion()
		);

		$borradorAprobado = new Borrador(
			1,
			1,
			1,
			'contenido',
			array(
				new AnotacionCorrector( PuntoCorrector::Hechos, true, 'ok' ),
				new AnotacionCorrector( PuntoCorrector::ProporcionInterpretativa, true, 'ok' ),
				new AnotacionCorrector( PuntoCorrector::Voz, true, 'ok' ),
			),
			true,
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);

		$resultado = $evaluador->evaluar(
			$expediente,
			new ClasificacionNoticia( 'politica', 40, 'gobierno vs. oposición', NovedadNoticia::Primicia, 60, TipoNoticia::EscandaloPolitico ),
			new EsqueletoPieza( 'gancho', 'hechos', array( 'm1', 'm2' ), 'contra', 'remate' ),
			$borradorAprobado,
			'El banco central subió la tasa de interés al nueve por ciento este martes. Los analistas esperaban un movimiento más cauto.',
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertFalse( $resultado->aprobada, 'Una pieza con riesgo de difamación jamás debe quedar "aprobada", ni en modo Autónomo.' );
		self::assertTrue( $resultado->retenida, 'El riesgo de difamación debe forzar RETENIDA — el sistema nunca decide solo esta categoría.' );
		self::assertNotEmpty( $resultado->motivos );
	}
}
