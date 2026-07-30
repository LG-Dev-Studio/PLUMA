import { useEffect, useMemo, useRef, useState } from 'react';
import { EditorEspecialidades } from './EditorEspecialidades';
import {
    ROLES_PERIODISTA,
    VERTICAL_COMODIN,
    type DetallePeriodista,
    type Diales,
    type Especialidad,
    type EstadoEspecialidades,
    type FilaMatrizTono,
    type MatrizTonos,
    type ReglasConducta,
    type TextosBancoPeriodistas,
} from './PantallaBancoPeriodistas';

function especialidadesAEstado(especialidades: Especialidad[]): EstadoEspecialidades {
    const comodin = especialidades.find((especialidad) => VERTICAL_COMODIN === especialidad.vertical);

    if (comodin) {
        return { cubreTodosLosTemas: true, nivelDominioComodin: comodin.nivelDominio, especialidades: [] };
    }

    return { cubreTodosLosTemas: false, nivelDominioComodin: 3, especialidades };
}

const ORDEN_DIALES: (keyof Diales)[] = [
    'agudezaCritica',
    'humor',
    'satira',
    'formalidad',
    'vehemencia',
    'empatia',
    'densidadDatos',
    'longitudPreferida',
];

const TIPOS_NOTICIA_EDITABLES = ['anuncio_corporativo', 'escandalo_politico', 'cultura_viral', 'dato_economico'];
const TONOS = ['analitico', 'critico', 'informativo_empatico', 'humoristico', 'opinion', 'persuasivo'];
const NIVELES_SATIRA_EDITABLES = ['no', 'con_moderacion', 'en_remate', 'pieza_completa'];

const DEBOUNCE_VISTA_PREVIA_MS = 800;

interface Props {
    restUrl: string;
    nonce: string;
    periodistaId: number;
    textos: TextosBancoPeriodistas;
    onCerrar: () => void;
    onCambio: () => void;
}

