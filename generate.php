<?php
$url = 'https://api.ssllabs.com/api/v2/analyze';
$host = $_GET['host'];

if(empty($host)) {
    die('Specify host parameter');
}

$parameters = http_build_query([
    'host' => $host,
]);

$ch = curl_init("{$url}?{$parameters}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
]);

$result = null;
while(empty($result) || $result['status'] !== 'READY') {
    $result = json_decode(curl_exec($ch), true);
    
    sleep(10);
}

// Pretend the following is done by the check service instead

$gradeMap = [
    'A+' => 100,
    'A' => 90,
    'A-' => 80,
    'B' => 70,
    'C' => 40,
    'D' => 30,
    'E' => 10,
    'F' => 0,
    'T' => 0,
    'M' => 0,
];

$payload = json_encode([
    'host' => $result['host'],
    'port' => $result['port'],
    'time' => round($result['testTime'] / 1000),
    'version' => $result['engineVersion'],
    'type' => $result['criteriaVersion'],
    'score' => $gradeMap[$result['endpoints'][0]['grade']],
]);

$privateKey = file_get_contents(__DIR__.'/pretend_private.pem');

$privateKeyResource = openssl_pkey_get_private($privateKey);

openssl_sign($payload, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

if(!empty($_GET['write'])) {
    file_put_contents(__DIR__.'/pow.txt', $payload.PHP_EOL.PHP_EOL);
    file_put_contents(__DIR__.'/pow.txt', $signature, FILE_APPEND);
}

?>
<h3>Payload (<?=strlen($payload)?> bytes)</h3>
<pre><?=$payload;?></pre>

<h3>Signature</h3>
<pre><?=base64_encode($signature)?></pre>
