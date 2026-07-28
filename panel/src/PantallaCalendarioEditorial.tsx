import { useCallback, useEffect, useState, type FormEvent } from 'react';

export interface EventoProgramado {
    id: number;
    titulo: string;
    vertical: string;
    fechaEsperada: string;
    estado: 'previsto' | 'preparado' | 'en_curso' | 'cubierto';
    periodistaAsignadoId: number | null;
    historiaId: number | null;
    tendenciaId: number | null;
}

export interface TextosCalendarioEditorial {
    titulo: string;
    cargando: string;
    errorCarga: string;
    errorAccion: string;
    vacio: string;
    nuevoTitulo: string;
    nuevoVertical: string;
    nuevaFecha: string;
    crear: string;
    estadoPrevisto: string;
    estadoPreparado: string;
    estadoEnCurso: string;
    estadoCubierto: string;
    prepararCobertura: string;
    fuenteTitulo: string;
    fuenteUrl: string;
    fuenteNombre: string;
    anadirFuente: string;
    confirmarPreparacion: string;
    marcarEnCurso: string;
    marcarCubierto: string;
    necesitaFuentes: string;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosCalendarioEditorial;
}

interface FuenteBorrador {
    titulo: string;
    url: string;
    fuente: string;
}

const FUENTE_VACIA: FuenteBorrador = { titulo: '', url: '', fuente: '' };

/**
 * Calendario Editorial (Nivel Cuatro V.1-V.2): "la mitad del calendario
 * noticioso se conoce con semanas de anticipación". El editor carga la
 * agenda manualmente (los sensores automáticos por vertical quedan como
 * deuda, `PLUMA-E9-2`) y, cuando ya reunió fuentes reales para un evento
 * previsto, dispara la preparación de cobertura — el pipeline normal hace
 * el resto.
 */
