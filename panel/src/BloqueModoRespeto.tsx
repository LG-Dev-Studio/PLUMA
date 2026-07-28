import { useCallback, useEffect, useState } from 'react';

export interface TextosModoRespeto {
    titulo: string;
    explicacion: string;
    activo: string;
    inactivo: string;
    activadoEn: string;
    activadoPorAutomatico: string;
    activadoPorManual: string;
    motivo: string;
    puedeDesactivarseDesde: string;
    activar: string;
    desactivar: string;
    errorCarga: string;
    errorAccion: string;
    aunNoDesactivable: string;
}

interface EstadoModoRespeto {
    activo: boolean;
    activadoEn: string | null;
    activadoPor: string | null;
    motivo: string | null;
    puedeDesactivarseDesde: string | null;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosModoRespeto;
}

/**
 * Modo respeto (Nivel Dos F.1-F.3): estado actual + activación manual de un
 * clic + desactivación, bloqueada mientras no se cumpla el piso de duración
 * mínima congelado en la propia activación.
 */
export function BloqueModoRespeto({ restUrl, nonce, textos }: Props) {
    const [estado, setEstado] = useState<EstadoModoRespeto | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [enCurso, setEnCurso] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/motor/modo-respeto`, { headers: cabeceras })
            .then((r) => r.json() as Promise<EstadoModoRespeto>)
            .then((datos) => {
                setEstado(datos);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const activar = () => {
        setEnCurso(true);
        fetch(`${restUrl}pluma/v1/motor/modo-respeto/activar`, {
            method: 'POST',
            headers: cabeceras,
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

    const desactivar = () => {
        setEnCurso(true);
        fetch(`${restUrl}pluma/v1/motor/modo-respeto/desactivar`, {
            method: 'POST',
            headers: cabeceras,
        })
            .then((respuesta) => {
                if (409 === respuesta.status) {
                    setError(textos.aunNoDesactivable);
                    return;
                }
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                setError(null);
                cargar();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setEnCurso(false));
    };

    return (
        <section className="pluma-maquinas__seccion">
            <h2>{textos.titulo}</h2>
            <p>{textos.explicacion}</p>
            {null !== estado && (
                <>
                    <p
                        data-estado={estado.activo ? 'advertencia' : 'ok'}
                        className={
                            estado.activo
                                ? 'pluma-maquinas__estado pluma-maquinas__estado--advertencia'
                                : 'pluma-maquinas__estado pluma-maquinas__estado--ok'
                        }
                    >
                        {estado.activo ? textos.activo : textos.inactivo}
                    </p>
                    {estado.activo && (
                        <dl className="pluma-maquinas__lista">
                            {null !== estado.activadoEn && (
                                <div className="pluma-maquinas__fila">
                                    <dt>{textos.activadoEn}</dt>
                                    <dd>{new Date(estado.activadoEn).toLocaleString()}</dd>
                                </div>
                            )}
                            {null !== estado.activadoPor && (
                                <div className="pluma-maquinas__fila">
                                    <dt />
                                    <dd>{'automatico' === estado.activadoPor ? textos.activadoPorAutomatico : textos.activadoPorManual}</dd>
                                </div>
                            )}
                            {null !== estado.motivo && (
                                <div className="pluma-maquinas__fila">
                                    <dt>{textos.motivo}</dt>
                                    <dd>{estado.motivo}</dd>
                                </div>
                            )}
                            {null !== estado.puedeDesactivarseDesde && (
                                <div className="pluma-maquinas__fila">
                                    <dt>{textos.puedeDesactivarseDesde}</dt>
                                    <dd>{new Date(estado.puedeDesactivarseDesde).toLocaleString()}</dd>
                                </div>
                            )}
                        </dl>
                    )}
                    <button type="button" disabled={enCurso || estado.activo} onClick={activar}>
                        {textos.activar}
                    </button>
                    <button type="button" disabled={enCurso || !estado.activo} onClick={desactivar}>
                        {textos.desactivar}
                    </button>
                </>
            )}
            {null !== error && (
                <p className="pluma-maquinas__aviso" role="alert">
                    {error}
                </p>
            )}
        </section>
    );
}
