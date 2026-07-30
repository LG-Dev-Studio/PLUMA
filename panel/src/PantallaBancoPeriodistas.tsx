import { useCallback, useEffect, useState } from 'react';
import { CreadorPersonalizado } from './CreadorPersonalizado';
import { EstudioDeConducta } from './EstudioDeConducta';

export interface Especialidad {
    vertical: string;
    nivelDominio: number;
}

export const VERTICAL_COMODIN = '*';

export const ROLES_PERIODISTA = ['analista', 'columnista', 'cronista', 'satirico'] as const;

/**
 * Estado editable de las especialidades de un periodista: o bien el
 * comodín "cubre todos los temas" (con su propio nivel de dominio), o
 * bien una lista de especialidades por vertical — mutuamente
 * excluyentes en la UI, igual que en la validación del servidor.
 */
export interface EstadoEspecialidades {
    cubreTodosLosTemas: boolean;
    nivelDominioComodin: number;
    especialidades: Especialidad[];
}

export interface Diales {
    agudezaCritica: number;
    humor: number;
    satira: number;
    formalidad: number;
    vehemencia: number;
    empatia: number;
    densidadDatos: number;
    longitudPreferida: number;
}

export interface ReglasConducta {
    lineaEditorial: string;
    lineasRojas: string[];
    muletillas: string[];
    vocabularioProhibido: string[];
    tratamientoLector: 'tu' | 'usted';
    estiloPreguntaFinal: string;
}

export interface FilaMatrizTono {
    tipoNoticia: string;
    tonoDominante: string;
    tonoApoyo: string;
    nivelSatira: string;
}

export type MatrizTonos = Record<string, FilaMatrizTono>;

export interface MetricasPeriodista {
    piezasPublicadas: number;
    verticalesTop: string[];
}

export interface TarjetaPeriodista {
    id: number;
    nombre: string;
    avatarUrl: string | null;
    rol: string;
    especialidades: Especialidad[];
    estado: 'activo' | 'jubilado' | 'propuesto';
    metricas: MetricasPeriodista;
    // Trabajo posterior a la Etapa 9 (creación automática de periodistas):
    // solo no nulo cuando estado === 'propuesto' (calculado en el servidor,
    // mismo criterio que la cola de veto de Piezas).
    ventanaVetoExpiraEn: string | null;
}

export interface EntradaMemoria {
    tipo: 'postura' | 'cobertura' | 'audiencia';
    tema: string;
    contenido: Record<string, unknown>;
    creadaEn: string;
}

export interface DetallePeriodista {
    id: number;
    nombre: string;
    avatarUrl: string | null;
    biografia: string;
    rol: string;
    especialidades: Especialidad[];
    estado: 'activo' | 'jubilado' | 'propuesto';
    diales: Diales;
    reglasConducta: ReglasConducta;
    matrizTonos: MatrizTonos;
    respuestasHabilitadas: boolean;
    metricas: MetricasPeriodista;
    memoriaReciente: EntradaMemoria[];
    ventanaVetoExpiraEn: string | null;
}

export interface PlantillaResumen {
    slug: string;
    nombre: string;
    biografia: string;
    rol: string;
}

export interface TextosBancoPeriodistas {
    titulo: string;
    cargando: string;
    errorCarga: string;
    errorAccion: string;
    sinPeriodistas: string;
    piezasPublicadas: string;
    verticalesTop: string;
    sinVerticales: string;
    estadoActivo: string;
    estadoJubilado: string;
    estadoPropuesto: string;
    ventanaVetoRestante: string;
    aprobarAhora: string;
    descartarPropuesta: string;
    confirmarDescartarPropuesta: string;
    crearDesdePlantilla: string;
    crearPersonalizado: string;
    elegirPlantilla: string;
    nombreOpcional: string;
    crear: string;
    cancelar: string;
    jubilar: string;
    confirmarJubilar: string;
    cerrar: string;
    estudioDeConducta: string;
    identidad: string;
    nombre: string;
    biografia: string;
    avatarUrl: string;
    rol: {
        titulo: string;
        analista: string;
        columnista: string;
        cronista: string;
        satirico: string;
    };
    especialidades: {
        titulo: string;
        cubreTodosLosTemas: string;
        nivelDominioComodin: string;
        vertical: string;
        nivelDominio: string;
        anadir: string;
        eliminar: string;
        sinEspecialidades: string;
    };
    guardarIdentidad: string;
    errorIdentidad: string;
    diales: Record<keyof Diales, string> & { titulo: string };
    reglas: {
        titulo: string;
        lineaEditorial: string;
        lineasRojas: string;
        muletillas: string;
        vocabularioProhibido: string;
        tratamientoLector: string;
        tratamientoTu: string;
        tratamientoUsted: string;
        estiloPreguntaFinal: string;
        agregar: string;
    };
    matriz: {
        titulo: string;
        tipoNoticia: Record<string, string>;
        tonoDominante: string;
        tonoApoyo: string;
        nivelSatira: string;
        tono: Record<string, string>;
        satira: Record<string, string>;
        filaSistema: string;
    };
    memoria: {
        titulo: string;
        vacia: string;
        tipo: Record<string, string>;
    };
    vistaPrevia: {
        titulo: string;
        generando: string;
        errorPresupuesto: string;
        errorGeneral: string;
    };
    guardarCambios: string;
    clonar: string;
    nombreDelClon: string;
    respuestasHabilitadas: string;
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosBancoPeriodistas;
}

