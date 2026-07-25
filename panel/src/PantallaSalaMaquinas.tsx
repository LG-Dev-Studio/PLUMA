import { useCallback, useEffect, useState } from 'react';
import { BloqueLlaveOpenRouter } from './BloqueLlaveOpenRouter';
import { BloqueSearchConsole, type TextosSearchConsole } from './BloqueSearchConsole';
import { BloqueTransparencia, type TextosTransparencia } from './BloqueTransparencia';

export interface DatosSalud {
    versionPhp: string;
    versionWordPress: string;
    versionBaseDatos: string;
    versionEsquemaPlugin: string;
    cronRealConfigurado: boolean;
    esMultisitio: boolean;
    textos: {
        titulo: string;
        etiquetaPhp: string;
        etiquetaWordPress: string;
        etiquetaBaseDatos: string;
        etiquetaEsquema: string;
        etiquetaCron: string;
        cronOk: string;
        cronAdvertencia: string;
        etiquetaMultisitio: string;
        multisitioSi: string;
        multisitioNo: string;
    };
}

export interface EjecucionBitacora {
    iniciadaEn: string;
    finalizadaEn: string | null;
    lotesProcesados: number;
    errores: string[];
}

export interface EstadoMotor {
    gastoHoyUsd: number;
    limiteDiarioUsd: number;
    openRouter: {
        configurada: boolean;
        ultimosCuatro: string | null;
        circuitoAbierto: boolean;
    };
    googleTrends: {
        circuitoAbierto: boolean;
    };
}

export interface EstadoTelemetria {
    habilitada: boolean;
    vistaPreviaPayload: Record<string, unknown>;
}

export interface TextosSalaMaquinas {
    cargando: string;
    errorCarga: string;
    errorAccion: string;
    bitacora: {
        titulo: string;
        vacia: string;
        inicio: string;
        duracion: string;
        lotes: string;
        errores: string;
        sinErrores: string;
        enCurso: string;
    };
    coste: {
        titulo: string;
        gastoHoy: string;
        limiteDiario: string;
        guardarLimite: string;
        guardado: string;
    };
    apis: {
        titulo: string;
        openRouter: string;
        googleTrends: string;
        configurada: string;
        noConfigurada: string;
        circuitoAbierto: string;
        circuitoCerrado: string;
    };
    llave: {
        titulo: string;
        actual: string;
        campoNueva: string;
        guardar: string;
        probar: string;
        probando: string;
        valida: string;
        invalida: string;
        cambiar: string;
        quitar: string;
        confirmarQuitar: string;
    };
    searchConsole: TextosSearchConsole;
    transparencia: TextosTransparencia;
    telemetria: {
        titulo: string;
        explicacion: string;
        habilitar: string;
        deshabilitar: string;
        verPayload: string;
        ocultarPayload: string;
    };
    diagnostico: {
        titulo: string;
        explicacion: string;
        descargar: string;
        descargando: string;
    };
}

interface Props {
    datos: DatosSalud;
    restUrl: string;
    nonce: string;
    textos: TextosSalaMaquinas;
}

/**
 * Sala de Máquinas (Libro Cap. 10.2): "la bitácora del motor... coste por
 * día contra presupuesto, estado de cada API conectada, y las llaves/
 * configuración técnica". "Coste por pieza" y "reintentos" quedan fuera —
 * sin fuente real todavía (`PLUMA-E3-7`); se muestra el gasto agregado del
 * día y los errores tal como se registraron, sin inventar una atribución
 * o un mecanismo de reintento que no existen.
 */
