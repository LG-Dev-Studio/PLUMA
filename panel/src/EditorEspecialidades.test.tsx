import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { EditorEspecialidades } from './EditorEspecialidades';
import type { EstadoEspecialidades, TextosBancoPeriodistas } from './PantallaBancoPeriodistas';

function textosDeEjemplo(): TextosBancoPeriodistas['especialidades'] {
    return {
        titulo: 'Especialidades',
        cubreTodosLosTemas: 'Cubre todos los temas',
        nivelDominioComodin: 'Nivel de dominio general',
        vertical: 'Vertical',
        nivelDominio: 'Nivel de dominio (1-5)',
        anadir: 'Añadir especialidad',
        eliminar: 'Eliminar',
        sinEspecialidades: 'Declara al menos una especialidad.',
    };
}

function estadoVacio(): EstadoEspecialidades {
    return { cubreTodosLosTemas: false, nivelDominioComodin: 3, especialidades: [] };
}

describe('EditorEspecialidades', () => {
    it('muestra el aviso de "sin especialidades" cuando la lista está vacía y el comodín está apagado', () => {
        render(<EditorEspecialidades valor={estadoVacio()} textos={textosDeEjemplo()} onCambiar={vi.fn()} />);

        expect(screen.getByText('Declara al menos una especialidad.')).toBeInTheDocument();
    });

    it('al activar "cubre todos los temas" oculta las filas por vertical y notifica el cambio', async () => {
        const usuario = userEvent.setup();
        const onCambiar = vi.fn();

        render(<EditorEspecialidades valor={estadoVacio()} textos={textosDeEjemplo()} onCambiar={onCambiar} />);

        await usuario.click(screen.getByLabelText('Cubre todos los temas'));

        expect(onCambiar).toHaveBeenCalledWith({ cubreTodosLosTemas: true, nivelDominioComodin: 3, especialidades: [] });
    });

    it('al añadir una especialidad agrega una fila con valores por defecto', async () => {
        const usuario = userEvent.setup();
        const onCambiar = vi.fn();

        render(<EditorEspecialidades valor={estadoVacio()} textos={textosDeEjemplo()} onCambiar={onCambiar} />);

        await usuario.click(screen.getByText('Añadir especialidad'));

        expect(onCambiar).toHaveBeenCalledWith({
            cubreTodosLosTemas: false,
            nivelDominioComodin: 3,
            especialidades: [{ vertical: '', nivelDominio: 3 }],
        });
    });

    it('al editar el vertical de una fila notifica solo esa fila cambiada', () => {
        const onCambiar = vi.fn();
        const estado: EstadoEspecialidades = {
            cubreTodosLosTemas: false,
            nivelDominioComodin: 3,
            especialidades: [
                { vertical: 'economia', nivelDominio: 3 },
                { vertical: 'tecnologia', nivelDominio: 4 },
            ],
        };

        render(<EditorEspecialidades valor={estado} textos={textosDeEjemplo()} onCambiar={onCambiar} />);

        fireEvent.change(screen.getAllByLabelText('Vertical')[1], { target: { value: 'deportes' } });

        expect(onCambiar).toHaveBeenCalledWith({
            cubreTodosLosTemas: false,
            nivelDominioComodin: 3,
            especialidades: [
                { vertical: 'economia', nivelDominio: 3 },
                { vertical: 'deportes', nivelDominio: 4 },
            ],
        });
    });

    it('al eliminar una fila la quita de la lista', async () => {
        const usuario = userEvent.setup();
        const onCambiar = vi.fn();
        const estado: EstadoEspecialidades = {
            cubreTodosLosTemas: false,
            nivelDominioComodin: 3,
            especialidades: [
                { vertical: 'economia', nivelDominio: 3 },
                { vertical: 'tecnologia', nivelDominio: 4 },
            ],
        };

        render(<EditorEspecialidades valor={estado} textos={textosDeEjemplo()} onCambiar={onCambiar} />);

        await usuario.click(screen.getAllByText('Eliminar')[0]);

        expect(onCambiar).toHaveBeenCalledWith({
            cubreTodosLosTemas: false,
            nivelDominioComodin: 3,
            especialidades: [{ vertical: 'tecnologia', nivelDominio: 4 }],
        });
    });

    it('con el comodín activo permite ajustar su nivel de dominio', () => {
        const onCambiar = vi.fn();
        const estado: EstadoEspecialidades = { cubreTodosLosTemas: true, nivelDominioComodin: 3, especialidades: [] };

        render(<EditorEspecialidades valor={estado} textos={textosDeEjemplo()} onCambiar={onCambiar} />);

        fireEvent.change(screen.getByLabelText('Nivel de dominio general'), { target: { value: '5' } });

        expect(onCambiar).toHaveBeenCalledWith({ cubreTodosLosTemas: true, nivelDominioComodin: 5, especialidades: [] });
    });
});
