import type { Metadata } from "next";
import { CONTACT_EMAIL, LegalPage } from "@/components/landing/LegalPage";

export const metadata: Metadata = { title: "Borrado de datos — Sinapsa" };

export default function DataDeletionPage() {
  return (
    <LegalPage title="Instrucciones para borrar tus datos">
      <section>
        <p>
          Puedes pedir que eliminemos los datos que Sinapsa guarda sobre ti o sobre las cuentas que conectaste (WhatsApp,
          Instagram, Facebook Messenger o Google Calendar).
        </p>
      </section>

      <section>
        <h2>1. Quitar el acceso de inmediato</h2>
        <ul>
          <li>
            <strong>Facebook e Instagram:</strong> en Facebook ve a Configuración y privacidad → Configuración →
            Integraciones comerciales, busca Sinapsa y elimínala.
          </li>
          <li>
            <strong>Google:</strong> entra a{" "}
            <a href="https://myaccount.google.com/permissions" target="_blank" rel="noreferrer">
              myaccount.google.com/permissions
            </a>
            , elige Sinapsa y quita el acceso.
          </li>
        </ul>
        <p>Desde ese momento Sinapsa ya no puede leer ni enviar nada en tu nombre.</p>
      </section>

      <section>
        <h2>2. Pedir el borrado de tus datos</h2>
        <p>
          Escribe a <a href={`mailto:${CONTACT_EMAIL}?subject=Borrado%20de%20datos`}>{CONTACT_EMAIL}</a> con el asunto
          &quot;Borrado de datos&quot; e indica el número de WhatsApp, la cuenta de Instagram, la página de Facebook o el
          email de Google que conectaste.
        </p>
        <p>
          Eliminaremos los tokens de acceso, los mensajes, los contactos y los datos de conexión asociados en un plazo
          máximo de 30 días y te confirmaremos por email cuando esté hecho.
        </p>
      </section>
    </LegalPage>
  );
}
