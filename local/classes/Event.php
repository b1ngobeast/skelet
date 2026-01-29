<?php

namespace Webizi;

use Exception;

class Event
{
    public function get(int $event): array
    {
        return [];
    }
    public function getAll(): array
    {
        return [];
    }
    public function create(array $data): int| Exception
    {
        return 1;
    }
    public function edit(int $ID, array $data): bool| Exception
    {
        $this->isExists();
        return true;
    }
    public function delete(int $ID): bool| Exception
    {
        $this->isExists();
        return true;
    }
    public function isExists(): bool
    {
        return true;
    }
}
