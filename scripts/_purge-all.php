<?php
if(($_GET['k']??'')!='purgeall20260917'){http_response_code(403);exit('no');}
require __DIR__.'/wp-load.php';
if(function_exists('litespeed_purge_all')){ litespeed_purge_all(); echo 'purged all';}
elseif(function_exists('litespeed_purge_ex')){ litespeed_purge_ex('url','https://sorteoseguro.cl/wp-content/uploads/2025/07/premio-2.png'); echo 'purged ex';}
else{ echo 'no function'; }
