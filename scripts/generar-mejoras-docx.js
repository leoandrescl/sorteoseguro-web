const { Document, Packer, Paragraph, TextRun, HeadingLevel, BorderStyle } = require("docx");
const fs = require("fs");
const path = require("path");

const h1 = (t) =>
  new Paragraph({
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 0, after: 200 },
    children: [new TextRun({ text: t, bold: true })],
  });

const h2 = (t) =>
  new Paragraph({
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 300, after: 140 },
    children: [new TextRun({ text: t, bold: true })],
  });

const h3 = (t) =>
  new Paragraph({
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 220, after: 100 },
    children: [new TextRun({ text: t, bold: true })],
  });

const p = (t, opts = {}) =>
  new Paragraph({
    spacing: { after: 110 },
    children: [new TextRun({ text: t, size: 22, ...opts })],
  });

const bullet = (t) =>
  new Paragraph({
    spacing: { after: 70 },
    indent: { left: 360 },
    children: [new TextRun({ text: `• ${t}`, size: 21 })],
  });

const item = (n, title, detail) =>
  new Paragraph({
    spacing: { after: 90 },
    indent: { left: 360 },
    children: [
      new TextRun({ text: `${n}. ${title}`, bold: true, size: 21 }),
      new TextRun({ text: detail ? ` — ${detail}` : "", size: 21 }),
    ],
  });

const meta = (t) =>
  new Paragraph({
    spacing: { after: 60 },
    indent: { left: 720 },
    children: [new TextRun({ text: t, size: 19, color: "555555", italics: true })],
  });

