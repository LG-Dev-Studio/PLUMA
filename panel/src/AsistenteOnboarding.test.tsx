import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AsistenteOnboarding, type TextosOnboarding } from './AsistenteOnboarding';
import type { TextosLlaveOpenRouter } from './BloqueLlaveOpenRouter';
import type { DetallePeriodista, TextosBancoPeriodistas } from './PantallaBancoPeriodistas';

function textosOnboardingDeEjemplo(): TextosOnboarding {
    return {
        titulo: 'Bienvenido a PLUMA Engine',
        saltar: 'Saltar por ahora',
        continuar: 'Continuar',
        atras: 'Atrás',
        finalizar: 'Finalizar',
        errorCarga: 'No se pudo cargar.',
        acto1: {
            titulo: 'Verificación técnica y cron real',
            etiquetaPhp: 'PHP',
            etiquetaWordPress: 'WordPress',
            etiquetaBaseDatos: 'Base de datos',
            cronOk: 'WP-Cron ya está desactivado.',
            cronAdvertencia: 'WP-Cron sigue activo.',
            cronDatosTitulo: 'Datos del cron real',
            cronUrl: 'URL',
            cronCabecera: 'Cabecera',
            cronComandoTitulo: 'Comando de ejemplo',
            recetaCpanelTitulo: 'Receta cPanel',
            recetaCpanelTexto: 'Añade una tarea cron en cPanel.',
            recetaSistemaTitulo: 'Receta de sistema',
            recetaSistemaTexto: 'Añade una línea a tu crontab.',
            avisoGenerico: 'Recetas genéricas.',
        },
        acto2: {
            titulo: 'Conecta tus llaves de API',
            googleTrendsInfo: 'Google Trends no necesita llave.',
        },
        acto3: {
            titulo: 'Línea editorial y categorías',
            lineaEditorialLabel: 'Línea editorial',
            lineaEditorialPlaceholder: 'Ej: escepticismo informado.',
            importarCategorias: 'Importar categorías existentes del sitio',
            importando: 'Importando…',
            resultadoImportadas: 'Categorías importadas',
            resultadoYaExistian: 'Ya existían',
            sinCategorias: 'Sin categorías.',
        },
        acto4: {
            titulo: 'Tu primer periodista sintético',
            elegirPlantilla: 'Elige una plantilla',
            crear: 'Crear periodista',
            creando: 'Creando…',
            ajusteFino: 'Ajuste fino opcional',
        },
        acto5: {
            titulo: 'Elige el modo y ejecuta el primer ciclo',
            modoTitulo: 'Modo de operación',
            modoPilotoDescripcion: 'Empieza en Piloto.',
            primerCiclo: 'Ejecutar el primer ciclo ahora',
            ejecutando: 'Ejecutando…',
            resultadoTitulo: 'Resultado',
            sinLotes: 'Nada que procesar todavía.',
        },
    };
}

function textosLlaveDeEjemplo(): TextosLlaveOpenRouter {
    return {
        titulo: 'Llave de OpenRouter',
        actual: 'Llave actual',
        campoNueva: 'Nueva llave',
        guardar: 'Guardar llave',
        probar: 'Probar conexión',
        probando: 'Probando…',
        valida: 'La llave es válida.',
        invalida: 'La llave no es válida.',
        cambiar: 'Cambiar llave',
        quitar: 'Quitar llave',
        confirmarQuitar: '¿Quitar la llave?',
    };
}

function textosBancoDeEjemplo(): TextosBancoPeriodistas {
    return {
        titulo: 'Banco de Periodistas',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'La acción no se pudo completar.',
        sinPeriodistas: 'sin periodistas',
        piezasPublicadas: 'piezas publicadas',
        verticalesTop: 'Verticales',
        sinVerticales: 'sin verticales',
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
        confirmarJubilar: '¿Jubilar?',
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
            titulo: 'Diales',
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
            titulo: 'Reglas',
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
            titulo: 'Memoria editorial',
            vacia: 'sin memoria',
            tipo: { postura: 'Postura', cobertura: 'Cobertura', audiencia: 'Audiencia' },
        },
        vistaPrevia: {
            titulo: 'Vista previa en vivo',
            generando: 'Redactando…',
            errorPresupuesto: 'Presupuesto agotado.',
            errorGeneral: 'No se pudo generar.',
        },
        guardarCambios: 'Guardar cambios',
        clonar: 'Clonar',
        nombreDelClon: 'Nombre del clon',
    };
}

