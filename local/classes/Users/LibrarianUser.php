<?php

namespace Webizi\Users;

use Bitrix\Main\UserTable;
use Webizi\Books;
use Webizi\Users\ReaderUser;
use Exception;

class LibrarianUser extends User
{
    #
    private bool $isFindLibrarian = false;

    public function isFindLibrarian(): bool
    {
        return $this->isFindLibrarian;
    }
    public function __construct($org = "",$user = "")
    {
        $userId = "";
        if (empty($org) && empty($user)) {
            $this->checkUserGroups(['library']);
        } else {
            if (!empty($org)) {
                if (empty($user)) {
                    # получение библиотекаря по организации
                    $userId = $this->getLibraryOrgUser($org);
                    if (!empty($userId)) $this->isFindLibrarian = true;
                } else {
                    # получение библиотекаря по организации и пользователю
                    $userId = $this->getLibraryOrgUser($org,$user);
                    if (!empty($userId)) $this->isFindLibrarian = true;
                }
            }
        }
        parent::__construct($userId);
    }

    private function getLibraryOrgUser($org="",$user="")
    {
        $filter = [];
        if (!empty($user)) $filter['ID'] = $user;
//        if (!empty($org)) $filter['UF_ORG_ID'] = $org;
        if (!empty($org)) $filter['UF_BUILDING'] = $org;
        $userId = UserTable::getList([
            'select'=>['ID','UF_ORG_ID'],
            'filter'=>$filter,
        ])->fetch()['ID'];
        if (empty($userId)) return "";
        if (!$this::checkLibraryRole($userId)) return "";
        return $userId;
    }

    public static function checkLibraryRole($userId) : bool
    {
        $groups = \CUser::GetUserGroup($userId);
        $groupsObj = \Bitrix\Main\GroupTable::getList([
            'select'=>['ID','STRING_ID'],
            'filter'=>['STRING_ID'=>'library'],
        ])->fetch();
        if (in_array($groupsObj['ID'], (array)$groups)) {
            return true;
        } else {
            return false;
        }
    }

    public static function getLibWithOrg() : array
    {
        # проверяем пользователя
        $libUser = new LibrarianUser();
        # ошибка если пользователь не принадлежит роли "Библиотекарь"
//        $checkUser = $libUser->checkUserGroup();
        # ищем организацию в которой работает библиотекарь
        $organization = $libUser->getOrganizationAndClass()['organization'];
        return ['error'=>false,'org'=>$organization,'user'=>$libUser];
    }
}
