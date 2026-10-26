<?php
if(($_GET['k']??'')!='purge20260917'){http_response_code(403);exit('no');}
require __DIR__.'/wp-load.php';
function litespeed_purge_url($url){ if(function_exists('litespeed_purge_url')){ litespeed_purge_url($url); } }
$urls=['https://sorteoseguro.cl/wp-content/uploads/2025/07/premio-2.png','https://sorteoseguro.cl/wp-content/uploads/2025/07/premio-3.png'];
foreach($urls as $u){ litespeed_purge_url($u); }
if(function_exists('wp_cache_flush')) wp_cache_flush();
echo 'purged';
