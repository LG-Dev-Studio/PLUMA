import { useCallback, useEffect, useState } from 'react';

export interface TextosRiesgoLegal {
    titulo: string;
    explicacion: string;
    etiquetaRegimen: string;
    regimenCivil: string;
    regimenPenal: string;
    guardar: string;
    guardado: string;
    errorCarga: string;
    errorAccion: string;
}

interface EstadoRiesgoLegal {
    regimenResponsabilidad: string;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosRiesgoLegal;
}

/**
 * Perfil de riesgo legal (Nivel Tres N.1): el editor declara el régimen de
 * responsabilidad de su jurisdicción real (civil/penal) — no es un dial para
 * relajar protecciones, es un hecho sobre dónde opera el medio. Default de
 * fábrica "civil".
 */
export function BloqueRiesgoLegal({ restUrl, nonce, textos }: Props) {
    const [regimen, setRegimen] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [enCurso, setEnCurso] = useState(false);
    const [guardado, setGuardado] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/motor/riesgo-legal`, { headers: cabeceras })
            .then((r) => r.json() as Promise<EstadoRiesgoLegal>)
            .then((datos) => {
                setRegimen(datos.regimenResponsabilidad);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const guardar = () => {
        if (null === regimen) {
            return;
        }

        setEnCurso(true);
        setGuardado(false);
        fetch(`${restUrl}pluma/v1/motor/riesgo-legal`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ regimenResponsabilidad: regimen }),
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
            {null !== regimen && (
                <>
                    <label className="pluma-maquinas__campo">
                        {textos.etiquetaRegimen}
                        <select
                            value={regimen}
                            onChange={(evento) => {
                                setRegimen(evento.target.value);
                                setGuardado(false);
                            }}
                        >
                            <option value="civil">{textos.regimenCivil}</option>
                            <option value="penal">{textos.regimenPenal}</option>
                        </select>
                    </label>
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
