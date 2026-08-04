<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Kernel;

use DateTimeImmutable;
use Pluma\Kernel\HechosEntorno;
use Pluma\Kernel\ResolutorPerfilEntorno;
use Pluma\Kernel\TransportePlano1;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Desde `ADR 0024` ya no existe T3 (cerebro remoto) — NLI y RRK son
 * pure-PHP, siempre disponibles. Este resolutor sigue midiendo T1/T2 como
 * medición prospectiva para un futuro rol que necesite ONNX embebido.
 *
 * @covers \Pluma\Kernel\ResolutorPerfilEntorno
 */
final class ResolutorPerfilEntornoTest extends CasoDePruebaUnitario {

	private function hechos(
		bool $ffi = false,
		bool $procesoHijo = false,
		bool $apiPago = false
	): HechosEntorno {
		return new HechosEntorno( $ffi, 128, 90, $procesoHijo, $apiPago );
	}

	private function ahora(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-07-31T12:00:00+00:00' );
	}

	public function test_hosting_compartido_sin_ninguna_capacidad_no_tiene_transporte(): void {
		$perfil = ( new ResolutorPerfilEntorno() )->resolver( $this->hechos(), $this->ahora() );

		self::assertSame( TransportePlano1::Ninguno, $perfil->transportePrioritario );
	}

	public function test_vps_sin_ffi_pero_con_proceso_hijo_elige_t2_sidecar_local(): void {
		$perfil = ( new ResolutorPerfilEntorno() )->resolver(
			$this->hechos( ffi: false, procesoHijo: true ),
			$this->ahora()
		);

		self::assertSame( TransportePlano1::T2SidecarLocal, $perfil->transportePrioritario );
	}

	public function test_vps_con_ffi_elige_t1_en_proceso_aunque_lo_demas_tambien_este_disponible(): void {
		$perfil = ( new ResolutorPerfilEntorno() )->resolver(
			$this->hechos( ffi: true, procesoHijo: true ),
			$this->ahora()
		);

		self::assertSame( TransportePlano1::T1EnProceso, $perfil->transportePrioritario );
	}

	public function test_api_de_pago_nunca_afecta_el_transporte_del_plano_1(): void {
		$perfilSinApi = ( new ResolutorPerfilEntorno() )->resolver( $this->hechos( apiPago: false ), $this->ahora() );
		$perfilConApi = ( new ResolutorPerfilEntorno() )->resolver( $this->hechos( apiPago: true ), $this->ahora() );

		self::assertSame( $perfilSinApi->transportePrioritario, $perfilConApi->transportePrioritario );
	}

	public function test_conserva_los_hechos_y_el_instante_de_medicion_recibidos(): void {
		$hechos = $this->hechos( ffi: true );
		$ahora  = $this->ahora();

		$perfil = ( new ResolutorPerfilEntorno() )->resolver( $hechos, $ahora );

		self::assertSame( $hechos, $perfil->hechos );
		self::assertSame( $ahora, $perfil->medidoEn );
	}
}
