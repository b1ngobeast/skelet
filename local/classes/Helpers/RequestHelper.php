<?php

namespace Webizi\Helpers;

use Exception;

class RequestHelper
{
    public static function jsonSuccess($data = []): void
    {
        header('Content-Type: application/json');
        if(!is_array($data))
            $data = [$data];

        $result = [
            'error' => false,
            ...$data
        ];

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit();
    }
    public static function checkAuthAndMethod($method = 'GET'): void
    {
        global $USER;

        if (!$USER->IsAuthorized())
            throw new Exception("Пользователь не авторизован.", 401);

        if($_SERVER['REQUEST_METHOD'] != $method)
            throw new Exception('Ошибка метода', 405);
    }
    public static function jsonFail($data = []): void
    {
        if(!is_array($data))
            $data = [$data];

        $result = [
            'error' => true,
            ...$data
        ];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit();
    }
}