import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PantallaDistribucion, type TextosDistribucion } from './PantallaDistribucion';

function textosDeEjemplo(): TextosDistribucion {
    return {
        titulo: 'Distribución',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar Distribución.',
        errorAccion: 'La acción no se pudo completar.',
        seccionBoletines: 'Boletines por periodista',
        enviarBoletin: 'Enviar boletín',
        sinPeriodistas: 'todavía no hay periodistas activos',
        piezasEnviadas: 'piezas incluidas',
        sinPiezasNuevas: 'sin piezas nuevas para enviar',
        seccionDerivados: 'Derivados sociales pendientes de revisión',
        sinDerivados: 'todavía no hay derivados pendientes',
        extractoSocial: 'Extracto social',
        titularDiscover: 'Titular Discover',
        aprobar: 'Aprobar',
        descartar: 'Descartar',
    };
}

function stubFetch(periodistas: unknown[], derivados: unknown[]) {
    const fetchSimulado = vi.fn().mockImplementation((url: string) => {
        if (url.includes('/periodistas')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(periodistas) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve(derivados) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('PantallaDistribucion', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('muestra los periodistas activos con el botón de enviar boletín', async () => {
        stubFetch([{ id: 7, nombre: 'Valentina Ruiz', estado: 'activo' }], []);

        render(<PantallaDistribucion restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('Valentina Ruiz')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Enviar boletín' })).toBeInTheDocument();
    });

    it('oculta a los periodistas jubilados', async () => {
        stubFetch([{ id: 7, nombre: 'Jubilado Ejemplo', estado: 'jubilado' }], []);

        render(<PantallaDistribucion restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await screen.findByText('todavía no hay periodistas activos');
        expect(screen.queryByText('Jubilado Ejemplo')).not.toBeInTheDocument();
    });

    it('envía el boletín contra la ruta REST correcta y muestra el resultado', async () => {
        const fetchSimulado = stubFetch([{ id: 7, nombre: 'Valentina Ruiz', estado: 'activo' }], []);
        fetchSimulado.mockImplementation((url: string) => {
            if (url.includes('/boletines/7/enviar')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve({ piezas: 3, email: 2, push: 1 }) });
            }
            if (url.includes('/periodistas')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 7, nombre: 'Valentina Ruiz', estado: 'activo' }]) });
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve([]) });
        });

        render(<PantallaDistribucion restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Enviar boletín' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/boletines/7/enviar',
                expect.objectContaining({ method: 'POST', headers: { 'X-WP-Nonce': 'nonce-x' } })
            )
        );
        expect(await screen.findByText('piezas incluidas: 3')).toBeInTheDocument();
    });

    it('muestra los derivados sociales pendientes y los aprueba', async () => {
        const fetchSimulado = stubFetch(
            [],
            [{ id: 5, piezaId: 1, extractoSocial: 'Extracto de prueba', titularDiscover: 'Titular de prueba', estado: 'pendiente', creadoEn: '2026-07-22T12:00:00+00:00' }]
        );

        render(<PantallaDistribucion restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('Extracto de prueba')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Aprobar' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/derivados-sociales/5/aprobar',
                expect.objectContaining({ method: 'POST', headers: { 'X-WP-Nonce': 'nonce-x' } })
            )
        );
    });

    it('muestra el mensaje vacío cuando no hay derivados pendientes', async () => {
        stubFetch([], []);

        render(<PantallaDistribucion restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('todavía no hay derivados pendientes')).toBeInTheDocument();
    });

    it('muestra el error de carga si la petición inicial falla', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, json: () => Promise.resolve({}) })
        );

        render(<PantallaDistribucion restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('No se pudo cargar Distribución.')).toBeInTheDocument();
    });
});
