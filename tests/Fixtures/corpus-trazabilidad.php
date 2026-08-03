<?php
/**
 * Nivel Tres J.3: corpus de calibración de trazabilidad determinista — mismo
 * estatus que `corpus-voz.php` (fixture de test, `GOVERNANCE.md` §3.4, no una
 * pantalla de administración).
 *
 * Corpus MÍNIMO de desarrollo: pares hecho/unidad escritos para esta
 * herramienta de calibración, NO el corpus real de expedientes/piezas del
 * cliente que `docs/ncp-estado-y-continuidad.md` §5(d) exige para calibrar
 * umbrales de producción — CLAUDE.md prohíbe presentar datos inventados
 * como reales, así que este corpus se declara explícitamente por lo que es:
 * copy de desarrollo para medir la forma de la distribución de similitud de
 * un proveedor de embeddings candidato, ampliable con expedientes/piezas
 * reales curados por el propietario durante Piloto.
 *
 * @return list<array{hecho: string, unidad_respaldada: string, unidad_sin_respaldo: string}>
 */

declare(strict_types=1);

return array(
	array(
		'hecho'               => 'El 62% de las pequeñas empresas encuestadas reportó un aumento en sus costos operativos durante el segundo trimestre, según la cámara de comercio local.',
		'unidad_respaldada'   => 'Casi dos tercios de las pequeñas empresas encuestadas dijeron que sus costos de operación subieron en el segundo trimestre, de acuerdo con la cámara de comercio.',
		'unidad_sin_respaldo' => 'El precio del petróleo cayó a su nivel más bajo en cinco años tras el anuncio de la OPEP.',
	),
	array(
		'hecho'               => 'El ayuntamiento cerró al tráfico la avenida principal desde las seis de la mañana por trabajos de repavimentación, según confirmó la oficina de movilidad urbana.',
		'unidad_respaldada'   => 'La oficina de movilidad urbana confirmó que la avenida principal quedó cerrada al tráfico desde temprano por obras de repavimentación.',
		'unidad_sin_respaldo' => 'El equipo local ganó el campeonato regional de fútbol por primera vez en una década.',
	),
	array(
		'hecho'               => 'Dos organizaciones internacionales de monitoreo climático coincidieron en que el mes pasado fue el más cálido registrado en la región desde que existen mediciones comparables.',
		'unidad_respaldada'   => 'Según dos organismos internacionales de vigilancia climática, el mes anterior fue el más caluroso del que se tenga registro en la zona.',
		'unidad_sin_respaldo' => 'La empresa lanzó una nueva línea de productos electrónicos dirigida al mercado juvenil.',
	),
	array(
		'hecho'               => 'El regulador multó a la plataforma con una cifra equivalente a la mitad de lo que factura en un solo día, tras una investigación por prácticas comerciales engañosas.',
		'unidad_respaldada'   => 'La plataforma recibió una sanción del regulador por prácticas comerciales engañosas, por un monto similar a medio día de su facturación.',
		'unidad_sin_respaldo' => 'El banco central mantuvo sin cambios su tasa de referencia por tercer mes consecutivo.',
	),
);
