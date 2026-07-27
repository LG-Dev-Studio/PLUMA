import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { BarraEstado } from './BarraEstado';
import type { DatosPortada, TextosPortada } from './PantallaPortada';

function textosDeEjemplo(): TextosPortada {
    return {
        titulo: 'Portada',
        navPortada: 'Portada',
        navSalud: 'Sala de Máquinas',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar la Portada.',
        modo: { piloto: 'Piloto', copiloto: 'Copiloto', autonomo: 'Autónomo' },
        cuota: {
            titulo: 'Cuota de hoy',
            publicadas: 'publicadas',
            programadas: 'programadas',
            objetivo: 'objetivo',
            proximaPublicacion: 'Próxima publicación',
            sinProximo: 'sin ranuras programadas pendientes',
            deficit: 'Déficit de cuota',
        },
        salud: {
            titulo: 'Salud del motor',
            ultimaEjecucion: 'Última ejecución',
            nunca: 'el motor no se ha ejecutado todavía',
            gastoHoy: 'Gasto de hoy',
            deLimite: 'de',
            errores: 'con errores en la última ejecución',
        },
        pipeline: { titulo: 'Piezas en el pipeline', estados: {} },
        alertas: {
            titulo: 'Alertas',
            retenidas: 'Retenidas esperando decisión',
            fallidas: 'Fallidas',
            sinPeriodistaIdoneo: 'Sin periodista idóneo',
            sinRetenidas: 'ninguna pieza retenida',
            sinFallidas: 'ninguna pieza fallida',
            sinPeriodistaIdoneoVacio: 'ninguna pieza sin periodista idóneo',
        },
        tendencias: { titulo: 'Tendencias calientes ahora', vacio: 'todavía no se ha detectado ninguna tendencia' },
        borradoresRespuestaPendientes: 'Borradores de respuesta esperando aprobación',
    };
}

function portadaDeEjemplo(sobrescribir: Partial<DatosPortada> = {}): DatosPortada {
    return {
        modoOperacion: 'copiloto',
        cuota: {
            objetivo: 6,
            minima: 3,
            maxima: 8,
            publicadasHoy: 2,
            programadasHoy: 1,
            proximaPublicacion: '2026-07-23T19:00:00+00:00',
            deficit: false,
        },
        salud: {
            ultimaEjecucion: { iniciadaEn: '2026-07-23T08:00:00+00:00', finalizadaEn: '2026-07-23T08:01:00+00:00', lotesProcesados: 3, errores: [] },
            gastoHoyUsd: 1.234,
            limiteDiarioUsd: 5,
        },
        piezasPorEstado: {},
        alertas: { retenidas: [], fallidas: [], sinPeriodistaIdoneo: [] },
        tendenciasCalientes: [],
        borradoresRespuestaPendientes: 0,
        ...sobrescribir,
    };
}

describe('BarraEstado', () => {
    it('muestra un estado de carga mientras la Portada no ha llegado', () => {
        render(<BarraEstado portada={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText('Cargando…')).toBeInTheDocument();
    });

    it('muestra el modo activo, la cuota de hoy y el coste contra el límite', () => {
        render(<BarraEstado portada={portadaDeEjemplo()} textos={textosDeEjemplo()} />);

        expect(screen.getByText('Copiloto')).toBeInTheDocument();
        expect(screen.getByText(/3\/6 publicadas/)).toBeInTheDocument();
        expect(screen.getByText(/\$1\.23 de \$5\.00/)).toBeInTheDocument();
    });

    it('marca la salud en alerta cuando la última ejecución tuvo errores', () => {
        const portada = portadaDeEjemplo({
            salud: {
                ultimaEjecucion: { iniciadaEn: '2026-07-23T08:00:00+00:00', finalizadaEn: null, lotesProcesados: 0, errores: ['fallo de proveedor'] },
                gastoHoyUsd: 0,
                limiteDiarioUsd: 5,
            },
        });

        render(<BarraEstado portada={portada} textos={textosDeEjemplo()} />);

        const salud = screen.getByText(/de \$5\.00/);
        expect(salud).toHaveAttribute('data-estado', 'alerta');
    });

    it('indica cuando no hay ninguna próxima publicación programada', () => {
        const portada = portadaDeEjemplo({
            cuota: { objetivo: 6, minima: 3, maxima: 8, publicadasHoy: 0, programadasHoy: 0, proximaPublicacion: null, deficit: false },
        });

        render(<BarraEstado portada={portada} textos={textosDeEjemplo()} />);

        expect(screen.getByText(/sin ranuras programadas pendientes/)).toBeInTheDocument();
    });
});
