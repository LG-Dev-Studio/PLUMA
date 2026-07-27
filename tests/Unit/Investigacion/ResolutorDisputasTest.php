<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Investigacion;

use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\InvestigacionException;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Investigacion\ResolutorDisputas;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * @covers \Pluma\Investigacion\ResolutorDisputas
 */
final class ResolutorDisputasTest extends CasoDePruebaUnitario {

	private function expediente( int $cantidadHechos ): Expediente {
		$hechos = array();

		for ( $i = 0; $i < $cantidadHechos; $i++ ) {
			$hechos[] = new HechoFuente( "hecho {$i}", "https://example.com/{$i}", new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido );
		}

		return new Expediente( 'una tendencia', $hechos );
	}

	public function test_expediente_con_menos_de_dos_hechos_no_llama_al_proveedor(): void {
		$proveedor = new ProveedorLenguajeFalso( 'no debería llamarse' );

		$resultado = ( new ResolutorDisputas( $proveedor ) )->resolver( $this->expediente( 1 ) );

		self::assertNull( $proveedor->ultimaPeticion );
		self::assertSame( 1, count( $resultado->hechos ) );
	}

	public function test_una_contradiccion_de_ocurrencia_marca_ambos_hechos_como_disputados(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": [{"indiceA": 0, "indiceB": 1, "tipo": "ocurrencia"}]}' );

		$resultado = ( new ResolutorDisputas( $proveedor ) )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Disputado, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Disputado, $resultado->hechos[1]->nivel );
	}

	public function test_una_contradiccion_de_cifra_no_muta_el_nivel_de_verificacion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": [{"indiceA": 0, "indiceB": 1, "tipo": "cifra"}]}' );

		$resultado = ( new ResolutorDisputas( $proveedor ) )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[1]->nivel );
	}

	public function test_una_contradiccion_de_atribucion_no_muta_el_nivel_de_verificacion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": [{"indiceA": 0, "indiceB": 1, "tipo": "atribucion"}]}' );

		$resultado = ( new ResolutorDisputas( $proveedor ) )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[1]->nivel );
	}

	public function test_sin_contradicciones_el_expediente_no_cambia(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": []}' );

		$resultado = ( new ResolutorDisputas( $proveedor ) )->resolver( $this->expediente( 2 ) );

		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[0]->nivel );
		self::assertSame( NivelVerificacion::Atribuido, $resultado->hechos[1]->nivel );
	}

	public function test_respuesta_truncada_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"contradicciones": []}', truncada: true );

		$this->expectException( InvestigacionException::class );

		( new ResolutorDisputas( $proveedor ) )->resolver( $this->expediente( 2 ) );
	}

	public function test_respuesta_sin_el_formato_esperado_lanza_excepcion(): void {
		$proveedor = new ProveedorLenguajeFalso( '{"otraCosa": true}' );

		$this->expectException( InvestigacionException::class );

		( new ResolutorDisputas( $proveedor ) )->resolver( $this->expediente( 2 ) );
	}
}
