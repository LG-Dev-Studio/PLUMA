import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { EstudioDeConducta } from './EstudioDeConducta';
import type { DetallePeriodista, TextosBancoPeriodistas } from './PantallaBancoPeriodistas';

function textosDeEjemplo(): TextosBancoPeriodistas {
    return {
        titulo: 'Banco de Periodistas',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar el Banco de Periodistas.',
        errorAccion: 'La acción no se pudo completar.',
        sinPeriodistas: 'todavía no hay ningún periodista en el banco',
        piezasPublicadas: 'piezas publicadas',
        verticalesTop: 'Verticales donde más publica',
        sinVerticales: 'sin piezas publicadas todavía',
        estadoActivo: 'Activo',
        estadoJubilado: 'Jubilado',
        estadoPropuesto: 'Propuesto',
        ventanaVetoRestante: 'Se activa solo en',
        aprobarAhora: 'Aprobar ahora',
        descartarPropuesta: 'Descartar',
        confirmarDescartarPropuesta: '¿Descartar esta propuesta?',
        crearDesdePlantilla: 'Crear desde plantilla',
        crearPersonalizado: 'Crear personalizado',
        elegirPlantilla: 'Elegir plantilla',
        nombreOpcional: 'Nombre (opcional)',
        crear: 'Crear',
        cancelar: 'Cancelar',
        jubilar: 'Jubilar',
        confirmarJubilar: '¿Jubilar a este periodista?',
        cerrar: 'Cerrar',
        estudioDeConducta: 'Estudio de Conducta',
        identidad: 'Identidad',
        nombre: 'Nombre',
        biografia: 'Biografía',
        avatarUrl: 'URL del avatar (opcional)',
        rol: {
            titulo: 'Rol',
            analista: 'Analista',
            columnista: 'Columnista',
            cronista: 'Cronista',
            satirico: 'Satírico',
        },
        especialidades: {
            titulo: 'Especialidades',
            cubreTodosLosTemas: 'Cubre todos los temas',
            nivelDominioComodin: 'Nivel de dominio general',
            vertical: 'Vertical',
            nivelDominio: 'Nivel de dominio (1-5)',
            anadir: 'Añadir especialidad',
            eliminar: 'Eliminar',
            sinEspecialidades: 'Declara al menos una especialidad.',
        },
        guardarIdentidad: 'Guardar identidad',
        errorIdentidad: 'No se pudo guardar la identidad.',
        diales: {
            titulo: 'Diales de temperamento',
            agudezaCritica: 'Agudeza crítica',
            humor: 'Humor',
            satira: 'Sátira',
            formalidad: 'Formalidad',
            vehemencia: 'Vehemencia',
            empatia: 'Empatía',
            densidadDatos: 'Densidad de datos',
            longitudPreferida: 'Longitud preferida',
        },
        reglas: {
            titulo: 'Reglas de conducta',
            lineaEditorial: 'Línea editorial',
            lineasRojas: 'Líneas rojas',
            muletillas: 'Muletillas',
            vocabularioProhibido: 'Vocabulario prohibido',
            tratamientoLector: 'Trato al lector',
            tratamientoTu: 'De tú',
            tratamientoUsted: 'De usted',
            estiloPreguntaFinal: 'Estilo de pregunta final',
            agregar: 'Agregar',
        },
        matriz: {
            titulo: 'Matriz de tonos',
            tipoNoticia: {
                anuncio_corporativo: 'Anuncio corporativo',
                escandalo_politico: 'Escándalo político',
                tragedia: 'Tragedia',
                cultura_viral: 'Cultura viral',
                dato_economico: 'Dato económico',
            },
            tonoDominante: 'Tono dominante',
            tonoApoyo: 'Tono de apoyo',
            nivelSatira: 'Sátira permitida',
            tono: {
                analitico: 'Analítico',
                critico: 'Crítico',
                informativo_empatico: 'Informativo empático',
                humoristico: 'Humorístico',
                opinion: 'Opinión',
                persuasivo: 'Persuasivo',
            },
            satira: {
                bloqueada: 'Bloqueada',
                no: 'No',
                con_moderacion: 'Con moderación',
                en_remate: 'Solo en el remate',
                pieza_completa: 'Pieza completa',
            },
            filaSistema: 'Regla de sistema, no editable.',
        },
        memoria: {
            titulo: 'Memoria editorial reciente',
            vacia: 'sin memoria registrada todavía',
            tipo: { postura: 'Postura', cobertura: 'Cobertura', audiencia: 'Audiencia' },
        },
        vistaPrevia: {
            titulo: 'Vista previa en vivo',
            generando: 'Redactando con esta conducta…',
            errorPresupuesto: 'Presupuesto diario agotado.',
            errorGeneral: 'No se pudo generar la vista previa.',
        },
        guardarCambios: 'Guardar cambios',
        clonar: 'Clonar',
        nombreDelClon: 'Nombre del nuevo periodista clonado',
    };
}

