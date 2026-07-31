<?php

declare(strict_types=1);

namespace Pluma\Tests\Invariantes;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Pluma\Datos\RepositorioBorradoresInterface;
use Pluma\Investigacion\Expediente;
use Pluma\Investigacion\HechoFuente;
use Pluma\Investigacion\NivelVerificacion;
use Pluma\Redaccion\AvisoTransparenciaIa;
use Pluma\Redaccion\CandidatoTesis;
use Pluma\Redaccion\ClasificacionNoticia;
use Pluma\Redaccion\CompiladorDirectrices;
use Pluma\Redaccion\ConductaVersion;
use Pluma\Redaccion\CorrectorInterno;
use Pluma\Redaccion\Diales;
use Pluma\Redaccion\EntradaMatrizTono;
use Pluma\Redaccion\EsqueletoPieza;
use Pluma\Redaccion\EstadoPeriodista;
use Pluma\Redaccion\FichaDecisionEditorial;
use Pluma\Redaccion\GeneradorBloqueEditor;
use Pluma\Redaccion\MatrizTonos;
use Pluma\Redaccion\NivelSatiraPermitida;
use Pluma\Redaccion\NovedadNoticia;
use Pluma\Redaccion\Periodista;
use Pluma\Redaccion\RedactorSintetico;
use Pluma\Redaccion\ReglasConducta;
use Pluma\Redaccion\RolPeriodista;
use Pluma\Redaccion\SegmentadorUnidadesFactuales;
use Pluma\Redaccion\TipoNoticia;
use Pluma\Redaccion\Tono;
use Pluma\Redaccion\TratamientoLector;
use Pluma\Redaccion\VerificadorNGramas;
use Pluma\Redaccion\VerificadorTrazabilidadDeterminista;
use Pluma\Redaccion\VerificadorVoz;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use Pluma\Tests\Unit\Dobles\EmbeddingsFalso;
use Pluma\Tests\Unit\Dobles\ProveedorLenguajeSecuencial;
use Pluma\Tests\Unit\Dobles\RelojFijo;

/**
 * GOVERNANCE §2.5 — "Extractos de fuentes: material interno, longitud
 * acotada, jamás reproducidos en la pieza publicada; toda fuente usada se
 * cita y enlaza."
 *
 * Si este test se pone en rojo, una pieza publicada podría citar una fuente
 * sin enlazarla (rompiendo la higiene de fuentes del Libro Cap. 4.3), o
 * reproducir un extracto textualmente en vez de reescribirlo.
 */
final class CitarYEnlazarFuentesInvarianteTest extends CasoDePruebaUnitario {

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
	 * El modelo puede olvidar enlazar sus fuentes en la prosa (aquí, a
	 * propósito, el cuerpo generado no menciona ninguna URL). La pieza final
	 * debe llevar el enlace de todas formas — mecánico, no delegado al modelo.
	 */
	public function test_la_pieza_final_enlaza_toda_fuente_del_expediente_aunque_la_prosa_no_la_mencione(): void {
		Functions\when( 'esc_html' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->alias( static fn ( string $s ): string => htmlspecialchars( $s, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn ( string $s ): string => $s );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( '__' )->alias( static fn ( string $s ): string => $s );

		$expediente = new Expediente(
			'una tendencia',
			array(
				new HechoFuente( 'primer hecho verificado', 'https://fuente-uno.example/articulo', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ),
				new HechoFuente( 'segundo hecho atribuido', 'https://fuente-dos.example/nota', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Atribuido ),
			)
		);

		$proveedor = new ProveedorLenguajeSecuencial(
			array(
				// La prosa del modelo NO contiene ninguna URL ni nombre de fuente.
				'{"titulo": "Un titular cualquiera", "cuerpo": "Un párrafo que no menciona ninguna fuente por su nombre."}',
				'{"hechos": {"aprobado": true, "detalle": "ok"}, "proporcion_interpretativa": {"aprobado": true, "detalle": "ok"}, "titular_honesto": {"aprobado": true, "detalle": "ok"}, "matriz_y_lineas_rojas": {"aprobado": true, "detalle": "ok"}}',
				'{"comentario": "comentario", "pregunta": "¿pregunta?"}',
			)
		);

		$redactor = new RedactorSintetico(
			$proveedor,
			new CompiladorDirectrices(),
			new CorrectorInterno( $proveedor, new VerificadorVoz(), new VerificadorNGramas(), new VerificadorTrazabilidadDeterminista( new EmbeddingsFalso(), new SegmentadorUnidadesFactuales() ) ),
			new GeneradorBloqueEditor( $proveedor ),
			new AvisoTransparenciaIa(),
			$this->createMock( RepositorioBorradoresInterface::class ),
			new RelojFijo()
		);

		$resultado = $redactor->redactar( 1, $this->periodista(), $expediente, $this->ficha() );

		self::assertStringContainsString( 'https://fuente-uno.example/articulo', $resultado->cuerpoHtml );
		self::assertStringContainsString( 'https://fuente-dos.example/nota', $resultado->cuerpoHtml );
		self::assertStringContainsString( '<a href=', $resultado->cuerpoHtml );
	}

	/**
	 * "Jamás reproducidos en la pieza publicada": un cuerpo que copia
	 * textualmente 8+ palabras seguidas de un extracto de fuente reprueba el
	 * Corrector Interno — es una barrera, no una sugerencia.
	 */
	public function test_un_extracto_copiado_textualmente_nunca_pasa_el_corrector(): void {
		$extracto   = 'El banco central subió la tasa de interés al nueve por ciento este martes por la mañana';
		$expediente = new Expediente( 'x', array( new HechoFuente( $extracto, 'https://example.com', new DateTimeImmutable( '2026-07-22T12:00:00+00:00' ), NivelVerificacion::Verificado ) ) );

		$anotacion = ( new VerificadorNGramas() )->verificar( $expediente, 'Fuentes confirman que ' . $extracto . ', algo sin precedentes.' );

		self::assertFalse( $anotacion->aprobado, 'Copiar 8+ palabras seguidas de una fuente debe reprobar el Corrector Interno.' );
	}
}
