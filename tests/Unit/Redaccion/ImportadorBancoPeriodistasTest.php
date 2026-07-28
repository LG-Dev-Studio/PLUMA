<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Redaccion;

use DateTimeImmutable;
use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\ImportacionBancoException;
use Pluma\Redaccion\ImportadorBancoPeriodistas;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * pl-periodistas §8.
 *
 * @covers \Pluma\Redaccion\ImportadorBancoPeriodistas
 */
final class ImportadorBancoPeriodistasTest extends CasoDePruebaUnitario {

	/**
	 * @return array<string, mixed>
	 */
	private function versionValida( int $agudezaCritica = 80 ): array {
		$diales = new Diales( $agudezaCritica, 55, 40, 55, 75, 60, 60, 65 );
		$reglas = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);

		return array(
			'diales'         => $diales->aArray(),
			'reglasConducta' => $reglas->aArray(),
			'matrizTonos'    => $matriz->aArray(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function periodistaValido(): array {
		return array(
			'nombre'            => 'Valentina Ruiz',
			'avatarUrl'         => null,
			'biografia'         => 'Bio.',
			'rol'               => 'columnista',
			'especialidades'    => array(
				array(
					'vertical'     => 'economia',
					'nivelDominio' => 5,
				),
			),
			'estado'            => 'activo',
			'versionesConducta' => array( $this->versionValida( 80 ), $this->versionValida( 90 ) ),
			'memoria'           => array(
				array(
					'tipo'      => 'postura',
					'tema'      => 'economia',
					'contenido' => array( 'postura' => 'x' ),
				),
			),
		);
	}

	public function test_importa_un_periodista_reconstruyendo_el_historial_y_la_memoria(): void {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->expects( self::once() )->method( 'crear' )
			->with(
				'Valentina Ruiz',
				null,
				'Bio.',
				\Pluma\Redaccion\RolPeriodista::Columnista,
				self::isType( 'array' ),
				\Pluma\Redaccion\EstadoPeriodista::Activo,
				self::callback( static fn ( Diales $d ): bool => 80 === $d->agudezaCritica ),
				self::isInstanceOf( ReglasConducta::class ),
				self::isInstanceOf( MatrizTonos::class ),
				self::isInstanceOf( DateTimeImmutable::class ),
				'es-ES'
			)
			->willReturn( 55 );
		$repoPeriodistas->expects( self::once() )->method( 'nuevaVersionConducta' )
			->with( 55, self::callback( static fn ( Diales $d ): bool => 90 === $d->agudezaCritica ), self::anything(), self::anything(), self::anything() );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->expects( self::once() )->method( 'registrar' )
			->with( 55, \Pluma\Redaccion\TipoMemoria::Postura, 'economia', array( 'postura' => 'x' ), null, self::isInstanceOf( DateTimeImmutable::class ) );

		$importados = ( new ImportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->importar(
			array(
				'version'     => '1.0',
				'periodistas' => array( $this->periodistaValido() ),
			)
		);

		self::assertSame( 1, $importados );
	}

	/**
	 * Nivel Tres Q.1: `localeEditorial` es un campo opcional añadido de
	 * forma retrocompatible (mismo `VERSION_FORMATO` '1.0') — una
	 * exportación que sí lo trae debe respetarlo, no defaultear a 'es-ES'.
	 */
	public function test_importa_el_locale_editorial_cuando_la_exportacion_lo_trae(): void {
		$periodista                    = $this->periodistaValido();
		$periodista['localeEditorial'] = 'fr-FR';

		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->expects( self::once() )->method( 'crear' )
			->with(
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				self::anything(),
				'fr-FR'
			)
			->willReturn( 55 );

		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );
		$repoMemoria->method( 'registrar' );

		( new ImportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->importar(
			array(
				'version'     => '1.0',
				'periodistas' => array( $periodista ),
			)
		);
	}

	public function test_lanza_excepcion_si_la_version_de_formato_no_es_compatible(): void {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoMemoria     = $this->createMock( RepositorioMemoriaEditorialInterface::class );

		$this->expectException( ImportacionBancoException::class );

		( new ImportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->importar(
			array(
				'version'     => '99.0',
				'periodistas' => array(),
			)
		);
	}

	public function test_lanza_excepcion_si_falta_la_forma_general(): void {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoMemoria     = $this->createMock( RepositorioMemoriaEditorialInterface::class );

		$this->expectException( ImportacionBancoException::class );

		( new ImportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->importar( array( 'algo' => 'x' ) );
	}

	public function test_lanza_excepcion_si_un_periodista_tiene_un_rol_desconocido(): void {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoMemoria     = $this->createMock( RepositorioMemoriaEditorialInterface::class );

		$periodista        = $this->periodistaValido();
		$periodista['rol'] = 'inventor_de_noticias';

		$this->expectException( ImportacionBancoException::class );

		( new ImportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->importar(
			array(
				'version'     => '1.0',
				'periodistas' => array( $periodista ),
			)
		);
	}

	public function test_lanza_excepcion_si_una_entrada_de_memoria_tiene_un_tipo_desconocido(): void {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoPeriodistas->method( 'crear' )->willReturn( 1 );
		$repoMemoria = $this->createMock( RepositorioMemoriaEditorialInterface::class );

		$periodista                       = $this->periodistaValido();
		$periodista['memoria'][0]['tipo'] = 'tipo_inexistente';

		$this->expectException( ImportacionBancoException::class );

		( new ImportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->importar(
			array(
				'version'     => '1.0',
				'periodistas' => array( $periodista ),
			)
		);
	}

	public function test_lanza_excepcion_si_no_hay_ninguna_version_de_conducta(): void {
		$repoPeriodistas = $this->createMock( RepositorioPeriodistasInterface::class );
		$repoMemoria     = $this->createMock( RepositorioMemoriaEditorialInterface::class );

		$periodista                      = $this->periodistaValido();
		$periodista['versionesConducta'] = array();

		$this->expectException( ImportacionBancoException::class );

		( new ImportadorBancoPeriodistas( $repoPeriodistas, $repoMemoria, new RelojFijo() ) )->importar(
			array(
				'version'     => '1.0',
				'periodistas' => array( $periodista ),
			)
		);
	}
}
