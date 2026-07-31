import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PantallaPortada, type DatosPortada, type TextosPortada } from './PantallaPortada';

function textosDeEjemplo(): TextosPortada {
    return {
        titulo: 'Portada',
        navPortada: 'Portada',
        navSalud: 'Sala de Máquinas',
        cronNoConfigurado: 'El motor lleva horas sin ejecutarse.',
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
            deficit: 'Déficit de cuota: por debajo del mínimo configurado',
        },
        salud: {
            titulo: 'Salud del motor',
            ultimaEjecucion: 'Última ejecución',
            nunca: 'el motor no se ha ejecutado todavía',
            gastoHoy: 'Gasto de hoy',
            deLimite: 'de',
            errores: 'con errores en la última ejecución',
        },
        pipeline: {
            titulo: 'Piezas en el pipeline',
            estados: {
                detectada: 'Detectada',
                en_investigacion: 'En investigación',
                investigada: 'Investigada',
                en_redaccion: 'En redacción',
                redactada: 'Redactada',
                optimizada: 'Optimizada',
                en_revision: 'En revisión',
                aprobada: 'Aprobada',
                programada: 'Programada',
                publicada: 'Publicada',
                retenida: 'Retenida',
                descartada: 'Descartada',
                fallida: 'Fallida',
                sin_periodista_idoneo: 'Sin periodista idóneo',
            },
        },
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
    };
}

function portadaDeEjemplo(sobrescribir: Partial<DatosPortada> = {}): DatosPortada {
    return {
        modoOperacion: 'copiloto',
        cuota: { objetivo: 6, minima: 3, maxima: 8, publicadasHoy: 2, programadasHoy: 1, proximaPublicacion: null, deficit: false },
        salud: { ultimaEjecucion: null, gastoHoyUsd: 0, limiteDiarioUsd: 5 },
        piezasPorEstado: {
            detectada: 3,
            en_investigacion: 0,
            investigada: 0,
            en_redaccion: 0,
            redactada: 0,
            optimizada: 1,
            en_revision: 0,
            aprobada: 0,
            programada: 1,
            publicada: 12,
            retenida: 0,
            descartada: 0,
            fallida: 0,
            sin_periodista_idoneo: 0,
        },
        alertas: { retenidas: [], fallidas: [], sinPeriodistaIdoneo: [] },
        tendenciasCalientes: [],
        ...sobrescribir,
    };
}

