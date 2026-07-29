import { useEffect, useState } from 'react';
import { AsistenteOnboarding, type TextosOnboarding } from './AsistenteOnboarding';
import { BarraEstado } from './BarraEstado';
import { PantallaCalendarioEditorial, type TextosCalendarioEditorial } from './PantallaCalendarioEditorial';
import { PantallaDistribucion, type TextosDistribucion } from './PantallaDistribucion';
import { PantallaBancoPeriodistas, type TextosBancoPeriodistas } from './PantallaBancoPeriodistas';
import { PantallaComentarios, type TextosComentarios } from './PantallaComentarios';
import { PantallaEstudioSeo, type TextosEstudioSeo } from './PantallaEstudioSeo';
import { PantallaInformes, type TextosInformes } from './PantallaInformes';
import { PantallaMesaEditorial, type TextosMesaEditorial } from './PantallaMesaEditorial';
import { PantallaPortada, type DatosPortada, type TextosPortada } from './PantallaPortada';
import { PantallaSalaRevision, type TextosSalaRevision } from './PantallaSalaRevision';
import { PantallaSalaMaquinas, type DatosSalud, type TextosSalaMaquinas } from './PantallaSalaMaquinas';
import { PantallaTendencias, type TextosTendencias } from './PantallaTendencias';

export interface DatosPlumaPanel {
    restUrl: string;
    nonce: string;
    salud: DatosSalud;
    textosPortada: TextosPortada;
    textosTendencias: TextosTendencias;
    textosCalendarioEditorial: TextosCalendarioEditorial;
    textosDistribucion: TextosDistribucion;
    textosMesaEditorial: TextosMesaEditorial;
    textosBancoPeriodistas: TextosBancoPeriodistas;
    textosSalaRevision: TextosSalaRevision;
    textosSalaMaquinas: TextosSalaMaquinas;
    textosEstudioSeo: TextosEstudioSeo;
    textosComentarios: TextosComentarios;
    textosInformes: TextosInformes;
    iaConfigurada: boolean;
    onboardingCompletado: boolean;
    textosOnboarding: TextosOnboarding;
}

interface Props {
    datos: DatosPlumaPanel;
}

type Ruta =
    | 'portada'
    | 'tendencias'
    | 'calendario-editorial'
    | 'distribucion'
    | 'mesa-editorial'
    | 'periodistas'
    | 'revision'
    | 'estudio-seo'
    | 'comentarios'
    | 'informes'
    | 'salud';

const INTERVALO_REFRESCO_MS = 60_000;

function leerRuta(): Ruta {
    if ('#/salud' === window.location.hash) {
        return 'salud';
    }

    if ('#/tendencias' === window.location.hash) {
        return 'tendencias';
    }

    if ('#/calendario-editorial' === window.location.hash) {
        return 'calendario-editorial';
    }

    if ('#/distribucion' === window.location.hash) {
        return 'distribucion';
    }

    if ('#/mesa-editorial' === window.location.hash) {
        return 'mesa-editorial';
    }

    if ('#/periodistas' === window.location.hash) {
        return 'periodistas';
    }

    if ('#/revision' === window.location.hash) {
        return 'revision';
    }

    if ('#/estudio-seo' === window.location.hash) {
        return 'estudio-seo';
    }

    if ('#/comentarios' === window.location.hash) {
        return 'comentarios';
    }

    return '#/informes' === window.location.hash ? 'informes' : 'portada';
}

/**
 * El shell del panel (Libro Cap. 10.1): una única página de wp-admin con
 * barra de estado persistente + enrutado por hash entre pantallas — así la
 * barra nunca desaparece al navegar (una recarga completa por pantalla
 * rompería justo la promesa de "saber en tres segundos si todo está bien").
 *
 * Solo se enlazan pantallas que YA EXISTEN (Portada, Sala de Máquinas): cada
 * porción nueva de la Etapa 4 añade su propia entrada de navegación cuando
 * esté lista, nunca antes — cero enlaces muertos.
 */