export function PantallaSalaMaquinas({ datos, restUrl, nonce, textos }: Props) {
    const { textos: textosSalud } = datos;

    return (
        <div className="pluma-maquinas">
            <h1>{textosSalud.titulo}</h1>

            <dl className="pluma-salud__lista">
                <div className="pluma-salud__fila">
                    <dt>{textosSalud.etiquetaPhp}</dt>
                    <dd>{datos.versionPhp}</dd>
                </div>
                <div className="pluma-salud__fila">
                    <dt>{textosSalud.etiquetaWordPress}</dt>
                    <dd>{datos.versionWordPress}</dd>
                </div>
                <div className="pluma-salud__fila">
                    <dt>{textosSalud.etiquetaBaseDatos}</dt>
                    <dd>{datos.versionBaseDatos}</dd>
                </div>
                <div className="pluma-salud__fila">
                    <dt>{textosSalud.etiquetaEsquema}</dt>
                    <dd>{datos.versionEsquemaPlugin}</dd>
                </div>
                <div className="pluma-salud__fila">
                    <dt>{textosSalud.etiquetaCron}</dt>
                    <dd
                        data-estado={datos.cronRealConfigurado ? 'ok' : 'advertencia'}
                        className={
                            datos.cronRealConfigurado
                                ? 'pluma-salud__estado pluma-salud__estado--ok'
                                : 'pluma-salud__estado pluma-salud__estado--advertencia'
                        }
                    >
                        {datos.cronRealConfigurado ? textosSalud.cronOk : textosSalud.cronAdvertencia}
                    </dd>
                </div>
                <div className="pluma-salud__fila">
                    <dt>{textosSalud.etiquetaMultisitio}</dt>
                    <dd>{datos.esMultisitio ? textosSalud.multisitioSi : textosSalud.multisitioNo}</dd>
                </div>
            </dl>

            <SeccionesMotor restUrl={restUrl} nonce={nonce} textos={textos} />
        </div>
    );
}

