<?php
list($payload, $signature) = explode(PHP_EOL.PHP_EOL, file_get_contents(__DIR__.'/pow.txt'));

$publicKey = file_get_contents(__DIR__.'/pretend_public.pem');
$publicKeyResource = openssl_pkey_get_public($publicKey);

$verified = openssl_verify($payload, $signature, $publicKeyResource, OPENSSL_ALGO_SHA256);
if($verified === 1) {
    echo 'Yep, they did the work'.PHP_EOL;
    
    $result = json_decode($payload, true);
    
    $decent = ($result['score'] >= 80);
    if($decent) {
        echo 'Their score is good enough'.PHP_EOL;
    } else {
        echo 'Their score sucks'.PHP_EOL;
    }
    
    $recent = (time() - $result['time'] < 86400);
    if($recent) {
        echo 'And they did it recently'.PHP_EOL;
    } else {
        echo 'But they didn\'t do it recently'.PHP_EOL;
    }
} else {
    echo 'Nope. They\'re big fat phonies!'.PHP_EOL;
}
