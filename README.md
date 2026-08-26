# Sorteo Seguro — código custom (mu-plugins)

Repositorio **solo** con el código personalizado del rediseño de [sorteoseguro.cl](https://sorteoseguro.cl).  
**No** incluye WordPress, WooCommerce, plugins de terceros, uploads ni bases de datos.

## Qué hay aquí

```
mu-plugins/
  sorteoseguro-*.php          # loaders / lógica
  sorteoseguro-*/             # assets, templates y CSS/JS por superficie
.cursor/rules/                # convenciones de UI y producción
.cursor/skills/               # skills del agente para este proyecto
```

### Superficies principales

| Mu-plugin | Rol |
|-----------|-----|
| `sorteoseguro-chrome` | Header / footer / fuentes |
| `sorteoseguro-home` | Home v2 (hero, packs, etc.) |
| `sorteoseguro-checkout` | Checkout guest + UI |
| `sorteoseguro-cart` | Carrito |
| `sorteoseguro-thankyou` | Gracias / post-compra |
| `sorteoseguro-pdp` | Fichas de producto |
| `sorteoseguro-packs-lottery` | DigiPacks + Lottery (sin editar el plugin core) |
| `sorteoseguro-mp-pack-preference` | Preferencia Mercado Pago con packs (fees negativos) |
| `sorteoseguro-mp-auto-complete` | Pedido Completado tras pago MP |
| `sorteoseguro-auth` / `faq` / `contacto` / `cookies` / `legal` / `quienes` / … | Páginas y flujos auxiliares |

## Instalación en un WP

Copiar el contenido de `mu-plugins/` a:

```
wp-content/mu-plugins/
```

WordPress carga automáticamente los `.php` de la raíz de `mu-plugins/`. Las carpetas hermanas (`sorteoseguro-home/`, etc.) deben quedar al mismo nivel.

## Qué no va en este repo

- Core WP / tema / Elementor / Lottery / Mercado Pago plugin
- `.env`, dumps SQL, FTP mirrors, zips, backups `.bak-*`
- Carpetas de diseño o screenshots de trabajo

## Producción

Sitio vivo: **sorteoseguro.cl**. Despliegue típico: backup remoto → subir mu-plugin → purge LiteSpeed.
