<?php
if (!isset($_GET['t']) || !hash_equals('945c6cfc5a341cb78a2b1e90644426ed472b', (string)$_GET['t'])) {
  http_response_code(404);
  exit;
}
require __DIR__ . '/wp-load.php';
header('Content-Type: text/plain; charset=utf-8');
$oid = 81735;
$o = wc_get_order($oid);
if (!$o) { echo "ORDER_NOT_FOUND\n"; exit; }

echo "=== ORDER ===\n";
echo "id=".$o->get_id()." status=".$o->get_status()." total=".$o->get_total()." currency=".$o->get_currency()."\n";
echo "created=".$o->get_date_created()." modified=".$o->get_date_modified()." paid=".$o->get_date_paid()."\n";
echo "payment_method=".$o->get_payment_method()." title=".$o->get_payment_method_title()."\n";
echo "txn=".$o->get_transaction_id()."\n";
echo "needs_payment=".($o->needs_payment()?'yes':'no')." is_paid=".($o->is_paid()?'yes':'no')."\n";
echo "billing=".$o->get_billing_first_name()." ".$o->get_billing_last_name()." email=".$o->get_billing_email()." phone=".$o->get_billing_phone()."\n";
echo "user_id=".$o->get_user_id()." customer_id=".$o->get_customer_id()."\n";
echo "created_via=".$o->get_created_via()."\n";

echo "\n=== ITEMS ===\n";
foreach ($o->get_items() as $item) {
  echo "item product=".$item->get_product_id()." name=".$item->get_name()." qty=".$item->get_quantity()." total=".$item->get_total()."\n";
}
foreach ($o->get_fees() as $fee) {
  echo "fee name=".$fee->get_name()." total=".$fee->get_total()."\n";
}

echo "\n=== ALL META ===\n";
foreach ($o->get_meta_data() as $m) {
  $data = $m->get_data();
  $k = (string)$data['key'];
  $v = $data['value'];
  if (stripos($k,'pass')!==false || stripos($k,'token')!==false || stripos($k,'secret')!==false) continue;
  if (is_array($v) || is_object($v)) $v = wp_json_encode($v);
  echo $k."=".substr((string)$v,0,400)."\n";
}

echo "\n=== NOTES ===\n";
$notes = wc_get_order_notes(array('order_id'=>$oid,'orderby'=>'date_created','order'=>'ASC','limit'=>80));
foreach ($notes as $n) {
  $c = isset($n->content) ? $n->content : (isset($n->comment_content)?$n->comment_content:'');
  $d = isset($n->date_created) ? (string)$n->date_created : '';
  echo "[".$d."] ".wp_strip_all_tags((string)$c)."\n";
}

$email = $o->get_billing_email();
echo "\n=== OTHER ORDERS SAME EMAIL ===\n";
$others = wc_get_orders(array('billing_email'=>$email,'limit'=>20,'orderby'=>'date','order'=>'DESC','return'=>'objects'));
foreach ($others as $oo) {
  echo "oid=".$oo->get_id()." status=".$oo->get_status()." total=".$oo->get_total()." method=".$oo->get_payment_method()." created=".$oo->get_date_created()." paid=".$oo->get_date_paid()." txn=".$oo->get_transaction_id()."\n";
}

