<?php

declare(strict_types=1);

namespace Pluma\Tests\Invariantes;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Kernel\Cifrado;
use Pluma\Proveedores\ProveedorCerebroRemoto;
use Pluma\Proveedores\ProveedorNliCerebroRemoto;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\CorrectorInterno;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VerificadorContradiccionNli;
use Pluma\Redaccion\VerificadorNGramas;
use Pluma\Redaccion\VerificadorTrazabilidadDeterminista;
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeFalso;

/**
 * GOVERNANCE §2.4 — "El redactor solo conoce el expediente: toda afirmación
 * del borrador debe ser trazable a un hecho del expediente (anti-alucinación).
 * El Corrector Interno lo verifica; el test verifica al Corrector."
 *
 * Si este test se pone en rojo, el producto ya no cumple la regla de oro
 * contra la alucinación (Libro Cap. 5.6): un borrador con una afirmación sin
 * respaldo en el expediente podría llegar a publicarse.
 */
final class AntiAlucinacionInvarianteTest extends CasoDePruebaUnitario {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'AUTH_KEY' ) ) {
			define( 'AUTH_KEY', 'clave-app-de-prueba' );
			define( 'SECURE_AUTH_KEY', 'clave-secure-de-prueba' );
		}
	}

	private function mockearCerebroRemoto(): void {
		Functions\when( 'get_option' )->alias(
			static function ( string $opcion, $defecto = false ) {
				return match ( $opcion ) {
					ProveedorCerebroRemoto::OPCION_URL => 'https://cerebro.example',
					ProveedorCerebroRemoto::OPCION_TOKEN_CIFRADO => Cifrado::cifrar( 'token' ),
					default => $defecto,
				};
			}
		);
		Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '[{"score":0.9,"label":"neutral"},{"score":0.08,"label":"entailment"},{"score":0.02,"label":"contradiction"}]' );
	}

	private function verificadorContradiccion(): VerificadorContradiccionNli {
		return new VerificadorContradiccionNli( new ProveedorNliCerebroRemoto( new ProveedorCerebroRemoto() ), new SegmentadorUnidadesFactuales() );
	}

	private function periodista(): Periodista {
		$diales   = new Diales( 80, 55, 40, 55, 75, 60, 60, 65 );
		$reglas   = new ReglasConducta( 'linea', array(), array(), array(), TratamientoLector::Tu, '¿Y tú?' );
		$matriz   = MatrizTonos::desdeFilas(
			array( new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ) )
		);
		$conducta = new ConductaVersion( 1, 1, $diales, $reglas, $matriz, new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ) );

		return new Periodista(
			1,
			'Periodista',
			null,
			'Bio.',
			RolPeriodista::Columnista,
			array(),
			EstadoPeriodista::Activo,
			$conducta,
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-01-01T00:00:00+00:00' )
		);
	}

	private function expediente(): Expediente {
		return new Expediente(
			'una tendencia',
			array( new HechoFuente( 'la inflación cerró en 4.2% en junio', 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) )
		);
	}

	private function ficha(): FichaDecisionEditorial {
		return new FichaDecisionEditorial(
			1,
			1,
			new ClasificacionNoticia( 'economia', 30, 'x', NovedadNoticia::Primicia, 50, TipoNoticia::DatoEconomico ),
			array( new CandidatoTesis( 'tesis', 80.0, 80.0, 80.0, 80.0 ) ),
			0,
			Tono::Analitico,
			Tono::Persuasivo,
			new EsqueletoPieza( 'g', 'h', array( 'm1', 'm2' ), 'c', 'r' ),
			new DateTimeImmutable( '2026-07-22T12:00:00+00:00' )
		);
	}

	/**
	 * Un borrador que inventa una cifra ausente del expediente (8.7% de
	 * desempleo, un dato que NUNCA se investigó) debe reprobar el punto
	 * "hechos" del Corrector Interno — y ese solo punto basta para que
	 * `aprobado()` sea `false`, sin importar que los otros cinco aprueben.
	 */
	public function test_una_afirmacion_sin_respaldo_en_el_expediente_reprueba_el_corrector(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": false, "detalle": "El borrador afirma un desempleo del 8.7% que no existe en ningún hecho del expediente."}, '
				. '"proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, '
				. '"matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$this->mockearCerebroRemoto();

		$corrector = new CorrectorInterno(
			$proveedor,
			new VerificadorVoz(),
			new VerificadorNGramas(),
			new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ),
			$this->verificadorContradiccion()
		);

		$anotaciones = $corrector->revisar(
			$this->periodista(),
			$this->expediente(),
			$this->ficha(),
			'Titular',
			'El desempleo alcanzó un histórico 8.7%, según cifras que el gobierno prefiere ocultar.'
		);

		self::assertFalse(
			$corrector->aprobado( $anotaciones ),
			'Una sola afirmación sin respaldo en el expediente debe bastar para reprobar el borrador completo.'
		);
	}

	/**
	 * Contraprueba: un borrador cuyas afirmaciones SÍ están en el expediente
	 * (y pasa los demás puntos) debe poder aprobar — el gate no es punitivo
	 * per se, exige trazabilidad real.
	 */
	public function test_un_borrador_trazable_al_expediente_puede_aprobar(): void {
		$proveedor = new ProveedorLenguajeFalso(
			'{"hechos": {"aprobado": true, "detalle": "toda cifra citada existe en el expediente"}, '
				. '"proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, '
				. '"titular_honesto": {"aprobado": true, "detalle": "ok"}, '
				. '"matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}'
		);

		$this->mockearCerebroRemoto();

		$corrector = new CorrectorInterno(
			$proveedor,
			new VerificadorVoz(),
			new VerificadorNGramas(),
			new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ),
			$this->verificadorContradiccion()
		);

		$anotaciones = $corrector->revisar(
			$this->periodista(),
			$this->expediente(),
			$this->ficha(),
			'Titular',
			'La inflación cerró en 4.2 por ciento durante el mes pasado, un dato que reordena el debate económico.'
		);

		self::assertTrue( $corrector->aprobado( $anotaciones ) );
	}
}
