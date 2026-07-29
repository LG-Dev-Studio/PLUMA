<?php

declare(strict_types=1);

namespace Pluma\Publicacion;

use DateTimeImmutable;
use Pluma\Compuertas\CategoriaComentario;

/**
 * Comentario real de WordPress (Libro Cap. 5.7, Etapa 5: memoria de
 * audiencia + respuestas asistidas). `contenidoTexto` ya viene sin HTML
 * (`wp_strip_all_tags`) — ni la memoria ni el proveedor de lenguaje deben
 * recibir marcado. `categoria` (Nivel Cuatro X.2, Etapa 9) es `null` cuando
 * el comentario no llegó a clasificarse (post ajeno a PLUMA, o la
 * clasificación falló y quedó sin categoría — mismo criterio "sin veredicto,
 * no bloquear" de `Pluma\Compuertas\CompuertaComentarios`).
 */
final readonly class ComentarioWordPress {

	public function __construct(
		public int $id,
		public int $postId,
		public string $autor,
		public string $contenidoTexto,
		public DateTimeImmutable $fecha,
		public ?CategoriaComentario $categoria = null,
	) {
	}
}
