import { useState } from 'react';
import { EditorEspecialidades } from './EditorEspecialidades';
import { LOCALES_EDITORIALES, ROLES_PERIODISTA, type EstadoEspecialidades, type TextosBancoPeriodistas } from './PantallaBancoPeriodistas';

interface Props {
    restUrl: string;
    cabeceras: Record<string, string>;
    textos: TextosBancoPeriodistas;
    onCreado: (periodistaId: number) => void;
    onCancelar: () => void;
}

/**
 * Creación de un periodista con identidad y especialidades reales,
 * alternativa a `SelectorPlantilla` (que sigue siendo el camino rápido de
 * las 4 plantillas fijas). Al crearse con éxito, el llamador abre el
 * Estudio de Conducta del periodista recién creado para que el editor
 * ajuste su temperamento a partir de la conducta neutra de partida.
 */
export function CreadorPersonalizado({ restUrl, cabeceras, textos, onCreado, onCancelar }: Props) {
    const [nombre, setNombre] = useState('');
    const [biografia, setBiografia] = useState('');
    const [avatarUrl, setAvatarUrl] = useState('');
    const [rol, setRol] = useState<(typeof ROLES_PERIODISTA)[number]>('analista');
    const [localeEditorial, setLocaleEditorial] = useState<(typeof LOCALES_EDITORIALES)[number]>('es-ES');
    const [especialidades, setEspecialidades] = useState<EstadoEspecialidades>({
        cubreTodosLosTemas: false,
        nivelDominioComodin: 3,
        especialidades: [],
    });
    const [error, setError] = useState<string | null>(null);
    const [enCurso, setEnCurso] = useState(false);

    const formularioValido =
        '' !== nombre.trim() &&
        '' !== biografia.trim() &&
        (especialidades.cubreTodosLosTemas || especialidades.especialidades.length > 0);

    const crear = () => {
        setEnCurso(true);
        setError(null);

        fetch(`${restUrl}pluma/v1/periodistas`, {
            method: 'POST',
            headers: { ...cabeceras, 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre,
                biografia,
                avatarUrl: '' === avatarUrl.trim() ? null : avatarUrl,
                rol,
                localeEditorial,
                cubreTodosLosTemas: especialidades.cubreTodosLosTemas,
                nivelDominioComodin: especialidades.nivelDominioComodin,
                especialidades: especialidades.especialidades,
            }),
        })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json() as Promise<{ id: number }>;
            })
            .then((json) => onCreado(json.id))
            .catch(() => setError(textos.errorIdentidad))
            .finally(() => setEnCurso(false));
    };

    return (
        <div className="pluma-banco__modal" role="dialog" aria-label={textos.crearPersonalizado}>
            <div className="pluma-banco__modal-contenido">
                <h2>{textos.crearPersonalizado}</h2>

                {null !== error && (
                    <p role="alert" className="pluma-banco__aviso">
                        {error}
                    </p>
                )}

                <label>
                    {textos.nombre}
                    <input type="text" value={nombre} onChange={(evento) => setNombre(evento.target.value)} />
                </label>

                <label>
                    {textos.biografia}
                    <textarea value={biografia} onChange={(evento) => setBiografia(evento.target.value)} />
                </label>

                <label>
                    {textos.avatarUrl}
                    <input type="text" value={avatarUrl} onChange={(evento) => setAvatarUrl(evento.target.value)} />
                </label>

                <label>
                    {textos.rol.titulo}
                    <select value={rol} onChange={(evento) => setRol(evento.target.value as (typeof ROLES_PERIODISTA)[number])}>
                        {ROLES_PERIODISTA.map((valorRol) => (
                            <option key={valorRol} value={valorRol}>
                                {textos.rol[valorRol]}
                            </option>
                        ))}
                    </select>
                </label>

                <label>
                    {textos.locale.titulo}
                    <select
                        value={localeEditorial}
                        onChange={(evento) => setLocaleEditorial(evento.target.value as (typeof LOCALES_EDITORIALES)[number])}
                    >
                        {LOCALES_EDITORIALES.map((valorLocale) => (
                            <option key={valorLocale} value={valorLocale}>
                                {textos.locale[valorLocale]}
                            </option>
                        ))}
                    </select>
                </label>

                <EditorEspecialidades valor={especialidades} textos={textos.especialidades} onCambiar={setEspecialidades} />

                <div className="pluma-banco__modal-botones">
                    <button type="button" disabled={!formularioValido || enCurso} onClick={crear}>
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
