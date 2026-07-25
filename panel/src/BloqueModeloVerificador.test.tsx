import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BloqueModeloVerificador, type TextosModeloVerificador } from './BloqueModeloVerificador';

function textos(): TextosModeloVerificador {
    return {
        titulo: 'Modelo verificador',
        explicacion: 'Declara un modelo distinto al premium.',
        etiquetaModelo: 'Slug del modelo verificador',
        guardar: 'Guardar modelo',
        guardado: 'Modelo actualizado',
        notaAlcance: 'Hoy es informativo.',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'No se pudo guardar.',
    };
}

function stubFetch(modeloVerificador = 'anthropic/claude-sonnet-5') {
    const fetchSimulado = vi.fn((_url: string, opciones?: RequestInit) => {
        if (opciones?.method === 'POST') {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ modeloVerificador: 'openai/gpt-5' }) });
        }
        return Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ modeloVerificador, obligatoriedadDeFabrica: false }),
        });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('BloqueModeloVerificador', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('muestra siempre la nota de alcance', async () => {
        stubFetch();

        render(<BloqueModeloVerificador restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        expect(await screen.findByText('Hoy es informativo.')).toBeInTheDocument();
    });

    it('carga el modelo actual y guarda el cambio', async () => {
        const fetchSimulado = stubFetch('anthropic/claude-sonnet-5');

        render(<BloqueModeloVerificador restUrl="https://x.test/wp-json/" nonce="nonce-x" textos={textos()} />);

        const campo = (await screen.findByLabelText('Slug del modelo verificador')) as HTMLInputElement;
        expect(campo.value).toBe('anthropic/claude-sonnet-5');

        await userEvent.clear(campo);
        await userEvent.type(campo, 'openai/gpt-5');
        await userEvent.click(screen.getByRole('button', { name: 'Guardar modelo' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://x.test/wp-json/pluma/v1/motor/modelo-verificador',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }),
                    body: JSON.stringify({ modeloVerificador: 'openai/gpt-5' }),
                })
            )
        );
        expect(await screen.findByText('Modelo actualizado')).toBeInTheDocument();
    });
});
