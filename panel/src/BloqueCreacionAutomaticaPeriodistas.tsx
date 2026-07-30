import { useCallback, useEffect, useState } from 'react';

export interface TextosCreacionAutomaticaPeriodistas {
    titulo: string;
    explicacion: string;
    activar: string;
    activada: string;
    etiquetaMinPiezas: string;
    etiquetaVentana: string;
    etiquetaCooldown: string;
    etiquetaMax: string;
    guardar: string;
    guardado: string;
    errorCarga: string;
    errorAccion: string;
}

interface EstadoCreacionAutomatica {
    activada: boolean;
    minPiezasGrupo: number;
    ventanaDias: number;
    cooldownHoras: number;
    maxPeriodistas: number;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosCreacionAutomaticaPeriodistas;
}

/**
 * Trabajo posterior a la Etapa 9: interruptor + límites configurables de la
 * creación automática de periodistas por grupo de noticias sin cobertura
 * (mismo patrón de auto-fetch que `BloqueModoRespeto`).
 */
export function BloqueCreacionAutomaticaPeriodistas({ restUrl, nonce, textos }: Props) {
    const [estado, setEstado] = useState<EstadoCreacionAutomatica | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [guardado, setGuardado] = useState(false);
    const [enCurso, setEnCurso] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' };

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/motor/creacion-automatica-periodistas`, { headers: cabeceras })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('respuesta no OK');
                }
                return r.json() as Promise<EstadoCreacionAutomatica>;
            })
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
        fetch(`${restUrl}pluma/v1/motor/creacion-automatica-periodistas`, {
            method: 'POST',
            headers: cabeceras,
            body: JSON.stringify(estado),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                setError(null);
                setGuardado(true);
                cargar();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setEnCurso(false));
    };

    if (null === estado) {
        return (
            <section className="pluma-maquinas__seccion">
                <h2>{textos.titulo}</h2>
                {null !== error && (
                    <p className="pluma-maquinas__aviso" role="alert">
                        {error}
                    </p>
                )}
            </section>
        );
    }

    return (
        <section className="pluma-maquinas__seccion">
            <h2>{textos.titulo}</h2>
            <p>{textos.explicacion}</p>

            <label className="pluma-maquinas__campo">
                <input
                    type="checkbox"
                    checked={estado.activada}
                    disabled={enCurso}
                    onChange={(evento) => setEstado({ ...estado, activada: evento.target.checked })}
                />
                {estado.activada ? textos.activada : textos.activar}
            </label>

            <label className="pluma-maquinas__campo">
                {textos.etiquetaMinPiezas}
                <input
                    type="number"
                    min={1}
                    value={estado.minPiezasGrupo}
                    onChange={(evento) => setEstado({ ...estado, minPiezasGrupo: Number(evento.target.value) })}
                />
            </label>

            <label className="pluma-maquinas__campo">
                {textos.etiquetaVentana}
                <input
                    type="number"
                    min={1}
                    value={estado.ventanaDias}
                    onChange={(evento) => setEstado({ ...estado, ventanaDias: Number(evento.target.value) })}
                />
            </label>

            <label className="pluma-maquinas__campo">
                {textos.etiquetaCooldown}
                <input
                    type="number"
                    min={1}
                    value={estado.cooldownHoras}
                    onChange={(evento) => setEstado({ ...estado, cooldownHoras: Number(evento.target.value) })}
                />
            </label>

            <label className="pluma-maquinas__campo">
                {textos.etiquetaMax}
                <input
                    type="number"
                    min={1}
                    value={estado.maxPeriodistas}
                    onChange={(evento) => setEstado({ ...estado, maxPeriodistas: Number(evento.target.value) })}
                />
            </label>

            <button type="button" disabled={enCurso} onClick={guardar}>
                {textos.guardar}
            </button>

            {guardado && <p className="pluma-maquinas__confirmacion">{textos.guardado}</p>}

            {null !== error && (
                <p className="pluma-maquinas__aviso" role="alert">
                    {error}
                </p>
            )}
        </section>
    );
}