describe('PantallaPortada', () => {
    /**
     * Descubierto depurando un caso real: sin cron el motor no se ejecuta y
     * las piezas se quedan quietas sin que el editor sepa por qué. El aviso
     * se basa en la EVIDENCIA (última ejecución real) y no en
     * `DISABLE_WP_CRON`, que estaba desactivado sin haber ningún cron real
     * detrás — la bandera reportaba que todo iba bien mientras nada avanzaba.
     */
    it('avisa cuando el motor no se ha ejecutado nunca', () => {
        render(<PantallaPortada datos={portadaDeEjemplo()} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText(/El motor lleva horas sin ejecutarse/)).toBeInTheDocument();
    });

    it('avisa cuando la última ejecución del motor es demasiado antigua', () => {
        const hace5Horas = new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString();
        const datos = portadaDeEjemplo({
            salud: { ultimaEjecucion: { iniciadaEn: hace5Horas, finalizadaEn: hace5Horas, lotesProcesados: 3, errores: [] }, gastoHoyUsd: 0, limiteDiarioUsd: 5 },
        });

        render(<PantallaPortada datos={datos} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText(/El motor lleva horas sin ejecutarse/)).toBeInTheDocument();
    });

    it('no avisa si el motor se ejecutó hace un momento', () => {
        const haceUnMinuto = new Date(Date.now() - 60 * 1000).toISOString();
        const datos = portadaDeEjemplo({
            salud: { ultimaEjecucion: { iniciadaEn: haceUnMinuto, finalizadaEn: haceUnMinuto, lotesProcesados: 3, errores: [] }, gastoHoyUsd: 0, limiteDiarioUsd: 5 },
        });

        render(<PantallaPortada datos={datos} error={null} textos={textosDeEjemplo()} />);

        expect(screen.queryByText(/El motor lleva horas sin ejecutarse/)).not.toBeInTheDocument();
    });

    it('muestra el mensaje de error cuando la carga falló', () => {
        render(<PantallaPortada datos={null} error="No se pudo cargar la Portada." textos={textosDeEjemplo()} />);

        expect(screen.getByRole('alert')).toHaveTextContent('No se pudo cargar la Portada.');
    });

    it('muestra el estado de carga mientras no hay datos ni error', () => {
        render(<PantallaPortada datos={null} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText('Cargando…')).toBeInTheDocument();
    });

    it('muestra el conteo real de piezas por estado en el kanban compacto', () => {
        render(<PantallaPortada datos={portadaDeEjemplo()} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText('Detectada').nextElementSibling).toHaveTextContent('3');
        expect(screen.getByText('Publicada').nextElementSibling).toHaveTextContent('12');
        expect(screen.getByText('Retenida').nextElementSibling).toHaveTextContent('0');
    });

    it('avisa cuando la cuota está en déficit', () => {
        render(<PantallaPortada datos={portadaDeEjemplo({ cuota: { objetivo: 6, minima: 3, maxima: 8, publicadasHoy: 0, programadasHoy: 1, proximaPublicacion: null, deficit: true } })} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText('Déficit de cuota: por debajo del mínimo configurado')).toBeInTheDocument();
    });

    it('no muestra ningún aviso de déficit cuando la cuota está sana', () => {
        render(<PantallaPortada datos={portadaDeEjemplo()} error={null} textos={textosDeEjemplo()} />);

        expect(screen.queryByText('Déficit de cuota: por debajo del mínimo configurado')).not.toBeInTheDocument();
    });

    it('lista las piezas retenidas con sus motivos', () => {
        const datos = portadaDeEjemplo({
            alertas: {
                retenidas: [{ id: 42, tendenciaId: 1, actualizadaEn: '2026-07-23T08:00:00+00:00', motivos: ['riesgo de difamación'] }],
                fallidas: [],
                sinPeriodistaIdoneo: [],
            },
        });

        render(<PantallaPortada datos={datos} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText(/#42 — riesgo de difamación/)).toBeInTheDocument();
        expect(screen.getByText('ninguna pieza fallida')).toBeInTheDocument();
    });

    it('lista las piezas sin periodista idóneo con su motivo', () => {
        const datos = portadaDeEjemplo({
            alertas: {
                retenidas: [],
                fallidas: [],
                sinPeriodistaIdoneo: [{ id: 7, tendenciaId: 3, actualizadaEn: '2026-07-27T08:00:00+00:00', motivos: ['ningún periodista supera el umbral de dominio'] }],
            },
        });

        render(<PantallaPortada datos={datos} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText(/#7 — ningún periodista supera el umbral de dominio/)).toBeInTheDocument();
    });

    it('muestra las tendencias calientes ordenadas tal como llegan', () => {
        const datos = portadaDeEjemplo({
            tendenciasCalientes: [{ id: 1, termino: 'elecciones 2026', puntuacionTotal: 87, detectadaEn: '2026-07-23T08:00:00+00:00' }],
        });

        render(<PantallaPortada datos={datos} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText('elecciones 2026')).toBeInTheDocument();
        expect(screen.getByText('87')).toBeInTheDocument();
    });

    it('muestra el mensaje vacío cuando no hay tendencias detectadas', () => {
        render(<PantallaPortada datos={portadaDeEjemplo()} error={null} textos={textosDeEjemplo()} />);

        expect(screen.getByText('todavía no se ha detectado ninguna tendencia')).toBeInTheDocument();
    });

});