const doc = new Document({
  sections: [
    {
      properties: {
        page: { margin: { top: 720, right: 720, bottom: 720, left: 720 } },
      },
      children: [
        h1("Sorteo Seguro — Plan de mejoras"),
        p("Sitio: sorteoseguro.cl", { italics: true, size: 20, color: "666666" }),
        p(
          "Plan de trabajo en producción, por fases. Prioridad: (1) plantillas en código y salida de Elementor, (2) DigiTickets asignados automáticamente por Lottery, sin elección manual y sin números duplicados."
        ),

        h2("1. Objetivo"),
        bullet("Reemplazar Elementor (y dependencias de maqueta asociadas) por plantillas PHP/CSS/JS del child theme."),
        bullet("Mantener el diseño actual (home, PDP, header, footer, packs, confianza, FAQ); mejorar solo estructura y rendimiento de código, sin inventar textos nuevos si ya existen."),
        bullet("Lottery asigna DigiTickets automáticamente (aleatorio / sistema), nunca el cliente elige número."),
        bullet("Garantizar que dos compras simultáneas no reciban el mismo número; cubrir colisiones, stock, carritos y pedidos pendientes."),
        bullet("Direct checkout de packs (Promo Engine) alineado con asignación automática."),
        bullet("Barra de avance por etapas reales de 10% + % total, con los textos/mensajes que ya usa el sitio (sin copy nuevo innecesario)."),
        bullet("Cambios uniformes en todos los sorteos en curso. No regenerar ni invalidar tickets ya vendidos."),

        h2("2. Principios"),
        bullet("Una fase a la vez; verificar; seguir."),
        bullet("Rollback por fase (revertir solo ese entregable)."),
        bullet("Lógica nueva en child theme / mu-plugin propio; ir absorbiendo WPCode y Custom CSS & JS."),
        bullet("Separar maqueta (plantillas) de negocio (Lottery, packs, reserva de números)."),
        bullet("Tras fases de tickets/packs/checkout: compra de prueba obligatoria."),

        h2("3. Contexto actual"),
        bullet("PDP: HTML custom dentro de Elementor + shortcodes WPCode."),
        bullet("Home / header / footer: Elementor + HFE + CSS/JS dispersos."),
        bullet("~37 WPCode publicados y ~15 Custom CSS & JS Pro activos."),
        bullet("Promo Engine = packs lleva X / paga Y."),
        bullet("Lottery hoy: cliente elige número en sorteos vivos (a cambiar)."),

        h2("4. Fases — orden de acción"),

        h3("Fase 0 — Preparación"),
        item("0.1", "Backup / punto de restauración Hostinger", ""),
        item("0.2", "Inventario de superficies y dependencias", "home, PDP, listados, checkout, header/footer; WPCode/CCJ usados en cada una."),
        item("0.3", "Base de plantillas en child theme", "estructura de archivos, CSS del diseño actual (tokens a partir de estilos existentes)."),
        meta("Rollback: restaurar backup. Sin cambio público hasta aplicar plantillas."),

        h3("Fase 1 — Asignación automática de DigiTickets (Lottery) + seguridad de números"),
        p(
          "Núcleo de negocio. Debe quedar sólido antes o en paralelo temprano con la migración visual de la PDP.",
          { italics: true, size: 20 }
        ),
        item(
          "1.1",
          "Configurar Lottery en asignación automática",
          "sin grilla ni buscador; ocultar/retirar toda UI y CSS/JS de selección manual en todos los sorteos en curso."
        ),
        item(
          "1.2",
          "Reserva atómica de números",
          "al agregar al carrito / al confirmar pack / al crear el pedido: el número se reserva de forma exclusiva para esa sesión/pedido."
        ),
        item(
          "1.3",
          "Anti-duplicados bajo concurrencia",
          "dos compradores al mismo tiempo no pueden obtener el mismo DigiTicket (locks / unicidad en BD / reintento si el número acaba de tomarse)."
        ),
        item(
          "1.4",
          "Cobertura de casos",
          "pago aprobado; pago pendiente; abandono de carrito; cancelación por hold stock; fallo de pasarela; reintento de compra; cantidad > 1; packs (N números de una vez)."
        ),
        item(
          "1.5",
          "Liberación controlada",
          "si el pedido no se paga y se cancela/expira, los números vuelven al pool disponible sin pisar ventas completadas."
        ),
        item(
          "1.6",
          "Historial intacto",
          "tickets ya emitidos (Completado/Procesando pagado) no se regeneran ni se reasignan."
        ),
        meta("Rollback: restaurar modo Lottery anterior + UI de selección. Validación: compras de prueba concurrentes (2 sesiones) + pack."),

        h3("Fase 2 — Packs y direct checkout"),
        item(
          "2.1",
          "Direct checkout por pack",
          "clic en pack (3×2, 10×5, etc.) → reserva N números automáticos → ir a checkout."
        ),
        item(
          "2.2",
          "Unificar UI de packs sobre Promo Engine",
          "una sola implementación clara; retirar banners/snippets duplicados de packs."
        ),
        item(
          "2.3",
          "Checkout muestra los DigiTickets asignados",
          "con el mismo criterio visual/textos que ya existan en el flujo actual cuando hay números."
        ),
        meta("Rollback: desactivar direct checkout; packs vuelven al flujo previo. Validación: compra con pack."),

        h3("Fase 3 — Barra de avance (etapas 10%)"),
        item(
          "3.1",
          "Barra por tramos reales 0–10, 10–20, … + % total del sorteo",
          "en PDP, listado de sorteos en curso y destacados de home."
        ),
        item(
          "3.2",
          "Mensajes por tramo",
          "reutilizar los textos/mensajes que el sitio ya usa hoy (no inventar copy nuevo)."
        ),
        item(
          "3.3",
          "Misma fórmula en todos lados",
          "solo tickets de pedidos pagados/completados."
        ),
        meta("Rollback: desactivar bloque/mu-plugin de avance."),

        h3("Fase 4 — PDP a plantilla de código"),
        item(
          "4.1",
          "Plantilla única de ficha de sorteo en child theme",
          "partir del HTML actual de la PDP; mismo diseño."
        ),
        item(
          "4.2",
          "Bloque unificado",
          "galería/video, countdown Lottery, avance, packs, compra, bases legales."
        ),
        item(
          "4.3",
          "Absorber WPCode de la PDP en partials/funciones del tema",
          "hasta no depender de shortcodes sueltos."
        ),
        item(
          "4.4",
          "Desactivar constructor Elementor en productos",
          "cuando la plantilla esté verificada en todos los sorteos en curso."
        ),
        meta("Rollback: reactivar Elementor en productos / restauración de revisión."),

        h3("Fase 5 — Header y footer a plantillas de código"),
        item("5.1", "Migrar header/footer HFE al child theme", "mismo diseño, menos peso."),
        item("5.2", "Consolidar CSS de header/footer", "eliminar duplicados."),
        item("5.3", "Desactivar plantillas HFE", "cuando header/footer en código estén estables."),
        meta("Rollback: reactivar HFE."),

        h3("Fase 6 — Home a plantilla de código"),
        item(
          "6.1",
          "Home en PHP/CSS del child theme",
          "hero, Digipacks, partners, confianza, testimonios, pasos, FAQ, CTA final — mismo contenido y textos actuales."
        ),
        item("6.2", "Desactivar Elementor en la home", "tras verificación visual y funcional."),
        meta("Rollback: restaurar página Elementor de la home."),

        h3("Fase 7 — Checkout a plantilla limpia (decisión fija)"),
        p(
          "Se migra el shell del checkout a plantilla/código del tema (o bloques Woo nativos sin envoltorio Elementor innecesario). La lógica de Mercado Pago no se reescribe; solo se elimina la dependencia de Elementor en esa pantalla y se ordena layout/CSS con el diseño actual.",
          { size: 21 }
        ),
        item("7.1", "Quitar Elementor del checkout", "plantilla limpia + estilos del diseño actual."),
        item("7.2", "Mantener pasarela y campos de pago intactos", "solo maqueta/estructura."),
        item("7.3", "Thank-you coherente con el flujo DigiTicket", "sin cambiar textos que ya existan salvo ajuste técnico."),
        meta("Rollback: restaurar plantilla/página previa del checkout. Validación: compra de prueba end-to-end."),

        h3("Fase 8 — Limpieza de fragmentos ya absorbidos"),
        item("8.1", "Desactivar WPCode ya reemplazados por el tema/mu-plugin", "uno a uno."),
        item("8.2", "Desactivar Custom CSS & JS ya movidos al child theme", "uno a uno."),
        item("8.3", "Ordenar functions.php del child", "sin cambiar comportamiento."),
        item("8.4", "Eliminar scripts sueltos en raíz solo si está confirmado que no se usan", ""),
        meta("Rollback: reactivar el snippet/archivo puntual."),

        h3("Fase 9 — Apagar builders"),
        item(
          "9.1",
          "Verificar que ninguna URL crítica dependa de Elementor / HFE / Hello Plus",
          ""
        ),
        item("9.2", "Desactivar Elementor, Header Footer Elementor y Hello Plus", ""),
        meta("Rollback: reactivar plugins. Solo cuando las fases 4–7 estén cerradas."),

        h2("5. Seguridad de números — requisitos explícitos"),
        bullet("Un DigiTicket = un solo dueño efectivo (pedido pagado / reserva vigente)."),
        bullet("Asignación automática; el cliente no elige ni busca números."),
        bullet("Concurrencia: dos checkouts paralelos no comparten el mismo número."),
        bullet("Packs: N números distintos reservados en la misma operación atómica o con reintento seguro."),
        bullet("Pendiente de pago: números retenidos el tiempo del hold stock; al cancelar/expirar, vuelven al pool."),
        bullet("Pago aprobado → Completado: números definitivos ligados al pedido."),
        bullet("Nunca reasignar números de pedidos ya pagados/completados."),

        h2("6. Criterios de hecho"),
        bullet("Fase 1: no hay UI de elección; pruebas concurrentes sin duplicados."),
        bullet("Fase 2: pack → checkout con N DigiTickets correctos y precio promo correcto."),
        bullet("Fase 3: % igual en ficha/listado/home; etapas 10% reales."),
        bullet("Fases 4–7: mismas pantallas sin Elementor; diseño equivalente al actual."),
        bullet("Fase 8: snippets residuales desactivados."),
        bullet("Fase 9: Elementor/HFE/Hello Plus desactivados y el sitio opera."),

        h2("7. Estimación orientativa"),
        bullet("Fase 0: ~0,5–1 h"),
        bullet("Fase 1 (Lottery + seguridad): ~6–12 h"),
        bullet("Fase 2 (packs/direct checkout): ~6–12 h"),
        bullet("Fase 3 (barra): ~3–5 h"),
        bullet("Fases 4–6 (PDP, header/footer, home): ~13–25 h"),
        bullet("Fase 7 (checkout limpio): ~3–6 h"),
        bullet("Fases 8–9 (limpieza + apagar builders): ~3–6 h"),
        p("Total orientativo: ~35–67 h (aprox. 5–9 jornadas), según fricción al absorber WPCode y pruebas de concurrencia.", {
          bold: true,
        }),

        h2("8. Checklist de orden"),
        p(
          "0 Prep → 1 Lottery automático + anti-duplicados → 2 Packs/direct checkout → 3 Barra 10% → 4 PDP código → 5 Header/footer código → 6 Home código → 7 Checkout sin Elementor → 8 Limpieza snippets → 9 Apagar Elementor/HFE/Hello Plus"
        ),

        new Paragraph({
          spacing: { before: 360 },
          border: { top: { style: BorderStyle.SINGLE, size: 6, color: "CCCCCC", space: 10 } },
          children: [
            new TextRun({
              text: "Este documento solo lista trabajo que se ejecutará. Prioridad visible para el cliente: plantillas profesionales sin Elementor + DigiTickets automáticos seguros bajo cualquier condición de compra.",
              italics: true,
              size: 18,
              color: "555555",
            }),
          ],
        }),
      ],
    },
  ],
});

const outPath = path.join(__dirname, "..", "docs", "Mejoras-sorteoseguro.docx");
fs.mkdirSync(path.dirname(outPath), { recursive: true });
Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync(outPath, buf);
  console.log("OK " + outPath);
});
