<?php

namespace webizi\users;

use Exception;

class DirectorUser extends User
{

    public function getReservedBooks(): array
    {
        return [];
    }
    public function reserveBooks(array $books): bool
    {
        return true;
    }
    public function refundBooks(array $books): bool
    {
        return true;
    }
    public function createBooksTransfer(array $data): int
    {
        $orderID = 1;
        return $orderID;
    }
    public function editBooksTransfer(int $orderID, array $data): bool
    {
        return true;
    }
    public function deleteBooksTransfer(int $orderID): bool
    {
        return true;
    }
    public function createTransferAct(int $orderID): array | string
    {
        return [];
    }
    public function getTransferAct(int $orderID): array | string
    {
        return [];
    }
    public function getOrder(int $orderID, bool $excel = null): array | string
    {
        return true;
    }
}
