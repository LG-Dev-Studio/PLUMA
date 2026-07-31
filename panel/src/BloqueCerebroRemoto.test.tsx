import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BloqueCerebroRemoto, type TextosCerebroRemoto } from './BloqueCerebroRemoto';

function textosDeEjemplo(): TextosCerebroRemoto {
    return {
        titulo: 'Cerebro remoto (T3)',
        urlActual: 'URL actual',
        campoUrl: 'URL del cerebro remoto',
        campoToken: 'Token de autenticación',
        guardar: 'Guardar',
        probar: 'Probar cerebro remoto',
        probando: 'Probando…',
        valida: 'El cerebro remoto respondió correctamente.',
        invalida: 'No se pudo alcanzar el cerebro remoto.',
        cambiar: 'Cambiar',
        quitar: 'Quitar',
        confirmarQuitar: '¿Quitar la configuración del cerebro remoto?',
    };
}

describe('BloqueCerebroRemoto', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('guarda una url y token nuevos tras probarlos y avisa al padre', async () => {
        const alGuardar = vi.fn();
        const fetchSimulado = vi.fn((url: string) => {
            if (url.endsWith('/motor/cerebro-remoto/probar')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve({ valida: true }) });
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
        });
        vi.stubGlobal('fetch', fetchSimulado);

        render(
            <BloqueCerebroRemoto
                restUrl="https://ejemplo.test/wp-json/"
                nonce="nonce-x"
                configurada={false}
                url={null}
                textos={textosDeEjemplo()}
                alGuardar={alGuardar}
                alError={() => {}}
            />
        );

        await userEvent.type(screen.getByLabelText('URL del cerebro remoto'), 'https://cerebro.example/salud');
        await userEvent.type(screen.getByLabelText('Token de autenticación'), 'token-de-prueba');
        await userEvent.click(screen.getByRole('button', { name: 'Probar cerebro remoto' }));

        expect(await screen.findByText('El cerebro remoto respondió correctamente.')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/motor/cerebro-remoto',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }),
                    body: JSON.stringify({ url: 'https://cerebro.example/salud', token: 'token-de-prueba' }),
                })
            )
        );
        await waitFor(() => expect(alGuardar).toHaveBeenCalled());
    });

    it('pide confirmación antes de quitar una configuración ya guardada', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({}) }))
        );
        const confirmSimulado = vi.spyOn(window, 'confirm').mockReturnValue(false);

        render(
            <BloqueCerebroRemoto
                restUrl="https://ejemplo.test/wp-json/"
                nonce="n"
                configurada
                url="https://cerebro.example/salud"
                textos={textosDeEjemplo()}
                alGuardar={() => {}}
                alError={() => {}}
            />
        );

        expect(screen.getByText(/https:\/\/cerebro\.example\/salud/)).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Quitar' }));

        expect(confirmSimulado).toHaveBeenCalledWith('¿Quitar la configuración del cerebro remoto?');
        confirmSimulado.mockRestore();
    });

    it('avisa al padre si guardar falla', async () => {
        const alError = vi.fn();
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve({ ok: false, json: () => Promise.resolve({}) }))
        );

        render(
            <BloqueCerebroRemoto
                restUrl="https://ejemplo.test/wp-json/"
                nonce="n"
                configurada={false}
                url={null}
                textos={textosDeEjemplo()}
                alGuardar={() => {}}
                alError={alError}
            />
        );

        await userEvent.type(screen.getByLabelText('URL del cerebro remoto'), 'https://cerebro.example/salud');
        await userEvent.type(screen.getByLabelText('Token de autenticación'), 'token-de-prueba');
        await userEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        await waitFor(() => expect(alError).toHaveBeenCalled());
    });
});
