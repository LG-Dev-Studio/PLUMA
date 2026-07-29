<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use DateTimeImmutable;

/**
 * Nivel Cuatro W.3 — una suscripción de un lector a un canal de un solo
 * objetivo. Ver `CanalSuscripcion`/`TipoSuscripcion` para el significado de
 * cada combinación de campos.
 */
final readonly class Suscriptor {

	public function __construct(
		public int $id,
		public CanalSuscripcion $canal,
		public TipoSuscripcion $tipo,
		public ?int $referenciaId,
		public ?string $vertical,
		public ?string $email,
		public ?string $pushEndpoint,
		public ?string $pushClaveP256dh,
		public ?string $pushClaveAuth,
		public string $token,
		public bool $confirmado,
		public DateTimeImmutable $creadoEn,
		public ?DateTimeImmutable $confirmadoEn,
	) {
	}
}
