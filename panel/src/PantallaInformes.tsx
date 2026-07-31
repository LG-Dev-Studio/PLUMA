import { useEffect, useState } from 'react';

export interface PiezaResumenInforme {
    id: number;
    tendenciaId: number;
    actualizadaEn: string;
    motivos: string[];
}

export interface DatosInformeEditorial {
    rango: { desde: string; hasta: string };
    piezas: {
        publicadas: number;
        porPeriodista: { periodistaId: number; nombre: string; publicadas: number }[];
        porVertical: { vertical: string; publicadas: number }[];
        retenidas: PiezaResumenInforme[];
        fallidas: PiezaResumenInforme[];
    };
    tendencias: {
        enPipeline: number;
        posibleActualizacion: number;
        ignoradas: number;
        vigiladas: number;
        sospechaManipulacion: number;
    };
    motor: {
        ejecuciones: number;
        lotesProcesados: number;
        ejecucionesConErrores: number;
    };
}

export interface TextosInformes {
    titulo: string;
    cargando: string;
    errorCarga: string;
    rango: string;
    piezas: {
        titulo: string;
        publicadas: string;
        porPeriodista: string;
        porVertical: string;
        sinDatos: string;
        retenidas: string;
        fallidas: string;
        sinRetenidas: string;
        sinFallidas: string;
    };
    tendencias: {
        titulo: string;
        enPipeline: string;
        posibleActualizacion: string;
        ignoradas: string;
        vigiladas: string;
        sospechaManipulacion: string;
    };
    motor: {
        titulo: string;
        ejecuciones: string;
        lotesProcesados: string;
        ejecucionesConErrores: string;
    };
}

interface Props {
    restUrl: string;
    nonce: string;
    textos: TextosInformes;
}

/**
 * Informes editoriales semanales (Libro Cap. 14, Etapa 5): una fotografía
 * real de los últimos 7 días, calculada bajo demanda al abrir la pantalla
 * — a diferencia de La Portada, no se refresca por sondeo: la semana no
 * cambia segundo a segundo.
 */
export function PantallaInformes({ restUrl, nonce, textos }: Props) {
    const [informe, setInforme] = useState<DatosInformeEditorial | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetch(`${restUrl}pluma/v1/panel/informes`, { headers: { 'X-WP-Nonce': nonce } })
            .then((respuesta) => {
                if (!respuesta.ok) {
                    throw new Error('respuesta no OK');
                }
                return respuesta.json() as Promise<DatosInformeEditorial>;
            })
            .then((json) => {
                setInforme(json);
                setError(null);
            })
            .catch(() => setError(textos.errorCarga));
    }, [restUrl, nonce, textos.errorCarga]);

    if (null !== error) {
        return (
            <div className="pluma-informes pluma-informes--error" role="alert">
                {error}
            </div>
        );
    }

    if (null === informe) {
        return <div className="pluma-informes pluma-informes--cargando">{textos.cargando}</div>;
    }

    return (
        <div className="pluma-informes">
            <h1>{textos.titulo}</h1>
            <p className="pluma-informes__rango">
                {textos.rango}: {new Date(informe.rango.desde).toLocaleDateString()} – {new Date(informe.rango.hasta).toLocaleDateString()}
            </p>

            <section className="pluma-informes__seccion" aria-label={textos.piezas.titulo}>
                <h2>{textos.piezas.titulo}</h2>
                <p className="pluma-informes__total">
                    {informe.piezas.publicadas} {textos.piezas.publicadas}
                </p>

                <div className="pluma-informes__tablas">
                    <div>
                        <h3>{textos.piezas.porPeriodista}</h3>
                        {0 === informe.piezas.porPeriodista.length ? (
                            <p className="pluma-informes__vacio">{textos.piezas.sinDatos}</p>
                        ) : (
                            <ul className="pluma-informes__tabla">
                                {informe.piezas.porPeriodista.map((fila) => (
                                    <li key={fila.periodistaId}>
                                        <span>{fila.nombre}</span>
                                        <span>{fila.publicadas}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <div>
                        <h3>{textos.piezas.porVertical}</h3>
                        {0 === informe.piezas.porVertical.length ? (
                            <p className="pluma-informes__vacio">{textos.piezas.sinDatos}</p>
                        ) : (
                            <ul className="pluma-informes__tabla">
                                {informe.piezas.porVertical.map((fila) => (
                                    <li key={fila.vertical}>
                                        <span>{fila.vertical}</span>
                                        <span>{fila.publicadas}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                <div className="pluma-informes__alertas">
                    <ListaResumen titulo={textos.piezas.retenidas} vacio={textos.piezas.sinRetenidas} piezas={informe.piezas.retenidas} />
                    <ListaResumen titulo={textos.piezas.fallidas} vacio={textos.piezas.sinFallidas} piezas={informe.piezas.fallidas} />
                </div>
            </section>

            <section className="pluma-informes__seccion" aria-label={textos.tendencias.titulo}>
                <h2>{textos.tendencias.titulo}</h2>
                <dl className="pluma-informes__contadores">
                    <div>
                        <dt>{textos.tendencias.enPipeline}</dt>
                        <dd>{informe.tendencias.enPipeline}</dd>
                    </div>
                    <div>
                        <dt>{textos.tendencias.posibleActualizacion}</dt>
                        <dd>{informe.tendencias.posibleActualizacion}</dd>
                    </div>
                    <div>
                        <dt>{textos.tendencias.ignoradas}</dt>
                        <dd>{informe.tendencias.ignoradas}</dd>
                    </div>
                    <div>
                        <dt>{textos.tendencias.vigiladas}</dt>
                        <dd>{informe.tendencias.vigiladas}</dd>
                    </div>
                    <div>
                        <dt>{textos.tendencias.sospechaManipulacion}</dt>
                        <dd>{informe.tendencias.sospechaManipulacion}</dd>
                    </div>
                </dl>
            </section>

            <section className="pluma-informes__seccion" aria-label={textos.motor.titulo}>
                <h2>{textos.motor.titulo}</h2>
                <dl className="pluma-informes__contadores">
                    <div>
                        <dt>{textos.motor.ejecuciones}</dt>
                        <dd>{informe.motor.ejecuciones}</dd>
                    </div>
                    <div>
                        <dt>{textos.motor.lotesProcesados}</dt>
                        <dd>{informe.motor.lotesProcesados}</dd>
                    </div>
                    <div>
                        <dt>{textos.motor.ejecucionesConErrores}</dt>
                        <dd>{informe.motor.ejecucionesConErrores}</dd>
                    </div>
                </dl>
            </section>
        </div>
    );
}

interface PropsListaResumen {
    titulo: string;
    vacio: string;
    piezas: PiezaResumenInforme[];
}

function ListaResumen({ titulo, vacio, piezas }: PropsListaResumen) {
    return (
        <div>
            <h3>{titulo}</h3>
            {0 === piezas.length ? (
                <p className="pluma-informes__vacio">{vacio}</p>
            ) : (
                <ul className="pluma-informes__resumen">
                    {piezas.map((pieza) => (
                        <li key={pieza.id}>
                            #{pieza.id} — {pieza.motivos.join(', ')}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