function detalleDeEjemplo(sobrescribir: Partial<DetallePeriodista> = {}): DetallePeriodista {
    return {
        id: 7,
        nombre: 'Valentina Ruiz',
        avatarUrl: null,
        biografia: 'Economista de formación.',
        rol: 'columnista',
        especialidades: [{ vertical: 'economia', nivelDominio: 5 }],
        estado: 'activo',
        diales: {
            agudezaCritica: 80,
            humor: 55,
            satira: 40,
            formalidad: 55,
            vehemencia: 75,
            empatia: 60,
            densidadDatos: 60,
            longitudPreferida: 65,
        },
        reglasConducta: {
            lineaEditorial: 'Escéptica del poder.',
            lineasRojas: ['menores de edad'],
            muletillas: ['abre con una pregunta retórica'],
            vocabularioProhibido: ['sin duda'],
            tratamientoLector: 'tu',
            estiloPreguntaFinal: '¿A quién le crees aquí?',
        },
        matrizTonos: {
            tragedia: { tipoNoticia: 'tragedia', tonoDominante: 'informativo_empatico', tonoApoyo: 'analitico', nivelSatira: 'bloqueada' },
            anuncio_corporativo: { tipoNoticia: 'anuncio_corporativo', tonoDominante: 'analitico', tonoApoyo: 'critico', nivelSatira: 'en_remate' },
            escandalo_politico: { tipoNoticia: 'escandalo_politico', tonoDominante: 'critico', tonoApoyo: 'analitico', nivelSatira: 'con_moderacion' },
            cultura_viral: { tipoNoticia: 'cultura_viral', tonoDominante: 'humoristico', tonoApoyo: 'opinion', nivelSatira: 'pieza_completa' },
            dato_economico: { tipoNoticia: 'dato_economico', tonoDominante: 'analitico', tonoApoyo: 'persuasivo', nivelSatira: 'no' },
        },
        metricas: { piezasPublicadas: 12, verticalesTop: ['economia'] },
        memoriaReciente: [],
        ventanaVetoExpiraEn: null,
        ...sobrescribir,
    };
}

