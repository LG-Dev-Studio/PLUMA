import { useCallback, useEffect, useState } from 'react';

export interface PersonaResumenRevision {
    id: number;
    nombre: string;
}

export interface ResultadoCompuertasResumen {
    aprobada: boolean;
    retenida: boolean;
    motivos: string[];
    modoEfectivo: string;
    calidad: { puntuacionTotal: number; umbral: number; sustentoAprobado: boolean; detalle: string[] };
    riesgo: { detalleDifamacion: string; hechosDisputadosSinSenalar: boolean; temaRegulado: string | null };
    originalidad: { ratioGananciaInformacion: number; umbralGananciaMinima: number };
}

export interface PiezaRevision {
    id: number;
    tendenciaId: number;
    tendenciaTermino: string | null;
    periodista: PersonaResumenRevision | null;
    actualizadaEn: string;
    motivos: string[];
    modoEfectivo: string | null;
    resultadoCompuertas: ResultadoCompuertasResumen | null;
    contenido: string | null;
}

export interface EntradaVeto extends PiezaRevision {
    horaProgramada: string;
    horaLimiteVeto: string;
}

export interface TextosSalaRevision {
    titulo: string;
    cargando: string;
    errorCarga: string;
    errorAccion: string;
    retenidas: string;
    sinRetenidas: string;
    sinPeriodistaIdoneo: string;
    sinPeriodistaIdoneoVacio: string;
    sinPeriodistaIdoneoExplicacion: string;
    reanudar: string;
    colaDeVeto: string;
    sinColaDeVeto: string;
    diagnostico: string;
    sinDiagnostico: string;
    calidad: string;
    riesgo: string;
    originalidad: string;
    sinDetalle: string;
    lectura: string;
    sinContenido: string;
    aprobar: string;
    devolver: string;
    notaOpcional: string;
    descartar: string;
    vetar: string;
    aprobarAhora: string;
    tiempoRestante: string;
    tiempoAgotado: string;
    confirmarDescartar: string;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosSalaRevision;
}

