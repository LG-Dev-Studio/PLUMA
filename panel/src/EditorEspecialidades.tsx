import type { Especialidad, EstadoEspecialidades, TextosBancoPeriodistas } from './PantallaBancoPeriodistas';

interface Props {
    valor: EstadoEspecialidades;
    textos: TextosBancoPeriodistas['especialidades'];
    onCambiar: (valor: EstadoEspecialidades) => void;
}

export function EditorEspecialidades({ valor, textos, onCambiar }: Props) {
    const alternarComodin = (activo: boolean) => {
        onCambiar({ ...valor, cubreTodosLosTemas: activo });
    };

    const actualizarFila = (indice: number, cambios: Partial<Especialidad>) => {
        onCambiar({
            ...valor,
            especialidades: valor.especialidades.map((fila, i) => (i === indice ? { ...fila, ...cambios } : fila)),
        });
    };

    const anadir = () => {
        onCambiar({ ...valor, especialidades: [...valor.especialidades, { vertical: '', nivelDominio: 3 }] });
    };

    const eliminar = (indice: number) => {
        onCambiar({ ...valor, especialidades: valor.especialidades.filter((_fila, i) => i !== indice) });
    };

    return (
        <div className="pluma-especialidades">
            <h4>{textos.titulo}</h4>

            <label className="pluma-especialidades__comodin">
                <input
                    type="checkbox"
                    checked={valor.cubreTodosLosTemas}
                    onChange={(evento) => alternarComodin(evento.target.checked)}
                />
                {textos.cubreTodosLosTemas}
            </label>

            {valor.cubreTodosLosTemas ? (
                <label className="pluma-especialidades__nivel-comodin">
                    {textos.nivelDominioComodin}
                    <input
                        type="number"
                        min={1}
                        max={5}
                        value={valor.nivelDominioComodin}
                        onChange={(evento) => onCambiar({ ...valor, nivelDominioComodin: Number(evento.target.value) })}
                    />
                </label>
            ) : (
                <>
                    <ul className="pluma-especialidades__lista">
                        {valor.especialidades.map((fila, indice) => (
                            // eslint-disable-next-line react/no-array-index-key -- filas sin id propio, orden estable mientras se edita.
                            <li key={indice} className="pluma-especialidades__fila">
                                <label>
                                    {textos.vertical}
                                    <input
                                        type="text"
                                        value={fila.vertical}
                                        onChange={(evento) => actualizarFila(indice, { vertical: evento.target.value })}
                                    />
                                </label>
                                <label>
                                    {textos.nivelDominio}
                                    <input
                                        type="number"
                                        min={1}
                                        max={5}
                                        value={fila.nivelDominio}
                                        onChange={(evento) => actualizarFila(indice, { nivelDominio: Number(evento.target.value) })}
                                    />
                                </label>
                                <button type="button" onClick={() => eliminar(indice)}>
                                    {textos.eliminar}
                                </button>
                            </li>
                        ))}
                    </ul>
                    <button type="button" onClick={anadir}>
                        {textos.anadir}
                    </button>
                    {0 === valor.especialidades.length && <p className="pluma-especialidades__aviso">{textos.sinEspecialidades}</p>}
                </>
            )}
        </div>
    );
}
