<?php

declare(strict_types=1);

namespace Pluma\Tests\Invariantes;

use Pluma\Pipeline\EstadoPieza;
use Pluma\Pipeline\Transicionador;
use Pluma\Tests\Unit\CasoDePruebaUnitario;
use ReflectionClass;

/**
 * Ninguna Pieza puede quedar varada.
 *
 * Invariante nacida de un fallo real en producción: 9 Piezas pasaron 6 días
 * atascadas en EN_REVISION con el diagnóstico de Compuertas sin escribir, y
 * otras 12 en SIN_PERIODISTA_IDONEO sin forma alguna de volver al pipeline.
 * Ninguna pantalla las mostraba y ningún paso del motor las recogía: trabajo
 * perdido en silencio, que es exactamente lo que GOVERNANCE §2.1 prohíbe
 * ("el panel muestra por qué cada pieza salió, se retuvo o se degradó").
 *
 * Las dos causas eran estructurales, no puntuales:
 *
 * 1. El estado no tenía arista a FALLIDA, así que
 *    `Orquestador::marcarFallida()` era rechazado por el propio grafo y el
 *    `catch` se lo tragaba.
 * 2. El estado no lo sondeaba el motor NI existía una acción humana que lo
 *    devolviera al pipeline — solo descartarlo, que es tirar el trabajo.
 *
 * Si este test se pone en rojo, alguien ha añadido (o dejado) un estado del
 * que una Pieza no puede salir hacia adelante.
 *
 * @covers \Pluma\Pipeline\Transicionador
 */
final class SinCallejonSinSalidaInvarianteTest extends CasoDePruebaUnitario {

	/**
	 * Estados que un humano puede devolver al pipeline con una acción
	 * explícita, y el método que la implementa. Descartar NO cuenta: tirar el
	 * trabajo no es una salida, es una pérdida.
	 *
	 * @var array<string, array{0: class-string, 1: string}>
	 */
	private const AVANCE_HUMANO = array(
		'retenida'              => array( \Pluma\Pipeline\GestorSalaRevision::class, 'devolver' ),
		'sin_periodista_idoneo' => array( \Pluma\Pipeline\GestorSalaRevision::class, 'reanudarSinPeriodistaIdoneo' ),
		'fallida'               => array( \Pluma\Pipeline\GestorSalaTendencias::class, 'cubrirAhora' ),
		'programada'            => array( \Pluma\Pipeline\GestorSalaRevision::class, 'aprobarAhora' ),
	);

	/**
	 * Transitorios: se entra y se sale dentro de un único método del
	 * Orquestador. No necesitan sondeo propio, pero SÍ arista a FALLIDA: sin
	 * ella, si ese método revienta a mitad, la Pieza se queda dentro.
	 *
	 * @var list<string>
	 */
	private const TRANSITORIOS = array( 'en_investigacion', 'en_redaccion' );

	/**
	 * @return array<string, list<string>>
	 */
	private function grafo(): array {
		/** @var array<string, list<string>> $grafo */
		$grafo = ( new ReflectionClass( Transicionador::class ) )->getConstant( 'GRAFO' );

		return $grafo;
	}

	/**
	 * Estados que `Orquestador::avanzarPipeline()` sondea de verdad, leídos
	 * del código fuente real: si alguien deja de sondear un estado, este test
	 * lo detecta sin que haya que acordarse de actualizar una lista a mano.
	 *
	 * @return list<string>
	 */
	private function estadosSondeadosPorElMotor(): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de un archivo del propio repo en un test de arquitectura, no una URL remota.
		$fuente = file_get_contents( dirname( __DIR__, 2 ) . '/src/Pipeline/Orquestador.php' );
		self::assertIsString( $fuente );

		preg_match_all( '/obtenerPorEstado\(\s*EstadoPieza::(\w+)/', $fuente, $coincidencias );

		$estados = array();
		foreach ( $coincidencias[1] as $nombreCase ) {
			$caso = constant( EstadoPieza::class . '::' . $nombreCase );
			self::assertInstanceOf( EstadoPieza::class, $caso );
			$estados[] = $caso->value;
		}

		return array_values( array_unique( $estados ) );
	}

	public function test_todo_estado_no_terminal_tiene_una_via_de_avance(): void {
		$sondeados = $this->estadosSondeadosPorElMotor();

		self::assertNotEmpty( $sondeados, 'No se pudo leer ningún estado sondeado del Orquestador: el test se habría vuelto decorativo.' );

		foreach ( EstadoPieza::cases() as $estado ) {
			if ( $estado->esTerminal() ) {
				continue;
			}

			$tieneVia = in_array( $estado->value, $sondeados, true )
				|| isset( self::AVANCE_HUMANO[ $estado->value ] )
				|| in_array( $estado->value, self::TRANSITORIOS, true );

			self::assertTrue(
				$tieneVia,
				"El estado '{$estado->value}' no es terminal, el motor no lo sondea y ningún humano puede devolverlo al pipeline: toda Pieza que caiga ahí queda varada para siempre."
			);
		}
	}

	public function test_las_acciones_humanas_de_avance_existen_de_verdad(): void {
		foreach ( self::AVANCE_HUMANO as $estado => [$clase, $metodo] ) {
			self::assertTrue(
				method_exists( $clase, $metodo ),
				"El estado '{$estado}' declara que se reanuda con {$clase}::{$metodo}(), pero ese método no existe: la salida es una promesa vacía."
			);
		}
	}

	public function test_todo_estado_con_trabajo_falible_puede_marcarse_fallida(): void {
		$grafo     = $this->grafo();
		$sondeados = $this->estadosSondeadosPorElMotor();

		foreach ( EstadoPieza::cases() as $estado ) {
			if ( $estado->esTerminal() || EstadoPieza::Fallida === $estado ) {
				continue;
			}

			$corretrabajoFalible = in_array( $estado->value, $sondeados, true )
				|| in_array( $estado->value, self::TRANSITORIOS, true );

			if ( ! $corretrabajoFalible ) {
				continue;
			}

			self::assertContains(
				EstadoPieza::Fallida->value,
				$grafo[ $estado->value ] ?? array(),
				"En '{$estado->value}' corre trabajo que puede reventar, pero el grafo no permite marcarla FALLIDA: `marcarFallida()` sería rechazado y la Pieza quedaría varada en silencio."
			);
		}
	}

	/**
	 * Regresión explícita de los dos callejones reales, por si alguien
	 * revierte las aristas sin entender por qué estaban.
	 */
	public function test_regresion_de_los_dos_callejones_reales(): void {
		$grafo = $this->grafo();

		self::assertContains( 'fallida', $grafo['en_revision'], 'EN_REVISION sin salida a FALLIDA: vuelve el callejón que varó 9 Piezas 6 días.' );
		self::assertContains( 'investigada', $grafo['sin_periodista_idoneo'], 'SIN_PERIODISTA_IDONEO debe reanudar hacia INVESTIGADA, un estado que el motor SÍ sondea (EN_REDACCION es transitorio y varaba la Pieza otra vez).' );
	}
}
