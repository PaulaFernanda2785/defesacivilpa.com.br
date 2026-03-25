<?php

return [
    'host'       => Env::get('SMTP_HOST'),
    'port'       => Env::get('SMTP_PORT'),
    'usuario'    => Env::get('SMTP_USER'),
    'senha'      => Env::get('SMTP_PASS'),
    'secure'     => Env::get('SMTP_SECURE'),
    'from_email' => Env::get('SMTP_FROM_EMAIL'),
    'from_nome'  => Env::get('SMTP_FROM_NAME'),
];



