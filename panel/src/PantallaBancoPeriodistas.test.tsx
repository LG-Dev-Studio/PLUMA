import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PantallaBancoPeriodistas, type PlantillaResumen, type TarjetaPeriodista, type TextosBancoPeriodistas } from './PantallaBancoPeriodistas';

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

function tarjetaDeEjemplo(sobrescribir: Partial<TarjetaPeriodista> = {}): TarjetaPeriodista {
    return {
        id: 1,
        nombre: 'Valentina Ruiz',
        avatarUrl: null,
        rol: 'columnista',
        especialidades: [{ vertical: 'economia', nivelDominio: 5 }],
        estado: 'activo',
        metricas: { piezasPublicadas: 12, verticalesTop: ['economia', 'tecnologia'] },
        ventanaVetoExpiraEn: null,
        ...sobrescribir,
    };
}

function stubFetch(tarjetas: TarjetaPeriodista[], plantillas: PlantillaResumen[] = []) {
    const fetchSimulado = vi.fn((url: string, opciones?: RequestInit) => {
        if (url.endsWith('/periodistas') && (!opciones || 'POST' !== opciones.method)) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(tarjetas) });
        }
        if (url.endsWith('/periodistas/plantillas')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(plantillas) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('PantallaBancoPeriodistas', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('muestra las tarjetas con sus métricas reales', async () => {
        stubFetch([tarjetaDeEjemplo()]);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('Valentina Ruiz')).toBeInTheDocument();
        expect(screen.getByText(/12 piezas publicadas/)).toBeInTheDocument();
        expect(screen.getByText('economia, tecnologia')).toBeInTheDocument();
    });

    it('muestra el mensaje de sin verticales cuando no hay piezas publicadas', async () => {
        stubFetch([tarjetaDeEjemplo({ metricas: { piezasPublicadas: 0, verticalesTop: [] } })]);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('sin piezas publicadas todavía')).toBeInTheDocument();
    });

    it('solo muestra el botón Jubilar para periodistas activos', async () => {
        stubFetch([tarjetaDeEjemplo({ id: 1, estado: 'activo' }), tarjetaDeEjemplo({ id: 2, nombre: 'Marcos Iriarte', estado: 'jubilado' })]);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await screen.findByText('Valentina Ruiz');

        expect(screen.getAllByRole('button', { name: 'Jubilar' })).toHaveLength(1);
    });

    it('pide confirmación antes de jubilar y llama al endpoint correcto', async () => {
        const fetchSimulado = stubFetch([tarjetaDeEjemplo()]);
        const confirmSimulado = vi.spyOn(window, 'confirm').mockReturnValue(true);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Jubilar' }));

        expect(confirmSimulado).toHaveBeenCalled();
        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas/1/jubilar',
                expect.objectContaining({ method: 'POST' })
            )
        );
        confirmSimulado.mockRestore();
    });

    it('muestra el badge Propuesto y la ventana de veto restante en vez de Jubilar', async () => {
        stubFetch([
            tarjetaDeEjemplo({
                id: 7,
                nombre: 'Periodista Propuesto',
                estado: 'propuesto',
                ventanaVetoExpiraEn: '2026-07-30T02:00:00+00:00',
            }),
        ]);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('Propuesto')).toBeInTheDocument();
        expect(screen.getByText(/Se activa solo en/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Jubilar' })).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Aprobar ahora' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Descartar' })).toBeInTheDocument();
    });

    it('aprueba una propuesta ahora contra el endpoint correcto', async () => {
        const fetchSimulado = stubFetch([tarjetaDeEjemplo({ id: 7, estado: 'propuesto', ventanaVetoExpiraEn: '2026-07-30T02:00:00+00:00' })]);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Aprobar ahora' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas/7/aprobar-ahora',
                expect.objectContaining({ method: 'POST' })
            )
        );
    });

    it('pide confirmación antes de descartar una propuesta y llama al endpoint correcto', async () => {
        const fetchSimulado = stubFetch([tarjetaDeEjemplo({ id: 7, estado: 'propuesto', ventanaVetoExpiraEn: '2026-07-30T02:00:00+00:00' })]);
        const confirmSimulado = vi.spyOn(window, 'confirm').mockReturnValue(true);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Descartar' }));

        expect(confirmSimulado).toHaveBeenCalled();
        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas/7/propuesta',
                expect.objectContaining({ method: 'DELETE' })
            )
        );
        confirmSimulado.mockRestore();
    });

    it('crea un periodista personalizado con el comodín y abre su Estudio de Conducta', async () => {
        const detalleCreado = {
            id: 99,
            nombre: 'Periodista Nuevo',
            avatarUrl: null,
            biografia: 'Cubre lo que haga falta.',
            rol: 'cronista',
            especialidades: [{ vertical: '*', nivelDominio: 3 }],
            estado: 'activo',
            diales: {
                agudezaCritica: 50,
                humor: 20,
                satira: 0,
                formalidad: 60,
                vehemencia: 30,
                empatia: 50,
                densidadDatos: 50,
                longitudPreferida: 40,
            },
            reglasConducta: {
                lineaEditorial: '',
                lineasRojas: [],
                muletillas: [],
                vocabularioProhibido: [],
                tratamientoLector: 'usted',
                estiloPreguntaFinal: '',
            },
            matrizTonos: {},
            metricas: { piezasPublicadas: 0, verticalesTop: [] },
            memoriaReciente: [],
            ventanaVetoExpiraEn: null,
        };

        const fetchSimulado = vi.fn((url: string, opciones?: RequestInit) => {
            if (url.endsWith('/periodistas') && opciones && 'POST' === opciones.method) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 99 }) });
            }
            if (url.endsWith('/periodistas/99')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve(detalleCreado) });
            }
            if (url.endsWith('/periodistas')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve([tarjetaDeEjemplo()]) });
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
        });
        vi.stubGlobal('fetch', fetchSimulado);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Crear personalizado' }));
        await userEvent.type(screen.getByLabelText('Nombre'), 'Periodista Nuevo');
        await userEvent.type(screen.getByLabelText('Biografía'), 'Cubre lo que haga falta.');
        await userEvent.click(screen.getByLabelText('Cubre todos los temas'));
        await userEvent.click(screen.getByRole('button', { name: 'Crear' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({
                        nombre: 'Periodista Nuevo',
                        biografia: 'Cubre lo que haga falta.',
                        avatarUrl: null,
                        rol: 'analista',
                        cubreTodosLosTemas: true,
                        nivelDominioComodin: 3,
                        especialidades: [],
                    }),
                })
            )
        );

        expect(await screen.findByRole('dialog', { name: 'Periodista Nuevo' })).toBeInTheDocument();
    });

    it('el botón de crear personalizado coexiste con el de crear desde plantilla', async () => {
        stubFetch([tarjetaDeEjemplo()]);

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByRole('button', { name: 'Crear desde plantilla' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Crear personalizado' })).toBeInTheDocument();
    });

    it('crea un periodista desde plantilla', async () => {
        const fetchSimulado = stubFetch(
            [tarjetaDeEjemplo()],
            [{ slug: 'analista', nombre: 'Marcos Iriarte', biografia: 'Economista.', rol: 'analista' }]
        );

        render(<PantallaBancoPeriodistas restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Crear desde plantilla' }));

        await userEvent.click(await screen.findByText('Marcos Iriarte'));
        await userEvent.click(screen.getByRole('button', { name: 'Crear' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/periodistas/plantilla',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({ plantilla: 'analista', nombre: '' }),
                })
            )
        );
    });
});
