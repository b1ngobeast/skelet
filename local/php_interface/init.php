<?php
/*Подключение автозагрузки композера*/
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

/**
 * Определение файла для логгов, по хорошему определять в /bitrix/dbconn.php
 * https://dev.1c-bitrix.ru/api_help/main/functions/debug/addmessage2log.php
 */
define("LOG_FILENAME", $_SERVER["DOCUMENT_ROOT"] . "/path/to/logFile.txt");