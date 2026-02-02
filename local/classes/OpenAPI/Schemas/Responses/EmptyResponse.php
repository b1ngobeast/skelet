<?php

namespace Webizi\OpenAPI\Schemas\Responses;

abstract class EmptyResponse
{
    protected static bool $error = true;

    protected static string $message = '';

    protected static int $code = 0;

    public static function send($message = ''): void
    {
        header('Content-Type: application/json');
        http_response_code(self::$code);
        echo json_encode(['error' => self::$error, 'message' => $message ?? self::$message], JSON_UNESCAPED_UNICODE);
        exit();
    }
}