import { useState } from 'react';

export interface TextosCerebroRemoto {
    titulo: string;
    urlActual: string;
    campoUrl: string;
    campoToken: string;
    guardar: string;
    probar: string;
    probando: string;
    valida: string;
    invalida: string;
    cambiar: string;
    quitar: string;
    confirmarQuitar: string;
}

interface Props {
    restUrl: string;
    nonce: string;
    configurada: boolean;
    url: string | null;
    textos: TextosCerebroRemoto;
    alGuardar: () => void;
    alError: () => void;
}

/**
 * NCP-1 · Sonda de Capacidades (`ADR 0013`): bloque de gestión del cerebro
 * remoto (T3) — mismo patrón exacto que `BloqueLlaveOpenRouter` (guardar/
 * probar/quitar), con dos campos en vez de uno (la URL no es secreta y se
 * muestra completa; el token sí, y nunca en texto plano fuera de este
 * formulario).
 */
export function BloqueCerebroRemoto({ restUrl, nonce, configurada, url, textos, alGuardar, alError }: Props) {
    const [urlNueva, setUrlNueva] = useState('');
    const [tokenNuevo, setTokenNuevo] = useState('');
    const [pruebaCerebroRemoto, setPruebaCerebroRemoto] = useState<'sin_probar' | 'probando' | 'valida' | 'invalida'>('sin_probar');
    const [enCurso, setEnCurso] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };
    const formularioValido = '' !== urlNueva.trim() && '' !== tokenNuevo.trim();

    const probarCerebroRemoto = () => {
        if (!formularioValido) {
            return;
        }

        setPruebaCerebroRemoto('probando');
        fetch(`${restUrl}pluma/v1/motor/cerebro-remoto/probar`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: urlNueva, token: tokenNuevo }),
        })
            .then((respuesta) => respuesta.json() as Promise<{ valida: boolean }>)
            .then((json) => setPruebaCerebroRemoto(json.valida ? 'valida' : 'invalida'))
            .catch(() => setPruebaCerebroRemoto('invalida'));
    };

    const guardarCerebroRemoto = () => {
        if (!formularioValido) {
            return;
        }

        setEnCurso(true);
        fetch(`${restUrl}pluma/v1/motor/cerebro-remoto`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: urlNueva, token: tokenNuevo }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                setUrlNueva('');
                setTokenNuevo('');
                setPruebaCerebroRemoto('sin_probar');
                alGuardar();
            })
            .catch(() => alError())
            .finally(() => setEnCurso(false));
    };

    const quitarCerebroRemoto = () => {
        // eslint-disable-next-line no-alert -- confirmación de una acción real.
        if (!window.confirm(textos.confirmarQuitar)) {
            return;
        }

        fetch(`${restUrl}pluma/v1/motor/cerebro-remoto`, { method: 'DELETE', headers: cabeceras })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                alGuardar();
            })
            .catch(() => alError());
    };

    return (
        <section className="pluma-maquinas__seccion">
            <h2>{textos.titulo}</h2>
            {configurada && (
                <p className="pluma-maquinas__llave-actual">
                    {textos.urlActual}: {url}
                </p>
            )}
            <label className="pluma-maquinas__campo">
                {textos.campoUrl}
                <input
                    type="text"
                    value={urlNueva}
                    onChange={(evento) => {
                        setUrlNueva(evento.target.value);
                        setPruebaCerebroRemoto('sin_probar');
                    }}
                />
            </label>
            <label className="pluma-maquinas__campo">
                {textos.campoToken}
                <input
                    type="password"
                    value={tokenNuevo}
                    onChange={(evento) => {
                        setTokenNuevo(evento.target.value);
                        setPruebaCerebroRemoto('sin_probar');
                    }}
                />
            </label>
            <div className="pluma-maquinas__llave-acciones">
                <button type="button" disabled={!formularioValido || 'probando' === pruebaCerebroRemoto} onClick={probarCerebroRemoto}>
                    {'probando' === pruebaCerebroRemoto ? textos.probando : textos.probar}
                </button>
                <button type="button" disabled={enCurso || !formularioValido} onClick={guardarCerebroRemoto}>
                    {configurada ? textos.cambiar : textos.guardar}
                </button>
                {configurada && (
                    <button type="button" className="pluma-maquinas__boton--quitar" onClick={quitarCerebroRemoto}>
                        {textos.quitar}
                    </button>
                )}
            </div>
            {'valida' === pruebaCerebroRemoto && <p className="pluma-maquinas__prueba-ok">{textos.valida}</p>}
            {'invalida' === pruebaCerebroRemoto && (
                <p className="pluma-maquinas__prueba-error" role="alert">
                    {textos.invalida}
                </p>
            )}
        </section>
    );
}
