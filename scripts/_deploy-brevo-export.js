const fs = require('fs');
const { Client } = require('ssh2');
const e = Object.fromEntries(
  fs.readFileSync('C:/Proyectos/sorteoseguro/.env.local', 'utf8')
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith('#'))
    .map((l) => {
      const i = l.indexOf('=');
      return [l.slice(0, i), l.slice(i + 1)];
    })
);
const stamp = new Date().toISOString().replace(/[-:T]/g, '').slice(0, 13);
const local = 'C:/Proyectos/sorteoseguro/mu-plugins/sorteoseguro-brevo-export.php';
const remoteDir = `${e.SSH_REMOTE_PATH}/wp-content/mu-plugins`;
const remote = `${remoteDir}/sorteoseguro-brevo-export.php`;
const backup = `${remote}.bak-${stamp}`;

function exec(conn, cmd) {
  return new Promise((resolve, reject) => {
    conn.exec(cmd, (err, stream) => {
      if (err) return reject(err);
      let out = '';
      stream
        .on('close', (code) => (code ? reject(new Error(out || `exit ${code}`)) : resolve(out)))
        .on('data', (d) => {
          out += d;
        });
      stream.stderr.on('data', (d) => {
        out += d;
      });
    });
  });
}

const conn = new Client();
conn
  .on('ready', async () => {
    try {
      conn.sftp((err, sftp) => {
        if (err) throw err;
        const upload = () => {
          const rs = fs.createReadStream(local);
          const ws = sftp.createWriteStream(remote);
          ws.on('close', async () => {
            try {
              console.log(await exec(conn, `php -l ${remote}`));
              const check = await exec(
                conn,
                `cd ${JSON.stringify(e.SSH_REMOTE_PATH)} && wp eval 'echo class_exists("SorteoSeguro_Brevo_Export") ? "class-ok" : "class-missing";' --allow-root 2>&1`
              );
              console.log(check);
              console.log(await exec(conn, `cd ${JSON.stringify(e.SSH_REMOTE_PATH)} && wp cache flush --allow-root 2>&1`));
              console.log(
                await exec(
                  conn,
                  `cd ${JSON.stringify(e.SSH_REMOTE_PATH)} && wp eval 'if(class_exists("LiteSpeed\\\\Purge")) LiteSpeed\\\\Purge::purge_all(); echo "purged";' --allow-root 2>&1`
                )
              );
              conn.end();
              console.log('deploy ok');
            } catch (e2) {
              console.error(e2.message);
              conn.end();
              process.exit(1);
            }
          });
          rs.pipe(ws);
        };
        conn.exec(`test -f ${remote} && cp ${remote} ${backup} || true`, (e2) => {
          if (e2) {
            console.error(e2);
            conn.end();
            return;
          }
          upload();
        });
      });
    } catch (err) {
      console.error(err.message);
      conn.end();
      process.exit(1);
    }
  })
  .connect({
    host: e.SSH_HOST,
    port: +e.SSH_PORT,
    username: e.SSH_USER,
    password: e.SSH_PASSWORD,
  });
