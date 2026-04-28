<?php
$config = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC
];
$serverKey = openssl_pkey_new($config);
var_dump($serverKey);
while($msg = openssl_error_string()) {
    echo $msg . "\n";
}
