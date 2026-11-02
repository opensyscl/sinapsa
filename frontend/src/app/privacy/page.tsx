import type { Metadata } from "next";
import { CONTACT_EMAIL, LegalPage } from "@/components/landing/LegalPage";

export const metadata: Metadata = { title: "Política de privacidad — Sinapsa" };

export default function PrivacyPage() {
  return (
    <LegalPage title="Política de privacidad">
      <section>
        <p>
          Sinapsa (sinapsahq.com) es una plataforma que permite a empresas de software (&quot;clientes&quot;) conectar
          las cuentas de WhatsApp, Instagram, Facebook Messenger y Google Calendar de sus usuarios para enviar y
          recibir mensajes y gestionar citas a través de una API. Esta política explica qué datos tratamos, para qué y
          cómo los protegemos.
        </p>
      </section>

      <section>
        <h2>1. Datos que tratamos</h2>
        <ul>
          <li><strong>Cuenta:</strong> nombre, email y contraseña (guardada con hash) de quienes usan el panel.</li>
          <li><strong>Conexiones de Meta:</strong> identificadores de la cuenta conectada (número de WhatsApp Business, cuenta de Instagram o página de Facebook) y su token de acceso.</li>
          <li><strong>Mensajería:</strong> contactos (nombre, teléfono o identificador del canal) y el contenido de los mensajes enviados y recibidos por los canales conectados.</li>
          <li><strong>Google Calendar:</strong> el identificador y email de la cuenta de Google conectada, sus tokens de acceso y, solo cuando el cliente lo pide, la disponibilidad (horarios ocupados) y los eventos del calendario.</li>
          <li><strong>Datos técnicos:</strong> registros de solicitudes a la API y de webhooks, usados para seguridad y diagnóstico.</li>
        </ul>
      </section>

      <section>
        <h2>2. Para qué los usamos</h2>
        <p>
          Usamos los datos únicamente para prestar el servicio: entregar los mensajes entre los canales y el cliente,
          consultar disponibilidad y crear o borrar eventos cuando el cliente lo solicita, mantener las conexiones
          activas, prevenir abusos y dar soporte. No vendemos datos personales ni los usamos para publicidad.
        </p>
      </section>

      <section>
        <h2>3. Datos de usuario de Google</h2>
        <p>
          Sinapsa accede a Google Calendar con los permisos <code>calendar.events</code> y <code>calendar.freebusy</code>,
          y solo después de que el usuario lo autoriza en la pantalla de consentimiento de Google. Con ellos consultamos
          los horarios ocupados y creamos, listamos o borramos eventos a pedido del cliente que integró Sinapsa.
        </p>
        <p>
          El uso y la transferencia a cualquier otra aplicación de la información recibida de las API de Google se
          ajustan a la{" "}
          <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noreferrer">
            Política de Datos de Usuario de los Servicios de API de Google
          </a>
          , incluidos los requisitos de Uso Limitado. En particular, no usamos los datos de Google para publicidad, no
          los vendemos, no los usamos para entrenar modelos de inteligencia artificial y ninguna persona los lee salvo
          que el usuario lo autorice expresamente, sea necesario por seguridad o lo exija la ley.
        </p>
      </section>

      <section>
        <h2>4. Con quién los compartimos</h2>
        <ul>
          <li>Con el cliente que integró Sinapsa y a cuyo servicio se conectó el usuario.</li>
          <li>Con Meta y Google, en la medida necesaria para enviar mensajes o operar el calendario.</li>
          <li>Con proveedores de infraestructura (alojamiento, DNS y red) que procesan datos por nuestra cuenta.</li>
          <li>Con autoridades, solo cuando la ley lo exija.</li>
        </ul>
      </section>

      <section>
        <h2>5. Seguridad</h2>
        <p>
          Todo el tráfico va cifrado con HTTPS. Los tokens de acceso de Meta y de Google se guardan cifrados en reposo y
          nunca se exponen al navegador ni a los clientes. Los datos de cada empresa están aislados del resto.
        </p>
      </section>

      <section>
        <h2>6. Conservación y borrado</h2>
        <p>
          Conservamos los datos mientras la conexión o la cuenta estén activas. Al desconectar una cuenta de Google
          revocamos el acceso y eliminamos sus tokens; al desconectar un canal de Meta dejamos de usar su token. Puedes
          pedir el borrado de tus datos en cualquier momento siguiendo las instrucciones de{" "}
          <a href="/data-deletion">borrado de datos</a>.
        </p>
      </section>

      <section>
        <h2>7. Tus derechos</h2>
        <p>
          Puedes pedir acceso, corrección o eliminación de tus datos, y revocar en cualquier momento el acceso de
          Sinapsa desde la configuración de seguridad de tu cuenta de Google o de Meta.
        </p>
      </section>

      <section>
        <h2>8. Contacto</h2>
        <p>
          Para cualquier consulta sobre privacidad escríbenos a <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>.
          Si cambiamos esta política, publicaremos la nueva versión en esta página.
        </p>
      </section>
    </LegalPage>
  );
}
