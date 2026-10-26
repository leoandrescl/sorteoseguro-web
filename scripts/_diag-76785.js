const { Client } = require("ssh2");
const conn = new Client();
conn
  .on("ready", () => {
    conn.exec(
      "cd /home/u367809546/domains/sorteoseguro.cl/public_html && wp eval 'foreach(array(76744,76785) as $oid){ $o=wc_get_order($oid); echo $oid.\" user=\".$o->get_user_id().PHP_EOL; }'",
      (e, stream) => {
        stream.on("data", (d) => process.stdout.write(d));
        stream.on("close", () => conn.end());
      }
    );
  })
  .connect({
    host: "92.113.35.130",
    port: 65002,
    username: "u367809546",
    password: "mw;:j9r9gRN8.!2#",
    readyTimeout: 20000,
  });
