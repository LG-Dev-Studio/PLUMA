import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    PantallaSalaMaquinas,
    type DatosSalud,
    type EjecucionBitacora,
    type EstadoMotor,
    type EstadoTelemetria,
    type TextosSalaMaquinas,
} from './PantallaSalaMaquinas';

function datosDeEjemplo(sobrescribir: Partial<DatosSalud> = {}): DatosSalud {
    return {
        versionPhp: '8.2.31',
        versionWordPress: '6.7.1',
        versionBaseDatos: '8.0.36',
        versionEsquemaPlugin: '0.1.0',
        cronRealConfigurado: true,
        esMultisitio: false,
        textos: {
            titulo: 'Sala de Máquinas',
            etiquetaPhp: 'PHP',
            etiquetaWordPress: 'WordPress',
            etiquetaBaseDatos: 'Base de datos',
            etiquetaEsquema: 'Esquema PLUMA',
            etiquetaCron: 'Cron real',
            cronOk: 'Configurado',
            cronAdvertencia: 'WP-Cron activo: no recomendado para producción',
            etiquetaMultisitio: 'Multisitio',
            multisitioSi: 'Sí',
            multisitioNo: 'No',
        },
        ...sobrescribir,
    };
}

function textosDeEjemplo(): TextosSalaMaquinas {
    return {
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'La acción no se pudo completar.',
        ejecutarMotor: {
            titulo: 'Ejecutar el motor ahora',
            explicacion: 'El motor no se ejecuta solo sin cron configurado.',
            boton: 'Ejecutar un ciclo ahora',
            ejecutando: 'Ejecutando…',
            resultado: 'Ciclo completado. Lotes procesados:',
            error: 'No se pudo ejecutar el ciclo.',
        },
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
        creacionAutomaticaPeriodistas: {
            titulo: 'Creación automática de periodistas',
            explicacion: 'Cuando hay suficientes noticias sin cobertura.',
            activar: 'Activar creación automática',
            activada: 'Creación automática activada',
            etiquetaMinPiezas: 'Mínimo de noticias sin cobertura',
            etiquetaVentana: 'Ventana de días considerados',
            etiquetaCooldown: 'Horas mínimas entre evaluaciones',
            etiquetaMax: 'Máximo de periodistas automáticos',
            guardar: 'Guardar',
            guardado: 'Ajustes actualizados',
            errorCarga: 'No se pudo cargar la configuración de creación automática de periodistas.',
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
        llamadasModelo: {
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
        },
    };
}

function estadoDeEjemplo(sobrescribir: Partial<EstadoMotor> = {}): EstadoMotor {
    return {
        gastoHoyUsd: 1.5,
        limiteDiarioUsd: 5,
        openRouter: { configurada: false, ultimosCuatro: null, circuitoAbierto: false },
        googleTrends: { circuitoAbierto: false },
        ...sobrescribir,
    };
}

function telemetriaDeEjemplo(sobrescribir: Partial<EstadoTelemetria> = {}): EstadoTelemetria {
    return {
        habilitada: false,
        vistaPreviaPayload: { versionPlugin: '0.13.0', piezasPublicadas: 3 },
        ...sobrescribir,
    };
}

function stubFetch(bitacora: EjecucionBitacora[], estado: EstadoMotor, telemetria: EstadoTelemetria = telemetriaDeEjemplo()) {
    const fetchSimulado = vi.fn((url: string) => {
        if (url.endsWith('/motor/bitacora')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(bitacora) });
        }
        if (url.endsWith('/motor/estado')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(estado) });
        }
        if (url.endsWith('/motor/telemetria')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve(telemetria) });
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
        if (url.endsWith('/motor/modo-respeto')) {
            return Promise.resolve({
                ok: true,
                json: () => Promise.resolve({ activo: false, activadoEn: null, activadoPor: null, motivo: null, puedeDesactivarseDesde: null }),
            });
        }
        if (url.endsWith('/motor/imagen-destacada')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ modo: 'ninguna', creditoVisible: true }) });
        }
        if (url.endsWith('/motor/diagnostico')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ version: '1.0', entorno: {} }) });
        }
        if (url.endsWith('/motor/llave-openrouter/probar')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve({ valida: true }) });
        }
        if (url.endsWith('/motor/llamadas-modelo')) {
            return Promise.resolve({ ok: true, json: () => Promise.resolve([]) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    });
    vi.stubGlobal('fetch', fetchSimulado);
    return fetchSimulado;
}

