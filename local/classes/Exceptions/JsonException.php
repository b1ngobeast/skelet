<?php
namespace webizi\Exception;

use Exception;
class JsonException extends Exception
{
    protected $httpCode;
    protected $data;
    protected $backtrace;

    public function __construct($message, $httpCode = 400, array $data = [], $withTrace = false)
    {
        parent::__construct($message, $httpCode);
        $this->httpCode = $httpCode;
        $this->data = $data;

        if ($withTrace || (defined("DEBUG") && DEBUG === true)) {
            $this->backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getBacktrace(): array
    {
        return $this->backtrace ?? [];
    }
}