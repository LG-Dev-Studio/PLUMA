import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BloqueLlamadasModelo, type TextosLlamadasModelo } from './BloqueLlamadasModelo';

function textos(): TextosLlamadasModelo {
    return {
        titulo: 'Llamadas al modelo de IA',
        explicacion: 'Gasto real de los últimos 30 días.',
        vacio: 'sin llamadas registradas en los últimos 30 días',
        errorCarga: 'No se pudo cargar el resumen de llamadas al modelo.',
        proposito: 'Propósito',
        origen: 'Origen',
        resultado: 'Resultado',
        llamadas: 'Llamadas',
        costeUsd: 'Coste (USD)',
        origenCron: 'Cron',
        origenPanel: 'Panel',
        origenVisitante: 'Visitante',
    };
}

function stubFetch(respuesta: unknown, ok = true) {
    const fetchSimulado = vi.fn(() => Promise.resolve({ ok, json: () => Promise.resolve(respuesta) }));
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('BloqueLlamadasModelo', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('pide el resumen al endpoint correcto con el nonce', async () => {
        const fetchSimulado = stubFetch([]);

        render(<BloqueLlamadasModelo restUrl="https://x.test/wp-json/" nonce="nonce-x" textos={textos()} />);

        await screen.findByText('sin llamadas registradas en los últimos 30 días');

        expect(fetchSimulado).toHaveBeenCalledWith(
            'https://x.test/wp-json/pluma/v1/motor/llamadas-modelo',
            expect.objectContaining({ headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }) })
        );
    });

    it('muestra el mensaje vacío cuando no hay filas', async () => {
        stubFetch([]);

        render(<BloqueLlamadasModelo restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        expect(await screen.findByText('sin llamadas registradas en los últimos 30 días')).toBeInTheDocument();
    });

    it('renderiza cada fila con su propósito, origen traducido, resultado y coste', async () => {
        stubFetch([
            { proposito: 'redactar', origen: 'cron', resultado: 'ok', llamadas: 12, costeUsd: 0.4521, tokensEntrada: 100, tokensSalida: 50 },
            { proposito: 'clasificar', origen: 'visitante', resultado: 'presupuesto_agotado', llamadas: 3, costeUsd: 0, tokensEntrada: 0, tokensSalida: 0 },
        ]);

        render(<BloqueLlamadasModelo restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        const filaCron = await screen.findByText('redactar');
        expect(filaCron.closest('tr')).toHaveTextContent('Cron');
        expect(filaCron.closest('tr')).toHaveTextContent('12');
        expect(filaCron.closest('tr')).toHaveTextContent('$0.4521');

        // Evidencia en pantalla del hallazgo 3 (`ADR 0010`): una fila real
        // con origen "visitante" debe ser visible, no solo existir en la BD.
        const filaVisitante = screen.getByText('clasificar');
        expect(filaVisitante.closest('tr')).toHaveTextContent('Visitante');
        expect(filaVisitante.closest('tr')).toHaveAttribute('data-origen', 'visitante');
    });

    it('muestra el error si la carga falla', async () => {
        stubFetch(null, false);

        render(<BloqueLlamadasModelo restUrl="https://x.test/wp-json/" nonce="n" textos={textos()} />);

        expect(await screen.findByText('No se pudo cargar el resumen de llamadas al modelo.')).toBeInTheDocument();
    });
});
