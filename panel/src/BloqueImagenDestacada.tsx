import { useCallback, useEffect, useState } from 'react';

export interface TextosImagenDestacada {
    titulo: string;
    explicacion: string;
    avisoRiesgo: string;
    etiquetaModo: string;
    modoNinguna: string;
    modoEnlazada: string;
    modoDescargada: string;
    etiquetaCredito: string;
    notaCredito: string;
    guardar: string;
    guardado: string;
    errorCarga: string;
    errorAccion: string;
}

interface EstadoImagenDestacada {
    modo: string;
    creditoVisible: boolean;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosImagenDestacada;
}

/**
 * Imagen destacada por autoridad de fuente (decisión del propietario —
 * `ADR 0006`): default "ninguna" — el cliente activa explícitamente el modo
 * que quiere, asumiendo el riesgo legal descrito en el aviso.
 */
export function BloqueImagenDestacada({ restUrl, nonce, textos }: Props) {
    const [estado, setEstado] = useState<EstadoImagenDestacada | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [enCurso, setEnCurso] = useState(false);
    const [guardado, setGuardado] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/motor/imagen-destacada`, { headers: cabeceras })
            .then((r) => r.json() as Promise<EstadoImagenDestacada>)
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

    const guardar = () => {
        if (null === estado) {
            return;
        }

        setEnCurso(true);
        setGuardado(false);
        fetch(`${restUrl}pluma/v1/motor/imagen-destacada`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ modo: estado.modo, creditoVisible: estado.creditoVisible }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                setGuardado(true);
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setEnCurso(false));
    };

    return (
        <section className="pluma-maquinas__seccion">
            <h2>{textos.titulo}</h2>
            <p>{textos.explicacion}</p>
            <p className="pluma-maquinas__aviso" role="alert">
                {textos.avisoRiesgo}
            </p>
            {null !== estado && (
                <>
                    <label className="pluma-maquinas__campo">
                        {textos.etiquetaModo}
                        <select
                            value={estado.modo}
                            onChange={(evento) => {
                                setEstado({ ...estado, modo: evento.target.value });
                                setGuardado(false);
                            }}
                        >
                            <option value="ninguna">{textos.modoNinguna}</option>
                            <option value="enlazada">{textos.modoEnlazada}</option>
                            <option value="descargada">{textos.modoDescargada}</option>
                        </select>
                    </label>
                    <label className="pluma-maquinas__campo">
                        <input
                            type="checkbox"
                            checked={estado.creditoVisible}
                            onChange={(evento) => {
                                setEstado({ ...estado, creditoVisible: evento.target.checked });
                                setGuardado(false);
                            }}
                        />
                        {textos.etiquetaCredito}
                    </label>
                    <p className="pluma-maquinas__nota">{textos.notaCredito}</p>
                    <button type="button" disabled={enCurso} onClick={guardar}>
                        {textos.guardar}
                    </button>
                    {guardado && <span className="pluma-maquinas__prueba-ok">{textos.guardado}</span>}
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
