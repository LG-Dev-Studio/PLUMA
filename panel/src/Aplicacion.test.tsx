import { render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Aplicacion, type DatosPlumaPanel } from './Aplicacion';
import type { DatosPortada } from './PantallaPortada';
import type { DatosSalud } from './PantallaSalaMaquinas';

function saludDeEjemplo(): DatosSalud {
    return {
        versionPhp: '8.2.31',
        versionWordPress: '6.7.1',
        versionBaseDatos: '8.0.36',
        versionEsquemaPlugin: '0.7.0',
        cronRealConfigurado: true,
        esMultisitio: false,
        textos: {
            titulo: 'Sala de Máquinas — Salud del sistema',
            etiquetaPhp: 'PHP',
            etiquetaWordPress: 'WordPress',
            etiquetaBaseDatos: 'Base de datos',
            etiquetaEsquema: 'Esquema PLUMA',
            etiquetaCron: 'Cron real',
            cronOk: 'Configurado',
            cronAdvertencia: 'WP-Cron activo',
            etiquetaMultisitio: 'Multisitio',
            multisitioSi: 'Sí',
            multisitioNo: 'No',
        },
    };
}

function textosOnboardingDeEjemplo(): DatosPlumaPanel['textosOnboarding'] {
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

function datosPanelDeEjemplo(): DatosPlumaPanel {
    return {
        restUrl: 'https://ejemplo.test/wp-json/',
        nonce: 'nonce-de-prueba',
        salud: saludDeEjemplo(),
        onboardingCompletado: true,
        textosOnboarding: textosOnboardingDeEjemplo(),
        textosPortada: {
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
                errores: 'con errores',
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
        },
        textosTendencias: {
            titulo: 'Sala de Tendencias',
            cargando: 'Cargando…',
            errorCarga: 'No se pudo cargar la Sala de Tendencias.',
            errorAccion: 'La acción no se pudo completar.',
            vacio: 'todavía no se ha detectado ninguna tendencia',
            velocidad: 'Velocidad',
            afinidad: 'Afinidad',
            total: 'Puntuación de Oportunidad',
            desgloseParcial: 'Desglose sobre velocidad y afinidad.',
            quienCubre: 'Quién la está cubriendo ya',
            nadieCubre: 'sin cobertura detectada en las señales',
            estadoVigilada: 'En vigilancia',
            cubrirAhora: 'Cubrir ahora',
            ignorar: 'Ignorar',
            vigilar: 'Vigilar',
            posibleActualizacion: 'Posible actualización de una historia ya cubierta',
            cubrirActualizacion: 'Cubrir como actualización',
        },
        textosBancoPeriodistas: {
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
            crearDesdePlantilla: 'Crear desde plantilla',
            elegirPlantilla: 'Elegir plantilla',
            nombreOpcional: 'Nombre (opcional)',
            crear: 'Crear',
            cancelar: 'Cancelar',
            jubilar: 'Jubilar',
            confirmarJubilar: '¿Jubilar a este periodista?',
            cerrar: 'Cerrar',
            estudioDeConducta: 'Estudio de Conducta',
            identidad: 'Identidad',
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
            respuestasHabilitadas: 'Responder comentarios automáticamente',
        },
        textosSalaRevision: {
            titulo: 'Sala de Revisión',
            cargando: 'Cargando…',
            errorCarga: 'No se pudo cargar la Sala de Revisión.',
            errorAccion: 'La acción no se pudo completar.',
            retenidas: 'Retenidas esperando decisión',
            sinRetenidas: 'ninguna pieza retenida',
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
        },
        textosSalaMaquinas: {
            cargando: 'Cargando…',
            errorCarga: 'No se pudo cargar.',
            errorAccion: 'La acción no se pudo completar.',
            bitacora: {
                titulo: 'Bitácora del motor',
                vacia: 'sin ejecuciones todavía',
                inicio: 'Inicio',
                duracion: 'Duración',
                lotes: 'Lotes',
                errores: 'Errores',
                sinErrores: 'sin errores',
                enCurso: 'en curso',
            },
            coste: {
                titulo: 'Coste',
                gastoHoy: 'Gasto de hoy',
                limiteDiario: 'Límite diario (USD)',
                guardarLimite: 'Guardar límite',
                guardado: 'Guardado',
            },
            apis: {
                titulo: 'Estado de las APIs',
                openRouter: 'OpenRouter',
                googleTrends: 'Google Trends',
                configurada: 'configurada',
                noConfigurada: 'sin configurar',
                circuitoAbierto: 'en enfriamiento',
                circuitoCerrado: 'conectada',
            },
            llave: {
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
            },
            searchConsole: {
                titulo: 'Search Console',
                cargando: 'Cargando…',
                errorCarga: 'No se pudo cargar Search Console.',
                errorAccion: 'La acción no se pudo completar.',
                avisoConectado: 'Conectado con Google Search Console.',
                avisoError: 'No se pudo completar la conexión.',
                campoClientId: 'Client ID',
                campoClientSecret: 'Client secret',
                conectar: 'Conectar',
                conectando: 'Conectando…',
                redirectUriTitulo: 'URI de redirección',
                redirectUriAyuda: 'Regístrala en Google Cloud.',
                irAGoogle: 'Ir a Google para autorizar',
                elegirSitio: 'Elegir sitio de Search Console',
                guardarSitio: 'Guardar sitio',
                sitioActual: 'Sitio conectado',
                sincronizarAhora: 'Sincronizar ahora',
                sincronizando: 'Sincronizando…',
                ultimaSincronizacion: 'Última sincronización',
                nuncaSincronizado: 'todavía no se ha sincronizado',
                circuitoAbierto: 'en enfriamiento',
                desconectar: 'Desconectar',
                confirmarDesconectar: '¿Desconectar Search Console?',
                tablaPagina: 'Página (post_id)',
                tablaConsulta: 'Consulta',
                tablaClics: 'Clics',
                tablaImpresiones: 'Impresiones',
                tablaCtr: 'CTR',
                tablaPosicion: 'Posición',
                sinMetricas: 'todavía no hay métricas sincronizadas',
            },
            transparencia: {
                titulo: 'Transparencia y cumplimiento',
                explicacion: 'Aviso visible + marcado legible por máquina.',
                etiquetaFormato: 'Formato del aviso visible',
                formatoBreve: 'Breve',
                formatoExtendido: 'Extendido',
                guardar: 'Guardar formato',
                guardado: 'Formato actualizado',
                marcadoDeFabrica: 'El marcado legible por máquina es requisito de fábrica.',
                errorCarga: 'No se pudo cargar la configuración de transparencia.',
                errorAccion: 'No se pudo guardar.',
            },
            riesgoLegal: {
                titulo: 'Perfil de riesgo legal',
                explicacion: 'Declara el régimen de responsabilidad de tu jurisdicción real.',
                etiquetaRegimen: 'Régimen de responsabilidad',
                regimenCivil: 'Civil',
                regimenPenal: 'Penal',
                guardar: 'Guardar régimen',
                guardado: 'Régimen actualizado',
                errorCarga: 'No se pudo cargar el perfil de riesgo legal.',
                errorAccion: 'No se pudo guardar.',
            },
            modeloVerificador: {
                titulo: 'Modelo verificador',
                explicacion: 'Declara un modelo distinto al premium.',
                etiquetaModelo: 'Slug del modelo verificador',
                guardar: 'Guardar modelo',
                guardado: 'Modelo actualizado',
                notaAlcance: 'Hoy es informativo.',
                errorCarga: 'No se pudo cargar el modelo verificador.',
                errorAccion: 'No se pudo guardar.',
            },
            modoRespeto: {
                titulo: 'Modo respeto',
                explicacion: 'Congela humor y sátira en todo el sitio.',
                activo: 'Activo',
                inactivo: 'Inactivo',
                activadoEn: 'Activado el',
                activadoPorAutomatico: 'Activado automáticamente por el sistema',
                activadoPorManual: 'Activado manualmente por el editor',
                motivo: 'Motivo',
                puedeDesactivarseDesde: 'Puede desactivarse a partir de',
                activar: 'Activar modo respeto ahora',
                desactivar: 'Desactivar modo respeto',
                errorCarga: 'No se pudo cargar el estado del modo respeto.',
                errorAccion: 'No se pudo completar la acción.',
                aunNoDesactivable: 'Todavía no puede desactivarse.',
            },
            imagenDestacada: {
                titulo: 'Imagen destacada por autoridad de fuente',
                explicacion: 'Toma la imagen del artículo de mayor confianza.',
                avisoRiesgo: 'Aviso legal: asumes el riesgo.',
                etiquetaModo: 'Modo',
                modoNinguna: 'Ninguna',
                modoEnlazada: 'Enlazada',
                modoDescargada: 'Descargada',
                etiquetaCredito: 'Mostrar crédito visible',
                notaCredito: 'El crédito no reduce el riesgo legal.',
                guardar: 'Guardar',
                guardado: 'Ajustes actualizados',
                errorCarga: 'No se pudo cargar la configuración de imagen destacada.',
                errorAccion: 'No se pudo guardar.',
            },
            telemetria: {
                titulo: 'Telemetría',
                explicacion: 'Opcional y anónima.',
                habilitar: 'Habilitar telemetría',
                deshabilitar: 'Telemetría habilitada',
                verPayload: 'Ver qué se compartiría',
                ocultarPayload: 'Ocultar',
            },
            diagnostico: {
                titulo: 'Modo diagnóstico',
                explicacion: 'Genera un reporte técnico.',
                descargar: 'Descargar reporte de diagnóstico',
                descargando: 'Generando…',
            },
        },
        textosEstudioSeo: {
            titulo: 'Estudio SEO y Taxonomía',
            cargando: 'Cargando…',
            errorCarga: 'No se pudo cargar el Estudio SEO y Taxonomía.',
            canibalizacion: {
                titulo: 'Auditoría de canibalización',
                vacio: 'ninguna keyword compartida',
                keyword: 'Keyword principal',
                piezas: 'Piezas publicadas',
            },
            taxonomia: {
                titulo: 'Salud taxonómica',
                cuarentenaTitulo: 'En cuarentena',
                cuarentenaVacio: 'sin cuarentena',
                vecesUsada: 'veces usada',
                fusionTitulo: 'Propuestas de fusión',
                fusionVacio: 'sin propuestas',
                similitud: 'similitud',
            },
            tipo: { categoria: 'categoría', etiqueta: 'etiqueta' },
        },
        textosMesaEditorial: {
            titulo: 'Mesa Editorial',
            cargando: 'Cargando…',
            errorCarga: 'No se pudo cargar la Mesa Editorial.',
            errorAccion: 'La acción no se pudo completar.',
            columnaVacia: 'sin piezas en este estado',
            sinPeriodista: 'sin periodista asignado',
            sinTesis: 'sin tesis todavía',
            cerrar: 'Cerrar',
            expediente: 'Expediente',
            sinExpediente: 'sin expediente todavía',
            nivelVerificado: 'Verificado',
            nivelAtribuido: 'Atribuido',
            nivelDisputado: 'Disputado',
            ficha: 'Ficha de Decisión Editorial',
            sinFicha: 'sin ficha de decisión editorial todavía',
            tesisElegida: 'Tesis elegida',
            tonoDominante: 'Tono dominante',
            tonoApoyo: 'Tono de apoyo',
            compuertas: 'Compuertas',
            sinCompuertas: 'sin evaluación de compuertas todavía',
            calidad: 'Calidad',
            riesgo: 'Riesgo',
            originalidad: 'Originalidad',
            motivos: 'Motivos',
            borradores: 'Borradores',
            sinBorradores: 'sin borradores todavía',
            cicloAnterior: 'Ciclo anterior',
            cicloActual: 'Ciclo',
            editadoManualmente: 'editado manualmente por un editor',
            aprobadoPorCorrector: 'aprobado por el Corrector Interno',
            editar: 'Editar',
            guardarEdicion: 'Guardar edición',
            cancelar: 'Cancelar',
            contenidoVacio: 'El contenido no puede estar vacío.',
            reasignar: 'Periodista asignado',
            reasignarBoton: 'Reasignar',
            aprobar: 'Forzar aprobación',
            descartar: 'Descartar',
            confirmarDescartar: '¿Descartar esta Pieza?',
            actualizacionDe: 'Actualización de la pieza',
        },
        textosComentarios: {
            titulo: 'Sala de Comentarios',
            cargando: 'Cargando…',
            errorCarga: 'No se pudo cargar la Sala de Comentarios.',
            errorAccion: 'La acción no se pudo completar.',
            vacio: 'no hay borradores de respuesta pendientes de aprobación',
            borrador: 'Borrador de respuesta',
            aprobar: 'Aprobar',
            descartar: 'Descartar',
        },
        textosInformes: {
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
            },
            motor: {
                titulo: 'Salud del motor esta semana',
                ejecuciones: 'Ejecuciones',
                lotesProcesados: 'Lotes procesados',
                ejecucionesConErrores: 'Ejecuciones con errores',
            },
            audiencia: {
                titulo: 'Audiencia esta semana',
                comentariosProcesados: 'Comentarios procesados',
                aprendizajesRegistrados: 'Aprendizajes registrados',
                sentimiento: 'Sentimiento de los comentarios',
                positivo: 'Positivo',
                negativo: 'Negativo',
                mixto: 'Mixto',
                neutral: 'Neutral',
                respuestasAprobadas: 'Respuestas aprobadas',
                respuestasDescartadas: 'Respuestas descartadas',
            },
        },
    };
}

