import { useCallback, useEffect, useState } from 'react';

export interface TextosTransparencia {
    titulo: string;
    explicacion: string;
    etiquetaFormato: string;
    formatoBreve: string;
    formatoExtendido: string;
    guardar: string;
    guardado: string;
    marcadoDeFabrica: string;
    errorCarga: string;
    errorAccion: string;
}

interface EstadoTransparencia {
    formato: string;
    marcadoIaDeFabrica: boolean;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosTransparencia;
}

/**
 * Transparencia y cumplimiento (Art. 50 UE, Nivel Tres N.3). El editor
 * configura SOLO el formato del bloque visible; el marcado legible por
 * máquina es piso de fábrica y se muestra como nota de solo lectura, sin
 * interruptor (ADR 0002).
 */
export function BloqueTransparencia({ restUrl, nonce, textos }: Props) {
    const [formato, setFormato] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [enCurso, setEnCurso] = useState(false);
    const [guardado, setGuardado] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/motor/transparencia`, { headers: cabeceras })
            .then((r) => r.json() as Promise<EstadoTransparencia>)
            .then((datos) => {
                setFormato(datos.formato);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const guardar = () => {
        if (null === formato) {
            return;
        }

        setEnCurso(true);
        setGuardado(false);
        fetch(`${restUrl}pluma/v1/motor/transparencia`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ formato }),
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
            {null !== formato && (
                <>
                    <label className="pluma-maquinas__campo">
                        {textos.etiquetaFormato}
                        <select
                            value={formato}
                            onChange={(evento) => {
                                setFormato(evento.target.value);
                                setGuardado(false);
                            }}
                        >
                            <option value="breve">{textos.formatoBreve}</option>
                            <option value="extendido">{textos.formatoExtendido}</option>
                        </select>
                    </label>
                    <button type="button" disabled={enCurso} onClick={guardar}>
                        {textos.guardar}
                    </button>
                    {guardado && <span className="pluma-maquinas__prueba-ok">{textos.guardado}</span>}
                </>
            )}
            <p className="pluma-maquinas__nota-fabrica">
                <small>{textos.marcadoDeFabrica}</small>
            </p>
            {null !== error && (
                <p className="pluma-maquinas__aviso" role="alert">
                    {error}
                </p>
            )}
        </section>
    );
}
