---
name: implementar-ui-sorteoseguro
description: Rediseña o implementa una pantalla completa de Sorteo Seguro igualando ss1/ss2 (home, PDP, FAQ, packs, header, footer). Use when the user asks for a full page/layout redesign, a new page, or to match a complete screenshot ss1/ss2 — not for small padding/button/copy tweaks.
---

# Implementar UI (Sorteo Seguro)

No codear hasta tener el diseño de referencia. El screenshot/ss2 gana sobre interpretación.

## Checklist (copiar y completar)

```
- [ ] Diseño abierto (carpeta screenshots o ss2 del usuario)
- [ ] Módulo canónico identificado (no página nueva desde cero)
- [ ] Tokens chrome / 1400px / tipografía
- [ ] Desktop calza ss2
- [ ] Mobile ~375 y ~782: orden, sin solapes, header 1 fila
- [ ] Imagen dentro de caja fija; cards mismo alto
- [ ] Hero Home: 5 slides **mismo alto** (nunca `display:none`); CTA abajo; sin recuadro de 3 premios; mobile **vertical** `1122 / 1402` (no `1 / 1`)
- [ ] Copy DigiTicket; cero textos inventados
- [ ] Subida a sorteoseguro.cl (FTP/SSH `.env.local`) + chrome header/footer + purge cache
```

## 1. Referencia

1. Si hay **ss1** (actual) y **ss2** (objetivo): implementar ss2; ss1 solo para no romper lo que ya está bien.
2. Si no hay imagen: buscar en `screenshots con informacion util para mejoras/` (`diseño-home-*`, `diseño-faq`, `diseño-footer`, packs).
3. Si no hay diseño: **parar** y pedir referencia. No inventar.

## 2. Módulo (reusar)

| Superficie | Entrada |
|---|---|
| Header/footer/fuentes | `mu-plugins/sorteoseguro-chrome.php` + `sorteoseguro-chrome/assets/` |
| Home | `sorteoseguro-home.php` + `sorteoseguro-home/` |
| Ficha/PDP | `sorteoseguro-pdp-templates.php` + `sorteoseguro-pdp/` |
| FAQ | `sorteoseguro-faq.php` + `sorteoseguro-faq/` |
| Quiénes somos | `sorteoseguro-quienes.php` + `sorteoseguro-quienes/` |
| Cookies | `sorteoseguro-cookies.php` |
| Packs | `sorteoseguro-packs-lottery.php` |

Activar chrome (`body.ss-chrome`) en plantillas nuevas. No clonar header. **Subir a sorteoseguro.cl** al terminar (ver `deploy-paginas.mdc`); no dar por lista una pantalla que solo existe en local.

## 3. Implementar

- CSS contra tokens existentes (`--ss-content-max`, `--ss-*-purple`, `--ss-home-radius-ui`). Ver rules `ui-estandar` y `ui-responsive`.
- Tocar **solo** lo pedido. Si el contador/fecha no está en el pedido, no moverlo.
- Hover/estados: discretos; no “mejoras creativas”.
- Si un ajuste deja peor el layout: **revertir** al estado previo al pedido.

## 4. No listo hasta

Comparar mentalmente ss2 vs resultado en desktop y mobile. Header compacto, menú no solapado, contenedor 1400, media en caja fija. Avisar qué archivos cambiaron y qué debe mirar el usuario.
