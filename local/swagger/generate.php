<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php');

use OpenApi\Generator;

try {
    // Сканирование директории
    $openapi = Generator::scan([$_SERVER['DOCUMENT_ROOT'] . '/local/classes/']);

    $yaml = $openapi->toYaml();
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/swagger/openapi.yaml', $yaml);

    $json = $openapi->toJson();
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/swagger/openapi.json', $json);

    clearstatcache();
//    header("Location: /local/swagger/");
    exit;
} catch (Exception $e) {
    echo 'Ошибка генерации OpenAPI документации: ' . $e->getMessage();
    exit(1);
}