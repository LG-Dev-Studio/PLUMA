export interface PiezaResumen {
    id: number;
    tendenciaId: number;
    actualizadaEn: string;
    motivos: string[];
}

export interface TendenciaCaliente {
    id: number;
    termino: string;
    puntuacionTotal: number;
    detectadaEn: string;
}

export interface DatosCuota {
    objetivo: number;
    minima: number;
    maxima: number;
    publicadasHoy: number;
    programadasHoy: number;
    proximaPublicacion: string | null;
    deficit: boolean;
}

export interface UltimaEjecucion {
    iniciadaEn: string;
    finalizadaEn: string | null;
    lotesProcesados: number;
    errores: string[];
}

export interface DatosSaludMotor {
    ultimaEjecucion: UltimaEjecucion | null;
    gastoHoyUsd: number;
    limiteDiarioUsd: number;
}

export interface DatosPortada {
    modoOperacion: 'piloto' | 'copiloto' | 'autonomo';
    cuota: DatosCuota;
    salud: DatosSaludMotor;
    piezasPorEstado: Record<string, number>;
    alertas: {
        retenidas: PiezaResumen[];
        fallidas: PiezaResumen[];
        sinPeriodistaIdoneo: PiezaResumen[];
    };
    tendenciasCalientes: TendenciaCaliente[];
    borradoresRespuestaPendientes: number;
}

export interface TextosPortada {
    cronNoConfigurado: string;
    titulo: string;
    navPortada: string;
    navSalud: string;
    cargando: string;
    errorCarga: string;
    modo: {
        piloto: string;
        copiloto: string;
        autonomo: string;
    };
    cuota: {
        titulo: string;
        publicadas: string;
        programadas: string;
        objetivo: string;
        proximaPublicacion: string;
        sinProximo: string;
        deficit: string;
    };
    salud: {
        titulo: string;
        ultimaEjecucion: string;
        nunca: string;
        gastoHoy: string;
        deLimite: string;
        errores: string;
    };
    pipeline: {
        titulo: string;
        estados: Record<string, string>;
    };
    alertas: {
        titulo: string;
        retenidas: string;
        fallidas: string;
        sinPeriodistaIdoneo: string;
        sinRetenidas: string;
        sinFallidas: string;
        sinPeriodistaIdoneoVacio: string;
    };
    borradoresRespuestaPendientes: string;
    tendencias: {
        titulo: string;
        vacio: string;
    };
}

/**
 * Orden editorial del pipeline para el kanban compacto de la Portada (Libro
 * Cap. 10.2): el mismo orden en que una Pieza real avanza, con las salidas
 * laterales (retenida/descartada/fallida) al final.
 */
const ORDEN_ESTADOS = [
    'detectada',
    'en_investigacion',
    'investigada',
    'en_redaccion',
    'redactada',
    'optimizada',
    'en_revision',
    'aprobada',
    'programada',
    'publicada',
    'retenida',
    'descartada',
    'fallida',
    'sin_periodista_idoneo',
];

interface Props {
    datos: DatosPortada | null;
    error: string | null;
    textos: TextosPortada;
}

/**
 * La Portada (Libro Cap. 10.2): "el día de un vistazo". "Resultados de
 * ayer" (tráfico, piezas top, comentarios) del Libro queda fuera a
 * propósito hasta que exista una fuente real de tráfico (Etapa 5).
 */
/**
 * El motor lleva demasiado sin ejecutarse. Se mide por EVIDENCIA (la última
 * ejecución real registrada en la bitácora), no por `DISABLE_WP_CRON`:
 * desactivar WP-Cron no significa que exista un cron real: en un entorno sin
 * cron configurado ambas cosas son ciertas a la vez y la bandera de
 * configuración reportaría que todo está bien mientras nada avanza.
 */
const HORAS_SIN_EJECUTAR_PARA_AVISAR = 3;

function motorParado(ultima: UltimaEjecucion | null): boolean {
    if (null === ultima) {
        return true;
    }

    const transcurridoMs = Date.now() - new Date(ultima.iniciadaEn).getTime();

    return transcurridoMs > HORAS_SIN_EJECUTAR_PARA_AVISAR * 60 * 60 * 1000;
}

