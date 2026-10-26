const { Client } = require("ssh2");
const conn = new Client();
conn
  .on("ready", () => {
    conn.exec(
      `cd /home/u367809546/domains/sorteoseguro.cl/public_html && wp eval '
$oid=76785; $pid=45941;
$posts=get_posts(array("post_type"=>"lty_lottery_ticket","post_status"=>"any","posts_per_page"=>-1,"meta_key"=>"lty_order_id","meta_value"=>(string)$oid));
echo "by order meta: ".count($posts).PHP_EOL;
foreach($posts as $p){ echo "#".$p->ID." parent=".$p->post_parent." num=".get_post_meta($p->ID,"lty_ticket_number",true)." status=".$p->post_status.PHP_EOL; }
$ids=lty_get_lottery_ticket_ids_by_order_id($oid,$pid);
echo "by fn count: ".count($ids).PHP_EOL;
$p=wc_get_product($pid);
echo "lottery_id=".$p->get_lottery_id()." product_id=".$pid.PHP_EOL;
SorteoSeguro_Lottery_Ticket_Ensure::ensure_for_order($oid);
$posts2=get_posts(array("post_type"=>"lty_lottery_ticket","post_status"=>"any","posts_per_page"=>-1,"meta_key"=>"lty_order_id","meta_value"=>(string)$oid));
echo "after ensure: ".count($posts2).PHP_EOL;
foreach($posts2 as $p){ echo "#".$p->ID." num=".get_post_meta($p->ID,"lty_ticket_number",true).PHP_EOL; }
'`,
      (e, stream) => {
        stream.on("data", (d) => process.stdout.write(d));
        stream.stderr.on("data", (d) => process.stderr.write(d));
        stream.on("close", () => conn.end());
      }
    );
  })
  .connect({
    host: "92.113.35.130",
    port: 65002,
    username: "u367809546",
    password: "mw;:j9r9gRN8.!2#",
    readyTimeout: 30000,
  });
