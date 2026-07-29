import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PantallaSalaRevision, type EntradaVeto, type PiezaRevision, type TextosSalaRevision } from './PantallaSalaRevision';

function textosDeEjemplo(): TextosSalaRevision {
    return {
        titulo: 'Sala de Revisión',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar la Sala de Revisión.',
        errorAccion: 'La acción no se pudo completar.',
        retenidas: 'Retenidas esperando decisión',
        sinRetenidas: 'ninguna pieza retenida',
        sinPeriodistaIdoneo: 'Sin periodista idóneo',
        sinPeriodistaIdoneoVacio: 'ninguna pieza esperando un periodista',
        sinPeriodistaIdoneoExplicacion: 'Crea un periodista que cubra ese vertical y pulsa Reanudar.',
        reanudar: 'Reanudar',
        colaDeVeto: 'Cola de veto (modo Copiloto)',
        sinColaDeVeto: 'ninguna pieza esperando la ventana de veto',
        diagnostico: 'Diagnóstico',
        sinDiagnostico: 'sin diagnóstico de compuertas todavía',
        calidad: 'Calidad',
        riesgo: 'Riesgo',
        originalidad: 'Originalidad',
        sinDetalle: 'sin motivos registrados',
        lectura: 'Leer la pieza',
        sinContenido: 'sin borrador todavía',
        aprobar: 'Aprobar',
        devolver: 'Devolver con nota',
        notaOpcional: 'Nota (opcional)',
        descartar: 'Descartar',
        vetar: 'Vetar (descartar antes de publicar)',
        aprobarAhora: 'Aprobar ahora (publicar sin esperar)',
        tiempoRestante: 'Tiempo restante para vetar',
        tiempoAgotado: 'La ventana de veto ya expiró.',
        confirmarDescartar: '¿Descartar esta Pieza?',
    };
}

function piezaDeEjemplo(sobrescribir: Partial<PiezaRevision> = {}): PiezaRevision {
    return {
        id: 42,
        tendenciaId: 1,
        tendenciaTermino: 'elecciones 2026',
        periodista: { id: 1, nombre: 'Ana Reyes' },
        actualizadaEn: '2026-07-23T08:00:00+00:00',
        motivos: ['riesgo de difamación'],
        modoEfectivo: 'copiloto',
        resultadoCompuertas: {
            aprobada: false,
            retenida: true,
            motivos: ['riesgo de difamación'],
            modoEfectivo: 'copiloto',
            calidad: { puntuacionTotal: 80, umbral: 70, sustentoAprobado: true, detalle: [] },
            riesgo: { detalleDifamacion: 'afirmación sin doble fuente', hechosDisputadosSinSenalar: false, temaRegulado: null },
            originalidad: { ratioGananciaInformacion: 0.8, umbralGananciaMinima: 0.4 },
        },
        contenido: '<p>Texto del borrador.</p>',
        ...sobrescribir,
    };
}

function entradaVetoDeEjemplo(sobrescribir: Partial<EntradaVeto> = {}): EntradaVeto {
    return {
        ...piezaDeEjemplo({ id: 7 }),
        horaProgramada: '2026-07-23T09:00:00+00:00',
        horaLimiteVeto: new Date(Date.now() + 3 * 60 * 60 * 1000).toISOString(),
        ...sobrescribir,
    };
}

function stubFetch(retenidas: PiezaRevision[], veto: EntradaVeto[], sinPeriodista: PiezaRevision[] = []) {
    const fetchSimulado = vi.fn((url: string) => {
        if (url.endsWith('/revision/retenidas')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(retenidas) });
        }
        if (url.endsWith('/revision/veto')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(veto) });
        }
        if (url.endsWith('/revision/sin-periodista-idoneo')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(sinPeriodista) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('PantallaSalaRevision', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    /**
     * Nivel Dos C.3 prometía en su docblock que una Pieza sin periodista
     * idóneo era "reanudable tras ajuste del banco por el editor", pero
     * ninguna pantalla ni endpoint lo implementaba: 12 Piezas reales solo
     * podían descartarse. La reanudación va a INVESTIGADA, que el motor sí
     * sondea (EN_REDACCION es transitorio y volvería a vararlas).
     */
    it('reanuda una pieza sin periodista idóneo contra el endpoint correcto', async () => {
        const fetchSimulado = stubFetch([], [], [piezaDeEjemplo({ id: 77 })]);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Reanudar' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/revision/77/reanudar',
                expect.objectContaining({ method: 'POST' })
            )
        );
    });

    it('avisa cuando no hay ninguna pieza esperando periodista', async () => {
        stubFetch([], [], []);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('ninguna pieza esperando un periodista')).toBeInTheDocument();
    });

    it('muestra las piezas retenidas con su diagnóstico', async () => {
        stubFetch([piezaDeEjemplo()], []);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('elecciones 2026')).toBeInTheDocument();
        expect(screen.getByText('riesgo de difamación')).toBeInTheDocument();
        expect(screen.getByText('ninguna pieza esperando la ventana de veto')).toBeInTheDocument();
    });

    it('muestra la cola de veto con la cuenta regresiva', async () => {
        stubFetch([], [entradaVetoDeEjemplo()]);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText(/Tiempo restante para vetar/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Vetar (descartar antes de publicar)' })).toBeInTheDocument();
    });

    it('aprueba una pieza retenida contra el endpoint correcto', async () => {
        const fetchSimulado = stubFetch([piezaDeEjemplo()], []);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Aprobar' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/revision/42/aprobar',
                expect.objectContaining({ method: 'POST', headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }) })
            )
        );
    });

    it('devuelve con la nota escrita', async () => {
        const fetchSimulado = stubFetch([piezaDeEjemplo()], []);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        const campoNota = await screen.findByPlaceholderText('Nota (opcional)');
        await userEvent.type(campoNota, 'falta doble fuente');
        await userEvent.click(screen.getByRole('button', { name: 'Devolver con nota' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/revision/42/devolver',
                expect.objectContaining({ method: 'POST', body: JSON.stringify({ nota: 'falta doble fuente' }) })
            )
        );
    });

    it('pide confirmación antes de descartar', async () => {
        stubFetch([piezaDeEjemplo()], []);
        const confirmSimulado = vi.spyOn(window, 'confirm').mockReturnValue(false);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Descartar' }));

        expect(confirmSimulado).toHaveBeenCalledWith('¿Descartar esta Pieza?');
        confirmSimulado.mockRestore();
    });

    it('aprueba ahora una pieza de la cola de veto contra el endpoint correcto', async () => {
        const fetchSimulado = stubFetch([], [entradaVetoDeEjemplo()]);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Aprobar ahora (publicar sin esperar)' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/revision/7/aprobar-ahora',
                expect.objectContaining({ method: 'POST', headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }) })
            )
        );
    });

    it('vetar llama al mismo endpoint de descartar', async () => {
        const fetchSimulado = stubFetch([], [entradaVetoDeEjemplo()]);
        vi.spyOn(window, 'confirm').mockReturnValue(true);

        render(<PantallaSalaRevision restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Vetar (descartar antes de publicar)' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/revision/7/descartar',
                expect.objectContaining({ method: 'POST' })
            )
        );
    });
});