export function PantallaBancoPeriodistas({ restUrl, nonce, textos }: Props) {
    const [tarjetas, setTarjetas] = useState<TarjetaPeriodista[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [periodistaSeleccionado, setPeriodistaSeleccionado] = useState<number | null>(null);
    const [creandoDesdePlantilla, setCreandoDesdePlantilla] = useState(false);
    const [creandoPersonalizado, setCreandoPersonalizado] = useState(false);

    const cabeceras = { 'X-WP-Nonce': nonce };

    const cargarLista = useCallback(() => {
        fetch(`${restUrl}pluma/v1/periodistas`, { headers: cabeceras })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json() as Promise<TarjetaPeriodista[]>;
            })
            .then((json) => {
                setTarjetas(json);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [restUrl, nonce, textos.errorCarga]);

    useEffect(() => {
        cargarLista();
    }, [cargarLista]);

    const jubilar = (periodistaId: number) => {
        // eslint-disable-next-line no-alert -- confirmación de una acción real e irreversible en el banco.
        if (!window.confirm(textos.confirmarJubilar)) {
            return;
        }

        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}/jubilar`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                cargarLista();
            })
            .catch(() => setError(textos.errorAccion));
    };

    // Trabajo posterior a la Etapa 9 (creación automática de periodistas).
    const aprobarPropuestaAhora = (periodistaId: number) => {
        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}/aprobar-ahora`, {
            method: 'POST',
            headers: cabeceras,
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                cargarLista();
            })
            .catch(() => setError(textos.errorAccion));
    };

    const descartarPropuesta = (periodistaId: number) => {
        // eslint-disable-next-line no-alert -- confirmación de una acción real e irreversible en el banco.
        if (!window.confirm(textos.confirmarDescartarPropuesta)) {
            return;
        }

        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}/propuesta`, {
            method: 'DELETE',
            headers: cabeceras,
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                cargarLista();
            })
            .catch(() => setError(textos.errorAccion));
    };

    const textoEstado = (tarjeta: TarjetaPeriodista): string => {
        if ('propuesto' === tarjeta.estado) {
            return textos.estadoPropuesto;
        }

        return 'activo' === tarjeta.estado ? textos.estadoActivo : textos.estadoJubilado;
    };

    if (null !== error) {
        return (
            <div className="pluma-banco pluma-banco--error" role="alert">
                {error}
            </div>
        );
    }

    if (null === tarjetas) {
        return <div className="pluma-banco pluma-banco--cargando">{textos.cargando}</div>;
    }

    return (
        <div className="pluma-banco">
            <header className="pluma-banco__cabecera">
                <h1>{textos.titulo}</h1>
                <button type="button" onClick={() => setCreandoDesdePlantilla(true)}>
                    {textos.crearDesdePlantilla}
                </button>
                <button type="button" onClick={() => setCreandoPersonalizado(true)}>
                    {textos.crearPersonalizado}
                </button>
            </header>

            {0 === tarjetas.length ? (
                <p className="pluma-banco__vacio">{textos.sinPeriodistas}</p>
            ) : (
                <ul className="pluma-banco__grid">
                    {tarjetas.map((tarjeta) => (
                        <li key={tarjeta.id} className={`pluma-banco__tarjeta pluma-banco__tarjeta--${tarjeta.estado}`}>
                            <button type="button" className="pluma-banco__abrir" onClick={() => setPeriodistaSeleccionado(tarjeta.id)}>
                                <div className="pluma-banco__avatar" aria-hidden="true">
                                    {tarjeta.avatarUrl ? (
                                        <img src={tarjeta.avatarUrl} alt="" />
                                    ) : (
                                        <span>{tarjeta.nombre.charAt(0).toUpperCase()}</span>
                                    )}
                                </div>
                                <h2>{tarjeta.nombre}</h2>
                                <p className="pluma-banco__rol">{tarjeta.rol}</p>
                                <p className="pluma-banco__metrica">
                                    {tarjeta.metricas.piezasPublicadas} {textos.piezasPublicadas}
                                </p>
                                <p className="pluma-banco__verticales">
                                    {tarjeta.metricas.verticalesTop.length > 0 ? tarjeta.metricas.verticalesTop.join(', ') : textos.sinVerticales}
                                </p>
                                <span className="pluma-banco__estado">{textoEstado(tarjeta)}</span>
                                {'propuesto' === tarjeta.estado && null !== tarjeta.ventanaVetoExpiraEn && (
                                    <p className="pluma-banco__ventana-veto">
                                        {textos.ventanaVetoRestante} {new Date(tarjeta.ventanaVetoExpiraEn).toLocaleString()}
                                    </p>
                                )}
                            </button>
                            {'activo' === tarjeta.estado && (
                                <button type="button" className="pluma-banco__jubilar" onClick={() => jubilar(tarjeta.id)}>
                                    {textos.jubilar}
                                </button>
                            )}
                            {'propuesto' === tarjeta.estado && (
                                <div className="pluma-banco__acciones-propuesta">
                                    <button type="button" onClick={() => aprobarPropuestaAhora(tarjeta.id)}>
                                        {textos.aprobarAhora}
                                    </button>
                                    <button type="button" onClick={() => descartarPropuesta(tarjeta.id)}>
                                        {textos.descartarPropuesta}
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {creandoDesdePlantilla && (
                <SelectorPlantilla
                    restUrl={restUrl}
                    cabeceras={cabeceras}
                    textos={textos}
                    onCreado={() => {
                        setCreandoDesdePlantilla(false);
                        cargarLista();
                    }}
                    onCancelar={() => setCreandoDesdePlantilla(false)}
                />
            )}

            {creandoPersonalizado && (
                <CreadorPersonalizado
                    restUrl={restUrl}
                    cabeceras={cabeceras}
                    textos={textos}
                    onCreado={(periodistaId) => {
                        setCreandoPersonalizado(false);
                        cargarLista();
                        setPeriodistaSeleccionado(periodistaId);
                    }}
                    onCancelar={() => setCreandoPersonalizado(false)}
                />
            )}

            {null !== periodistaSeleccionado && (
                <EstudioDeConducta
                    restUrl={restUrl}
                    nonce={nonce}
                    periodistaId={periodistaSeleccionado}
                    textos={textos}
                    onCerrar={() => setPeriodistaSeleccionado(null)}
                    onCambio={cargarLista}
                />
            )}
        </div>
    );
}

interface PropsSelectorPlantilla {
    restUrl: string;
    cabeceras: Record<string, string>;
    textos: TextosBancoPeriodistas;
    onCreado: () => void;
    onCancelar: () => void;
}

function SelectorPlantilla({ restUrl, cabeceras, textos, onCreado, onCancelar }: PropsSelectorPlantilla) {
    const [plantillas, setPlantillas] = useState<PlantillaResumen[] | null>(null);
    const [slugElegido, setSlugElegido] = useState<string | null>(null);
    const [nombre, setNombre] = useState('');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetch(`${restUrl}pluma/v1/periodistas/plantillas`, { headers: cabeceras })
            .then((respuesta) => respuesta.json() as Promise<PlantillaResumen[]>)
            .then(setPlantillas)
            .catch(() => setError(textos.errorCarga));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const crear = () => {
        if (null === slugElegido) {
            return;
        }

        fetch(`${restUrl}pluma/v1/periodistas/plantilla`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({ plantilla: slugElegido, nombre }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                onCreado();
            })
            .catch(() => setError(textos.errorAccion));
    };

    return (
        <div className="pluma-banco__modal" role="dialog" aria-label={textos.elegirPlantilla}>
            <div className="pluma-banco__modal-contenido">
                <h2>{textos.elegirPlantilla}</h2>

                {null !== error && (
                    <p role="alert" className="pluma-banco__aviso">
                        {error}
                    </p>
                )}

                {null === plantillas ? (
                    <p>{textos.cargando}</p>
                ) : (
                    <ul className="pluma-banco__plantillas">
                        {plantillas.map((plantilla) => (
                            <li key={plantilla.slug}>
                                <button
                                    type="button"
                                    className={
                                        slugElegido === plantilla.slug
                                            ? 'pluma-banco__plantilla pluma-banco__plantilla--elegida'
                                            : 'pluma-banco__plantilla'
                                    }
                                    onClick={() => setSlugElegido(plantilla.slug)}
                                >
                                    <strong>{plantilla.nombre}</strong>
                                    <span>{plantilla.rol}</span>
                                    <p>{plantilla.biografia}</p>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <label>
                    {textos.nombreOpcional}
                    <input type="text" value={nombre} onChange={(evento) => setNombre(evento.target.value)} />
                </label>

                <div className="pluma-banco__modal-botones">
                    <button type="button" disabled={null === slugElegido} onClick={crear}>
                        {textos.crear}
                    </button>
                    <button type="button" onClick={onCancelar}>
                        {textos.cancelar}
                    </button>
                </div>
            </div>
        </div>
    );
}