function portadaDeEjemplo(): DatosPortada {
    return {
        modoOperacion: 'autonomo',
        cuota: { objetivo: 6, minima: 3, maxima: 8, publicadasHoy: 4, programadasHoy: 0, proximaPublicacion: null, deficit: false },
        salud: { ultimaEjecucion: null, gastoHoyUsd: 0.5, limiteDiarioUsd: 5 },
        piezasPorEstado: {},
        alertas: { retenidas: [], fallidas: [], sinPeriodistaIdoneo: [] },
        tendenciasCalientes: [],
        borradoresRespuestaPendientes: 0,
    };
}

describe('Aplicacion', () => {
    beforeEach(() => {
        window.location.hash = '';
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        window.location.hash = '';
    });

    it('pide la Portada al montar, enviando el nonce de REST, y la muestra', async () => {
        const fetchSimulado = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve(portadaDeEjemplo()),
        });
        vi.stubGlobal('fetch', fetchSimulado);

        render(<Aplicacion datos={datosPanelDeEjemplo()} />);

        await waitFor(() => expect(screen.getByText('Autónomo')).toBeInTheDocument());

        expect(fetchSimulado).toHaveBeenCalledWith(
            'https://ejemplo.test/wp-json/pluma/v1/panel/portada',
            expect.objectContaining({ headers: { 'X-WP-Nonce': 'nonce-de-prueba' } })
        );
    });

    it('muestra el error de carga cuando la petición REST falla', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, json: () => Promise.resolve({}) }));

        render(<Aplicacion datos={datosPanelDeEjemplo()} />);

        await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('No se pudo cargar la Portada.'));
    });

    it('navega a la Sala de Máquinas cuando el hash cambia, sin perder la barra de estado', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn((url: string) => {
                if (url.endsWith('/motor/bitacora')) {
                    return Promise.resolve({ ok: true, json: () => Promise.resolve([]) });
                }
                if (url.endsWith('/motor/estado')) {
                    return Promise.resolve({
                        ok: true,
                        json: () =>
                            Promise.resolve({
                                gastoHoyUsd: 0,
                                limiteDiarioUsd: 5,
                                openRouter: { configurada: false, ultimosCuatro: null, circuitoAbierto: false },
                                googleTrends: { circuitoAbierto: false },
                            }),
                    });
                }
                if (url.endsWith('/motor/telemetria')) {
                    return Promise.resolve({ ok: true, json: () => Promise.resolve({ habilitada: false, vistaPreviaPayload: {} }) });
                }
                if (url.endsWith('/motor/transparencia')) {
                    return Promise.resolve({ ok: true, json: () => Promise.resolve({ formato: 'breve', marcadoIaDeFabrica: true }) });
                }
                if (url.endsWith('/motor/riesgo-legal')) {
                    return Promise.resolve({ ok: true, json: () => Promise.resolve({ regimenResponsabilidad: 'civil' }) });
                }
                if (url.endsWith('/motor/modelo-verificador')) {
                    return Promise.resolve({
                        ok: true,
                        json: () => Promise.resolve({ modeloVerificador: 'anthropic/claude-sonnet-5', obligatoriedadDeFabrica: false }),
                    });
                }
                return Promise.resolve({ ok: true, json: () => Promise.resolve(portadaDeEjemplo()) });
            })
        );

        render(<Aplicacion datos={datosPanelDeEjemplo()} />);

        await waitFor(() => expect(screen.getByText('Autónomo')).toBeInTheDocument());

        window.location.hash = '#/salud';
        window.dispatchEvent(new HashChangeEvent('hashchange'));

        expect(await screen.findByText('Sala de Máquinas — Salud del sistema')).toBeInTheDocument();
        // La barra de estado persiste al navegar (Libro Cap. 10.1).
        expect(screen.getByText('Autónomo')).toBeInTheDocument();
    });
});
