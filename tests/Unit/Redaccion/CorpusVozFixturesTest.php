<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\PlantillaPeriodista;
use Pluma\Redaccion\PlantillasSiembra;
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Tests\Unit\CasoDePruebaUnitario;

/**
 * Nivel Dos A.5, verificación 1 de 3 (presencia estructural): el corpus de
 * regresión de voz (`tests/Fixtures/corpus-voz.php`) es copy editorial
 * congelado, no piezas generadas por el pipeline — este test confirma que
 * sigue siendo un corpus honesto: cubre a los 3 periodistas de
 * `PlantillasSiembra` con al menos 2 piezas cada uno, y ninguna contiene
 * vocabulario prohibido (global o propio) de ese periodista.
 *
 * Nivel Tres P.3 (Etapa 8, Porción 10): además de correr en cada
 * `composer test:unit`/CI, este test es invocable de forma aislada vía
 * `composer test:voz` — cadencia mensual independiente del ciclo de
 * releases (`docs/protocolo-corpus-voz.md`).
 *
 * @covers \Pluma\Redaccion\VerificadorVoz
 * @group voz
 */
final class CorpusVozFixturesTest extends CasoDePruebaUnitario {

	/**
	 * @return array<string, list<string>>
	 */
	private function corpus(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- lectura de un fixture de archivo local del repo, no de red.
		return require __DIR__ . '/../../Fixtures/corpus-voz.php';
	}

	private function periodista( PlantillaPeriodista $plantilla ): Periodista {
		$conducta = new ConductaVersion( 1, 1, $plantilla->diales, $plantilla->reglas, $plantilla->matrizTonos, false, new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ) );

		return new Periodista(
			1,
			$plantilla->nombre,
			$plantilla->avatarUrl,
			$plantilla->biografia,
			$plantilla->rol,
			$plantilla->especialidades,
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	/**
	 * @return array<string, PlantillaPeriodista>
	 */
	private function plantillasPorNombre(): array {
		$plantillas = array();

		foreach ( PlantillasSiembra::todas() as $plantilla ) {
			$plantillas[ $plantilla->nombre ] = $plantilla;
		}

		return $plantillas;
	}

	public function test_el_corpus_cubre_a_los_tres_periodistas_sembrados_con_al_menos_dos_piezas(): void {
		$corpus     = $this->corpus();
		$plantillas = $this->plantillasPorNombre();

		foreach ( array_keys( $plantillas ) as $nombre ) {
			self::assertArrayHasKey( $nombre, $corpus, "Falta corpus de regresión de voz para «{$nombre}»." );
			self::assertGreaterThanOrEqual( 2, count( $corpus[ $nombre ] ), "El corpus de «{$nombre}» debe tener al menos 2 piezas de referencia." );
		}
	}

	public function test_ninguna_pieza_del_corpus_contiene_vocabulario_prohibido(): void {
		$corpus         = $this->corpus();
		$plantillas     = $this->plantillasPorNombre();
		$verificadorVoz = new VerificadorVoz();

		foreach ( $corpus as $nombre => $piezas ) {
			self::assertArrayHasKey( $nombre, $plantillas, "«{$nombre}» en el corpus no corresponde a ningún periodista de PlantillasSiembra." );

			$periodista = $this->periodista( $plantillas[ $nombre ] );

			foreach ( $piezas as $indice => $pieza ) {
				$anotacion = $verificadorVoz->verificar( $periodista, $pieza );

				self::assertStringNotContainsString(
					'Vocabulario prohibido detectado',
					$anotacion->detalle,
					"La pieza #{$indice} del corpus de «{$nombre}» contiene vocabulario prohibido: {$anotacion->detalle}"
				);
			}
		}
	}
}