function detalleDeEjemplo(): DetallePeriodista {
    return {
        id: 9,
        nombre: 'Analista de Datos',
        avatarUrl: null,
        biografia: 'Sobrio y riguroso.',
        rol: 'analista',
        especialidades: [],
        estado: 'activo',
        diales: {
            agudezaCritica: 50,
            humor: 20,
            satira: 10,
            formalidad: 70,
            vehemencia: 30,
            empatia: 50,
            densidadDatos: 80,
            longitudPreferida: 60,
        },
        reglasConducta: {
            lineaEditorial: 'Escepticismo informado.',
            lineasRojas: [],
            muletillas: [],
            vocabularioProhibido: [],
            tratamientoLector: 'usted',
            estiloPreguntaFinal: '¿Qué dicen los datos?',
        },
        matrizTonos: {
            tragedia: { tipoNoticia: 'tragedia', tonoDominante: 'informativo_empatico', tonoApoyo: 'analitico', nivelSatira: 'bloqueada' },
            anuncio_corporativo: { tipoNoticia: 'anuncio_corporativo', tonoDominante: 'analitico', tonoApoyo: 'critico', nivelSatira: 'no' },
            escandalo_politico: { tipoNoticia: 'escandalo_politico', tonoDominante: 'critico', tonoApoyo: 'analitico', nivelSatira: 'no' },
            cultura_viral: { tipoNoticia: 'cultura_viral', tonoDominante: 'analitico', tonoApoyo: 'opinion', nivelSatira: 'no' },
            dato_economico: { tipoNoticia: 'dato_economico', tonoDominante: 'analitico', tonoApoyo: 'persuasivo', nivelSatira: 'no' },
        },
        metricas: { piezasPublicadas: 0, verticalesTop: [] },
        memoriaReciente: [],
        ventanaVetoExpiraEn: null,
    };
}

function stubFetch() {
    const periodista = detalleDeEjemplo();

    return vi.fn((url: string, opciones?: RequestInit) => {
        if (url.endsWith('/onboarding/estado-tecnico')) {
            return Promise.resolve({
                ok: true,
                json: () =>
                    Promise.resolve({
                        versionPhp: '8.2.31',
                        versionWordPress: '6.7.1',
                        versionBaseDatos: '8.0.36',
                        cronRealConfigurado: true,
                        esMultisitio: false,
                        cron: { url: 'https://ejemplo.test/wp-json/pluma/v1/motor/tick', cabecera: 'X-Pluma-Token', token: 'token-de-prueba' },
                    }),
            });
        }
        if (url.endsWith('/motor/estado')) {
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ openRouter: { configurada: false, ultimosCuatro: null } }),
            });
        }
        if (url.endsWith('/onboarding/importar-categorias')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ importadas: ['Economía'], yaExistian: [] }) });
        }
        if (url.endsWith('/periodistas/plantillas')) {
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve([{ slug: 'analista', nombre: 'Analista de Datos', biografia: '...', rol: 'analista' }]),
            });
        }
        if (url.endsWith('/periodistas/plantilla') && opciones?.method === 'POST') {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ id: periodista.id }) });
        }
        if (url.endsWith(`/periodistas/${periodista.id}`)) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(periodista) });
        }
        if (url.endsWith('/onboarding/modo')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ modoOperacion: 'piloto' }) });
        }
        if (url.endsWith('/onboarding/primer-ciclo')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ ejecutado: true, lotesProcesados: 3, errores: [] }) });
        }
        if (url.endsWith('/onboarding/completar')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ completado: true }) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    });
}