describe('PantallaSalaMaquinas', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('muestra las versiones reales recibidas por props', async () => {
        stubFetch([], estadoDeEjemplo());

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(screen.getByText('8.2.31')).toBeInTheDocument();
        expect(screen.getByText('6.7.1')).toBeInTheDocument();
        expect(screen.getByText('8.0.36')).toBeInTheDocument();
        expect(screen.getByText('0.1.0')).toBeInTheDocument();
    });

    it('marca el cron como OK cuando el hosting lo configuró', () => {
        stubFetch([], estadoDeEjemplo());

        render(
            <PantallaSalaMaquinas
                datos={datosDeEjemplo({ cronRealConfigurado: true })}
                restUrl="https://ejemplo.test/wp-json/"
                nonce="n"
                textos={textosDeEjemplo()}
            />
        );

        expect(screen.getByText('Configurado')).toHaveAttribute('data-estado', 'ok');
    });

    it('muestra el gasto de hoy contra el límite', async () => {
        stubFetch([], estadoDeEjemplo({ gastoHoyUsd: 1.5, limiteDiarioUsd: 5 }));

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText(/\$1\.50 \/ \$5\.00/)).toBeInTheDocument();
    });

    it('muestra el estado de las APIs, incluyendo el circuito en enfriamiento', async () => {
        stubFetch([], estadoDeEjemplo({ openRouter: { configurada: true, ultimosCuatro: 'ab12', circuitoAbierto: true } }));

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('configurada')).toBeInTheDocument();
        expect(screen.getByText('en enfriamiento')).toBeInTheDocument();
        expect(screen.getByText(/sk-…ab12/)).toBeInTheDocument();
    });

    it('la bitácora muestra las ejecuciones con su duración calculada', async () => {
        stubFetch(
            [
                {
                    iniciadaEn: '2026-07-23T08:00:00+00:00',
                    finalizadaEn: '2026-07-23T08:00:12+00:00',
                    lotesProcesados: 3,
                    errores: [],
                },
                {
                    iniciadaEn: '2026-07-23T09:00:00+00:00',
                    finalizadaEn: null,
                    lotesProcesados: 0,
                    errores: [],
                },
            ],
            estadoDeEjemplo()
        );

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        expect(await screen.findByText('12.0s')).toBeInTheDocument();
        expect(screen.getByText('en curso')).toBeInTheDocument();
    });

    it('guarda una llave nueva tras probarla', async () => {
        const fetchSimulado = stubFetch([], estadoDeEjemplo());

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        const campo = await screen.findByLabelText('Nueva llave');
        await userEvent.type(campo, 'sk-or-v1-prueba');
        await userEvent.click(screen.getByRole('button', { name: 'Probar conexión' }));

        expect(await screen.findByText('La llave es válida.')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Guardar llave' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/motor/llave-openrouter',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }),
                    body: JSON.stringify({ llave: 'sk-or-v1-prueba' }),
                })
            )
        );
    });

    it('pide confirmación antes de quitar una llave ya configurada', async () => {
        stubFetch([], estadoDeEjemplo({ openRouter: { configurada: true, ultimosCuatro: 'ab12', circuitoAbierto: false } }));
        const confirmSimulado = vi.spyOn(window, 'confirm').mockReturnValue(false);

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Quitar llave' }));

        expect(confirmSimulado).toHaveBeenCalledWith('¿Quitar la llave?');
        confirmSimulado.mockRestore();
    });

    it('habilita la telemetría y persiste la elección', async () => {
        const fetchSimulado = stubFetch([], estadoDeEjemplo(), telemetriaDeEjemplo({ habilitada: false }));

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="nonce-x" textos={textosDeEjemplo()} />);

        const casilla = await screen.findByRole('checkbox');
        await userEvent.click(casilla);

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/motor/telemetria',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({ 'X-WP-Nonce': 'nonce-x' }),
                    body: JSON.stringify({ habilitada: true }),
                })
            )
        );
    });

    it('muestra y oculta la vista previa del payload de telemetría', async () => {
        stubFetch([], estadoDeEjemplo(), telemetriaDeEjemplo());

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Ver qué se compartiría' }));

        expect(screen.getByText(/piezasPublicadas/)).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Ocultar' }));

        expect(screen.queryByText(/piezasPublicadas/)).not.toBeInTheDocument();
    });

    /**
     * Sin cron real configurado el motor no arranca solo. Este botón es la
     * única forma de procesar un ciclo desde el panel una vez terminado el
     * asistente de bienvenida.
     */
    it('ejecuta un ciclo del motor a mano y reporta los lotes procesados', async () => {
        const fetchSimulado = vi.fn().mockImplementation((url: string) => {
            if (url.includes('/motor/ejecutar')) {
                return Promise.resolve({ ok: true, json: () => Promise.resolve({ lotesProcesados: 7 }) });
            }
            return Promise.resolve({ ok: true, json: () => Promise.resolve(url.includes('/estado') ? estadoDeEjemplo() : []) });
        });
        vi.stubGlobal('fetch', fetchSimulado);

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Ejecutar un ciclo ahora' }));

        await waitFor(() =>
            expect(fetchSimulado).toHaveBeenCalledWith(
                'https://ejemplo.test/wp-json/pluma/v1/motor/ejecutar',
                expect.objectContaining({ method: 'POST', headers: { 'X-WP-Nonce': 'n' } })
            )
        );
        await waitFor(() => expect(screen.getByRole('status')).toHaveTextContent('Ciclo completado. Lotes procesados: 7'));
    });

    it('descarga el reporte de diagnóstico como un archivo', async () => {
        stubFetch([], estadoDeEjemplo());
        const crearUrlSimulado = vi.fn().mockReturnValue('blob:falso');
        const revocarUrlSimulado = vi.fn();
        vi.stubGlobal('URL', { ...URL, createObjectURL: crearUrlSimulado, revokeObjectURL: revocarUrlSimulado });

        render(<PantallaSalaMaquinas datos={datosDeEjemplo()} restUrl="https://ejemplo.test/wp-json/" nonce="n" textos={textosDeEjemplo()} />);

        await userEvent.click(await screen.findByRole('button', { name: 'Descargar reporte de diagnóstico' }));

        await waitFor(() => expect(crearUrlSimulado).toHaveBeenCalled());
        expect(revocarUrlSimulado).toHaveBeenCalledWith('blob:falso');
    });
});
