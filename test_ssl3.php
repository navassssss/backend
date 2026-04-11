<?php
putenv('OPENSSL_CONF=' . __DIR__ . '/openssl.cnf');
$config = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'config' => __DIR__ . '/openssl.cnf'
];
$serverKey = openssl_pkey_new($config);
var_dump($serverKey);
while($msg = openssl_error_string()) {
    echo $msg . "\n";
}
