<?php
require_once __DIR__ . '/../app/Services/InmetService.php';

$url = 'https://alertas2.inmet.gov.br/52845';

$html = (new ReflectionClass('InmetService'))
    ->getMethod('baixarHtml')
    ->invoke(null, $url);

echo substr($html, 0, 4000);
