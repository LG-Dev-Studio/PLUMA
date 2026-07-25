import { useCallback, useEffect, useState } from 'react';

export interface TextosModeloVerificador {
    titulo: string;
    explicacion: string;
    etiquetaModelo: string;
    guardar: string;
    guardado: string;
    notaAlcance: string;
    errorCarga: string;
    errorAccion: string;
}

interface EstadoModeloVerificador {
    modeloVerificador: string;
    obligatoriedadDeFabrica: boolean;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosModeloVerificador;
}

/**
 * Modelo verificador (Nivel Tres J.1-J.2): el editor puede declarar un
 * modelo distinto al premium para el Corrector Interno. Nota de alcance
 * siempre visible: hoy es informativo, la obligatoriedad dura en Autónomo
 * espera validación empírica en Piloto (ADR 0003).
 */
export function BloqueModeloVerificador({ restUrl, nonce, textos }: Props) {
    const [modelo, setModelo] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [enCurso, setEnCurso] = useState(false);
    const [guardado, setGuardado] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/motor/modelo-verificador`, { headers: cabeceras })
            .then((r) => r.json() as Promise<EstadoModeloVerificador>)
            .then((datos) => {
                setModelo(datos.modeloVerificador);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const guardar = () => {
        if (null === modelo) {
            return;
        }

        setEnCurso(true);
        setGuardado(false);
        fetch(`${restUrl}pluma/v1/motor/modelo-verificador`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ modeloVerificador: modelo }),
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
            {null !== modelo && (
                <>
                    <label className="pluma-maquinas__campo">
                        {textos.etiquetaModelo}
                        <input
                            type="text"
                            value={modelo}
                            onChange={(evento) => {
                                setModelo(evento.target.value);
                                setGuardado(false);
                            }}
                        />
                    </label>
                    <button type="button" disabled={enCurso} onClick={guardar}>
                        {textos.guardar}
                    </button>
                    {guardado && <span className="pluma-maquinas__prueba-ok">{textos.guardado}</span>}
                </>
            )}
            <p className="pluma-maquinas__nota-fabrica">
                <small>{textos.notaAlcance}</small>
            </p>
            {null !== error && (
                <p className="pluma-maquinas__aviso" role="alert">
                    {error}
                </p>
            )}
        </section>
    );
}