export function Aplicacion({ datos }: Props) {
    const [ruta, setRuta] = useState<Ruta>(leerRuta);
    const [portada, setPortada] = useState<DatosPortada | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [onboardingCompletado, setOnboardingCompletado] = useState(datos.onboardingCompletado);

    useEffect(() => {
        const escuchar = () => setRuta(leerRuta());
        window.addEventListener('hashchange', escuchar);
        return () => window.removeEventListener('hashchange', escuchar);
    }, []);

    useEffect(() => {
        let cancelado = false;

        const cargar = () => {
            fetch(`${datos.restUrl}pluma/v1/panel/portada`, {
                headers: { 'X-WP-Nonce': datos.nonce },
            })
                .then((respuesta) => {
                    if (!respuesta.ok) {
                        throw new Error('respuesta no OK');
                    }
                    return respuesta.json() as Promise<DatosPortada>;
                })
                .then((json) => {
                    if (!cancelado) {
                        setPortada(json);
                        setError(null);
                    }
                })
                .catch(() => {
                    if (!cancelado) {
                        setError(datos.textosPortada.errorCarga);
                    }
                });
        };

        cargar();
        const intervalo = window.setInterval(cargar, INTERVALO_REFRESCO_MS);

        return () => {
            cancelado = true;
            window.clearInterval(intervalo);
        };
    }, [datos.restUrl, datos.nonce, datos.textosPortada.errorCarga]);

    if (!onboardingCompletado) {
        return (
            <AsistenteOnboarding
                restUrl={datos.restUrl}
                nonce={datos.nonce}
                textos={datos.textosOnboarding}
                textosModo={datos.textosPortada.modo}
                textosLlave={datos.textosSalaMaquinas.llave}
                textosBancoPeriodistas={datos.textosBancoPeriodistas}
                alTerminar={() => setOnboardingCompletado(true)}
            />
        );
    }

    return (
        <div className="pluma-panel">
            <BarraEstado portada={portada} textos={datos.textosPortada} />

            <nav className="pluma-panel__nav" aria-label={datos.textosPortada.titulo}>
                <a href="#/portada" className={'portada' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}>
                    {datos.textosPortada.navPortada}
                </a>
                <a
                    href="#/tendencias"
                    className={'tendencias' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosTendencias.titulo}
                </a>
                <a
                    href="#/calendario-editorial"
                    className={'calendario-editorial' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosCalendarioEditorial.titulo}
                </a>
                <a
                    href="#/distribucion"
                    className={'distribucion' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosDistribucion.titulo}
                </a>
                <a
                    href="#/mesa-editorial"
                    className={'mesa-editorial' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosMesaEditorial.titulo}
                </a>
                <a
                    href="#/periodistas"
                    className={'periodistas' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosBancoPeriodistas.titulo}
                </a>
                <a
                    href="#/revision"
                    className={'revision' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosSalaRevision.titulo}
                </a>
                <a
                    href="#/estudio-seo"
                    className={'estudio-seo' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosEstudioSeo.titulo}
                </a>
                <a
                    href="#/comentarios"
                    className={'comentarios' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosComentarios.titulo}
                </a>
                <a
                    href="#/informes"
                    className={'informes' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}
                >
                    {datos.textosInformes.titulo}
                </a>
                <a href="#/salud" className={'salud' === ruta ? 'pluma-panel__nav-enlace pluma-panel__nav-enlace--activo' : 'pluma-panel__nav-enlace'}>
                    {datos.textosPortada.navSalud}
                </a>
            </nav>

            <main className="pluma-panel__contenido">
                {'portada' === ruta && <PantallaPortada datos={portada} error={error} textos={datos.textosPortada} />}
                {'tendencias' === ruta && (
                    <PantallaTendencias
                        restUrl={datos.restUrl}
                        nonce={datos.nonce}
                        textos={datos.textosTendencias}
                        iaConfigurada={datos.iaConfigurada}
                    />
                )}
                {'calendario-editorial' === ruta && (
                    <PantallaCalendarioEditorial restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosCalendarioEditorial} />
                )}
                {'distribucion' === ruta && (
                    <PantallaDistribucion restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosDistribucion} />
                )}
                {'mesa-editorial' === ruta && (
                    <PantallaMesaEditorial restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosMesaEditorial} />
                )}
                {'periodistas' === ruta && (
                    <PantallaBancoPeriodistas restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosBancoPeriodistas} />
                )}
                {'revision' === ruta && (
                    <PantallaSalaRevision restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosSalaRevision} />
                )}
                {'estudio-seo' === ruta && (
                    <PantallaEstudioSeo restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosEstudioSeo} />
                )}
                {'comentarios' === ruta && (
                    <PantallaComentarios restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosComentarios} />
                )}
                {'informes' === ruta && (
                    <PantallaInformes restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosInformes} />
                )}
                {'salud' === ruta && (
                    <PantallaSalaMaquinas datos={datos.salud} restUrl={datos.restUrl} nonce={datos.nonce} textos={datos.textosSalaMaquinas} />
                )}
            </main>
        </div>
    );
}
