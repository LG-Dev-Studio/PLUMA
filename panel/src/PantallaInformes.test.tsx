import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PantallaInformes, type DatosInformeEditorial, type TextosInformes } from './PantallaInformes';

function textosDeEjemplo(): TextosInformes {
    return {
        titulo: 'Informes Editoriales',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar el Informe Editorial.',
        rango: 'Semana',
        piezas: {
            titulo: 'Piezas publicadas',
            publicadas: 'piezas publicadas esta semana',
            porPeriodista: 'Por periodista',
            porVertical: 'Por vertical',
            sinDatos: 'sin datos esta semana',
            retenidas: 'Retenidas esta semana',
            fallidas: 'Fallidas esta semana',
            sinRetenidas: 'ninguna pieza retenida esta semana',
            sinFallidas: 'ninguna pieza fallida esta semana',
        },
        tendencias: {
            titulo: 'Tendencias de la semana',
            enPipeline: 'En el pipeline',
            posibleActualizacion: 'Posibles actualizaciones detectadas',
            ignoradas: 'Ignoradas',
            vigiladas: 'En vigilancia',
            sospechaManipulacion: 'Con sospecha de manipulación',
        },
        motor: {
            titulo: 'Salud del motor esta semana',
            ejecuciones: 'Ejecuciones',
            lotesProcesados: 'Lotes procesados',
            ejecucionesConErrores: 'Ejecuciones con errores',
        },
    };
}

function informeDeEjemplo(sobrescribir: Partial<DatosInformeEditorial> = {}): DatosInformeEditorial {
    return {
        rango: { desde: '2026-07-16T08:00:00+00:00', hasta: '2026-07-23T08:00:00+00:00' },
        piezas: {
            publicadas: 5,
            porPeriodista: [{ periodistaId: 7, nombre: 'Valentina Ruiz', publicadas: 5 }],
            porVertical: [{ vertical: 'economia', publicadas: 5 }],
            retenidas: [],
            fallidas: [],
        },
        tendencias: { enPipeline: 3, posibleActualizacion: 1, ignoradas: 2, vigiladas: 0, sospechaManipulacion: 0 },
        motor: { ejecuciones: 10, lotesProcesados: 20, ejecucionesConErrores: 1 },
        ...sobrescribir,
    };
}

function stubFetchConInforme(informe: DatosInformeEditorial) {
    const fetchSimulado = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve(informe) });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('PantallaInformes', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('muestra las piezas publicadas y su desglose por periodista y vertical', async () => {
        stubFetchConInforme(informeDeEjemplo());

        render(<PantallaInformes restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText(/5 piezas publicadas esta semana/)).toBeInTheDocument();
        expect(screen.getByText('Valentina Ruiz')).toBeInTheDocument();
        expect(screen.getByText('economia')).toBeInTheDocument();
    });

    it('muestra los contadores de tendencias y motor', async () => {
        stubFetchConInforme(informeDeEjemplo());

        render(<PantallaInformes restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await screen.findByText('Informes Editoriales');

        expect(screen.getByText('En el pipeline').nextElementSibling).toHaveTextContent('3');
        expect(screen.getByText('Ejecuciones').nextElementSibling).toHaveTextContent('10');
    });

    it('muestra el mensaje vacío cuando no hay retenidas ni fallidas', async () => {
        stubFetchConInforme(informeDeEjemplo());

        render(<PantallaInformes restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('ninguna pieza retenida esta semana')).toBeInTheDocument();
        expect(screen.getByText('ninguna pieza fallida esta semana')).toBeInTheDocument();
    });

    it('lista las piezas retenidas de la semana con sus motivos', async () => {
        const informe = informeDeEjemplo({
            piezas: {
                ...informeDeEjemplo().piezas,
                retenidas: [{ id: 42, tendenciaId: 1, actualizadaEn: '2026-07-20T08:00:00+00:00', motivos: ['riesgo de difamación'] }],
            },
        });
        stubFetchConInforme(informe);

        render(<PantallaInformes restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText(/#42 — riesgo de difamación/)).toBeInTheDocument();
    });

    it('muestra el error de carga si la petición falla', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({ ok: false, json: () => Promise.resolve({}) })
        );

        render(<PantallaInformes restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar el Informe Editorial.');
    });
});
