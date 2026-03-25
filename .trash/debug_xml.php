<?php
$url = 'https://apiprevmet3.inmet.gov.br/avisos/rss/52845';

$xml = simplexml_load_file($url);

echo '<pre>';
print_r($xml);
echo '</pre>';