describe('AsistenteOnboarding', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('muestra los datos técnicos reales y el comando del cron en el Acto 1', async () => {
        vi.stubGlobal('fetch', stubFetch());

        render(
            <AsistenteOnboarding
                restUrl="https://ejemplo.test/wp-json/"
                nonce="n"
                textos={textosOnboardingDeEjemplo()}
                textosModo={{ piloto: 'Piloto', copiloto: 'Copiloto', autonomo: 'Autónomo' }}
                textosLlave={textosLlaveDeEjemplo()}
                textosBancoPeriodistas={textosBancoDeEjemplo()}
                alTerminar={() => {}}
            />
        );

        expect(await screen.findByText('8.2.31')).toBeInTheDocument();
        expect(screen.getByText(/curl -X POST/)).toBeInTheDocument();
    });

    it('el botón "Saltar por ahora" completa el onboarding sin recorrer los actos', async () => {
        const fetchSimulado = stubFetch();
        vi.stubGlobal('fetch', fetchSimulado);
        const alTerminar = vi.fn();

        render(
            <AsistenteOnboarding
                restUrl="https://ejemplo.test/wp-json/"
                nonce="n"
                textos={textosOnboardingDeEjemplo()}
                textosModo={{ piloto: 'Piloto', copiloto: 'Copiloto', autonomo: 'Autónomo' }}
                textosLlave={textosLlaveDeEjemplo()}
                textosBancoPeriodistas={textosBancoDeEjemplo()}
                alTerminar={alTerminar}
            />
        );

        await userEvent.click(screen.getByRole('button', { name: 'Saltar por ahora' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/onboarding/completar',
                expect.objectContaining({ method: 'POST' })
            )
        );
        await waitFor(() => expect(alTerminar).toHaveBeenCalled());
    });

    it('el Acto 3 importa categorías y muestra el resultado real', async () => {
        vi.stubGlobal('fetch', stubFetch());

        render(
            <AsistenteOnboarding
                restUrl="https://ejemplo.test/wp-json/"
                nonce="n"
                textos={textosOnboardingDeEjemplo()}
                textosModo={{ piloto: 'Piloto', copiloto: 'Copiloto', autonomo: 'Autónomo' }}
                textosLlave={textosLlaveDeEjemplo()}
                textosBancoPeriodistas={textosBancoDeEjemplo()}
                alTerminar={() => {}}
            />
        );

        await screen.findByText('8.2.31');
        await userEvent.click(screen.getByRole('button', { name: 'Continuar' }));
        await userEvent.click(screen.getByRole('button', { name: 'Continuar' }));

        expect(await screen.findByText('Línea editorial y categorías')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Importar categorías existentes del sitio' }));

        expect(await screen.findByText('Economía')).toBeInTheDocument();
    });

    it('el Acto 4 exige crear un periodista antes de habilitar Continuar, y el Acto 5 ejecuta el primer ciclo', async () => {
        vi.stubGlobal('fetch', stubFetch());

        render(
            <AsistenteOnboarding
                restUrl="https://ejemplo.test/wp-json/"
                nonce="n"
                textos={textosOnboardingDeEjemplo()}
                textosModo={{ piloto: 'Piloto', copiloto: 'Copiloto', autonomo: 'Autónomo' }}
                textosLlave={textosLlaveDeEjemplo()}
                textosBancoPeriodistas={textosBancoDeEjemplo()}
                alTerminar={() => {}}
            />
        );

        await screen.findByText('8.2.31');
        await userEvent.click(screen.getByRole('button', { name: 'Continuar' })); // -> Acto 2
        await userEvent.click(screen.getByRole('button', { name: 'Continuar' })); // -> Acto 3
        await userEvent.click(screen.getByRole('button', { name: 'Continuar' })); // -> Acto 4

        expect(await screen.findByText('Tu primer periodista sintético')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Continuar' })).toBeDisabled();

        await userEvent.click(screen.getByRole('button', { name: 'Crear periodista' }));

        await waitFor(() => expect(screen.getByRole('button', { name: 'Continuar' })).not.toBeDisabled());

        await userEvent.click(screen.getByRole('button', { name: 'Continuar' })); // -> Acto 5

        expect(await screen.findByText('Elige el modo y ejecuta el primer ciclo')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Ejecutar el primer ciclo ahora' }));

        expect(await screen.findByText('3', { selector: 'p' })).toBeInTheDocument();
    });
});
