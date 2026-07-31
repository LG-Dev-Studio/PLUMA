<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EntradaMemoria;
use Pluma\Redaccion\Especialidad;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\ExportadorBancoPeriodistas;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\TipoMemoria;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * pl-periodistas §8: "export/import del banco... es API pública del producto".
 *
 * @covers \Pluma\Redaccion\ExportadorBancoPeriodistas
 */
final class ExportadorBancoPeriodistasTest extends CasoDePruebaUnitario {

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea', array(), array( 'muletilla' ), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 9, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			9,
			'Valentina Ruiz',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array( new Especialidad( 'economia', 5 ) ),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	public function test_exporta_la_version_de_formato_y_los_periodistas_con_su_historial_y_memoria(): void {
		$periodista = $this->periodista();

		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'obtenerTodos' )->willReturn( array( $periodista ) );
		$repoPeriodistas->method( 'obtenerHistorialVersiones' )->with( 9 )->willReturn( array( $periodista->conductaActual ) );

		$entradaMemoria = new EntradaMemoria( 1, 9, TipoMemoria::Postura, 'economia', array( 'postura' => 'x' ), 42, new DateTimeImmutable( '2026-02-01T00:00:00+00:00' ) );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'obtenerTodoPorPeriodista' )->with( 9 )->willReturn( array( $entradaMemoria ) );

		$exportacion = ( new ExportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->exportar();

		self::assertSame( ExportadorBancoPeriodistas::VERSION_FORMATO, $exportacion['version'] );
		self::assertCount( 1, $exportacion['periodistas'] );

		$exportado = $exportacion['periodistas'][0];
		self::assertSame( 'Valentina Ruiz', $exportado['nombre'] );
		self::assertSame( 'es-ES', $exportado['localeEditorial'] );
		self::assertSame( 'economia', $exportado['especialidades'][0]['vertical'] );
		self::assertCount( 1, $exportado['versionesConducta'] );
		self::assertSame( 80, $exportado['versionesConducta'][0]['diales']['agudezaCritica'] );
		self::assertCount( 1, $exportado['memoria'] );
		self::assertSame( 'postura', $exportado['memoria'][0]['tipo'] );

		// piezaId nunca viaja en la exportación: no es portable entre sitios.
		self::assertArrayNotHasKey( 'piezaId', $exportado['memoria'][0] );
	}
}