function stubFetchDetalle(detalle: DetallePeriodista, respuestaVistaPrevia: { ok: boolean; status?: number; body?: unknown } = { ok: true, body: { texto: 'Un párrafo de muestra.' } }) {
    const fetchSimulado = vi.fn((url: string, opciones?: RequestInit) => {
        if (url.endsWith(`/periodistas/${detalle.id}`) && (!opciones || 'POST' !== opciones.method)) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(detalle) });
        }
        if (url.endsWith('/periodistas/vista-previa')) {
            return Promise.resolve({
                ok: respuestaVistaPrevia.ok,
                status: respuestaVistaPrevia.status ?? 200,
                json: () => Promise.resolve(respuestaVistaPrevia.body ?? {}),
            });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('EstudioDeConducta', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('carga los diales, la matriz y marca la fila de Tragedia como regla de sistema', async () => {
        stubFetchDetalle(detalleDeEjemplo());

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={() => {}} />);

        expect(await screen.findByText(/Agudeza crítica — 80/)).toBeInTheDocument();
        expect(screen.getByText('Regla de sistema, no editable.')).toBeInTheDocument();
        expect(screen.getByText('Dato económico')).toBeInTheDocument();
    });

    it('dispara la vista previa con debounce tras mover un dial, sin repetir por el mismo valor', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const fetchSimulado = stubFetchDetalle(detalleDeEjemplo());

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={() => {}} />);

        await vi.waitFor(() => expect(screen.getByText(/Agudeza crítica — 80/)).toBeInTheDocument());

        // La carga inicial ya dispara una vista previa (combinación nunca pedida antes).
        await vi.advanceTimersByTimeAsync(900);
        expect(fetchSimulado).toHaveBeenCalledWith(
            'https://ejemplo.test/wp-json/pluma/v1/periodistas/vista-previa',
            expect.objectContaining({ method: 'POST' })
        );

        const llamadasVistaPrevia = fetchSimulado.mock.calls.filter(([url]) => String(url).includes('vista-previa')).length;

        // Re-renderizar sin cambios no debe disparar una segunda llamada idéntica.
        await vi.advanceTimersByTimeAsync(900);
        expect(fetchSimulado.mock.calls.filter(([url]) => String(url).includes('vista-previa')).length).toBe(llamadasVistaPrevia);
    });

    it('muestra el aviso de presupuesto agotado cuando la vista previa devuelve 409', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        stubFetchDetalle(detalleDeEjemplo(), { ok: false, status: 409, body: { code: 'pluma_vista_previa_no_disponible' } });

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={() => {}} />);

        await vi.waitFor(() => expect(screen.getByText(/Agudeza crítica — 80/)).toBeInTheDocument());
        await vi.advanceTimersByTimeAsync(900);

        await vi.waitFor(() => expect(screen.getByText('Presupuesto diario agotado.')).toBeInTheDocument());
    });

    it('guarda cambios de conducta contra el endpoint correcto', async () => {
        const fetchSimulado = stubFetchDetalle(detalleDeEjemplo());
        const onCambio = vi.fn();

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={onCambio} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Guardar cambios' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas/7/conducta',
                expect.objectContaining({ method: 'POST' })
            )
        );
        await waitFor(() => expect(onCambio).toHaveBeenCalled());
    });

    it('carga los valores actuales de Identidad, incluido el comodín cuando aplica', async () => {
        stubFetchDetalle(detalleDeEjemplo({ especialidades: [{ vertical: '*', nivelDominio: 4 }] }));

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={() => {}} />);

        expect(await screen.findByDisplayValue('Valentina Ruiz')).toBeInTheDocument();
        expect(screen.getByDisplayValue('Economista de formación.')).toBeInTheDocument();
        expect(screen.getByLabelText('Cubre todos los temas')).toBeChecked();
        expect(screen.getByLabelText('Nivel de dominio general')).toHaveValue(4);
    });

    it('guarda Identidad contra el endpoint PUT correcto, sin mezclarse con el guardado de Conducta', async () => {
        const detalle = detalleDeEjemplo();
        const fetchSimulado = vi.fn((url: string, opciones?: RequestInit) => {
            if (url.endsWith(`/periodistas/${detalle.id}`) && (!opciones || undefined === opciones.method)) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve(detalle) });
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
        });
        vi.stubGlobal('fetch', fetchSimulado);
        const onCambio = vi.fn();

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={onCambio} />);

        await screen.findByDisplayValue('Valentina Ruiz');
        await userEvent.click(screen.getByRole('button', { name: 'Guardar identidad' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas/7',
                expect.objectContaining({ method: 'PUT' })
            )
        );
        expect(fetchSimulado.mock.calls.some(([url, opciones]) => String(url).includes('/conducta') && opciones)).toBe(false);
        await waitFor(() => expect(onCambio).toHaveBeenCalled());
    });

    it('muestra el error de identidad cuando el guardado de Identidad falla', async () => {
        const detalle = detalleDeEjemplo();
        const fetchSimulado = vi.fn((url: string, opciones?: RequestInit) => {
            if (url.endsWith(`/periodistas/${detalle.id}`) && (!opciones || undefined === opciones.method)) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve(detalle) });
            }
            if (url.endsWith(`/periodistas/${detalle.id}`) && 'PUT' === opciones?.method) {
                return Promise.resolve({ ok: false, json: () => Promise.resolve({}) });
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
        });
        vi.stubGlobal('fetch', fetchSimulado);

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={() => {}} />);

        await screen.findByDisplayValue('Valentina Ruiz');
        await userEvent.click(screen.getByRole('button', { name: 'Guardar identidad' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo guardar la identidad.');
    });

    it('clona pidiendo un nombre nuevo', async () => {
        const fetchSimulado = stubFetchDetalle(detalleDeEjemplo());
        vi.spyOn(window, 'prompt').mockReturnValue('Valentina II');

        render(<EstudioDeConducta restUrl="https://ejemplo.test/wp-json/" nonce="n" periodistaId={7} textos={textosDeEjemplo()} onCerrar={() => {}} onCambio={() => {}} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Clonar' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas/7/clonar',
                expect.objectContaining({ method: 'POST', body: JSON.stringify({ nombre: 'Valentina II' }) })
            )
        );
    });
});
