<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

use Webizi\Helpers\RequestHelper;
use Webizi\Services\BooksService;

RequestHelper::checkAuthAndMethod("PUT}");

$data = json_decode(file_get_contents("php://input"), true);

$result = BooksService::update($data);

RequestHelper::jsonResponse($result);