function SeccionesMotor({ restUrl, nonce, textos }: { restUrl: string; nonce: string; textos: TextosSalaMaquinas }) {
    const [bitacora, setBitacora] = useState<EjecucionBitacora[] | null>(null);
    const [estado, setEstado] = useState<EstadoMotor | null>(null);
    const [telemetria, setTelemetria] = useState<EstadoTelemetria | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [limiteEditado, setLimiteEditado] = useState('');
    const [enCurso, setEnCurso] = useState(false);
    const [mostrarPayload, setMostrarPayload] = useState(false);
    const [descargandoDiagnostico, setDescargandoDiagnostico] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        Promise.all([
            fetch(`${restUrl}pluma/v1/motor/bitacora`, { headers: cabeceras }).then((r) => r.json() as Promise<EjecucionBitacora[]>),
            fetch(`${restUrl}pluma/v1/motor/estado`, { headers: cabeceras }).then((r) => r.json() as Promise<EstadoMotor>),
            fetch(`${restUrl}pluma/v1/motor/telemetria`, { headers: cabeceras }).then((r) => r.json() as Promise<EstadoTelemetria>),
        ])
            .then(([listaBitacora, datosEstado, datosTelemetria]) => {
                setBitacora(listaBitacora);
                setEstado(datosEstado);
                setTelemetria(datosTelemetria);
                setLimiteEditado(String(datosEstado.limiteDiarioUsd));
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const guardarLimite = () => {
        const limite = Number(limiteEditado);

        if (Number.isNaN(limite) || limite < 0) {
            return;
        }

        setEnCurso(true);
        fetch(`${restUrl}pluma/v1/motor/presupuesto`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ limiteDiarioUsd: limite }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                cargar();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setEnCurso(false));
    };

    const alternarTelemetria = () => {
        if (null === telemetria) {
            return;
        }

        const habilitada = !telemetria.habilitada;

        setEnCurso(true);
        fetch(`${restUrl}pluma/v1/motor/telemetria`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ habilitada }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                cargar();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setEnCurso(false));
    };

    const descargarDiagnostico = () => {
        setDescargandoDiagnostico(true);
        fetch(`${restUrl}pluma/v1/motor/diagnostico`, { headers: cabeceras })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json();
            })
            .then((datos) => {
                const blob = new Blob([JSON.stringify(datos, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const enlace = document.createElement('a');
                enlace.href = url;
                enlace.download = `pluma-diagnostico-${new Date().toISOString().slice(0, 10)}.json`;
                enlace.click();
                URL.revokeObjectURL(url);
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setDescargandoDiagnostico(false));
    };

    if (null !== error) {
        return (
            <p className="pluma-maquinas__aviso" role="alert">
                {error}
            </p>
        );
    }

    if (null === bitacora || null === estado || null === telemetria) {
        return <p className="pluma-maquinas__cargando">{textos.cargando}</p>;
    }

    return (
        <>
            <section className="pluma-maquinas__seccion">
                <h2>{textos.coste.titulo}</h2>
                <p className="pluma-maquinas__coste">
                    {textos.coste.gastoHoy}: ${estado.gastoHoyUsd.toFixed(2)} / ${estado.limiteDiarioUsd.toFixed(2)}
                </p>
                <label className="pluma-maquinas__campo">
                    {textos.coste.limiteDiario}
                    <input type="number" min="0" step="0.5" value={limiteEditado} onChange={(evento) => setLimiteEditado(evento.target.value)} />
                </label>
                <button type="button" disabled={enCurso} onClick={guardarLimite}>
                    {textos.coste.guardarLimite}
                </button>
            </section>

            <section className="pluma-maquinas__seccion">
                <h2>{textos.apis.titulo}</h2>
                <ul className="pluma-maquinas__apis">
                    <li>
                        <strong>{textos.apis.openRouter}</strong>
                        <span>{estado.openRouter.configurada ? textos.apis.configurada : textos.apis.noConfigurada}</span>
                        <span data-estado={estado.openRouter.circuitoAbierto ? 'advertencia' : 'ok'}>
                            {estado.openRouter.circuitoAbierto ? textos.apis.circuitoAbierto : textos.apis.circuitoCerrado}
                        </span>
                    </li>
                    <li>
                        <strong>{textos.apis.googleTrends}</strong>
                        <span data-estado={estado.googleTrends.circuitoAbierto ? 'advertencia' : 'ok'}>
                            {estado.googleTrends.circuitoAbierto ? textos.apis.circuitoAbierto : textos.apis.circuitoCerrado}
                        </span>
                    </li>
                </ul>
            </section>

            <BloqueLlaveOpenRouter
                restUrl={restUrl}
                nonce={nonce}
                configurada={estado.openRouter.configurada}
                ultimosCuatro={estado.openRouter.ultimosCuatro}
                textos={textos.llave}
                alGuardar={cargar}
                alError={() => setError(textos.errorAccion)}
            />

            <BloqueSearchConsole restUrl={restUrl} nonce={nonce} textos={textos.searchConsole} />

            <BloqueTransparencia restUrl={restUrl} nonce={nonce} textos={textos.transparencia} />

            <section className="pluma-maquinas__seccion">
                <h2>{textos.telemetria.titulo}</h2>
                <p>{textos.telemetria.explicacion}</p>
                <label className="pluma-maquinas__campo">
                    <input type="checkbox" checked={telemetria.habilitada} disabled={enCurso} onChange={alternarTelemetria} />
                    {telemetria.habilitada ? textos.telemetria.deshabilitar : textos.telemetria.habilitar}
                </label>
                <button type="button" onClick={() => setMostrarPayload(!mostrarPayload)}>
                    {mostrarPayload ? textos.telemetria.ocultarPayload : textos.telemetria.verPayload}
                </button>
                {mostrarPayload && (
                    <pre className="pluma-maquinas__payload">{JSON.stringify(telemetria.vistaPreviaPayload, null, 2)}</pre>
                )}
            </section>

            <section className="pluma-maquinas__seccion">
                <h2>{textos.diagnostico.titulo}</h2>
                <p>{textos.diagnostico.explicacion}</p>
                <button type="button" disabled={descargandoDiagnostico} onClick={descargarDiagnostico}>
                    {descargandoDiagnostico ? textos.diagnostico.descargando : textos.diagnostico.descargar}
                </button>
            </section>

            <section className="pluma-maquinas__seccion">
                <h2>{textos.bitacora.titulo}</h2>
                {0 === bitacora.length ? (
                    <p className="pluma-maquinas__vacio">{textos.bitacora.vacia}</p>
                ) : (
                    <table className="pluma-maquinas__tabla">
                        <thead>
                            <tr>
                                <th>{textos.bitacora.inicio}</th>
                                <th>{textos.bitacora.duracion}</th>
                                <th>{textos.bitacora.lotes}</th>
                                <th>{textos.bitacora.errores}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bitacora.map((ejecucion) => (
                                <tr key={ejecucion.iniciadaEn}>
                                    <td>{new Date(ejecucion.iniciadaEn).toLocaleString()}</td>
                                    <td>{calcularDuracion(ejecucion, textos.bitacora.enCurso)}</td>
                                    <td>{ejecucion.lotesProcesados}</td>
                                    <td>{ejecucion.errores.length > 0 ? ejecucion.errores.join('; ') : textos.bitacora.sinErrores}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>
        </>
    );
}

function calcularDuracion(ejecucion: EjecucionBitacora, textoEnCurso: string): string {
    if (null === ejecucion.finalizadaEn) {
        return textoEnCurso;
    }

    const segundos = Math.max(0, (new Date(ejecucion.finalizadaEn).getTime() - new Date(ejecucion.iniciadaEn).getTime()) / 1000);

    return `${segundos.toFixed(1)}s`;
}
