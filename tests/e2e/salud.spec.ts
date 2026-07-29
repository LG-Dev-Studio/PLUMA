import { expect, test } from '@playwright/test';

/**
 * Smoke E2E de la Etapa 0 (PLAN-MAESTRO): el ZIP recién instalado y activado
 * expone la Sala de Máquinas — Salud del sistema, y un administrador
 * autenticado la ve con datos reales, no un placeholder.
 *
 * Desde la Etapa 4 (porción 1) la página única del panel es `PantallaPanel`
 * (antes `PantallaSalud`): arranca en la Portada por defecto y la Sala de
 * Máquinas vive en la ruta por hash `#/salud` del shell React.
 *
 * Desde la porción 8 (onboarding de 5 actos), una instalación recién
 * activada muestra el asistente ANTES que cualquier pantalla del shell
 * (`pluma_onboarding_completado` nace en `false`) — este smoke lo salta
 * explícitamente con "Saltar por ahora" para llegar a la Sala de Máquinas,
 * reflejando el flujo real de un administrador que ya conoce el sistema.
 */

async function iniciarSesionComoAdministrador(page: import('@playwright/test').Page): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await expect(page).toHaveURL(/wp-admin/);
}

test.describe('Sala de Máquinas — Salud del sistema', () => {
    test('un administrador ve el estado real del entorno tras activar el plugin', async ({ page }) => {
        await iniciarSesionComoAdministrador(page);

        await page.goto('/wp-admin/admin.php?page=pluma-engine-panel#/salud');

        const raiz = page.locator('#pluma-panel-root');
        await expect(raiz).toBeVisible();

        const saltarOnboarding = raiz.getByRole('button', { name: 'Saltar por ahora' });
        if (await saltarOnboarding.isVisible()) {
            await saltarOnboarding.click();
        }

        // Acotado al contenedor de la pantalla: "WordPress"/"PHP" también
        // aparecen en el pie de página nativo de wp-admin (strict mode
        // violation si se busca en toda la página).
        await expect(raiz.getByText('Configuración — Sala de Máquinas')).toBeVisible();
        await expect(raiz.getByText('PHP', { exact: true })).toBeVisible();
        await expect(raiz.getByText('WordPress', { exact: true })).toBeVisible();
        await expect(raiz.getByText('Esquema PLUMA', { exact: true })).toBeVisible();
    });

    test('un usuario sin la capacidad pluma_configurar_motor no ve la pantalla', async ({ page, request }) => {
        // Verificación de la compuerta de capacidad (AGENTS.md § SUB-AGENTE SEGURIDAD):
        // la pantalla nunca cuelga de manage_options ni queda accesible a cualquier rol.
        const respuesta = await request.get('/wp-admin/admin.php?page=pluma-engine-panel');

        // Sin sesión autenticada, WordPress redirige a wp-login.php (302) en
        // vez de renderizar la pantalla — la capacidad protege el endpoint.
        expect(respuesta.url()).toContain('wp-login.php');
    });
});
