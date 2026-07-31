<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

use Pluma\Datos\RepositorioMemoriaEditorialInterface;
use Pluma\Datos\RepositorioPeriodistasInterface;
use Pluma\Kernel\RelojInterface;

/**
 * Importación del banco de periodistas exportado por
 * {@see ExportadorBancoPeriodistas} (pl-periodistas §8). Crea periodistas
 * NUEVOS (ids propios del sitio destino) reconstruyendo el historial
 * completo de versiones de Conducta en orden, y su memoria editorial.
 *
 * El `piezaId` de cada entrada de memoria se descarta al importar: una
 * Pieza de otro sitio no existe aquí — conservar el id sería una referencia
 * rota, no trazabilidad.
 */
final class ImportadorBancoPeriodistas {

	public function __construct(
		private readonly RepositorioPeriodistasInterface $repoPeriodistas,
		private readonly RepositorioMemoriaEditorialInterface $repoMemoria,
		private readonly RelojInterface $reloj,
	) {
	}

	/**
	 * @param array<string, mixed> $datos
	 *
	 * @throws ImportacionBancoException si el formato no es compatible.
	 */
	public function importar( array $datos ): int {
		if ( ! isset( $datos['version'], $datos['periodistas'] ) || ! is_string( $datos['version'] ) || ! is_array( $datos['periodistas'] ) ) {
			throw new ImportacionBancoException( 'El archivo no tiene la forma esperada de una exportación del banco de periodistas.' );
		}

		if ( ExportadorBancoPeriodistas::VERSION_FORMATO !== $datos['version'] ) {
			$mensaje = "Versión de exportación '{$datos['version']}' no compatible con este PLUMA (esperada " . ExportadorBancoPeriodistas::VERSION_FORMATO . ').';

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- mensaje de excepción interno, nunca se imprime como HTML.
			throw new ImportacionBancoException( $mensaje );
		}

		$importados = 0;

		foreach ( $datos['periodistas'] as $periodistaCrudo ) {
			$this->importarUno( $periodistaCrudo );
			++$importados;
		}

		return $importados;
	}

	private function importarUno( mixed $periodistaCrudo ): void {
		if (
			! is_array( $periodistaCrudo )
			|| ! isset( $periodistaCrudo['nombre'], $periodistaCrudo['biografia'], $periodistaCrudo['rol'], $periodistaCrudo['especialidades'], $periodistaCrudo['estado'], $periodistaCrudo['versionesConducta'], $periodistaCrudo['memoria'] )
			|| ! is_string( $periodistaCrudo['nombre'] )
			|| ! is_string( $periodistaCrudo['biografia'] )
			|| ! is_string( $periodistaCrudo['rol'] )
			|| ! is_array( $periodistaCrudo['especialidades'] )
			|| ! is_string( $periodistaCrudo['estado'] )
			|| ! is_array( $periodistaCrudo['versionesConducta'] )
			|| array() === $periodistaCrudo['versionesConducta']
			|| ! is_array( $periodistaCrudo['memoria'] )
		) {
			throw new ImportacionBancoException( 'Un periodista del archivo de importación no tiene el formato esperado.' );
		}

		$rol    = RolPeriodista::tryFrom( $periodistaCrudo['rol'] );
		$estado = EstadoPeriodista::tryFrom( $periodistaCrudo['estado'] );

		if ( null === $rol || null === $estado ) {
			throw new ImportacionBancoException( 'Un periodista del archivo de importación usa un rol o estado desconocido.' );
		}

		/** @var list<array{vertical: string, nivelDominio: int}> $especialidadesCrudas */
		$especialidadesCrudas = $periodistaCrudo['especialidades'];
		$especialidades       = array_map(
			static fn ( array $e ): Especialidad => Especialidad::desdeArray( $e ),
			$especialidadesCrudas
		);

		$versiones = array_map(
			fn ( array $v ): array => $this->versionDesdeArray( $v ),
			array_values( $periodistaCrudo['versionesConducta'] )
		);

		$ahora     = $this->reloj->ahora();
		$primera   = $versiones[0];
		$avatarUrl = isset( $periodistaCrudo['avatarUrl'] ) && is_string( $periodistaCrudo['avatarUrl'] ) ? $periodistaCrudo['avatarUrl'] : null;
		// Ausente en exportaciones previas a la Etapa 8 Porción 10 (mismo
		// VERSION_FORMATO '1.0', campo añadido de forma retrocompatible):
		// por defecto 'es-ES', igual que un periodista nuevo.
		$localeEditorial = isset( $periodistaCrudo['localeEditorial'] ) && is_string( $periodistaCrudo['localeEditorial'] ) ? $periodistaCrudo['localeEditorial'] : 'es-ES';
		$periodistaId    = $this->repoPeriodistas->crear(
			$periodistaCrudo['nombre'],
			$avatarUrl,
			$periodistaCrudo['biografia'],
			$rol,
			$especialidades,
			$estado,
			$primera['diales'],
			$primera['reglasConducta'],
			$primera['matrizTonos'],
			$ahora,
			$localeEditorial
		);

		$totalVersiones = count( $versiones );

		for ( $i = 1; $i < $totalVersiones; $i++ ) {
			$this->repoPeriodistas->nuevaVersionConducta(
				$periodistaId,
				$versiones[ $i ]['diales'],
				$versiones[ $i ]['reglasConducta'],
				$versiones[ $i ]['matrizTonos'],
				$ahora
			);
		}

		foreach ( $periodistaCrudo['memoria'] as $memoriaCruda ) {
			$this->importarMemoria( $periodistaId, $memoriaCruda, $ahora );
		}
	}