export function EstudioDeConducta({ restUrl, nonce, periodistaId, textos, onCerrar, onCambio }: Props) {
    const [detalle, setDetalle] = useState<DetallePeriodista | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [enCurso, setEnCurso] = useState(false);

    const [diales, setDiales] = useState<Diales | null>(null);
    const [reglas, setReglas] = useState<ReglasConducta | null>(null);
    const [matriz, setMatriz] = useState<MatrizTonos | null>(null);
    const [respuestasHabilitadas, setRespuestasHabilitadas] = useState(false);

    const [identidadNombre, setIdentidadNombre] = useState('');
    const [identidadBiografia, setIdentidadBiografia] = useState('');
    const [identidadAvatarUrl, setIdentidadAvatarUrl] = useState('');
    const [identidadRol, setIdentidadRol] = useState<(typeof ROLES_PERIODISTA)[number]>('analista');
    const [identidadEspecialidades, setIdentidadEspecialidades] = useState<EstadoEspecialidades>({
        cubreTodosLosTemas: false,
        nivelDominioComodin: 3,
        especialidades: [],
    });
    const [enCursoIdentidad, setEnCursoIdentidad] = useState(false);
    const [errorIdentidad, setErrorIdentidad] = useState<string | null>(null);

    const [vistaPrevia, setVistaPrevia] = useState<string | null>(null);
    const [vistaPreviaError, setVistaPreviaError] = useState<string | null>(null);
    const [generandoVistaPrevia, setGenerandoVistaPrevia] = useState(false);

    const cabeceras = useMemo(() => ({ 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }), [nonce]);
    const ultimaCombinacionPedida = useRef<string | null>(null);

    useEffect(() => {
        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}`, { headers: { 'X-WP-Nonce': nonce } })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json() as Promise<DetallePeriodista>;
            })
            .then((json) => {
                setDetalle(json);
                setDiales(json.diales);
                setReglas(json.reglasConducta);
                setMatriz(json.matrizTonos);
                setRespuestasHabilitadas(json.respuestasHabilitadas);
                setIdentidadNombre(json.nombre);
                setIdentidadBiografia(json.biografia);
                setIdentidadAvatarUrl(json.avatarUrl ?? '');
                setIdentidadRol(json.rol as (typeof ROLES_PERIODISTA)[number]);
                setIdentidadEspecialidades(especialidadesAEstado(json.especialidades));
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
    }, [restUrl, nonce, periodistaId, textos.errorCarga]);

    // Vista previa en vivo (Libro Cap. 10.2): debounce de 800ms tras el
    // último cambio, sin repetir la llamada si la combinación exacta de
    // diales/reglas/matriz ya se pidió — consume el mismo presupuesto
    // diario compartido con la producción real (decisión del propietario).
    useEffect(() => {
        if (null === diales || null === reglas || null === matriz || null === detalle) {
            return;
        }

        const combinacion = JSON.stringify({ diales, reglas, matriz });

        if (combinacion === ultimaCombinacionPedida.current) {
            return;
        }

        const temporizador = window.setTimeout(() => {
            ultimaCombinacionPedida.current = combinacion;
            setGenerandoVistaPrevia(true);
            setVistaPreviaError(null);

            fetch(`${restUrl}pluma/v1/periodistas/vista-previa`, {
                method: 'POST',
                headers: cabeceras,
                body: JSON.stringify({ periodistaId: detalle.id, diales, reglasConducta: reglas, matrizTonos: matriz }),
            })
                .then(async (respuesta) => {
                    if (!respuesta.ok) {
                        const cuerpo = (await respuesta.json().catch(() => null)) as { code?: string } | null;
                        throw new Error(409 === respuesta.status || 'pluma_vista_previa_no_disponible' === cuerpo?.code ? 'presupuesto' : 'general');
                    }
                    return respuesta.json() as Promise<{ texto: string }>;
                })
                .then((json) => setVistaPrevia(json.texto))
                .catch((error: Error) =>
                    setVistaPreviaError('presupuesto' === error.message ? textos.vistaPrevia.errorPresupuesto : textos.vistaPrevia.errorGeneral)
                )
                .finally(() => setGenerandoVistaPrevia(false));
        }, DEBOUNCE_VISTA_PREVIA_MS);

        return () => window.clearTimeout(temporizador);
    }, [diales, reglas, matriz, detalle, restUrl, cabeceras, textos.vistaPrevia]);

    const identidadValida =
        '' !== identidadNombre.trim() &&
        '' !== identidadBiografia.trim() &&
        (identidadEspecialidades.cubreTodosLosTemas || identidadEspecialidades.especialidades.length > 0);

    const guardarIdentidad = () => {
        setEnCursoIdentidad(true);
        setErrorIdentidad(null);

        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}`, {
            method: 'PUT',
            headers: cabeceras,
            body: JSON.stringify({
                nombre: identidadNombre,
                biografia: identidadBiografia,
                avatarUrl: '' === identidadAvatarUrl.trim() ? null : identidadAvatarUrl,
                rol: identidadRol,
                cubreTodosLosTemas: identidadEspecialidades.cubreTodosLosTemas,
                nivelDominioComodin: identidadEspecialidades.nivelDominioComodin,
                especialidades: identidadEspecialidades.especialidades,
            }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                onCambio();
            })
            .catch(() => setErrorIdentidad(textos.errorIdentidad))
            .finally(() => setEnCursoIdentidad(false));
    };

    const guardarCambios = () => {
        if (null === diales || null === reglas || null === matriz) {
            return;
        }

        setEnCurso(true);
        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}/conducta`, {
            method: 'POST',
            headers: cabeceras,
            body: JSON.stringify({ diales, reglasConducta: reglas, matrizTonos: matriz, respuestasHabilitadas }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                onCambio();
            })
            .catch(() => setError(textos.errorAccion))
            .finally(() => setEnCurso(false));
    };

    const clonar = () => {
        // eslint-disable-next-line no-alert -- captura el nombre del clon; no hay otro control nativo simple para esto.
        const nombreNuevo = window.prompt(textos.nombreDelClon);

        if (null === nombreNuevo || '' === nombreNuevo.trim()) {
            return;
        }

        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}/clonar`, {
            method: 'POST',
            headers: cabeceras,
            body: JSON.stringify({ nombre: nombreNuevo }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                onCambio();
            })
            .catch(() => setError(textos.errorAccion));
    };

    const jubilar = () => {
        // eslint-disable-next-line no-alert -- confirmación de una acción real e irreversible en el banco.
        if (!window.confirm(textos.confirmarJubilar)) {
            return;
        }

        fetch(`${restUrl}pluma/v1/periodistas/${periodistaId}/jubilar`, {
            method: 'POST',
            headers: cabeceras,
            body: JSON.stringify({}),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                onCambio();
                onCerrar();
            })
            .catch(() => setError(textos.errorAccion));
    };

    if (null !== error && null === detalle) {
        return (
            <div className="pluma-estudio" role="alert">
                {error}
                <button type="button" onClick={onCerrar}>
                    {textos.cerrar}
                </button>
            </div>
        );
    }

    if (null === detalle || null === diales || null === reglas || null === matriz) {
        return <div className="pluma-estudio">{textos.cargando}</div>;
    }

    const filaTragedia = matriz.tragedia;

    return (
        <div className="pluma-estudio" role="dialog" aria-label={detalle.nombre}>
            <header className="pluma-estudio__cabecera">
                <h2>{detalle.nombre}</h2>
                <button type="button" onClick={onCerrar}>
                    {textos.cerrar}
                </button>
            </header>

            {null !== error && (
                <p role="alert" className="pluma-estudio__aviso">
                    {error}
                </p>
            )}

            <section className="pluma-estudio__seccion">
                <h3>{textos.identidad}</h3>

                {null !== errorIdentidad && (
                    <p role="alert" className="pluma-estudio__aviso">
                        {errorIdentidad}
                    </p>
                )}

                <label className="pluma-estudio__campo">
                    {textos.nombre}
                    <input type="text" value={identidadNombre} onChange={(evento) => setIdentidadNombre(evento.target.value)} />
                </label>

                <label className="pluma-estudio__campo">
                    {textos.biografia}
                    <textarea value={identidadBiografia} onChange={(evento) => setIdentidadBiografia(evento.target.value)} />
                </label>

                <label className="pluma-estudio__campo">
                    {textos.avatarUrl}
                    <input type="text" value={identidadAvatarUrl} onChange={(evento) => setIdentidadAvatarUrl(evento.target.value)} />
                </label>

                <label className="pluma-estudio__campo">
                    {textos.rol.titulo}
                    <select
                        value={identidadRol}
                        onChange={(evento) => setIdentidadRol(evento.target.value as (typeof ROLES_PERIODISTA)[number])}
                    >
                        {ROLES_PERIODISTA.map((valorRol) => (
                            <option key={valorRol} value={valorRol}>
                                {textos.rol[valorRol]}
                            </option>
                        ))}
                    </select>
                </label>

                <EditorEspecialidades valor={identidadEspecialidades} textos={textos.especialidades} onCambiar={setIdentidadEspecialidades} />

                <button type="button" disabled={!identidadValida || enCursoIdentidad} onClick={guardarIdentidad}>
                    {textos.guardarIdentidad}
                </button>
            </section>

            <section className="pluma-estudio__vista-previa">
                <h3>{textos.vistaPrevia.titulo}</h3>
                {generandoVistaPrevia && <p className="pluma-estudio__generando">{textos.vistaPrevia.generando}</p>}
                {null !== vistaPreviaError && (
                    <p role="alert" className="pluma-estudio__aviso">
                        {vistaPreviaError}
                    </p>
                )}
                {null !== vistaPrevia && !generandoVistaPrevia && null === vistaPreviaError && (
                    <p className="pluma-estudio__parrafo">{vistaPrevia}</p>
                )}
            </section>

            <section className="pluma-estudio__seccion">
                <h3>{textos.diales.titulo}</h3>
                {ORDEN_DIALES.map((clave) => (
                    <label key={clave} className="pluma-estudio__dial">
                        <span>
                            {textos.diales[clave]} — {diales[clave]}
                        </span>
                        <input
                            type="range"
                            min={0}
                            max={100}
                            value={diales[clave]}
                            onChange={(evento) => setDiales({ ...diales, [clave]: Number(evento.target.value) })}
                        />
                    </label>
                ))}
            </section>

            <section className="pluma-estudio__seccion">
                <h3>{textos.reglas.titulo}</h3>
                <label className="pluma-estudio__campo">
                    {textos.reglas.lineaEditorial}
                    <textarea
                        value={reglas.lineaEditorial}
                        onChange={(evento) => setReglas({ ...reglas, lineaEditorial: evento.target.value })}
                    />
                </label>

                <ListaEditable
                    titulo={textos.reglas.lineasRojas}
                    valores={reglas.lineasRojas}
                    textoAgregar={textos.reglas.agregar}
                    onCambiar={(valores) => setReglas({ ...reglas, lineasRojas: valores })}
                />
                <ListaEditable
                    titulo={textos.reglas.muletillas}
                    valores={reglas.muletillas}
                    textoAgregar={textos.reglas.agregar}
                    onCambiar={(valores) => setReglas({ ...reglas, muletillas: valores })}
                />
                <ListaEditable
                    titulo={textos.reglas.vocabularioProhibido}
                    valores={reglas.vocabularioProhibido}
                    textoAgregar={textos.reglas.agregar}
                    onCambiar={(valores) => setReglas({ ...reglas, vocabularioProhibido: valores })}
                />

                <label className="pluma-estudio__campo">
                    {textos.reglas.tratamientoLector}
                    <select
                        value={reglas.tratamientoLector}
                        onChange={(evento) => setReglas({ ...reglas, tratamientoLector: evento.target.value as 'tu' | 'usted' })}
                    >
                        <option value="tu">{textos.reglas.tratamientoTu}</option>
                        <option value="usted">{textos.reglas.tratamientoUsted}</option>
                    </select>
                </label>

                <label className="pluma-estudio__campo">
                    {textos.reglas.estiloPreguntaFinal}
                    <input
                        type="text"
                        value={reglas.estiloPreguntaFinal}
                        onChange={(evento) => setReglas({ ...reglas, estiloPreguntaFinal: evento.target.value })}
                    />
                </label>

                <label className="pluma-estudio__campo pluma-estudio__campo--checkbox">
                    <input
                        type="checkbox"
                        checked={respuestasHabilitadas}
                        onChange={(evento) => setRespuestasHabilitadas(evento.target.checked)}
                    />
                    {textos.respuestasHabilitadas}
                </label>
            </section>

            <section className="pluma-estudio__seccion">
                <h3>{textos.matriz.titulo}</h3>

                {filaTragedia && (
                    <div className="pluma-estudio__fila-matriz pluma-estudio__fila-matriz--sistema">
                        <strong>{textos.matriz.tipoNoticia.tragedia ?? 'tragedia'}</strong>
                        <span>{textos.matriz.filaSistema}</span>
                    </div>
                )}

                {TIPOS_NOTICIA_EDITABLES.map((tipo) => {
                    const fila: FilaMatrizTono = matriz[tipo] ?? {
                        tipoNoticia: tipo,
                        tonoDominante: 'analitico',
                        tonoApoyo: 'persuasivo',
                        nivelSatira: 'no',
                    };

                    const actualizarFila = (cambios: Partial<FilaMatrizTono>) => {
                        setMatriz({ ...matriz, [tipo]: { ...fila, ...cambios } });
                    };

                    return (
                        <div key={tipo} className="pluma-estudio__fila-matriz">
                            <strong>{textos.matriz.tipoNoticia[tipo] ?? tipo}</strong>
                            <label>
                                {textos.matriz.tonoDominante}
                                <select value={fila.tonoDominante} onChange={(evento) => actualizarFila({ tonoDominante: evento.target.value })}>
                                    {TONOS.map((tono) => (
                                        <option key={tono} value={tono}>
                                            {textos.matriz.tono[tono] ?? tono}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                {textos.matriz.tonoApoyo}
                                <select value={fila.tonoApoyo} onChange={(evento) => actualizarFila({ tonoApoyo: evento.target.value })}>
                                    {TONOS.map((tono) => (
                                        <option key={tono} value={tono}>
                                            {textos.matriz.tono[tono] ?? tono}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                {textos.matriz.nivelSatira}
                                <select value={fila.nivelSatira} onChange={(evento) => actualizarFila({ nivelSatira: evento.target.value })}>
                                    {NIVELES_SATIRA_EDITABLES.map((nivel) => (
                                        <option key={nivel} value={nivel}>
                                            {textos.matriz.satira[nivel] ?? nivel}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                    );
                })}
            </section>

            <section className="pluma-estudio__seccion">
                <h3>{textos.memoria.titulo}</h3>
                {0 === detalle.memoriaReciente.length ? (
                    <p className="pluma-estudio__vacio">{textos.memoria.vacia}</p>
                ) : (
                    <ul className="pluma-estudio__memoria">
                        {detalle.memoriaReciente.map((entrada, indice) => (
                            // eslint-disable-next-line react/no-array-index-key -- la memoria no expone un id propio en esta respuesta.
                            <li key={indice}>
                                <span className="pluma-estudio__memoria-tipo">{textos.memoria.tipo[entrada.tipo] ?? entrada.tipo}</span>
                                <strong>{entrada.tema}</strong>
                                <p>
                                    {Object.entries(entrada.contenido)
                                        .map(([clave, valor]) => `${clave}: ${String(valor)}`)
                                        .join(' · ')}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="pluma-estudio__seccion pluma-estudio__acciones">
                <button type="button" disabled={enCurso} onClick={guardarCambios}>
                    {textos.guardarCambios}
                </button>
                <button type="button" onClick={clonar}>
                    {textos.clonar}
                </button>
                {'activo' === detalle.estado && (
                    <button type="button" className="pluma-estudio__boton--jubilar" onClick={jubilar}>
                        {textos.jubilar}
                    </button>
                )}
            </section>
        </div>
    );
}

interface PropsListaEditable {
    titulo: string;
    valores: string[];
    textoAgregar: string;
    onCambiar: (valores: string[]) => void;
}

function ListaEditable({ titulo, valores, textoAgregar, onCambiar }: PropsListaEditable) {
    const [nuevoValor, setNuevoValor] = useState('');

    const agregar = () => {
        if ('' === nuevoValor.trim()) {
            return;
        }

        onCambiar([...valores, nuevoValor.trim()]);
        setNuevoValor('');
    };

    const quitar = (indice: number) => {
        onCambiar(valores.filter((_valor, i) => i !== indice));
    };

    return (
        <div className="pluma-estudio__lista-editable">
            <h4>{titulo}</h4>
            <ul>
                {valores.map((valor, indice) => (
                    // eslint-disable-next-line react/no-array-index-key -- lista simple de strings, sin id propio.
                    <li key={indice}>
                        {valor}
                        <button type="button" aria-label={`quitar ${valor}`} onClick={() => quitar(indice)}>
                            ×
                        </button>
                    </li>
                ))}
            </ul>
            <div className="pluma-estudio__lista-editable-agregar">
                <input type="text" value={nuevoValor} onChange={(evento) => setNuevoValor(evento.target.value)} />
                <button type="button" onClick={agregar}>
                    {textoAgregar}
                </button>
            </div>
        </div>
    );
}
