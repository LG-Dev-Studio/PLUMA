import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BloqueModoRespeto, type TextosModoRespeto } from './BloqueModoRespeto';

function textos(): TextosModoRespeto {
    return {
        titulo: 'Modo respeto',
        explicacion: 'Congela humor y sátira en todo el sitio.',
        activo: 'Activo',
        inactivo: 'Inactivo',
        activadoEn: 'Activado el',
        activadoPorAutomatico: 'Activado automáticamente por el sistema',
        activadoPorManual: 'Activado manualmente por el editor',
        motivo: 'Motivo',
        puedeDesactivarseDesde: 'Puede desactivarse a partir de',
        activar: 'Activar modo respeto ahora',
        desactivar: 'Desactivar modo respeto',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'No se pudo completar la acción.',
        aunNoDesactivable: 'Todavía no puede desactivarse.',
    };
}

interface EstadoModoRespetoDePrueba {
    activo: boolean;
    activadoEn: string | null;
    activadoPor: string | null;
    motivo: string | null;
    puedeDesactivarseDesde: string | null;
}

const estadoInactivo: EstadoModoRespetoDePrueba = {
    activo: false,
    activadoEn: null,
    activadoPor: null,
    motivo: null,
    puedeDesactivarseDesde: null,
};

const estadoActivo: EstadoModoRespetoDePrueba = {
    activo: true,
    activadoEn: '2026-07-27T10:00:00+00:00',
    activadoPor: 'manual',
    motivo: 'Activado manualmente por el editor.',
    puedeDesactivarseDesde: '2026-07-27T16:00:00+00:00',
};

function stubFetch(estadoInicial: EstadoModoRespetoDePrueba, respuestaPost: { ok: boolean; status?: number } = { ok: true }) {
    const fetchSimulado = vi.fn((_url: string, opciones?: RequestInit) => {
        if ('POST' === opciones?.method) {
            return Promise.resolve({ ...respuestaPost, json: () => Promise.resolve(estadoActivo) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve(estadoInicial) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('BloqueModoRespeto', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('muestra inactivo y el botón de activar habilitado', async () => {
        stubFetch(estadoInactivo);

        render(<BloqueModoRespeto restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        expect(await screen.findByText('Inactivo')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Activar modo respeto ahora' })).not.toBeDisabled();
        expect(screen.getByRole('button', { name: 'Desactivar modo respeto' })).toBeDisabled();
    });

    it('activa manualmente con un clic', async () => {
        const fetchSimulado = stubFetch(estadoInactivo);

        render(<BloqueModoRespeto restUrl="https://x.test/wp-json/" nonce="nonce-x" textos={textos()} />);

        await screen.findByText('Inactivo');
        await userEvent.click(screen.getByRole('button', { name: 'Activar modo respeto ahora' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://x.test/wp-json/pluma/v1/motor/modo-respeto/activar',
                expect.objectContaining({ method: 'POST', headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }) })
            )
        );
    });

    it('muestra activo con motivo y piso de duración, y avisa si aún no puede desactivarse', async () => {
        const fetchSimulado = stubFetch(estadoActivo, { ok: false, status: 409 });

        render(<BloqueModoRespeto restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        expect(await screen.findByText('Activo')).toBeInTheDocument();
        expect(screen.getByText('Activado manualmente por el editor.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Activar modo respeto ahora' })).toBeDisabled();

        await userEvent.click(screen.getByRole('button', { name: 'Desactivar modo respeto' }));

        await waitFor(() => expect(fetchSimulado).toHaveBeenCalledTimes(2));
        expect(await screen.findByText('Todavía no puede desactivarse.')).toBeInTheDocument();
    });
});
