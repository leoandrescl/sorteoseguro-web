---
name: cambio-produccion-sorteoseguro
description: Cambia código en producción sorteoseguro.cl (mu-plugins, FTP/SSH, Mercado Pago, Lottery, packs, deploy). Use when the user mentions producción, deploy, FTP, SSH, mu-plugin, Mercado Pago, pagos, Lottery, DigiTickets, webhook, completar pedido, o pide parchear el sitio vivo.
---

# Cambio en producción (Sorteo Seguro)

Producción = **sorteoseguro.cl**. Si el usuario no pidió ejecutar, solo revisar.

**Excepción UI:** página nueva o rediseño se **sube siempre** a sorteoseguro.cl (header/footer chrome). Credenciales `.env.local`. Ver `deploy-paginas.mdc`. Solo no subir si dijo “aún no / no subas”.

## Gate

1. ¿Dijo “no hagas nada / solo revisa / aún no”? → cero writes.
2. ¿Pidió una fase concreta? → solo esa fase.
3. ¿Afecta pagos, números o BD? → backup + explicar riesgo en una frase, luego ejecutar si ya autorizó.

## Dónde

- Código nuevo: `mu-plugins/sorteoseguro-*.php` (y subcarpeta del módulo).
- **Prohibido:** editar `lottery-for-woocommerce`.
- **Prohibido:** mezclar credenciales/URLs `.com` en `.env` o configs `.cl`.
- Pagos: `sorteoseguro-mp-auto-complete.php` — MP aprobado ⇒ pedido **Completado**, independiente de thank-you.
- Números: `sorteoseguro-packs-lottery.php` — aleatorios, únicos, pending reserva.

## Backup y deploy (barato)

Un SSH al **cerrar**: backup remoto `.bak-YYYYMMDD-HHMM` de cada archivo que se pisa, upload, LiteSpeed purge. No SSH de “exploración” (WooCommerce core, plugins de pago, logs) salvo que el bug lo exija.

No grep `respaldos-produccion/` ni `.bak` para implementar.

## Después

Avisar: archivos tocados + cómo probar. Sin recap de fases anteriores. No ampliar alcance.
