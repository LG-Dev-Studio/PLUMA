import { useCallback, useEffect, useState } from 'react';

export interface TarjetaTendencia {
    id: number;
    termino: string;
    fuenteSenal: string;
    velocidad: number;
    afinidad: number;
    puntuacionTotal: number;
    estado: 'en_pipeline' | 'ignorada' | 'vigilada' | 'posible_actualizacion' | 'sospecha_manipulacion';
    articulosRelacionados: { titulo: string; url: string; fuente: string }[];
    detectadaEn: string;
    tendenciaOriginalId: number | null;
}

export interface TextosTendencias {
    titulo: string;
    cargando: string;
    errorCarga: string;
    errorAccion: string;
    vacio: string;
    velocidad: string;
    afinidad: string;
    total: string;
    desgloseParcial: string;
    quienCubre: string;
    nadieCubre: string;
    estadoVigilada: string;
    estadoSospechaManipulacion: string;
    cubrirAhora: string;
    ignorar: string;
    vigilar: string;
    posibleActualizacion: string;
    cubrirActualizacion: string;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosTendencias;
}

/**
 * Sala de Tendencias (Libro Cap. 10.2): "el radar en vivo". El desglose de
 * la Puntuación de Oportunidad muestra los componentes que el Radar calcula
 * HOY (velocidad y afinidad — hueco competitivo y vida útil son deuda
 * PLUMA-E1-1 del Radar) y lo declara en pantalla en vez de inventar cifras.
 */
export function PantallaTendencias({ restUrl, nonce, textos }: Props) {
    const [tarjetas, setTarjetas] = useState<TarjetaTendencia[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [accionEnCurso, setAccionEnCurso] = useState<number | null>(null);

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/tendencias`, { headers: { 'X-WP-Nonce': nonce } })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json() as Promise<TarjetaTendencia[]>;
            })
            .then((json) => {
                setTarjetas(json);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const ejecutar = (tendenciaId: number, accion: 'cubrir' | 'ignorar' | 'vigilar' | 'cubrir-actualizacion') => {
        setAccionEnCurso(tendenciaId);
        fetch(`${restUrl}pluma/v1/tendencias/${tendenciaId}/${accion}`, {
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

    if (null !== error) {
        return (
            <div className="pluma-tendencias pluma-tendencias--error" role="alert">
                {error}
            </div>
        );
    }

    if (null === tarjetas) {
        return <div className="pluma-tendencias pluma-tendencias--cargando">{textos.cargando}</div>;
    }

    return (
        <div className="pluma-tendencias">
            <h1>{textos.titulo}</h1>

            {0 === tarjetas.length ? (
                <p className="pluma-tendencias__vacio">{textos.vacio}</p>
            ) : (
                <ol className="pluma-tendencias__lista">
                    {tarjetas.map((tarjeta) => (
                        <li key={tarjeta.id} className={`pluma-tendencias__tarjeta pluma-tendencias__tarjeta--${tarjeta.estado}`}>
                            <header className="pluma-tendencias__cabecera">
                                <h2>{tarjeta.termino}</h2>
                                {'vigilada' === tarjeta.estado && (
                                    <span className="pluma-tendencias__insignia">{textos.estadoVigilada}</span>
                                )}
                                {'sospecha_manipulacion' === tarjeta.estado && (
                                    <span className="pluma-tendencias__insignia pluma-tendencias__insignia--sospecha">
                                        {textos.estadoSospechaManipulacion}
                                    </span>
                                )}
                                {'posible_actualizacion' === tarjeta.estado && (
                                    <span className="pluma-tendencias__insignia pluma-tendencias__insignia--actualizacion">
                                        {textos.posibleActualizacion}
                                    </span>
                                )}
                                <span className="pluma-tendencias__total" title={textos.total}>
                                    {tarjeta.puntuacionTotal.toFixed(0)}
                                </span>
                            </header>

                            <dl className="pluma-tendencias__desglose" aria-label={textos.desgloseParcial}>
                                <div>
                                    <dt>{textos.velocidad}</dt>
                                    <dd>{tarjeta.velocidad.toFixed(0)}</dd>
                                </div>
                                <div>
                                    <dt>{textos.afinidad}</dt>
                                    <dd>{tarjeta.afinidad.toFixed(0)}</dd>
                                </div>
                            </dl>
                            <p className="pluma-tendencias__nota">{textos.desgloseParcial}</p>

                            <div className="pluma-tendencias__cobertura">
                                <h3>{textos.quienCubre}</h3>
                                {0 === tarjeta.articulosRelacionados.length ? (
                                    <p className="pluma-tendencias__vacio">{textos.nadieCubre}</p>
                                ) : (
                                    <ul>
                                        {tarjeta.articulosRelacionados.map((articulo) => (
                                            <li key={articulo.url}>
                                                <a href={articulo.url} target="_blank" rel="noreferrer noopener">
                                                    {articulo.titulo}
                                                </a>{' '}
                                                <span className="pluma-tendencias__fuente">({articulo.fuente})</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>

                            <div className="pluma-tendencias__acciones">
                                {'posible_actualizacion' === tarjeta.estado ? (
                                    <button
                                        type="button"
                                        className="pluma-tendencias__boton pluma-tendencias__boton--cubrir"
                                        disabled={accionEnCurso === tarjeta.id}
                                        onClick={() => ejecutar(tarjeta.id, 'cubrir-actualizacion')}
                                    >
                                        {textos.cubrirActualizacion}
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        className="pluma-tendencias__boton pluma-tendencias__boton--cubrir"
                                        disabled={accionEnCurso === tarjeta.id}
                                        onClick={() => ejecutar(tarjeta.id, 'cubrir')}
                                    >
                                        {textos.cubrirAhora}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    className="pluma-tendencias__boton"
                                    disabled={accionEnCurso === tarjeta.id || 'vigilada' === tarjeta.estado}
                                    onClick={() => ejecutar(tarjeta.id, 'vigilar')}
                                >
                                    {textos.vigilar}
                                </button>
                                <button
                                    type="button"
                                    className="pluma-tendencias__boton pluma-tendencias__boton--ignorar"
                                    disabled={accionEnCurso === tarjeta.id}
                                    onClick={() => ejecutar(tarjeta.id, 'ignorar')}
                                >
                                    {textos.ignorar}
                                </button>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}
