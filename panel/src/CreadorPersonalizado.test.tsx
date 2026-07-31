import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CreadorPersonalizado } from './CreadorPersonalizado';
import type { TextosBancoPeriodistas } from './PantallaBancoPeriodistas';

function textosDeEjemplo(): TextosBancoPeriodistas {
    return {
        titulo: 'Banco de Periodistas',
        cargando: 'Cargando…',
        errorCarga: 'No se pudo cargar.',
        errorAccion: 'No se pudo completar.',
        sinPeriodistas: 'sin periodistas',
        piezasPublicadas: 'piezas publicadas',
        verticalesTop: 'Verticales',
        sinVerticales: 'sin verticales',
        estadoActivo: 'Activo',
        estadoJubilado: 'Jubilado',
        estadoPropuesto: 'Propuesto',
        ventanaVetoRestante: 'Se activa solo en',
        aprobarAhora: 'Aprobar ahora',
        descartarPropuesta: 'Descartar',
        confirmarDescartarPropuesta: '¿Descartar esta propuesta?',
        crearDesdePlantilla: 'Crear desde plantilla',
        crearPersonalizado: 'Crear personalizado',
        elegirPlantilla: 'Elegir plantilla',
        nombreOpcional: 'Nombre (opcional)',
        crear: 'Crear',
        cancelar: 'Cancelar',
        jubilar: 'Jubilar',
        confirmarJubilar: '¿Jubilar?',
        cerrar: 'Cerrar',
        estudioDeConducta: 'Estudio de Conducta',
        identidad: 'Identidad',
        nombre: 'Nombre',
        biografia: 'Biografía',
        avatarUrl: 'URL del avatar (opcional)',
        rol: {
            titulo: 'Rol',
            analista: 'Analista',
            columnista: 'Columnista',
            cronista: 'Cronista',
            satirico: 'Satírico',
        },
        especialidades: {
            titulo: 'Especialidades',
            cubreTodosLosTemas: 'Cubre todos los temas',
            nivelDominioComodin: 'Nivel de dominio general',
            vertical: 'Vertical',
            nivelDominio: 'Nivel de dominio (1-5)',
            anadir: 'Añadir especialidad',
            eliminar: 'Eliminar',
            sinEspecialidades: 'Declara al menos una especialidad.',
        },
        guardarIdentidad: 'Guardar identidad',
        errorIdentidad: 'No se pudo guardar la identidad.',
        diales: {
            titulo: 'Diales',
            agudezaCritica: 'Agudeza crítica',
            humor: 'Humor',
            satira: 'Sátira',
            formalidad: 'Formalidad',
            vehemencia: 'Vehemencia',
            empatia: 'Empatía',
            densidadDatos: 'Densidad de datos',
            longitudPreferida: 'Longitud preferida',
        },
        reglas: {
            titulo: 'Reglas',
            lineaEditorial: 'Línea editorial',
            lineasRojas: 'Líneas rojas',
            muletillas: 'Muletillas',
            vocabularioProhibido: 'Vocabulario prohibido',
            tratamientoLector: 'Trato al lector',
            tratamientoTu: 'De tú',
            tratamientoUsted: 'De usted',
            estiloPreguntaFinal: 'Estilo de pregunta final',
            agregar: 'Agregar',
        },
        matriz: {
            titulo: 'Matriz',
            tipoNoticia: {},
            tonoDominante: 'Tono dominante',
            tonoApoyo: 'Tono de apoyo',
            nivelSatira: 'Sátira permitida',
            tono: {},
            satira: {},
            filaSistema: 'Regla de sistema.',
        },
        memoria: { titulo: 'Memoria', vacia: 'sin memoria', tipo: {} },
        vistaPrevia: { titulo: 'Vista previa', generando: 'Redactando…', errorPresupuesto: 'Presupuesto agotado.', errorGeneral: 'Error.' },
        guardarCambios: 'Guardar cambios',
        clonar: 'Clonar',
        nombreDelClon: 'Nombre del clon',
    };
}

describe('CreadorPersonalizado', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('deshabilita el botón Crear mientras el formulario está incompleto', () => {
        render(
            <CreadorPersonalizado restUrl="https://ejemplo.test/wp-json/" cabeceras={{}} textos={textosDeEjemplo()} onCreado={vi.fn()} onCancelar={vi.fn()} />
        );

        expect(screen.getByRole('button', { name: 'Crear' })).toBeDisabled();
    });

    it('habilita Crear cuando hay nombre, biografía y al menos una especialidad', async () => {
        const usuario = userEvent.setup();

        render(
            <CreadorPersonalizado restUrl="https://ejemplo.test/wp-json/" cabeceras={{}} textos={textosDeEjemplo()} onCreado={vi.fn()} onCancelar={vi.fn()} />
        );

        await usuario.type(screen.getByLabelText('Nombre'), 'X');
        await usuario.type(screen.getByLabelText('Biografía'), 'Y');

        expect(screen.getByRole('button', { name: 'Crear' })).toBeDisabled();

        await usuario.click(screen.getByLabelText('Cubre todos los temas'));

        expect(screen.getByRole('button', { name: 'Crear' })).toBeEnabled();
    });

    it('al enviar con éxito llama a onCreado con el id devuelto', async () => {
        const usuario = userEvent.setup();
        const onCreado = vi.fn();
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 42 }) }))
        );

        render(
            <CreadorPersonalizado
                restUrl="https://ejemplo.test/wp-json/"
                cabeceras={{}}
                textos={textosDeEjemplo()}
                onCreado={onCreado}
                onCancelar={vi.fn()}
            />
        );

        await usuario.type(screen.getByLabelText('Nombre'), 'X');
        await usuario.type(screen.getByLabelText('Biografía'), 'Y');
        await usuario.click(screen.getByLabelText('Cubre todos los temas'));
        await usuario.click(screen.getByRole('button', { name: 'Crear' }));

        await waitFor(() => expect(onCreado).toHaveBeenCalledWith(42));
    });

    it('muestra el error de la API cuando la creación falla', async () => {
        const usuario = userEvent.setup();
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve({ ok: false, json: () => Promise.resolve({}) }))
        );

        render(
            <CreadorPersonalizado restUrl="https://ejemplo.test/wp-json/" cabeceras={{}} textos={textosDeEjemplo()} onCreado={vi.fn()} onCancelar={vi.fn()} />
        );

        await usuario.type(screen.getByLabelText('Nombre'), 'X');
        await usuario.type(screen.getByLabelText('Biografía'), 'Y');
        await usuario.click(screen.getByLabelText('Cubre todos los temas'));
        await usuario.click(screen.getByRole('button', { name: 'Crear' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo guardar la identidad.');
    });

    it('el botón Cancelar llama a onCancelar', async () => {
        const usuario = userEvent.setup();
        const onCancelar = vi.fn();

        render(
            <CreadorPersonalizado
                restUrl="https://ejemplo.test/wp-json/"
                cabeceras={{}}
                textos={textosDeEjemplo()}
                onCreado={vi.fn()}
                onCancelar={onCancelar}
            />
        );

        await usuario.click(screen.getByRole('button', { name: 'Cancelar' }));

        expect(onCancelar).toHaveBeenCalled();
    });
});
