import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PantallaTendencias, type TarjetaTendencia, type TextosTendencias } from './PantallaTendencias';

function textosDeEjemplo(): TextosTendencias {
    return {
        titulo: 'Sala de Tendencias',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar la Sala de Tendencias.',
        errorAccion: 'La acción no se pudo completar.',
        confirmacion: {
            cubrir: 'Cobertura priorizada.',
            ignorar: 'Tendencia ignorada.',
            vigilar: 'Tendencia en vigilancia.',
            'cubrir-actualizacion': 'Actualización priorizada.',
        },
        vacio: 'todavía no se ha detectado ninguna tendencia',
        velocidad: 'Velocidad',
        afinidad: 'Afinidad',
        total: 'Puntuación de Oportunidad',
        desgloseParcial: 'Desglose sobre velocidad y afinidad; hueco competitivo y vida útil llegan con el Radar completo.',
        quienCubre: 'Quién la está cubriendo ya',
        nadieCubre: 'sin cobertura detectada en las señales',
        estadoVigilada: 'En vigilancia',
        estadoSospechaManipulacion: 'Sospecha de manipulación (concentración de fuente) — heurística, no un veredicto',
        cubrirAhora: 'Cubrir ahora',
        ignorar: 'Ignorar',
        vigilar: 'Vigilar',
        posibleActualizacion: 'Posible actualización de una historia ya cubierta',
        cubrirActualizacion: 'Cubrir como actualización',
    };
}

function tarjetaDeEjemplo(sobrescribir: Partial<TarjetaTendencia> = {}): TarjetaTendencia {
    return {
        id: 7,
        termino: 'elecciones 2026',
        fuenteSenal: 'google_trends',
        velocidad: 90,
        afinidad: 70,
        puntuacionTotal: 81,
        estado: 'en_pipeline',
        articulosRelacionados: [{ titulo: 'Cobertura previa', url: 'https://example.com/a', fuente: 'Example' }],
        detectadaEn: '2026-07-23T08:00:00+00:00',
        tendenciaOriginalId: null,
        ...sobrescribir,
    };
}

function stubFetchConTarjetas(tarjetas: TarjetaTendencia[]) {
    const fetchSimulado = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(tarjetas) });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('PantallaTendencias', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('muestra la tarjeta con su desglose real y quién la cubre', async () => {
        stubFetchConTarjetas([tarjetaDeEjemplo()]);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('elecciones 2026')).toBeInTheDocument();
        expect(screen.getByText('81')).toBeInTheDocument();
        expect(screen.getByText('90')).toBeInTheDocument();
        expect(screen.getByText('70')).toBeInTheDocument();
        expect(screen.getByText('Cobertura previa')).toBeInTheDocument();
        // Honestidad del desglose: se declara qué componentes existen hoy.
        expect(screen.getByText(/hueco competitivo y vida útil llegan con el Radar completo/)).toBeInTheDocument();
    });

    it('destaca las tendencias en vigilancia y desactiva su botón Vigilar', async () => {
        stubFetchConTarjetas([tarjetaDeEjemplo({ estado: 'vigilada' })]);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('En vigilancia')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Vigilar' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Cubrir ahora' })).toBeEnabled();
    });

    it('muestra la insignia de sospecha de manipulación (Nivel Dos G.1) sin desactivar Vigilar', async () => {
        stubFetchConTarjetas([tarjetaDeEjemplo({ estado: 'sospecha_manipulacion' })]);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('Sospecha de manipulación (concentración de fuente) — heurística, no un veredicto')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Vigilar' })).toBeEnabled();
        expect(screen.getByRole('button', { name: 'Cubrir ahora' })).toBeEnabled();
    });

    it('ejecuta "Cubrir ahora" contra la ruta REST correcta con el nonce y recarga', async () => {
        const fetchSimulado = stubFetchConTarjetas([tarjetaDeEjemplo()]);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Cubrir ahora' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/tendencias/7/cubrir',
                expect.objectContaining({ method: 'POST', headers: { 'X-WP-Nonce': 'nonce-x' } })
            )
        );
        // GET inicial + POST + GET de recarga.
        await waitFor(() => expect(fetchSimulado).toHaveBeenCalledTimes(3));
    });

    /**
     * Bug real reportado en producción: "Cubrir ahora" sobre una tendencia que
     * ya está EN_PIPELINE devolvía 200 y priorizaba la pieza de verdad, pero la
     * tarjeta quedaba idéntica — el editor no tenía forma de saber que el
     * sistema había hecho algo ("no pasa nada").
     */
    it('confirma en pantalla que la acción se completó, aunque la tarjeta no cambie', async () => {
        stubFetchConTarjetas([tarjetaDeEjemplo()]);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(screen.queryByRole('status')).not.toBeInTheDocument();

        await userEvent.click(await screen.findByRole('button', { name: 'Cubrir ahora' }));

        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Cobertura priorizada.'));
    });

    it('muestra el error de acción si el POST falla', async () => {
        const fetchSimulado = vi
            .fn()
            .mockResolvedValueOnce({ ok: true, json: () => Promise.resolve([tarjetaDeEjemplo()]) })
            .mockResolvedValueOnce({ ok: false, json: () => Promise.resolve({}) });
        vi.stubGlobal('fetch', fetchSimulado);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Ignorar' }));

        await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('La acción no se pudo completar.'));
    });

    it('muestra la insignia y el botón "Cubrir como actualización" para una posible actualización', async () => {
        const fetchSimulado = stubFetchConTarjetas([tarjetaDeEjemplo({ estado: 'posible_actualizacion', tendenciaOriginalId: 3 })]);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('Posible actualización de una historia ya cubierta')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Cubrir ahora' })).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Cubrir como actualización' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/tendencias/7/cubrir-actualizacion',
                expect.objectContaining({ method: 'POST', headers: { 'X-WP-Nonce': 'nonce-x' } })
            )
        );
    });

    it('muestra el mensaje vacío cuando el radar no tiene tendencias', async () => {
        stubFetchConTarjetas([]);

        render(<PantallaTendencias restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('todavía no se ha detectado ninguna tendencia')).toBeInTheDocument();
    });
});
