# Reglas del proyecto — Sorteo Seguro

## Acceso remoto: SOLO FTP, nunca SSH

**Regla obligatoria para cualquier chat de este proyecto.**

Toda operación contra el servidor de sorteoseguro.cl se hace **por FTP**. SSH está roto en este entorno: **ignóralo por completo y no intentes reintentar comandos SSH** (ni `ssh`, ni `scp`, ni `rsync` por SSH, ni `SSH_*` de `.env.local`). No pierdas tiempo con métodos SSH.

Esto aplica a: arreglar, modificar, revisar, leer, listar, respaldar o subir un archivo para cualquier tarea.

- Credenciales: `.env.local` (`FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASSWORD`, `FTP_REMOTE_PATH`). Host = **sorteoseguro.cl** (nunca `.com`).
- Cliente FTP del proyecto: `basic-ftp` (ver `scripts/_deploy-*-ftp.js` y `scripts/_ftp-*.js`).
- **Leer/revisar** un archivo remoto → descargarlo por FTP a `_ftp_readonly/` (o `_ftp_readonly_cl/`) y leerlo localmente. Nunca por SSH.
- **Subir/modificar** → backup remoto `.bak-YYYYMMDD-HHMM` de cada archivo que se pisa, luego upload por FTP.

## Alcance

Esta regla solo define el **método de acceso** (FTP). No autoriza por sí sola a subir ni a tocar producción: si el usuario no pidió ejecutar/subir, no se sube nada ni se toca la web. Al preparar o editar archivos localmente no se envía nada por FTP hasta que la tarea lo requiera.
