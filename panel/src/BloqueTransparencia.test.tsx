import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BloqueTransparencia, type TextosTransparencia } from './BloqueTransparencia';

function textos(): TextosTransparencia {
    return {
        titulo: 'Transparencia y cumplimiento',
        explicacion: 'Aviso visible + marcado legible por máquina.',
        etiquetaFormato: 'Formato del aviso visible',
        formatoBreve: 'Breve',
        formatoExtendido: 'Extendido',
        guardar: 'Guardar formato',
        guardado: 'Formato actualizado',
        marcadoDeFabrica: 'El marcado legible por máquina es requisito de fábrica.',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'No se pudo guardar.',
    };
}

function stubFetch(formato = 'breve') {
    const fetchSimulado = vi.fn((_url: string, opciones?: RequestInit) => {
        if (opciones?.method === 'POST') {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ formato: 'extendido' }) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({ formato, marcadoIaDeFabrica: true }) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('BloqueTransparencia', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('muestra siempre la nota de marcado de fábrica', async () => {
        stubFetch();

        render(<BloqueTransparencia restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        expect(await screen.findByText('El marcado legible por máquina es requisito de fábrica.')).toBeInTheDocument();
    });

    it('carga el formato actual y guarda el cambio', async () => {
        const fetchSimulado = stubFetch('breve');

        render(<BloqueTransparencia restUrl="https://x.test/wp-json/" nonce="nonce-x" textos={textos()} />);

        const selector = (await screen.findByLabelText('Formato del aviso visible')) as HTMLSelectElement;
        await userEvent.selectOptions(selector, 'extendido');
        await userEvent.click(screen.getByRole('button', { name: 'Guardar formato' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://x.test/wp-json/pluma/v1/motor/transparencia',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }),
                    body: JSON.stringify({ formato: 'extendido' }),
                })
            )
        );
        expect(await screen.findByText('Formato actualizado')).toBeInTheDocument();
    });
});
