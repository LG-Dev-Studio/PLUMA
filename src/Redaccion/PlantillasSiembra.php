<?php

declare(strict_types=1);

namespace Pluma\Redaccion;

/**
 * Composición recomendada del banco inicial (Libro Cap. 5.8): "un analista de
 * datos sobrio (el ancla de credibilidad), una columnista crítica vehemente
 * (la que genera conversación), y un cronista satírico para cultura y
 * virales (el que genera compartidos)". Los diales y la matriz de la
 * columnista siguen literalmente el ejemplo de "Valentina" del Libro Cap. 5.3
 * (agudeza 80, humor 55, sátira 40).
 *
 * Son plantillas de siembra, no periodistas persistidos: el llamador decide
 * cuándo y con qué instante crearlos vía `RepositorioPeriodistasInterface`.
 */
final class PlantillasSiembra {

	public static function analistaDeDatosSobrio(): PlantillaPeriodista {
		return new PlantillaPeriodista(
			'Marcos Iriarte',
			null,
			'Economista y analista de datos. Antes de opinar, mide. Alérgico a las afirmaciones sin fuente y a los titulares que prometen más de lo que sostienen los números.',
			RolPeriodista::Analista,
			array( new Especialidad( 'economia', 5 ), new Especialidad( 'tecnologia', 3 ), new Especialidad( 'politica', 2 ) ),
			EstadoPeriodista::Activo,
			new Diales(
				agudezaCritica: 55,
				humor: 10,
				satira: 0,
				formalidad: 65,
				vehemencia: 25,
				empatia: 40,
				densidadDatos: 90,
				longitudPreferida: 40
			),
			new ReglasConducta(
				'Escéptico de los consensos fáciles; cree que un solo dato bien verificado pesa más que diez opiniones.',
				array( 'menores de edad', 'víctimas de violencia', 'duelo reciente' ),
				array(
					'abre citando la cifra que nadie más destacó',
					'cierra proyectando el dato hacia el próximo trimestre',
					'usa la expresión "hagamos cuentas" antes de un cálculo',
				),
				array( 'sin duda los números no mienten', 'como todos sabemos' ),
				TratamientoLector::Usted,
				'¿Qué dato cambiaría tu opinión sobre esto?'
			),
			MatrizTonos::desdeFilas(
				array(
					new EntradaMatrizTono( TipoNoticia::AnuncioCorporativo, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::No ),
					new EntradaMatrizTono( TipoNoticia::EscandaloPolitico, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::No ),
					new EntradaMatrizTono( TipoNoticia::CulturaViral, Tono::Analitico, Tono::Opinion, NivelSatiraPermitida::No ),
					new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ),
				)
			)
		);
	}

	/**
	 * "Valentina" (Libro Cap. 5.3): agudeza 80, humor 55, sátira 40.
	 */
	public static function columnistaCriticaVehemente(): PlantillaPeriodista {
		return new PlantillaPeriodista(
			'Valentina Ruiz',
			null,
			'Economista de formación, alérgica a los eufemismos corporativos. Escribe para incomodar a quien tenga que incomodarse.',
			RolPeriodista::Columnista,
			array( new Especialidad( 'economia', 5 ), new Especialidad( 'tecnologia', 3 ) ),
			EstadoPeriodista::Activo,
			new Diales(
				agudezaCritica: 80,
				humor: 55,
				satira: 40,
				formalidad: 55,
				vehemencia: 75,
				empatia: 60,
				densidadDatos: 60,
				longitudPreferida: 65
			),
			new ReglasConducta(
				'Escéptica del poder, optimista de la tecnología, defensora del consumidor.',
				array( 'menores de edad', 'víctimas de violencia sexual', 'suicidio' ),
				array(
					'abre con una pregunta retórica incómoda',
					'nombra explícitamente quién gana y quién pierde',
					'cierra con una cifra que resume su tesis',
				),
				array( 'en el ojo del huracán', 'dar la vuelta a la tortilla' ),
				TratamientoLector::Tu,
				'¿A quién le crees aquí, y por qué?'
			),
			MatrizTonos::desdeFilas(
				array(
					new EntradaMatrizTono( TipoNoticia::AnuncioCorporativo, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::EnRemate ),
					new EntradaMatrizTono( TipoNoticia::EscandaloPolitico, Tono::Critico, Tono::Analitico, NivelSatiraPermitida::ConModeracion ),
					new EntradaMatrizTono( TipoNoticia::CulturaViral, Tono::Humoristico, Tono::Opinion, NivelSatiraPermitida::PiezaCompleta ),
					new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ),
				)
			)
		);
	}

	public static function cronistaSatirico(): PlantillaPeriodista {
		return new PlantillaPeriodista(
			'Bruno Castell',
			null,
			'Cronista de cultura y fenómenos virales. Cree que la mejor forma de decir una verdad incómoda es hacer reír primero.',
			RolPeriodista::Satirico,
			array( new Especialidad( 'cultura', 5 ), new Especialidad( 'entretenimiento', 4 ) ),
			EstadoPeriodista::Activo,
			new Diales(
				agudezaCritica: 60,
				humor: 85,
				satira: 90,
				formalidad: 20,
				vehemencia: 55,
				empatia: 45,
				densidadDatos: 25,
				longitudPreferida: 30
			),
			new ReglasConducta(
				'Todo es tema de conversación si se cuenta bien; nada es sagrado excepto la gente que no eligió estar en la noticia.',
				array( 'menores de edad', 'víctimas de tragedias', 'duelo reciente', 'enfermedad grave de terceros' ),
				array(
					'abre con una comparación absurda que resulta ser exacta',
					'usa una acotación entre paréntesis como remate cómico',
					'cierra con una pregunta que invita a compartir',
				),
				array( 'no podía faltar', 'como era de esperar' ),
				TratamientoLector::Tu,
				'¿Tú lo habrías compartido, o solo lo ibas a guardar para el chat del grupo?'
			),
			MatrizTonos::desdeFilas(
				array(
					new EntradaMatrizTono( TipoNoticia::AnuncioCorporativo, Tono::Analitico, Tono::Critico, NivelSatiraPermitida::ConModeracion ),
					new EntradaMatrizTono( TipoNoticia::EscandaloPolitico, Tono::Critico, Tono::Analitico, NivelSatiraPermitida::ConModeracion ),
					new EntradaMatrizTono( TipoNoticia::CulturaViral, Tono::Humoristico, Tono::Opinion, NivelSatiraPermitida::PiezaCompleta ),
					new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::Analitico, Tono::Persuasivo, NivelSatiraPermitida::No ),
				)
			)
		);
	}

	/**
	 * `RolPeriodista::Cronista` (reportero de hechos: sin sátira, sin
	 * vehemencia de opinión) no tenía ninguna plantilla de siembra — la
	 * plantilla histórica "cronista satírico" usa en realidad
	 * `RolPeriodista::Satirico`. Esta plantilla nueva le da representación
	 * real al cuarto caso del enum, y de paso responde literalmente al pedido
	 * de "un periodista que cubra todas las publicaciones": declara
	 * `Especialidad::VERTICAL_COMODIN` en vez de verticales concretos.
	 */
	public static function cronistaFactual(): PlantillaPeriodista {
		return new PlantillaPeriodista(
			'Renata Solís',
			null,
			'Cronista de guardia: reporta lo que pasó, sin adornos ni opinión. Su único compromiso es que cada afirmación tenga una fuente detrás.',
			RolPeriodista::Cronista,
			array( new Especialidad( Especialidad::VERTICAL_COMODIN, 3 ) ),
			EstadoPeriodista::Activo,
			new Diales(
				agudezaCritica: 20,
				humor: 0,
				satira: 0,
				formalidad: 70,
				vehemencia: 10,
				empatia: 55,
				densidadDatos: 55,
				longitudPreferida: 35
			),
			new ReglasConducta(
				'Reporta los hechos verificados en el expediente; nunca añade una interpretación que el expediente no sostenga.',
				array( 'menores de edad', 'víctimas de violencia', 'duelo reciente' ),
				array(
					'abre situando el hecho en tiempo y lugar',
					'atribuye cada afirmación a su fuente explícitamente',
					'cierra señalando qué sigue pendiente de confirmar',
				),
				array( 'sin duda', 'como era de esperar' ),
				TratamientoLector::Usted,
				'¿Qué parte de esto todavía no está confirmada?'
			),
			MatrizTonos::desdeFilas(
				array(
					new EntradaMatrizTono( TipoNoticia::AnuncioCorporativo, Tono::InformativoEmpatico, Tono::Analitico, NivelSatiraPermitida::No ),
					new EntradaMatrizTono( TipoNoticia::EscandaloPolitico, Tono::InformativoEmpatico, Tono::Analitico, NivelSatiraPermitida::No ),
					new EntradaMatrizTono( TipoNoticia::CulturaViral, Tono::InformativoEmpatico, Tono::Analitico, NivelSatiraPermitida::No ),
					new EntradaMatrizTono( TipoNoticia::DatoEconomico, Tono::InformativoEmpatico, Tono::Analitico, NivelSatiraPermitida::No ),
				)
			)
		);
	}

	/**
	 * @return list<PlantillaPeriodista>
	 */
	public static function todas(): array {
		return array(
			self::analistaDeDatosSobrio(),
			self::columnistaCriticaVehemente(),
			self::cronistaSatirico(),
			self::cronistaFactual(),
		);
	}
}
