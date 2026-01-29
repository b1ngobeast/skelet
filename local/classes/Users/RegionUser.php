<?php

namespace Webizi\Users;

use Exception;

class RegionUser extends User
{
    protected int $userID;

    public function __construct()
    {
        $userId = "";
        $this->checkUserGroups(['region']);
        parent::__construct($userId);
    }

    public function getMunicipalities()
    {
        $muns = \Webizi\Models\MunicipalitiesTable::getList([
            'select' => ['*'],
        ])->fetchAll();
        foreach ($muns as $mun) {
            $result[] = ['NAME' => $mun['UF_NAME'], 'ID' => $mun['ID']];
        }
        return ['data' => $result];
    }
    public function getReaders(): array
    {
        return [];
    }

    public function getBooks(): bool
    {
        return true;
    }

    public function getOrders(): bool
    {
        return true;
    }

    public function getBookTransfers(): bool
    {
        return true;
    }
}