export function PantallaSalaRevision({ restUrl, nonce, textos }: Props) {
    const [retenidas, setRetenidas] = useState<PiezaRevision[] | null>(null);
    const [cola, setCola] = useState<EntradaVeto[] | null>(null);
    const [sinPeriodista, setSinPeriodista] = useState<PiezaRevision[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [notas, setNotas] = useState<Record<number, string>>({});
    const [enCurso, setEnCurso] = useState<number | null>(null);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        Promise.all([
            fetch(`${restUrl}pluma/v1/revision/retenidas`, { headers: cabeceras }).then((r) => r.json() as Promise<PiezaRevision[]>),
            fetch(`${restUrl}pluma/v1/revision/veto`, { headers: cabeceras }).then((r) => r.json() as Promise<EntradaVeto[]>),
            fetch(`${restUrl}pluma/v1/revision/sin-periodista-idoneo`, { headers: cabeceras }).then((r) => r.json() as Promise<PiezaRevision[]>),
        ])
            .then(([listaRetenidas, listaVeto, listaSinPeriodista]) => {
                setRetenidas(listaRetenidas);
                setCola(listaVeto);
                setSinPeriodista(listaSinPeriodista);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const ejecutar = (piezaId: number, accion: 'aprobar' | 'aprobar-ahora' | 'devolver' | 'descartar' | 'reanudar') => {
        setEnCurso(piezaId);

        const cuerpo = 'devolver' === accion ? { nota: notas[piezaId] ?? '' } : {};

        fetch(`${restUrl}pluma/v1/revision/${piezaId}/${accion}`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify(cuerpo),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                cargar();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setEnCurso(null));
    };

    const descartarConConfirmacion = (piezaId: number) => {
        // eslint-disable-next-line no-alert -- confirmación de una acción destructiva real.
        if (window.confirm(textos.confirmarDescartar)) {
            ejecutar(piezaId, 'descartar');
        }
    };

    if (null !== error) {
        return (
            <div className="pluma-revision pluma-revision--error" role="alert">
                {error}
            </div>
        );
    }

    if (null === retenidas || null === cola || null === sinPeriodista) {
        return <div className="pluma-revision pluma-revision--cargando">{textos.cargando}</div>;
    }

    return (
        <div className="pluma-revision">
            <h1>{textos.titulo}</h1>

            <section className="pluma-revision__seccion">
                <h2>{textos.sinPeriodistaIdoneo}</h2>
                <p className="pluma-revision__explicacion">{textos.sinPeriodistaIdoneoExplicacion}</p>
                {0 === sinPeriodista.length ? (
                    <p className="pluma-revision__vacio">{textos.sinPeriodistaIdoneoVacio}</p>
                ) : (
                    <ul className="pluma-revision__lista">
                        {sinPeriodista.map((pieza) => (
                            <li key={pieza.id} className="pluma-revision__tarjeta">
                                <TarjetaPieza pieza={pieza} textos={textos} />

                                <div className="pluma-revision__acciones">
                                    <button type="button" disabled={enCurso === pieza.id} onClick={() => ejecutar(pieza.id, 'reanudar')}>
                                        {textos.reanudar}
                                    </button>
                                    <button
                                        type="button"
                                        className="pluma-revision__boton--descartar"
                                        disabled={enCurso === pieza.id}
                                        onClick={() => descartarConConfirmacion(pieza.id)}
                                    >
                                        {textos.descartar}
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="pluma-revision__seccion">
                <h2>{textos.retenidas}</h2>
                {0 === retenidas.length ? (
                    <p className="pluma-revision__vacio">{textos.sinRetenidas}</p>
                ) : (
                    <ul className="pluma-revision__lista">
                        {retenidas.map((pieza) => (
                            <li key={pieza.id} className="pluma-revision__tarjeta">
                                <TarjetaPieza pieza={pieza} textos={textos} />

                                <div className="pluma-revision__acciones">
                                    <button type="button" disabled={enCurso === pieza.id} onClick={() => ejecutar(pieza.id, 'aprobar')}>
                                        {textos.aprobar}
                                    </button>
                                    <div className="pluma-revision__devolver">
                                        <input
                                            type="text"
                                            placeholder={textos.notaOpcional}
                                            value={notas[pieza.id] ?? ''}
                                            onChange={(evento) => setNotas({ ...notas, [pieza.id]: evento.target.value })}
                                        />
                                        <button type="button" disabled={enCurso === pieza.id} onClick={() => ejecutar(pieza.id, 'devolver')}>
                                            {textos.devolver}
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        className="pluma-revision__boton--descartar"
                                        disabled={enCurso === pieza.id}
                                        onClick={() => descartarConConfirmacion(pieza.id)}
                                    >
                                        {textos.descartar}
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="pluma-revision__seccion">
                <h2>{textos.colaDeVeto}</h2>
                {0 === cola.length ? (
                    <p className="pluma-revision__vacio">{textos.sinColaDeVeto}</p>
                ) : (
                    <ul className="pluma-revision__lista">
                        {cola.map((entrada) => (
                            <li key={entrada.id} className="pluma-revision__tarjeta">
                                <TarjetaPieza pieza={entrada} textos={textos} />
                                <CuentaRegresiva horaLimiteVeto={entrada.horaLimiteVeto} textos={textos} />
                                <div className="pluma-revision__acciones">
                                    <button type="button" disabled={enCurso === entrada.id} onClick={() => ejecutar(entrada.id, 'aprobar-ahora')}>
                                        {textos.aprobarAhora}
                                    </button>
                                    <button
                                        type="button"
                                        className="pluma-revision__boton--descartar"
                                        disabled={enCurso === entrada.id}
                                        onClick={() => descartarConConfirmacion(entrada.id)}
                                    >
                                        {textos.vetar}
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

function TarjetaPieza({ pieza, textos }: { pieza: PiezaRevision; textos: TextosSalaRevision }) {
    const resultado = pieza.resultadoCompuertas;

    return (
        <div className="pluma-revision__contenido-tarjeta">
            <header>
                <strong>#{pieza.id}</strong> {pieza.tendenciaTermino ?? '—'}
                <span className="pluma-revision__periodista">{pieza.periodista?.nombre ?? ''}</span>
            </header>

            <div className="pluma-revision__diagnostico">
                <h3>{textos.diagnostico}</h3>
                {null === resultado ? (
                    <p className="pluma-revision__vacio">{textos.sinDiagnostico}</p>
                ) : (
                    <ul>
                        {pieza.motivos.map((motivo) => (
                            <li key={motivo}>{motivo}</li>
                        ))}
                        {0 === pieza.motivos.length && <li>{textos.sinDetalle}</li>}
                    </ul>
                )}
            </div>

            <details className="pluma-revision__lectura">
                <summary>{textos.lectura}</summary>
                {pieza.contenido ? (
                    // eslint-disable-next-line react/no-danger -- contenido HTML propio del borrador, generado y saneado internamente (wp_kses_post), no entrada de un tercero no confiable.
                    <div dangerouslySetInnerHTML={{ __html: pieza.contenido }} />
                ) : (
                    <p className="pluma-revision__vacio">{textos.sinContenido}</p>
                )}
            </details>
        </div>
    );
}

function CuentaRegresiva({ horaLimiteVeto, textos }: { horaLimiteVeto: string; textos: TextosSalaRevision }) {
    const [ahora, setAhora] = useState(() => Date.now());

    useEffect(() => {
        const intervalo = window.setInterval(() => setAhora(Date.now()), 1000);
        return () => window.clearInterval(intervalo);
    }, []);

    const restanteMs = new Date(horaLimiteVeto).getTime() - ahora;

    if (restanteMs <= 0) {
        return <p className="pluma-revision__cuenta-regresiva pluma-revision__cuenta-regresiva--agotada">{textos.tiempoAgotado}</p>;
    }

    const minutos = Math.floor(restanteMs / 60000);
    const horas = Math.floor(minutos / 60);
    const minutosRestantes = minutos % 60;

    return (
        <p className="pluma-revision__cuenta-regresiva">
            {textos.tiempoRestante}: {horas}h {minutosRestantes}min
        </p>
    );
}