	/**
	 * @param array<string, mixed> $version
	 * @return array{diales: Diales, reglasConducta: ReglasConducta, matrizTonos: MatrizTonos}
	 */
	private function versionDesdeArray( array $version ): array {
		if ( ! isset( $version['diales'], $version['reglasConducta'], $version['matrizTonos'] ) || ! is_array( $version['diales'] ) || ! is_array( $version['reglasConducta'] ) || ! is_array( $version['matrizTonos'] ) ) {
			throw new ImportacionBancoException( 'Una versión de Conducta del archivo de importación no tiene el formato esperado.' );
		}

		/** @var array{agudezaCritica: int, humor: int, satira: int, formalidad: int, vehemencia: int, empatia: int, densidadDatos: int, longitudPreferida: int} $dialesCrudos */
		$dialesCrudos = $version['diales'];

		/** @var array{lineaEditorial: string, lineasRojas: list<string>, muletillas: list<string>, vocabularioProhibido: list<string>, tratamientoLector: string, estiloPreguntaFinal: string} $reglasCrudas */
		$reglasCrudas = $version['reglasConducta'];

		/** @var array<string, array{tipoNoticia: string, tonoDominante: string, tonoApoyo: string, nivelSatira: string}> $matrizCruda */
		$matrizCruda = $version['matrizTonos'];

		return array(
			'diales'         => Diales::desdeArray( $dialesCrudos ),
			'reglasConducta' => ReglasConducta::desdeArray( $reglasCrudas ),
			'matrizTonos'    => MatrizTonos::desdeArray( $matrizCruda ),
		);
	}

	private function importarMemoria( int $periodistaId, mixed $memoriaCruda, \DateTimeImmutable $ahora ): void {
		if (
			! is_array( $memoriaCruda )
			|| ! isset( $memoriaCruda['tipo'], $memoriaCruda['tema'], $memoriaCruda['contenido'] )
			|| ! is_string( $memoriaCruda['tipo'] )
			|| ! is_string( $memoriaCruda['tema'] )
			|| ! is_array( $memoriaCruda['contenido'] )
		) {
			throw new ImportacionBancoException( 'Una entrada de memoria del archivo de importación no tiene el formato esperado.' );
		}

		$tipo = TipoMemoria::tryFrom( $memoriaCruda['tipo'] );

		if ( null === $tipo ) {
			throw new ImportacionBancoException( 'Una entrada de memoria del archivo de importación usa un tipo desconocido.' );
		}

		$this->repoMemoria->registrar( $periodistaId, $tipo, $memoriaCruda['tema'], $memoriaCruda['contenido'], null, $ahora );
	}
}
