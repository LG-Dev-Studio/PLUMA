<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\DetectorContradiccionesNli;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\InvestigacionException;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\ResolutorDisputas;
use Pluma\Proveedores\EtiquetaNli;
use Pluma\Proveedores\ResultadoNli;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\NliFalso;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * @covers \Pluma\Investigacion\ResolutorDisputas
 */
final class ResolutorDisputasTest extends CasoDePruebaUnitario {

	/**
	 * Por defecto el doble de NLI no marca ningún par como contradictorio.
	 */
	private function resolutor(
		ProveedorLenguajeFalso $proveedor,
		string $etiquetaNliPrincipal = 'neutral'
	): ResolutorDisputas {
		Functions\when( 'get_option' )->justReturn( false );

		$resultados = array( new ResultadoNli( EtiquetaNli::from( $etiquetaNliPrincipal ), 0.9 ) );
		$nli        = new NliFalso( static fn (): array => $resultados );

		return new ResolutorDisputas( $proveedor, new DetectorContradiccionesNli( $nli ) );
	}

	private function expediente( int $cantidadHechos ): Expediente {
		$hechos = array();

		for ( $i = 0; $i < $cantidadHechos; $i++ ) {
			$hechos[] = new HechoFuente( "hecho {$i}", "https://example.com/{$i}", new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		}

		return new Expediente( 'una tendencia', $hechos );
	}

	public function test_expediente_con_menos_de_dos_hechos_no_llama_al_proveedor(): void {
		$proveedor = new ProveedorLenguajeFalso( 'no debería llamarse' );

		$resultado = $this->resolutor( $proveedor )->resolver( $this->expediente( 1 ) );

		self::assertNull( $proveedor->ultimaPeticion );
		self::assertSame( 1, count( $resultado->hechos ) );
	}

	public function test_una_contradiccion_de_ocurrencia_marca_ambos_hechos_como_disputados(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": [{"indiceA": 0, "indiceB": 1, "tipo": "ocurrencia"}]}' );

		$resultado = $this->resolutor( $proveedor )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Disputado, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Disputado, $resultado->hechos[1]->nivel );
	}

	public function test_una_contradiccion_de_cifra_no_muta_el_nivel_de_verificacion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": [{"indiceA": 0, "indiceB": 1, "tipo": "cifra"}]}' );

		$resultado = $this->resolutor( $proveedor )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[1]->nivel );
	}

	public function test_una_contradiccion_de_atribucion_no_muta_el_nivel_de_verificacion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": [{"indiceA": 0, "indiceB": 1, "tipo": "atribucion"}]}' );

		$resultado = $this->resolutor( $proveedor )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[1]->nivel );
	}

	public function test_sin_contradicciones_el_expediente_no_cambia(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": []}' );

		$resultado = $this->resolutor( $proveedor )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[1]->nivel );
	}

	public function test_respuesta_truncada_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": []}', truncada: true );

		$this->expectException( InvestigacionException::class );

		$this->resolutor( $proveedor )->resolver( $this->expediente( 2 ) );
	}

	public function test_respuesta_sin_el_formato_esperado_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"otraCosa": true}' );

		$this->expectException( InvestigacionException::class );

		$this->resolutor( $proveedor )->resolver( $this->expediente( 2 ) );
	}

	/**
	 * NCP-3 Porción 2 (`ADR 0022`): un par de hechos que el detector NLI
	 * marca como contradictorio se señala en la petición al proveedor de
	 * lenguaje.
	 */
	public function test_un_par_contradictorio_se_señala_en_la_peticion_al_proveedor(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": []}' );

		$this->resolutor( $proveedor, 'contradiction' )
			->resolver( $this->expediente( 2 ) );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringContainsString( 'ALERTA DE CONTRADICCIÓN DETERMINISTA (NLI)', $proveedor->ultimaPeticion->directrices );
		self::assertStringContainsString( '(0,1)', $proveedor->ultimaPeticion->directrices );
	}

	/**
	 * Contraprueba: sin ningún par contradictorio real, la alerta NLI no se
	 * dispara.
	 */
	public function test_sin_pares_contradictorios_no_dispara_la_alerta_nli(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": []}' );

		$this->resolutor( $proveedor )->resolver( $this->expediente( 2 ) );

		self::assertNotNull( $proveedor->ultimaPeticion );
		self::assertStringNotContainsString( 'ALERTA DE CONTRADICCIÓN DETERMINISTA', $proveedor->ultimaPeticion->directrices );
	}
}
