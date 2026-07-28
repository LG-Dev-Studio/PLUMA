import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PantallaCalendarioEditorial, type EventoProgramado, type TextosCalendarioEditorial } from './PantallaCalendarioEditorial';

function textosDeEjemplo(): TextosCalendarioEditorial {
    return {
        titulo: 'Calendario Editorial',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar el Calendario Editorial.',
        errorAccion: 'La acción no se pudo completar.',
        vacio: 'todavía no hay eventos programados',
        nuevoTitulo: 'Título',
        nuevoVertical: 'Vertical',
        nuevaFecha: 'Fecha esperada',
        crear: 'Añadir a la agenda',
        estadoPrevisto: 'Previsto',
        estadoPreparado: 'Preparado',
        estadoEnCurso: 'En curso',
        estadoCubierto: 'Cubierto',
        prepararCobertura: 'Preparar cobertura',
        fuenteTitulo: 'Título del artículo',
        fuenteUrl: 'URL',
        fuenteNombre: 'Fuente',
        anadirFuente: 'Añadir otra fuente',
        confirmarPreparacion: 'Confirmar preparación',
        marcarEnCurso: 'Marcar en curso',
        marcarCubierto: 'Marcar cubierto',
        necesitaFuentes: 'Añade al menos una fuente real antes de preparar la cobertura.',
    };
}

function eventoDeEjemplo(sobrescribir: Partial<EventoProgramado> = {}): EventoProgramado {
    return {
        id: 5,
        titulo: 'Elecciones generales',
        vertical: 'politica',
        fechaEsperada: '2026-11-15T00:00:00+00:00',
        estado: 'previsto',
        periodistaAsignadoId: null,
        historiaId: null,
        tendenciaId: null,
        ...sobrescribir,
    };
}

function stubFetchConEventos(eventos: EventoProgramado[]) {
    const fetchSimulado = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(eventos) });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('PantallaCalendarioEditorial', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('muestra el evento con su vertical y estado', async () => {
        stubFetchConEventos([eventoDeEjemplo()]);

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('Elecciones generales')).toBeInTheDocument();
        expect(screen.getByText('politica')).toBeInTheDocument();
        expect(screen.getByText('Previsto')).toBeInTheDocument();
    });

    it('muestra el mensaje vacío cuando no hay eventos', async () => {
        stubFetchConEventos([]);

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('todavía no hay eventos programados')).toBeInTheDocument();
    });

    it('crea un evento nuevo contra la ruta REST correcta con el nonce', async () => {
        const fetchSimulado = stubFetchConEventos([]);

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await screen.findByText('todavía no hay eventos programados');

        await userEvent.type(screen.getByLabelText('Título'), 'Nuevo evento');
        await userEvent.type(screen.getByLabelText('Vertical'), 'deportes');
        await userEvent.type(screen.getByLabelText('Fecha esperada'), '2026-12-01T10:00');
        await userEvent.click(screen.getByRole('button', { name: 'Añadir a la agenda' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/calendario-editorial',
                expect.objectContaining({
                    method: 'POST',
                    headers: { 'X-WP-Nonce': 'nonce-x', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ titulo: 'Nuevo evento', vertical: 'deportes', fechaEsperada: '2026-12-01T10:00' }),
                })
            )
        );
    });

    it('prepara cobertura con las fuentes reunidas por el editor', async () => {
        const fetchSimulado = stubFetchConEventos([eventoDeEjemplo()]);

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Preparar cobertura' }));

        await userEvent.type(screen.getByPlaceholderText('Título del artículo'), 'Encuestas previas');
        await userEvent.type(screen.getByPlaceholderText('URL'), 'https://example.test/encuestas');
        await userEvent.type(screen.getByPlaceholderText('Fuente'), 'Diario de prueba');
        await userEvent.click(screen.getByRole('button', { name: 'Confirmar preparación' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/calendario-editorial/5/preparar',
                expect.objectContaining({
                    method: 'POST',
                    headers: { 'X-WP-Nonce': 'nonce-x', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        articulosRelacionados: [{ titulo: 'Encuestas previas', url: 'https://example.test/encuestas', fuente: 'Diario de prueba' }],
                    }),
                })
            )
        );
    });

    it('exige al menos una fuente antes de confirmar la preparación', async () => {
        stubFetchConEventos([eventoDeEjemplo()]);

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Preparar cobertura' }));
        await userEvent.click(screen.getByRole('button', { name: 'Confirmar preparación' }));

        expect(await screen.findByText('Añade al menos una fuente real antes de preparar la cobertura.')).toBeInTheDocument();
    });

    it('muestra "Marcar en curso" para un evento preparado y lo dispara', async () => {
        const fetchSimulado = stubFetchConEventos([eventoDeEjemplo({ estado: 'preparado' })]);

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Marcar en curso' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/calendario-editorial/5/marcar-en-curso',
                expect.objectContaining({ method: 'POST', headers: { 'X-WP-Nonce': 'nonce-x' } })
            )
        );
    });

    it('muestra "Marcar cubierto" para un evento en curso y lo dispara', async () => {
        const fetchSimulado = stubFetchConEventos([eventoDeEjemplo({ estado: 'en_curso' })]);

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Marcar cubierto' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/calendario-editorial/5/marcar-cubierto',
                expect.objectContaining({ method: 'POST', headers: { 'X-WP-Nonce': 'nonce-x' } })
            )
        );
    });

    it('muestra el error de carga si la petición inicial falla', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, json: () => Promise.resolve({}) })
        );

        render(<PantallaCalendarioEditorial restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('No se pudo cargar el Calendario Editorial.')).toBeInTheDocument();
    });
});
