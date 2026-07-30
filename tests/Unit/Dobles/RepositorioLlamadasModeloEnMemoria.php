<?php

declare(strict_types=1);

namespace Pluma\Tests\Unit\Dobles;

use DateTimeImmutable;
use Pluma\Datos\RepositorioLlamadasModeloInterface;
use Pluma\Proveedores\RegistroLlamada;
use RuntimeException;

/**
 * Doble en memoria de `RepositorioLlamadasModeloInterface` (NCP-1,
 * `ADR 0010`): guarda cada registro recibido para que el test lo inspeccione,
 * y opcionalmente lanza para ejercitar el camino de degradación de los
 * decoradores de instrumentación sin tocar `$wpdb`.
 */
final class RepositorioLlamadasModeloEnMemoria implements RepositorioLlamadasModeloInterface {

	/** @var list<array{registro: RegistroLlamada, ahora: DateTimeImmutable}> */
	public array $registros = array();

	public function __construct( private readonly bool $fallaAlRegistrar = false ) {
	}

	public function registrar( RegistroLlamada $registro, DateTimeImmutable $ahora ): void {
		if ( $this->fallaAlRegistrar ) {
			throw new RuntimeException( 'fallo simulado del repositorio de llamadas al modelo' );
		}

		$this->registros[] = array(
			'registro' => $registro,
			'ahora'    => $ahora,
		);
	}

	public function resumirEntre( DateTimeImmutable $desde, DateTimeImmutable $hasta ): array {
		return array();
	}

	public function purgarAnterioresA( DateTimeImmutable $limite ): int {
		return 0;
	}
}
