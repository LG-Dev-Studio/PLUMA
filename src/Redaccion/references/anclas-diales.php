<?php
/**
 * Anclas de 3 tramos por dial de temperamento (Nivel Dos A.3): copy editorial
 * original, redactado una vez y congelado aquí — cada tramo lleva una
 * directriz corta más un párrafo ancla (ejemplo de prosa en ese registro,
 * no una descripción abstracta) que calibra al proveedor de lenguaje sobre
 * qué significa el número en la práctica.
 *
 * Tramos por dial continuo 0-100: bajo [0,33) / medio [33,67) / alto [67,100].
 *
 * @return array<string, array{
 *     etiqueta: string,
 *     bajo: array{directriz: string, parrafoAncla: string},
 *     medio: array{directriz: string, parrafoAncla: string},
 *     alto: array{directriz: string, parrafoAncla: string}
 * }>
 */

declare(strict_types=1);
return array(
	'agudezaCritica' => array(
		'etiqueta' => 'Agudeza crítica',
		'bajo'     => array(
			'directriz'    => 'relata con neutralidad, sin cuestionar motivos de los actores',
			'parrafoAncla' => 'El ayuntamiento aprobó ayer la partida de 4 millones para el nuevo centro de salud. La votación se resolvió 12 a 3, con el bloque opositor absteniéndose en dos casos. El proyecto entra ahora en fase de licitación, con obras previstas para el primer trimestre del próximo año.',
		),
		'medio'    => array(
			'directriz'    => 'señala tensiones y contradicciones puntuales, sin acusar directamente',
			'parrafoAncla' => 'El ayuntamiento celebra la partida de 4 millones como un logro de gestión, aunque la misma cifra llevaba dos años bloqueada en comisión sin explicación pública. Nadie del bloque de gobierno ha aclarado por qué el centro de salud, prometido en la campaña anterior, tardó tanto en salir de un cajón.',
		),
		'alto'     => array(
			'directriz'    => 'interroga motivos, señala contradicciones, nombra ganadores y perdedores',
			'parrafoAncla' => 'La partida de 4 millones que hoy se aplaude como gesto de gestión llevaba dos años enterrada en comisión, y nadie explica por qué. La constructora que se llevará la licitación donó a la campaña del alcalde hace tres años. El barrio que necesitaba el centro de salud lleva una década esperando; los que ganan hoy no son los pacientes, son los de siempre.',
		),
	),
	'humor'          => array(
		'etiqueta' => 'Humor',
		'bajo'     => array(
			'directriz'    => 'tono sobrio, sin licencias cómicas',
			'parrafoAncla' => 'El informe del banco central confirma una desaceleración moderada del consumo interno durante el segundo trimestre. Los analistas atribuyen el fenómeno a la subida de tipos aplicada en marzo, cuyo efecto sobre el crédito al consumo empieza recién a notarse en las cifras oficiales.',
		),
		'medio'    => array(
			'directriz'    => 'una nota de ironía puntual, sin forzarla, sin quitarle peso al argumento',
			'parrafoAncla' => 'El banco central anuncia que el consumo se enfría, justo el mes en que también anunció que todo iba "según lo previsto". Lo previsto, al parecer, incluía que la gente dejara de comprar. Los tipos subieron en marzo; la sorpresa de julio es que subir el precio del dinero hace que la gente lo gaste menos.',
		),
		'alto'     => array(
			'directriz'    => 'ironía recurrente, remates cómicos, sin perder el hilo argumental',
			'parrafoAncla' => 'El banco central mira el consumo enfriarse y llama a esto "desaceleración moderada", que es la forma elegante de decir que la fiesta se acabó y nadie quiere pagar la cuenta. Subieron los tipos en marzo prometiendo que el crédito respiraría; lo que respira hoy es la calculadora de quien intenta llegar a fin de mes. Previsto, dicen. Previsto como el frío en enero.',
		),
	),
	'formalidad'     => array(
		'etiqueta' => 'Formalidad',
		'bajo'     => array(
			'directriz'    => 'cercano, coloquial, como quien le cuenta la noticia a un vecino',
			'parrafoAncla' => 'Oye, ¿te enteraste de lo del centro de salud? Al final sí sale, con 4 millones y todo. Llevaban dos años dándole vueltas, pero parece que esta vez va en serio.',
		),
		'medio'    => array(
			'directriz'    => 'registro periodístico estándar, directo, sin jerga ni excesiva distancia',
			'parrafoAncla' => 'El proyecto del nuevo centro de salud, con una inversión de 4 millones, obtuvo finalmente luz verde municipal tras dos años de tramitación. Las obras comenzarían en el primer trimestre del próximo año, según fuentes del consistorio.',
		),
		'alto'     => array(
			'directriz'    => 'registro de columna dominical, elaborado, con distancia y cuidado en la construcción de cada frase',
			'parrafoAncla' => 'Tras un lustro de promesas aplazadas y dos años de tramitación silenciosa, el consistorio ha resuelto por fin dotar de 4 millones de euros al proyecto del nuevo centro de salud, cuya necesidad el vecindario venía reclamando desde hace tiempo con una paciencia que roza lo administrativo.',
		),
	),
	'vehemencia'     => array(
		'etiqueta' => 'Vehemencia',
		'bajo'     => array(
			'directriz'    => 'matiza, concede terreno al argumento contrario, evita afirmaciones tajantes',
			'parrafoAncla' => 'Podría argumentarse que la demora en aprobar el centro de salud respondió a razones presupuestarias legítimas, aunque también es cierto que otras partidas de menor urgencia sí avanzaron durante ese mismo periodo. Ambas lecturas conviven en los datos disponibles.',
		),
		'medio'    => array(
			'directriz'    => 'toma partido con claridad, sin cerrar la puerta a que el lector discrepe',
			'parrafoAncla' => 'Dos años de demora para una obra de salud pública no se explican solo con presupuesto: otras partidas menos urgentes avanzaron mientras esta esperaba. La versión oficial no cuadra del todo con el calendario real.',
		),
		'alto'     => array(
			'directriz'    => 'afirma sin ambigüedad, desafía al lector a rebatir el argumento',
			'parrafoAncla' => 'No hay excusa presupuestaria que sostenga dos años de demora para un centro de salud mientras otras partidas menos urgentes se aprobaban sin pestañear. Quien defienda la versión oficial tendrá que explicar, cifra en mano, por qué la salud del barrio esperó y otras prioridades no.',
		),
	),
	'empatia'        => array(
		'etiqueta' => 'Empatía',
		'bajo'     => array(
			'directriz'    => 'distante, centrado en el dato institucional, sin detenerse en el impacto humano',
			'parrafoAncla' => 'La partida aprobada asciende a 4 millones de euros, financiados mediante remanente de tesorería. El plazo de ejecución estimado es de dieciocho meses desde la adjudicación de la obra.',
		),
		'medio'    => array(
			'directriz'    => 'reconoce el impacto humano sin desplazar el centro de la pieza hacia él',
			'parrafoAncla' => 'Los 4 millones aprobados llegan tarde para muchas familias del barrio que llevan años trasladándose al hospital más cercano por consultas básicas. La obra, al menos, empieza a resolver esa distancia.',
		),
		'alto'     => array(
			'directriz'    => 'centra la pieza en el impacto humano, el dato institucional queda al servicio del relato de las personas afectadas',
			'parrafoAncla' => 'Marta lleva tres años cruzando la ciudad en autobús cada vez que su hijo necesita una consulta que debería resolverse a cinco minutos de su casa. El centro de salud que hoy se aprueba con 4 millones de euros no es una cifra en un acta: es el fin de esos trayectos para cientos de familias como la suya.',
		),
	),
	'densidadDatos'  => array(
		'etiqueta' => 'Densidad de datos',
		'bajo'     => array(
			'directriz'    => 'narrativo, prioriza el relato sobre la cifra exacta',
			'parrafoAncla' => 'El barrio llevaba años esperando un centro de salud propio, y esa espera parece llegar a su fin con el proyecto recién aprobado. La obra promete cambiar la rutina de quienes hoy deben desplazarse lejos para una simple consulta.',
		),
		'medio'    => array(
			'directriz'    => 'combina relato y dato, sin saturar cada frase con cifras',
			'parrafoAncla' => 'El proyecto, aprobado con una inversión de 4 millones de euros, pondrá fin a una espera de varios años para el barrio. Las obras, de dieciocho meses, beneficiarán directamente a unas 12.000 personas según el padrón municipal.',
		),
		'alto'     => array(
			'directriz'    => 'cada afirmación relevante lleva su número, su fuente y su unidad',
			'parrafoAncla' => 'La partida aprobada asciende a 4.000.000 de euros, financiados en un 70% con remanente de tesorería y el 30% restante con transferencia autonómica. El plazo de ejecución es de 18 meses y la obra beneficiará, según el padrón municipal de 2026, a 12.340 residentes del distrito.',
		),
	),
);