echo "\n=== MP SEARCH BY EXTERNAL REF ===\n";
$token = (string) get_option('_mp_access_token_prod','');
$prefix = (string) get_option('_mp_store_identificator','WC-');
$expected = $prefix.$oid;
echo "external_reference_expected=".$expected." token_len=".strlen($token)."\n";
if ($token !== '') {
  $url = add_query_arg(array(
    'external_reference' => $expected,
    'sort' => 'date_created',
    'criteria' => 'desc',
    'range' => 'date_created',
    'begin_date' => 'NOW-7DAYS',
    'end_date' => 'NOW',
  ), 'https://api.mercadopago.com/v1/payments/search');
  $resp = wp_remote_get($url, array('timeout'=>25,'headers'=>array('Authorization'=>'Bearer '.$token)));
  if (is_wp_error($resp)) {
    echo "mp_err=".$resp->get_error_message()."\n";
  } else {
    $code = (int) wp_remote_retrieve_response_code($resp);
    $body = json_decode((string) wp_remote_retrieve_body($resp), true);
    echo "mp_http=".$code." paging=".wp_json_encode(isset($body['paging'])?$body['paging']:null)." results=". (isset($body['results'])?count($body['results']):0) ."\n";
    if (!empty($body['results'])) {
      foreach ($body['results'] as $row) {
        echo "mp_pay id=".$row['id']." status=".$row['status']." status_detail=".($row['status_detail']??'')." amount=".($row['transaction_amount']??'')." method=".($row['payment_method_id']??'')." type=".($row['payment_type_id']??'')." date=".($row['date_created']??'')." approved=".($row['date_approved']??'')." ext=".($row['external_reference']??'')."\n";
      }
    }
  }

  echo "\n=== MP SEARCH 30000 LAST 3D ===\n";
  $url2 = add_query_arg(array(
    'sort' => 'date_created',
    'criteria' => 'desc',
    'range' => 'date_created',
    'begin_date' => 'NOW-3DAYS',
    'end_date' => 'NOW',
    'limit' => 50,
    'transaction_amount' => 30000,
  ), 'https://api.mercadopago.com/v1/payments/search');
  $resp2 = wp_remote_get($url2, array('timeout'=>25,'headers'=>array('Authorization'=>'Bearer '.$token)));
  if (!is_wp_error($resp2)) {
    $code2 = (int) wp_remote_retrieve_response_code($resp2);
    $body2 = json_decode((string) wp_remote_retrieve_body($resp2), true);
    echo "mp30_http=".$code2." results=".count($body2['results']??array())."\n";
    $needle = strtolower($email);
    $name = strtolower($o->get_billing_last_name());
    $hits = 0;
    foreach (($body2['results']??array()) as $row) {
      $payer = $row['payer'] ?? array();
      $pemail = strtolower((string)($payer['email']??''));
      $ext = (string)($row['external_reference']??'');
      $amt = (float)($row['transaction_amount']??0);
      $blob = strtolower($pemail.' '.$ext.' '.($row['description']??''));
      $interesting = ($amt==30000) || strpos($blob,$needle)!==false || strpos($blob,'81735')!==false || ($name!=='' && strpos($blob,$name)!==false);
      if ($interesting) {
        $hits++;
        echo "mp_hit id=".$row['id']." status=".$row['status']." detail=".($row['status_detail']??'')." amount=".$amt." payer=".$pemail." ext=".$ext." date=".($row['date_created']??'')." method=".($row['payment_method_id']??'')."\n";
      }
    }
    echo "hits=".$hits."\n";
  }

  echo "\n=== MP SEARCH PAYER EMAIL ===\n";
  $url3 = add_query_arg(array(
    'sort' => 'date_created',
    'criteria' => 'desc',
    'range' => 'date_created',
    'begin_date' => 'NOW-7DAYS',
    'end_date' => 'NOW',
    'payer.email' => $email,
  ), 'https://api.mercadopago.com/v1/payments/search');
  $resp3 = wp_remote_get($url3, array('timeout'=>25,'headers'=>array('Authorization'=>'Bearer '.$token)));
  if (!is_wp_error($resp3)) {
    $code3 = (int) wp_remote_retrieve_response_code($resp3);
    $body3 = json_decode((string) wp_remote_retrieve_body($resp3), true);
    echo "mpemail_http=".$code3." results=".count($body3['results']??array())."\n";
    foreach (($body3['results']??array()) as $row) {
      echo "mp_email id=".$row['id']." status=".$row['status']." amount=".($row['transaction_amount']??'')." ext=".($row['external_reference']??'')." date=".($row['date_created']??'')." method=".($row['payment_method_id']??'')."\n";
    }
  }
}

echo "\n=== TUU SETTINGS (no secrets) ===\n";
$tuu = get_option('woocommerce_wcplugingateway_settings', array());
if (is_array($tuu)) {
  foreach ($tuu as $k=>$v) {
    if (stripos($k,'key')!==false || stripos($k,'secret')!==false || stripos($k,'token')!==false || stripos($k,'pass')!==false) {
      echo $k."=len:".strlen((string)$v)."\n";
      continue;
    }
    if (is_array($v)||is_object($v)) $v=wp_json_encode($v);
    echo $k."=".substr((string)$v,0,200)."\n";
  }
}

echo "\n=== WC LOGS LAST LINES FOR 81735 ===\n";
$logdir = WP_CONTENT_DIR . '/uploads/wc-logs';
echo "logdir_exists=".(is_dir($logdir)?'yes':'no')."\n";
if (is_dir($logdir)) {
  $files = glob($logdir.'/*.{log,txt}', GLOB_BRACE);
  usort($files, function($a,$b){ return filemtime($b)-filemtime($a); });
  $shown = 0;
  foreach (array_slice($files, 0, 40) as $f) {
    $c = @file_get_contents($f);
    if ($c === false || strpos($c, '81735') === false) continue;
    echo "FILE ".basename($f)."\n";
    foreach (preg_split('/\r?\n/', $c) as $line) {
      if (strpos($line, '81735') !== false) echo $line."\n";
    }
    $shown++;
    if ($shown >= 8) break;
  }
  echo "log_files_with_id=".$shown."\n";
}

echo "\nDONE\n";
