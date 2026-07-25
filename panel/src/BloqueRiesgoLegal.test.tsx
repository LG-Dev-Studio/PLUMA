import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BloqueRiesgoLegal, type TextosRiesgoLegal } from './BloqueRiesgoLegal';

function textos(): TextosRiesgoLegal {
    return {
        titulo: 'Perfil de riesgo legal',
        explicacion: 'Declara el régimen de responsabilidad de tu jurisdicción real.',
        etiquetaRegimen: 'Régimen de responsabilidad',
        regimenCivil: 'Civil',
        regimenPenal: 'Penal',
        guardar: 'Guardar régimen',
        guardado: 'Régimen actualizado',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'No se pudo guardar.',
    };
}

function stubFetch(regimenResponsabilidad = 'civil') {
    const fetchSimulado = vi.fn((_url: string, opciones?: RequestInit) => {
        if (opciones?.method === 'POST') {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ regimenResponsabilidad: 'penal' }) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({ regimenResponsabilidad }) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('BloqueRiesgoLegal', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('carga el régimen actual', async () => {
        stubFetch('civil');

        render(<BloqueRiesgoLegal restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        const selector = (await screen.findByLabelText('Régimen de responsabilidad')) as HTMLSelectElement;
        expect(selector.value).toBe('civil');
    });

    it('guarda el cambio de régimen', async () => {
        const fetchSimulado = stubFetch('civil');

        render(<BloqueRiesgoLegal restUrl="https://x.test/wp-json/" nonce="nonce-x" textos={textos()} />);

        const selector = (await screen.findByLabelText('Régimen de responsabilidad')) as HTMLSelectElement;
        await userEvent.selectOptions(selector, 'penal');
        await userEvent.click(screen.getByRole('button', { name: 'Guardar régimen' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://x.test/wp-json/pluma/v1/motor/riesgo-legal',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }),
                    body: JSON.stringify({ regimenResponsabilidad: 'penal' }),
                })
            )
        );
        expect(await screen.findByText('Régimen actualizado')).toBeInTheDocument();
    });
});
