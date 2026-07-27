<?php
/**
 * Nivel Dos A.5: corpus de regresión de voz — mismo estatus que el corpus
 * adversarial de GOVERNANCE §3.4 (fixtures de test, no UI de administración).
 *
 * Corpus MÍNIMO de desarrollo (2-3 piezas por periodista sembrado de
 * `PlantillasSiembra`), no las 15-20 piezas curadas en Piloto que exige la
 * versión madura de A.5 — CLAUDE.md prohíbe presentar datos inventados como
 * reales, así que esto se declara explícitamente como lo que es: copy
 * editorial original, escrito para fijar la voz de fábrica de cada
 * periodista sembrado, ampliable con piezas reales curadas por el
 * propietario durante Piloto.
 *
 * @return array<string, list<string>> clave = nombre del periodista en `PlantillasSiembra`
 */

declare(strict_types=1);

return array(
	'Marcos Iriarte' => array(
		'El 62% de las pequeñas empresas encuestadas reportó un aumento en sus costos operativos durante el segundo trimestre, según la cámara de comercio local. La cifra contrasta con el 48% del mismo periodo del año anterior. Hagamos cuentas: si el ritmo se sostiene, el cierre de año rondaría el 58%, salvo un cambio abrupto en el costo de los insumos importados.',
		'La inflación interanual cerró en 4.2%, medio punto por debajo del consenso de los analistas privados. El dato surge de una muestra de 3.400 hogares en doce regiones, publicada este martes por el instituto oficial de estadística. Nada en la serie histórica reciente anticipaba una desaceleración de esta magnitud.',
		'Tres bancos centrales de la región subieron su tasa de referencia en las últimas dos semanas, en un rango de 25 a 50 puntos básicos. El crédito al consumo ya muestra el primer freno desde 2024, con una caída del 6% interanual en las colocaciones de tarjeta. Hagamos cuentas: a este ritmo, el crédito al consumo terminaría el año por debajo de su nivel de hace tres años.',
	),
	'Valentina Ruiz' => array(
		'¿A quién le crees aquí, y por qué? La empresa anunció una "reestructuración estratégica" que, leída con calma, es un recorte del 15% de la plantilla justo el trimestre en que reportó ganancias récord. Los ejecutivos que firmaron el comunicado no perdieron su bono anual.',
		'Dos años de demora para una obra de salud pública no se explican solo con presupuesto: otras partidas menos urgentes avanzaron mientras esta esperaba. La constructora que se llevará la licitación donó a la campaña del alcalde hace tres años. ¿A quién le crees aquí, y por qué?',
		'El regulador multó a la plataforma por la mitad de lo que factura en un solo día. La sanción se presentó como un golpe ejemplar; el mercado, que sabe leer un balance, ni se inmutó. Quien gana con este arreglo no es el usuario afectado.',
	),
	'Bruno Castell'  => array(
		'El video de disculpas del influencer (con música triste de fondo, faltaba más) ya lleva doce millones de vistas, muy por encima de las que tuvo el video original que causó el escándalo. ¿Tú también lo viste dos veces solo para juzgar la actuación?',
		'La marca lanzó una edición limitada que se agotó en cuarenta minutos, y en cuarenta minutos y un segundo ya había reventa a triple precio (la misma marca "condenó" la reventa, en un comunicado que suena más a publicidad gratuita que a reclamo). ¿Alguien más piensa comprarle a un revendedor y fingir demencia?',
		'El programa de concursos volvió con un giro que nadie pidió: ahora el público puede eliminar a un participante a mitad de prueba, por voto directo. Un concursante le agradeció a "su proceso" con la voz quebrada (el edecán de fondo, impasible, sin saber bien dónde mirar). ¿Vale la pena seguir viéndolo, o ya perdimos esa batalla hace rato?',
	),
);
