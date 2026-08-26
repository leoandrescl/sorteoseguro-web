---
name: ajuste-ui-rapido
description: Ajustes visuales chicos en Sorteo Seguro (padding, centrar, botón, hover, copy, radio 8px, morado). Use when the user asks for a small CSS/layout/copy tweak on checkout, cart, thank-you, TUU pay, FAQ, home, or a screenshot of one control — not a full page redesign.
---

# Ajuste UI rápido

No es un rediseño. No hace falta screenshot de carpeta ni skill de UI completa.

## Hacer

1. Grep el selector o el copy **solo** en `mu-plugins/sorteoseguro-<superficie>/`.
2. Cambiar CSS/HTML de esa superficie. Tokens: morado `#6b3bb8`, UI radius `8px`, sin hover fucsia, copy DigiTicket.
3. Subir esos archivos a sorteoseguro.cl (backup remoto + purge). Un SSH.

## No hacer

- Explorar el repo entero, respaldos, plugins de pago o Lottery.
- Crear página/mu-plugin nuevo (eso es rediseño).
- Tocar checkout de pagos (`mp-pack-preference`, auto-complete) por un cambio visual.
