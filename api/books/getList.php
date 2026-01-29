<?php

use Webizi\Helpers\RequestHelper;
use Webizi\Services\BooksService;
use Bitrix\Main\Application;

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

RequestHelper::checkAuthAndMethod("GET");

$request = Application::getInstance()->getContext()->getRequest();
$data = $request->getQueryList()->toArray() ?? [];
//TODO доделать метод
$result = BooksService::getList($data);

RequestHelper::jsonSuccess($result);