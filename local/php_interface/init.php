<?php
/*Подключение автозагрузки композера*/
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

/**
 * Определение файла для логгов
 * https://dev.1c-bitrix.ru/api_help/main/functions/debug/addmessage2log.php
 */
define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"] . "/path/to/logFile.txt");