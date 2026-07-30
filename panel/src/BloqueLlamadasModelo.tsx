import { useCallback, useEffect, useState } from 'react';

export interface TextosLlamadasModelo {
    titulo: string;
    explicacion: string;
    vacio: string;
    errorCarga: string;
    proposito: string;
    origen: string;
    resultado: string;
    llamadas: string;
    costeUsd: string;
    origenCron: string;
    origenPanel: string;
    origenVisitante: string;
}

interface ResumenLlamadaModelo {
    proposito: string;
    origen: string;
    resultado: string;
    llamadas: number;
    costeUsd: number;
    tokensEntrada: number;
    tokensSalida: number;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosLlamadasModelo;
}

const ETIQUETAS_ORIGEN: Record<string, keyof TextosLlamadasModelo> = {
    cron: 'origenCron',
    panel: 'origenPanel',
    visitante: 'origenVisitante',
};

/**
 * NCP-1 (`ADR 0010`): resumen de gasto real por propósito/origen/resultado
 * de los últimos 30 días — el instrumento de medición que hace visible en
 * el panel la exposición real del hallazgo 3 (filas con origen "visitante").
 * Mismo patrón de auto-fetch que `BloqueCreacionAutomaticaPeriodistas`, sin
 * acción de escritura: este bloque solo lee.
 */
export function BloqueLlamadasModelo({ restUrl, nonce, textos }: Props) {
    const [filas, setFilas] = useState<ResumenLlamadaModelo[] | null>(null);
    const [error, setError] = useState<string | null>(null);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/motor/llamadas-modelo`, { headers: cabeceras })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('respuesta no OK');
                }
                return r.json() as Promise<ResumenLlamadaModelo[]>;
            })
            .then((datos) => {
                setFilas(datos);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const etiquetaOrigen = (origen: string): string => {
        const clave = ETIQUETAS_ORIGEN[origen];
        return undefined === clave ? origen : textos[clave];
    };

    return (
        <section className="pluma-maquinas__seccion">
            <h2>{textos.titulo}</h2>
            <p>{textos.explicacion}</p>

            {null !== error && (
                <p className="pluma-maquinas__aviso" role="alert">
                    {error}
                </p>
            )}

            {null === error && null === filas && <p className="pluma-maquinas__cargando">{textos.explicacion}</p>}

            {null === error && null !== filas && 0 === filas.length && (
                <p className="pluma-maquinas__vacio">{textos.vacio}</p>
            )}

            {null === error && null !== filas && filas.length > 0 && (
                <table className="pluma-maquinas__tabla">
                    <thead>
                        <tr>
                            <th>{textos.proposito}</th>
                            <th>{textos.origen}</th>
                            <th>{textos.resultado}</th>
                            <th>{textos.llamadas}</th>
                            <th>{textos.costeUsd}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map((fila) => (
                            <tr key={`${fila.proposito}-${fila.origen}-${fila.resultado}`} data-origen={fila.origen}>
                                <td>{fila.proposito}</td>
                                <td>{etiquetaOrigen(fila.origen)}</td>
                                <td>{fila.resultado}</td>
                                <td>{fila.llamadas}</td>
                                <td>${fila.costeUsd.toFixed(4)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}
