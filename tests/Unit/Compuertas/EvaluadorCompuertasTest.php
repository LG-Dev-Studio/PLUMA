<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Compuertas;

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
 * pl-compuertas §1: "toda ruta hacia publicar atraviesa las tres compuertas".
 *
 * @covers \Pluma\Compuertas\EvaluadorCompuertas
 */
final class EvaluadorCompuertasTest extends CasoDePruebaUnitario {

	private const TEXTO_LEGIBLE = 'El banco central subió la tasa de interés al nueve por ciento este martes. '
		. 'Los analistas esperaban un movimiento más cauto según el último informe trimestral publicado.';

	private const RIESGO_SIN_PROBLEMAS = '{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": false, "detalleDifamacion": "", "hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": false, "posturaSenaladoAusente": false}';

	private function expediente(): Expediente {
		return new Expediente(
			'x',
			array( new HechoFuente( 'un hecho verificado que no aparece en el texto final de esta prueba', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function clasificacion( TipoNoticia $tipo = TipoNoticia::DatoEconomico ): ClasificacionNoticia {
		return new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, $tipo );
	}

	private function esqueletoCompleto(): EsqueletoPieza {
		return new EsqueletoPieza( 'gancho', 'hechos esenciales', array( 'm1', 'm2' ), 'contraargumento', 'remate' );
	}

	private function borradorAprobado(): Borrador {
		return new Borrador(
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
	}

	private function evaluador( ProveedorLenguajeFalso $proveedor ): EvaluadorCompuertas {
		return new EvaluadorCompuertas(
			new CompuertaCalidad( new VerificadorLegibilidad() ),
			new CompuertaRiesgo( $proveedor ),
			new CompuertaOriginalidad(),
			new GestorDegradacion()
		);
	}

	public function test_una_pieza_impecable_aprueba_las_tres_compuertas(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$resultado = $this->evaluador( new ProveedorLenguajeFalso( self::RIESGO_SIN_PROBLEMAS ) )->evaluar(
			$this->expediente(),
			$this->clasificacion(),
			$this->esqueletoCompleto(),
			$this->borradorAprobado(),
			self::TEXTO_LEGIBLE,
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertTrue( $resultado->aprobada );
		self::assertFalse( $resultado->retenida );
		self::assertSame( array(), $resultado->motivos );
		self::assertSame( ModoOperacion::Autonomo, $resultado->modoEfectivo );
	}

	public function test_riesgo_de_difamacion_retiene_la_pieza_con_motivo_explicito(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$proveedor = new ProveedorLenguajeFalso(
			'{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": true, "detalleDifamacion": "acusación sin doble fuente", "hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": false, "posturaSenaladoAusente": false}'
		);

		$resultado = $this->evaluador( $proveedor )->evaluar(
			$this->expediente(),
			$this->clasificacion(),
			$this->esqueletoCompleto(),
			$this->borradorAprobado(),
			self::TEXTO_LEGIBLE,
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertFalse( $resultado->aprobada );
		self::assertTrue( $resultado->retenida );
		self::assertCount( 1, $resultado->motivos );
		self::assertStringContainsString( 'difamación', $resultado->motivos[0] );
	}

	public function test_tragedia_degrada_el_modo_efectivo_aunque_la_pieza_apruebe(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$resultado = $this->evaluador( new ProveedorLenguajeFalso( self::RIESGO_SIN_PROBLEMAS ) )->evaluar(
			$this->expediente(),
			$this->clasificacion( TipoNoticia::Tragedia ),
			$this->esqueletoCompleto(),
			$this->borradorAprobado(),
			self::TEXTO_LEGIBLE,
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertTrue( $resultado->aprobada );
		self::assertSame( ModoOperacion::Copiloto, $resultado->modoEfectivo );
	}

	public function test_calidad_insuficiente_retiene_con_diagnostico(): void {
		Functions\when( 'get_option' )->justReturn( 70 );

		$borradorSinSustento = new Borrador(
			1,
			1,
			1,
			'contenido',
			array(
				new AnotacionCorrector( PuntoCorrector::Hechos, false, 'cifra no encontrada en el expediente' ),
				new AnotacionCorrector( PuntoCorrector::ProporcionInterpretativa, true, 'ok' ),
				new AnotacionCorrector( PuntoCorrector::Voz, true, 'ok' ),
			),
			false,
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);

		$resultado = $this->evaluador( new ProveedorLenguajeFalso( self::RIESGO_SIN_PROBLEMAS ) )->evaluar(
			$this->expediente(),
			$this->clasificacion(),
			$this->esqueletoCompleto(),
			$borradorSinSustento,
			self::TEXTO_LEGIBLE,
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertFalse( $resultado->aprobada );
		self::assertTrue( $resultado->retenida );
		self::assertStringContainsString( 'Calidad insuficiente', $resultado->motivos[0] );
	}

	/**
	 * Nivel Tres M.1: motivo de retención independiente, distinto del texto
	 * de difamación (que aquí es falso).
	 */
	public function test_postura_del_senalado_ausente_retiene_con_motivo_propio(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$proveedor = new ProveedorLenguajeFalso(
			'{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": false, "detalleDifamacion": "", "hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": true, "posturaSenaladoAusente": true}'
		);

		$resultado = $this->evaluador( $proveedor )->evaluar(
			$this->expediente(),
			$this->clasificacion(),
			$this->esqueletoCompleto(),
			$this->borradorAprobado(),
			self::TEXTO_LEGIBLE,
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertTrue( $resultado->retenida );
		self::assertCount( 1, $resultado->motivos );
		self::assertStringContainsString( 'réplica previa', $resultado->motivos[0] );
	}

	/**
	 * Nivel Tres N.1: en régimen penal, la retención aplica aunque la
	 * postura del señalado sí esté registrada — "nunca Autónomo" para esta
	 * categoría, motivo propio distinto del de M.1.
	 */
	public function test_regimen_penal_retiene_con_motivo_propio_aunque_postura_este_presente(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				if ( CompuertaRiesgo::OPCION_REGIMEN_RESPONSABILIDAD === $opcion ) {
					return 'penal';
				}

				return $defecto;
			}
		);

		$proveedor = new ProveedorLenguajeFalso(
			'{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": false, "detalleDifamacion": "", "hechosDisputadosSinSenalar": false, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": true, "posturaSenaladoAusente": false}'
		);

		$resultado = $this->evaluador( $proveedor )->evaluar(
			$this->expediente(),
			$this->clasificacion(),
			$this->esqueletoCompleto(),
			$this->borradorAprobado(),
			self::TEXTO_LEGIBLE,
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertTrue( $resultado->retenida );
		self::assertCount( 1, $resultado->motivos );
		self::assertStringContainsString( 'régimen de responsabilidad penal', $resultado->motivos[0] );
	}

	public function test_puede_haber_varios_motivos_de_retencion_a_la_vez(): void {
		Functions\when( 'get_option' )->justReturn( 70 );

		$proveedor = new ProveedorLenguajeFalso(
			'{"implicaMenores": false, "implicaSalud": false, "implicaViolencia": false, "riesgoDifamacion": true, "detalleDifamacion": "x", "hechosDisputadosSinSenalar": true, "temaRegulado": null, "afirmacionNegativaSobrePersonaIdentificable": false, "posturaSenaladoAusente": false}'
		);

		$resultado = $this->evaluador( $proveedor )->evaluar(
			$this->expediente(),
			$this->clasificacion(),
			$this->esqueletoCompleto(),
			$this->borradorAprobado(),
			self::TEXTO_LEGIBLE,
			true,
			array(),
			ModoOperacion::Autonomo
		);

		self::assertTrue( $resultado->retenida );
		self::assertCount( 2, $resultado->motivos );
	}
}
