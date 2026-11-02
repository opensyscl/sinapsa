import type { Metadata } from "next";
import { CONTACT_EMAIL, LegalPage } from "@/components/landing/LegalPage";

export const metadata: Metadata = { title: "Condiciones del servicio — Sinapsa" };

export default function TermsPage() {
  return (
    <LegalPage title="Condiciones del servicio">
      <section>
        <p>
          Estas condiciones regulan el uso de Sinapsa (sinapsahq.com), su panel y su API. Al crear una cuenta o usar el
          servicio aceptas estas condiciones.
        </p>
      </section>

      <section>
        <h2>1. El servicio</h2>
        <p>
          Sinapsa permite conectar cuentas de WhatsApp Business, Instagram, Facebook Messenger y Google Calendar para
          enviar y recibir mensajes y gestionar eventos mediante una API y webhooks. Las funciones disponibles pueden
          cambiar con el tiempo.
        </p>
      </section>

      <section>
        <h2>2. Tu cuenta</h2>
        <ul>
          <li>Eres responsable de la información de tu cuenta y de mantener seguras tus contraseñas y claves de API.</li>
          <li>Debes avisarnos de inmediato si sospechas un uso no autorizado.</li>
          <li>Si integras Sinapsa en tu producto, eres responsable de obtener el consentimiento de tus usuarios finales para conectar sus cuentas.</li>
        </ul>
      </section>

      <section>
        <h2>3. Uso aceptable</h2>
        <p>No puedes usar Sinapsa para:</p>
        <ul>
          <li>Enviar spam, mensajes no solicitados o contenido ilegal, engañoso u ofensivo.</li>
          <li>Incumplir las políticas de WhatsApp Business, de Meta o de Google.</li>
          <li>Intentar acceder a datos de otros clientes o vulnerar la seguridad del servicio.</li>
        </ul>
        <p>Podemos suspender cuentas que incumplan estas reglas.</p>
      </section>

      <section>
        <h2>4. Servicios de terceros</h2>
        <p>
          Sinapsa depende de las plataformas de Meta y Google. Sus condiciones también aplican a tu uso, y cambios o
          interrupciones de esas plataformas pueden afectar al servicio.
        </p>
      </section>

      <section>
        <h2>5. Precios</h2>
        <p>
          El servicio se cobra por uso según las tarifas acordadas con cada cliente. Las tarifas que cobre Meta por los
          mensajes se facturan aparte o se trasladan según lo acordado.
        </p>
      </section>

      <section>
        <h2>6. Datos</h2>
        <p>
          El tratamiento de datos personales se rige por nuestra <a href="/privacy">política de privacidad</a>. Los datos
          de tus usuarios siguen siendo tuyos; los tratamos solo para prestarte el servicio.
        </p>
      </section>

      <section>
        <h2>7. Disponibilidad y responsabilidad</h2>
        <p>
          Hacemos lo posible por mantener el servicio disponible y seguro, pero se ofrece &quot;tal cual&quot;, sin
          garantía de funcionamiento ininterrumpido. En la medida que permita la ley, no respondemos por daños
          indirectos ni por fallas de plataformas de terceros.
        </p>
      </section>

      <section>
        <h2>8. Término</h2>
        <p>
          Puedes dejar de usar Sinapsa y pedir el cierre de tu cuenta cuando quieras. Podemos terminar o suspender el
          servicio si incumples estas condiciones.
        </p>
      </section>

      <section>
        <h2>9. Cambios y contacto</h2>
        <p>
          Podemos actualizar estas condiciones; publicaremos la versión vigente en esta página. Dudas en{" "}
          <a href={`mailto:${CONTACT_EMAIL}`}>{CONTACT_EMAIL}</a>.
        </p>
      </section>
    </LegalPage>
  );
}
