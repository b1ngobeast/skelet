<?php

namespace webizi\users;

use Exception;

class MunicipalitiesUser extends User
{

    public function approveBooksTransfer(int $orderID): bool
    {
        return true;
    }
    public function rejectBooksTransfer(int $orderID, string $reason): bool
    {
        return true;
    }
    public function getOrder(int $orderID, bool $excel = null): array | string
    {
        return true;
    }
    public function approveOrder(int $orderID): bool
    {
        return true;
    }
    public function rejectOrder(int $orderID, string $reason): bool
    {
        return true;
    }
}
