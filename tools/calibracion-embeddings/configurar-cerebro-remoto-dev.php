<?php
/**
 * Apunta el cerebro remoto del plugin al TEI local de `tools/tei-local/`, para
 * poder ejecutar `calibrar.php` sin pasar por el panel. Herramienta de
 * desarrollo — usa la misma clave de prueba fija documentada en
 * `tools/tei-local/README.md` ("nunca usar en producción real").
 *
 * Uso: npx wp-env run cli --env-cwd=wp-content/plugins/PLUMA -- wp eval-file tools/calibracion-embeddings/configurar-cerebro-remoto-dev.php
 *
 * Nota: sin `declare(strict_types=1)` a propósito, mismo motivo que
 * `calibrar.php` (`wp eval-file` evalúa el contenido, no lo incluye).
 */

update_option( 'pluma_cerebro_remoto_url', 'http://host.docker.internal:8089' );
update_option( 'pluma_cerebro_remoto_token_cifrado', Pluma\Kernel\Cifrado::cifrar( 'clave-de-prueba-local' ) );

echo "Cerebro remoto configurado contra el TEI local (host.docker.internal:8089).\n";
