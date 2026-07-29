import { useCallback, useEffect, useState } from 'react';

interface PeriodistaResumen {
    id: number;
    nombre: string;
    estado: 'activo' | 'jubilado';
}

interface DerivadoSocial {
    id: number;
    piezaId: number;
    extractoSocial: string;
    titularDiscover: string;
    estado: string;
    creadoEn: string;
}

export interface TextosDistribucion {
    titulo: string;
    cargando: string;
    errorCarga: string;
    errorAccion: string;
    seccionBoletines: string;
    enviarBoletin: string;
    sinPeriodistas: string;
    piezasEnviadas: string;
    sinPiezasNuevas: string;
    seccionDerivados: string;
    sinDerivados: string;
    extractoSocial: string;
    titularDiscover: string;
    aprobar: string;
    descartar: string;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosDistribucion;
}

/**
 * Canal propio (Nivel Cuatro W.1-W.2): el boletín como producto del
 * periodista (composición automática, disparo manual) y los derivados por
 * canal que el editor revisa antes de usarlos — "los derivados JAMÁS
 * contradicen ni exageran la pieza", así que la revisión humana es la
 * última compuerta antes de que un editor los copie a su red social.
 */
export function PantallaDistribucion({ restUrl, nonce, textos }: Props) {
    const [periodistas, setPeriodistas] = useState<PeriodistaResumen[] | null>(null);
    const [derivados, setDerivados] = useState<DerivadoSocial[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [accionEnCurso, setAccionEnCurso] = useState<number | null>(null);
    const [ultimoEnvio, setUltimoEnvio] = useState<Record<number, number>>({});

    const cargar = useCallback(() => {
        Promise.all([
            fetch(`${restUrl}pluma/v1/periodistas`, { headers: { 'X-WP-Nonce': nonce } }),
            fetch(`${restUrl}pluma/v1/derivados-sociales`, { headers: { 'X-WP-Nonce': nonce } }),
        ])
            .then(async ([respuestaPeriodistas, respuestaDerivados]) => {
                if (!respuestaPeriodistas.ok || !respuestaDerivados.ok) {
                    throw new Error('respuesta no OK');
                }
                setPeriodistas((await respuestaPeriodistas.json()) as PeriodistaResumen[]);
                setDerivados((await respuestaDerivados.json()) as DerivadoSocial[]);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const enviarBoletin = (periodistaId: number) => {
        setAccionEnCurso(periodistaId);
        fetch(`${restUrl}pluma/v1/boletines/${periodistaId}/enviar`, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json() as Promise<{ piezas: number }>;
            })
            .then((json) => setUltimoEnvio((previo) => ({ ...previo, [periodistaId]: json.piezas })))
            .catch(() => setError(textos.errorAccion))
            .finally(() => setAccionEnCurso(null));
    };

    const resolverDerivado = (id: number, accion: 'aprobar' | 'descartar') => {
        setAccionEnCurso(id);
        fetch(`${restUrl}pluma/v1/derivados-sociales/${id}/${accion}`, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                cargar();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setAccionEnCurso(null));
    };

    if (null !== error && (null === periodistas || null === derivados)) {
        return (
            <div className="pluma-distribucion pluma-distribucion--error" role="alert">
                {error}
            </div>
        );
    }

    if (null === periodistas || null === derivados) {
        return <div className="pluma-distribucion pluma-distribucion--cargando">{textos.cargando}</div>;
    }

    const periodistasActivos = periodistas.filter((p) => 'activo' === p.estado);

    return (
        <div className="pluma-distribucion">
            <h1>{textos.titulo}</h1>

            {null !== error && (
                <div className="pluma-distribucion__error" role="alert">
                    {error}
                </div>
            )}

            <section className="pluma-distribucion__boletines">
                <h2>{textos.seccionBoletines}</h2>
                {0 === periodistasActivos.length ? (
                    <p className="pluma-distribucion__vacio">{textos.sinPeriodistas}</p>
                ) : (
                    <ul>
                        {periodistasActivos.map((periodista) => (
                            <li key={periodista.id}>
                                <span>{periodista.nombre}</span>
                                <button type="button" disabled={accionEnCurso === periodista.id} onClick={() => enviarBoletin(periodista.id)}>
                                    {textos.enviarBoletin}
                                </button>
                                {undefined !== ultimoEnvio[periodista.id] && (
                                    <span className="pluma-distribucion__resultado">
                                        {0 === ultimoEnvio[periodista.id] ? textos.sinPiezasNuevas : `${textos.piezasEnviadas}: ${ultimoEnvio[periodista.id]}`}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="pluma-distribucion__derivados">
                <h2>{textos.seccionDerivados}</h2>
                {0 === derivados.length ? (
                    <p className="pluma-distribucion__vacio">{textos.sinDerivados}</p>
                ) : (
                    <ul>
                        {derivados.map((derivado) => (
                            <li key={derivado.id} className="pluma-distribucion__derivado">
                                <p>
                                    <strong>{textos.extractoSocial}:</strong> {derivado.extractoSocial}
                                </p>
                                <p>
                                    <strong>{textos.titularDiscover}:</strong> {derivado.titularDiscover}
                                </p>
                                <div className="pluma-distribucion__acciones">
                                    <button type="button" disabled={accionEnCurso === derivado.id} onClick={() => resolverDerivado(derivado.id, 'aprobar')}>
                                        {textos.aprobar}
                                    </button>
                                    <button type="button" disabled={accionEnCurso === derivado.id} onClick={() => resolverDerivado(derivado.id, 'descartar')}>
                                        {textos.descartar}
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