export function PantallaPortada({ datos, error, textos }: Props) {
    if (null !== error) {
        return (
            <div className="pluma-portada pluma-portada--error" role="alert">
                {error}
            </div>
        );
    }

    if (null === datos) {
        return <div className="pluma-portada pluma-portada--cargando">{textos.cargando}</div>;
    }

    return (
        <div className="pluma-portada">
            <h1>{textos.titulo}</h1>

            {motorParado(datos.salud.ultimaEjecucion) && (
                <p className="pluma-portada__aviso" role="alert">
                    {textos.cronNoConfigurado}
                </p>
            )}

            {datos.cuota.deficit && (
                <p className="pluma-portada__aviso" role="alert">
                    {textos.cuota.deficit}
                </p>
            )}

            {datos.borradoresRespuestaPendientes > 0 && (
                <p className="pluma-portada__aviso" role="alert">
                    <a href="#/comentarios">
                        {textos.borradoresRespuestaPendientes} ({datos.borradoresRespuestaPendientes})
                    </a>
                </p>
            )}

            <section className="pluma-portada__seccion" aria-label={textos.pipeline.titulo}>
                <h2>{textos.pipeline.titulo}</h2>
                <ol className="pluma-portada__kanban">
                    {ORDEN_ESTADOS.map((estado) => (
                        <li key={estado} className={`pluma-portada__columna pluma-portada__columna--${estado}`}>
                            <span className="pluma-portada__columna-etiqueta">{textos.pipeline.estados[estado] ?? estado}</span>
                            <span className="pluma-portada__columna-total">{datos.piezasPorEstado[estado] ?? 0}</span>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="pluma-portada__seccion" aria-label={textos.alertas.titulo}>
                <h2>{textos.alertas.titulo}</h2>
                <div className="pluma-portada__alertas">
                    <ListaAlertas
                        titulo={textos.alertas.retenidas}
                        vacio={textos.alertas.sinRetenidas}
                        piezas={datos.alertas.retenidas}
                        tono="retenida"
                    />
                    <ListaAlertas
                        titulo={textos.alertas.fallidas}
                        vacio={textos.alertas.sinFallidas}
                        piezas={datos.alertas.fallidas}
                        tono="fallida"
                    />
                    <ListaAlertas
                        titulo={textos.alertas.sinPeriodistaIdoneo}
                        vacio={textos.alertas.sinPeriodistaIdoneoVacio}
                        piezas={datos.alertas.sinPeriodistaIdoneo}
                        tono="sin_periodista_idoneo"
                    />
                </div>
            </section>

            <section className="pluma-portada__seccion" aria-label={textos.tendencias.titulo}>
                <h2>{textos.tendencias.titulo}</h2>
                {0 === datos.tendenciasCalientes.length ? (
                    <p className="pluma-portada__vacio">{textos.tendencias.vacio}</p>
                ) : (
                    <ol className="pluma-portada__tendencias">
                        {datos.tendenciasCalientes.map((tendencia) => (
                            <li key={tendencia.id}>
                                <span className="pluma-portada__tendencia-termino">{tendencia.termino}</span>
                                <span className="pluma-portada__tendencia-puntuacion">{tendencia.puntuacionTotal.toFixed(0)}</span>
                            </li>
                        ))}
                    </ol>
                )}
            </section>
        </div>
    );
}

interface PropsListaAlertas {
    titulo: string;
    vacio: string;
    piezas: PiezaResumen[];
    tono: 'retenida' | 'fallida' | 'sin_periodista_idoneo';
}

function ListaAlertas({ titulo, vacio, piezas, tono }: PropsListaAlertas) {
    return (
        <div className={`pluma-portada__alerta pluma-portada__alerta--${tono}`}>
            <h3>
                {titulo} <span className="pluma-portada__alerta-total">{piezas.length}</span>
            </h3>
            {0 === piezas.length ? (
                <p className="pluma-portada__vacio">{vacio}</p>
            ) : (
                <ul>
                    {piezas.map((pieza) => (
                        <li key={pieza.id}>
                            #{pieza.id} — {pieza.motivos.length > 0 ? pieza.motivos.join('; ') : '—'}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