export function PantallaCalendarioEditorial({ restUrl, nonce, textos }: Props) {
    const [eventos, setEventos] = useState<EventoProgramado[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [accionEnCurso, setAccionEnCurso] = useState<number | null>(null);
    const [eventoEnPreparacion, setEventoEnPreparacion] = useState<number | null>(null);
    const [fuentesBorrador, setFuentesBorrador] = useState<FuenteBorrador[]>([{ ...FUENTE_VACIA }]);
    const [nuevoTitulo, setNuevoTitulo] = useState('');
    const [nuevoVertical, setNuevoVertical] = useState('');
    const [nuevaFecha, setNuevaFecha] = useState('');

    const cargar = useCallback(() => {
        fetch(`${restUrl}pluma/v1/calendario-editorial`, { headers: { 'X-WP-Nonce': nonce } })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json() as Promise<EventoProgramado[]>;
            })
            .then((json) => {
                setEventos(json);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargar();
    }, [cargar]);

    const crearEvento = (evento: FormEvent) => {
        evento.preventDefault();

        fetch(`${restUrl}pluma/v1/calendario-editorial`, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify({ titulo: nuevoTitulo, vertical: nuevoVertical, fechaEsperada: nuevaFecha }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                setNuevoTitulo('');
                setNuevoVertical('');
                setNuevaFecha('');
                cargar();
            })
            .catch(() => setError(textos.errorAccion));
    };

    const transicionar = (eventoId: number, accion: 'marcar-en-curso' | 'marcar-cubierto') => {
        setAccionEnCurso(eventoId);
        fetch(`${restUrl}pluma/v1/calendario-editorial/${eventoId}/${accion}`, {
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

    const confirmarPreparacion = (eventoId: number) => {
        const articulosRelacionados = fuentesBorrador.filter((f) => '' !== f.titulo.trim() && '' !== f.url.trim());

        if (0 === articulosRelacionados.length) {
            setError(textos.necesitaFuentes);
            return;
        }

        setAccionEnCurso(eventoId);
        fetch(`${restUrl}pluma/v1/calendario-editorial/${eventoId}/preparar`, {
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify({ articulosRelacionados }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                setEventoEnPreparacion(null);
                setFuentesBorrador([{ ...FUENTE_VACIA }]);
                cargar();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setAccionEnCurso(null));
    };

    const etiquetaEstado = (estado: EventoProgramado['estado']): string => {
        switch (estado) {
            case 'previsto':
                return textos.estadoPrevisto;
            case 'preparado':
                return textos.estadoPreparado;
            case 'en_curso':
                return textos.estadoEnCurso;
            case 'cubierto':
                return textos.estadoCubierto;
        }
    };

    if (null !== error && null === eventos) {
        return (
            <div className="pluma-calendario pluma-calendario--error" role="alert">
                {error}
            </div>
        );
    }

    if (null === eventos) {
        return <div className="pluma-calendario pluma-calendario--cargando">{textos.cargando}</div>;
    }

    return (
        <div className="pluma-calendario">
            <h1>{textos.titulo}</h1>

            {null !== error && (
                <div className="pluma-calendario__error" role="alert">
                    {error}
                </div>
            )}

            <form className="pluma-calendario__form-nuevo" onSubmit={crearEvento}>
                <label>
                    {textos.nuevoTitulo}
                    <input type="text" value={nuevoTitulo} onChange={(e) => setNuevoTitulo(e.target.value)} required />
                </label>
                <label>
                    {textos.nuevoVertical}
                    <input type="text" value={nuevoVertical} onChange={(e) => setNuevoVertical(e.target.value)} required />
                </label>
                <label>
                    {textos.nuevaFecha}
                    <input type="datetime-local" value={nuevaFecha} onChange={(e) => setNuevaFecha(e.target.value)} required />
                </label>
                <button type="submit">{textos.crear}</button>
            </form>

            {0 === eventos.length ? (
                <p className="pluma-calendario__vacio">{textos.vacio}</p>
            ) : (
                <ul className="pluma-calendario__lista">
                    {eventos.map((evento) => (
                        <li key={evento.id} className={`pluma-calendario__evento pluma-calendario__evento--${evento.estado}`}>
                            <header>
                                <h2>{evento.titulo}</h2>
                                <span className="pluma-calendario__vertical">{evento.vertical}</span>
                                <span className="pluma-calendario__estado">{etiquetaEstado(evento.estado)}</span>
                            </header>
                            <p className="pluma-calendario__fecha">{new Date(evento.fechaEsperada).toLocaleString()}</p>

                            {'previsto' === evento.estado && eventoEnPreparacion !== evento.id && (
                                <button type="button" onClick={() => setEventoEnPreparacion(evento.id)}>
                                    {textos.prepararCobertura}
                                </button>
                            )}

                            {'previsto' === evento.estado && eventoEnPreparacion === evento.id && (
                                <div className="pluma-calendario__preparacion">
                                    {fuentesBorrador.map((fuente, indice) => (
                                        // eslint-disable-next-line react/no-array-index-key -- borrador local en memoria, sin id propio hasta enviarse.
                                        <div className="pluma-calendario__fuente" key={indice}>
                                            <input
                                                type="text"
                                                placeholder={textos.fuenteTitulo}
                                                value={fuente.titulo}
                                                onChange={(e) => {
                                                    const copia = [...fuentesBorrador];
                                                    copia[indice] = { ...copia[indice], titulo: e.target.value };
                                                    setFuentesBorrador(copia);
                                                }}
                                            />
                                            <input
                                                type="url"
                                                placeholder={textos.fuenteUrl}
                                                value={fuente.url}
                                                onChange={(e) => {
                                                    const copia = [...fuentesBorrador];
                                                    copia[indice] = { ...copia[indice], url: e.target.value };
                                                    setFuentesBorrador(copia);
                                                }}
                                            />
                                            <input
                                                type="text"
                                                placeholder={textos.fuenteNombre}
                                                value={fuente.fuente}
                                                onChange={(e) => {
                                                    const copia = [...fuentesBorrador];
                                                    copia[indice] = { ...copia[indice], fuente: e.target.value };
                                                    setFuentesBorrador(copia);
                                                }}
                                            />
                                        </div>
                                    ))}
                                    <button type="button" onClick={() => setFuentesBorrador([...fuentesBorrador, { ...FUENTE_VACIA }])}>
                                        {textos.anadirFuente}
                                    </button>
                                    <button
                                        type="button"
                                        disabled={accionEnCurso === evento.id}
                                        onClick={() => confirmarPreparacion(evento.id)}
                                    >
                                        {textos.confirmarPreparacion}
                                    </button>
                                </div>
                            )}

                            {'preparado' === evento.estado && (
                                <button type="button" disabled={accionEnCurso === evento.id} onClick={() => transicionar(evento.id, 'marcar-en-curso')}>
                                    {textos.marcarEnCurso}
                                </button>
                            )}

                            {'en_curso' === evento.estado && (
                                <button type="button" disabled={accionEnCurso === evento.id} onClick={() => transicionar(evento.id, 'marcar-cubierto')}>
                                    {textos.marcarCubierto}
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
