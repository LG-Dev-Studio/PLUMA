import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BloqueCreacionAutomaticaPeriodistas, type TextosCreacionAutomaticaPeriodistas } from './BloqueCreacionAutomaticaPeriodistas';

function textos(): TextosCreacionAutomaticaPeriodistas {
    return {
        titulo: 'Creación automática de periodistas',
        explicacion: 'Cuando hay suficientes noticias sin cobertura.',
        activar: 'Activar creación automática',
        activada: 'Creación automática activada',
        etiquetaMinPiezas: 'Mínimo de noticias sin cobertura',
        etiquetaVentana: 'Ventana de días considerados',
        etiquetaCooldown: 'Horas mínimas entre evaluaciones',
        etiquetaMax: 'Máximo de periodistas automáticos',
        guardar: 'Guardar',
        guardado: 'Ajustes actualizados',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'No se pudo guardar.',
    };
}

const estadoInicial = {
    activada: false,
    minPiezasGrupo: 3,
    ventanaDias: 14,
    cooldownHoras: 24,
    maxPeriodistas: 5,
};

function stubFetch(respuestaPost: { ok: boolean } = { ok: true }) {
    const fetchSimulado = vi.fn((_url: string, opciones?: RequestInit) => {
        if ('POST' === opciones?.method) {
            return Promise.resolve({ ...respuestaPost, json: () => Promise.resolve(estadoInicial) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve(estadoInicial) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('BloqueCreacionAutomaticaPeriodistas', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('carga los valores actuales, desactivado por defecto', async () => {
        stubFetch();

        render(<BloqueCreacionAutomaticaPeriodistas restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        expect(await screen.findByText('Activar creación automática')).toBeInTheDocument();
        expect(screen.getByLabelText(/Activar creación automática/)).not.toBeChecked();
        expect(screen.getByLabelText('Mínimo de noticias sin cobertura')).toHaveValue(3);
    });

    it('activa el interruptor y guarda contra el endpoint correcto', async () => {
        const fetchSimulado = stubFetch();

        render(<BloqueCreacionAutomaticaPeriodistas restUrl="https://x.test/wp-json/" nonce="nonce-x" textos={textos()} />);

        await screen.findByText('Activar creación automática');
        await userEvent.click(screen.getByLabelText(/Activar creación automática/));
        await userEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://x.test/wp-json/pluma/v1/motor/creacion-automatica-periodistas',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }),
                    body: JSON.stringify({ ...estadoInicial, activada: true }),
                })
            )
        );
        expect(await screen.findByText('Ajustes actualizados')).toBeInTheDocument();
    });

    it('muestra el error si el guardado falla', async () => {
        stubFetch({ ok: false });

        render(<BloqueCreacionAutomaticaPeriodistas restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        await screen.findByText('Activar creación automática');
        await userEvent.click(screen.getByRole('button', { name: 'Guardar' }));

        expect(await screen.findByText('No se pudo guardar.')).toBeInTheDocument();
    });
});
