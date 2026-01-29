<?php

use Webizi\Helpers\RequestHelper;
use Webizi\Services\BooksService;
use Bitrix\Main\Application;

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

RequestHelper::checkAuthAndMethod("POST");

$request = Application::getInstance()->getContext()->getRequest();
$data = $request->getPostList()->toArray() ?? [];

//TODO доделать метод
$result = BooksService::add($data);

RequestHelper::jsonSuccess($result);