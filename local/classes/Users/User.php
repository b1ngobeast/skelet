<?php

namespace Webizi\Users;

use Bitrix\Main\AccessDeniedException;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserGroupTable;
use Bitrix\Main\UserTable;
use CSite;
use CUser;
use Webizi\Helpers\EnumHelper;
use Webizi\Helpers\RequestHelper;
use Webizi\Limitations;
use Webizi\Models\BuildingsTable;
use Webizi\Models\MunicipalitiesTable;
use Webizi\Models\OrganizationsTable;
use Webizi\Models\TeachHistoryTable;

# убрал абстрактность, так как есть общие методы для нескольких пользователей, тогда нет смысла делать разделять по ролям, а общие методы пишем тут
class User
{
    protected int $userID;
    # свойство подтверждающее роль пользователя
    protected bool $confirm = false;

    # заполняется после методов
    private array $organization = [];
    private string $class = "";
    private array $classes = [];
    private array $mun = [];
    private string $group = "";

    # справочник роль
    protected array $groupList = [
        'library',
        'director',
        'region',
        'mun',
        'admin',
        'class',
        'edu',
    ];

    protected array $editFields = [
        'NAME',
        'LAST_NAME',
        'SECOND_NAME',
        'EMAIL',
        'PERSONAL_BIRTHDAY',
        'PERSONAL_PHONE',
    ];

    public function __construct($userID = "")
    {
        global $USER;
        if (empty($userID)) {
            $this->userID = $USER->GetID();
            $this->group = $this->getGroups()['group'];
        } else {
            $this->userID = $userID;
            $this->group = $this->getGroups()['group'];
        }
    }

    # для одной роли
    public function checkUserGroup()
    {
        if (!$this->confirm) {
            throw new AccessDeniedException('У вас нет доступа к данному разделу');
        }
        return ['error' => false,];
    }

    # для нескольких ролей, для этого случая создаем объект User, а не объект отдельной роли (одна из причин почему этот класс не abstact)
    public function checkUserGroups(array $userGroups)
    {
        $userGroupIds = [];
        # получение ID групп пользователей
        $arUsersGroups = $this->getGroupListId($userGroups);
        foreach ($userGroups as $userGroup) {
            if (in_array($userGroup, $this->groupList)) {
                $userGroupIds[] = $arUsersGroups[$userGroup];
            }
        }
        if (!\CSite::InGroup($userGroupIds)) {
            throw new AccessDeniedException('У вас нет доступа к данному разделу');
        }
        return ['error' => false,];
    }

    # получение массива STRING_ID=>ID
    protected function getGroupListId(array $listGroups = []): array
    {
        $arUsersGroups = [];
        if (empty($listGroups)) $listGroups = $this->groupList;
        $groupsObj = \Bitrix\Main\GroupTable::getList([
            'select' => ['ID', 'STRING_ID'],
            'filter' => ['STRING_ID' => $listGroups],
        ]);
        while ($arGroups = $groupsObj->fetch()) {
            $arUsersGroups[$arGroups['STRING_ID']] = $arGroups['ID'];
        }
        return $arUsersGroups;
    }

    # получение по STRING_ID ID
    public static function getGroupId($stringId)
    {
        $groupsObj = \Bitrix\Main\GroupTable::getList([
            'select' => ['ID', 'STRING_ID'],
            'filter' => ['STRING_ID' => $stringId,],
        ])->fetch();
        return $groupsObj['ID'];
    }

    # получение [STRING_ID,NAME,ID]
    public function getRoles($groupsFilter = false)
    {
        if ($groupsFilter) {
            switch ($this->group) {
                case 'edu':
                    $groupsFilter = '';
                    break;
                case 'class':
                    $groupsFilter = 'edu';
                    break;
                case 'library':
                    $groupsFilter = ['edu', 'class'];
                    break;
                case 'admin':
                    $groupsFilter = ['edu', 'class','library'];
                    break;
                case 'director':
                    $groupsFilter = ['edu', 'class','library','admin'];
                    break;
                case 'mun':
                    $groupsFilter = ['edu', 'class','library','admin','director'];
                    break;
                case 'region':
                    $groupsFilter = $this->groupList;
                    break;
            }
        } else {
            $groupsFilter = $this->groupList;
        }
        $groupsObj = \Bitrix\Main\GroupTable::getList([
            'select' => ['ID', 'STRING_ID', 'NAME'],
            'filter' => ['STRING_ID' => $groupsFilter],
        ]);
        $res = [];
        while ($groups = $groupsObj?->fetchObject()) {
            $res[] = [
                'ROLE' => $groups->getStringId(),
                'NAME' => $groups->getName(),
                'ID' => $groups->getId(),
            ];
        }
        return $res;
    }

    # получение STRING_ID=>ID
    public function getRolesStringId()
    {
        $groupsObj = \Bitrix\Main\GroupTable::getList([
            'select' => ['ID', 'STRING_ID', 'NAME'],
            'filter' => ['STRING_ID' => $this->groupList],
        ]);
        $res = [];
        while ($groups = $groupsObj?->fetchObject()) {
            $res[$groups->getStringId()] = $groups->getId();
        }
        return $res;
    }

    public function getGroups()
    {
        $groups = \CUser::GetUserGroup($this->userID);
        # получение ID групп пользователей
        $arUsersGroups = $this->getGroupListId();
        $groupRes = "";
        foreach ($arUsersGroups as $groupStr => $id) {
            if (in_array($id, (array)$groups)) {
                $groupRes = $groupStr;
                break;
            }
        }
        if (empty($groupRes)) {
            throw new AccessDeniedException('У вас недостаточно прав для просмотра данной страницы');
        }
//        $this->group = $groupRes;
        return ['error' => false, 'group' => $groupRes];
    }

    public function getOrganizationAndClass($userFieldsData = [], $active = 'Y')
    {
        $class = "";
        $classes = [];
        $organization = [];
        $builds = [];
        $builds_data = [];
        $org = [];
        $build = [];
        # получаем enum для uf_status для фильтрации
        $enums = EnumHelper::getListXmlId(OrganizationsTable::getUfId(), ['UF_STATUS'], false);
        if (empty($userFieldsData)) {
            $userFields = $this->getUserFields($active);
        } else {
            $userFields = $userFieldsData;
        }
        # проверка роли
        if ($this->group == 'edu') {
            # получаем класс из userField и корпус
            $class = $userFields['UF_CLASS'];
            $build = BuildingsTable::getList([
                'select' => ['*', 'ORG'],
                'filter' => [
                    'ID' => $userFields['UF_BUILDING'],
                    'ORG.UF_STATUS' => $enums['active'],
                    'UF_ACTIVE' => 1
                ]
            ])->fetchObject();
//            echo json_encode($userFields);exit();
            if (empty($build)) {
                throw new \Exception('Организация, в архиве или заблокирована', 404);
            }
            $organization = [
                'BUILD_ID' => $build?->getId(),
                'ID' => $build->getOrg()->getId(),
                'UF_NAME' => $build->get('UF_NAME'),
                'UF_MUN_ID' => $build->getOrg()->get('UF_MUN_ID'),
            ];
//            if ($build->getOrg()->get('UF_IS_OC')) {
//                $organization['UF_NAME'] = $build->get('UF_NAME');
//            }
            $builds[] = $build->getId();

        } elseif ($this->group == 'class') {
            $class = $userFields['UF_CLASS'];
            # получаем список классов с привязками к корпусу/организации
            $teach = TeachHistoryTable::getList([
                'select' => ['*', 'BUILD', 'BUILD.ORG'],
                'filter' => [
                    'UF_USER_ID' => $this->userID,
                    'BUILD.UF_ACTIVE' => 1,
                    'BUILD.ORG.UF_STATUS' => $enums['active'],
//                    'BUILD.ID'=>$userFields['UF_ORG_ID'],
                    'UF_ACTIVE' => 1
                ]
            ])->fetchCollection();
            if (empty($teach)) {
                throw new ObjectNotFoundException('Организация, в архиве или заблокирована');
            }
            foreach ($teach as $el) {
                $classes[] = $el->get('UF_CLASS');
                $builds[] = $el->getBuild()->getId();
                $organization['ID'] = $el->getBuild()->getOrg()->getId();
                $organization['BUILD_ID'] = $el->getBuild()->getId();
                $organization['UF_NAME'] = $el->getBuild()?->get('UF_NAME');
                $organization['UF_MUN_ID'] = $el->getBuild()->getOrg()->get('UF_MUN_ID');
//                if ($el->getBuild()->getOrg()->get('UF_IS_OC')) {
//                    $organization['UF_NAME'] = $el->getBuild()->get('UF_NAME');
//                }
            }
//            echo json_encode($teach->getIdList());exit();
//            $organization = [
//                'ID'=>$build->getId(),
//                'UF_NAME'=>$build->getOrg()->get('UF_NAME'),
//            ];
        } elseif ($this->group == 'admin') {
            # получаем организацию через UserField
            $org = OrganizationsTable::getList([
                'select' => ['*', 'BUILD'],
                'filter' => ['ID' => $userFields['UF_ORG_ID'], 'UF_STATUS' => $enums['active']],
                'order' => ['BUILD.ID' => 'ASC'],
            ])->fetchObject();
            if (empty($org) || empty($org->getBuild())) {
                throw new ObjectNotFoundException('Организация, в архиве или заблокирована');
            }
            $organization = [
                'ID' => $org->getId(),
                'UF_NAME' => $org->get('UF_NAME'),
                'UF_MUN_ID' => $org->get('UF_MUN_ID'),
                'UF_IS_OC' => $org->get('UF_IS_OC'),
            ];
            $builds = $org->getBuild()->getIdList();
            foreach ($org->getBuild() as $building) {
                $builds_data[] = [
                    'NAME' => $building->get('UF_NAME'),
                    'ID' => $building->getId(),
                ];
            }
        } elseif ($this->group == 'director') {
            # получаем организацию через HL
            $org = OrganizationsTable::getList([
                'select' => ['*', 'BUILD'],
                'filter' => ['UF_DIRECTOR' => $this->userID, 'UF_STATUS' => $enums['active']],
                'order' => ['BUILD.ID' => 'ASC'],
            ])->fetchObject();
            if (empty($org) || empty($org->getBuild())) {
                throw new ObjectNotFoundException('Организация, в архиве или заблокирована');
            }
            $organization = [
                'ID' => $org->getId(),
                'UF_NAME' => $org->get('UF_NAME'),
                'UF_MUN_ID' => $org->get('UF_MUN_ID'),
                'UF_IS_OC' => $org->get('UF_IS_OC'),
            ];
            $builds = $org->getBuild()->getIdList();
            foreach ($org->getBuild() as $building) {
                $builds_data[] = [
                    'NAME' => $building->get('UF_NAME'),
                    'ID' => $building->getId(),
                ];
            }
        } elseif ($this->group == 'library') {
            # получаем организацию через UserField
            $build = BuildingsTable::getList([
                'select' => ['*', 'ORG'],
                'filter' => ['ID' => $userFields['UF_BUILDING'], 'ORG.UF_STATUS' => $enums['active']],
            ])->fetchObject();
            if (empty($build)) {
                throw new ObjectNotFoundException('Организация, в архиве или заблокирована');
            }
            $organization = [
                'ID' => $build->getOrg()->getId(),
                'BUILD_ID' => $build->getId(),
                'UF_NAME' => $build->get('UF_NAME'),
                'UF_MUN_ID' => $build->getOrg()->get('UF_MUN_ID'),
            ];
            $builds[] = $build->getId();
//            if ($build->getOrg()->get('UF_IS_OC')) {
//                $organization['UF_NAME'] = $build->get('UF_NAME');
//            }
        } elseif ($this->group == 'mun' || $this->group == 'region') {
            throw new AccessDeniedException('Нет доступа');
        }
        $this->organization = $organization;
        $this->class = (string)$userFields['UF_CLASS'];
        $this->classes = $classes;
        return [
            'error' => false,
            'organization' => $organization,
            'builds' => $builds,
            'builds_data' => $builds_data,
            'class' => $class,
            'classes' => $classes,
        ];
    }

    public function getMunicipality($userFieldsData = [], $active = 'Y'): array
    {
        # берем user field привязку к муниципалитету
        if (empty($userFieldsData)) {
            $userFields = $this->getUserFields($active);
        } else {
            $userFields = $userFieldsData;
        }

        $userMun = $userFields['UF_MUN_ID'];
        if (empty($userMun)) {
            throw new AccessDeniedException('Недостаточно прав для выполнения действия');
        }

        # находим организацию с проверкой на её активность
        $municipality = MunicipalitiesTable::getList([
            'select' => ['*'],
            'filter' => ['ID' => $userMun,],
        ])->fetch();
        if (empty($municipality)) {
            throw new ObjectNotFoundException('Муниципалитет не найден в системе');
        }
        $this->mun = $municipality;
        return [
            'error' => false,
            'mun' => $municipality
        ];
    }

    # роль в STRING_ID
    public function getRoleName($role)
    {
        $groupsObj = \Bitrix\Main\GroupTable::getList([
            'select' => ['ID', 'STRING_ID', 'NAME'],
            'filter' => ['STRING_ID' => $role,],
        ])->fetch();
        return $groupsObj['NAME'];
    }

    public function getUserFields($active = 'Y')
    {
        $filter = [
            'ID' => $this->userID,
        ];
        if ($active == 'Y') {
            $filter['ACTIVE'] = 'Y';
        }
        $userData = UserTable::getList([
            'select' => ['*', 'UF_MUN_ID', 'UF_ORG_ID', 'UF_CLASS', 'ACTIVE', 'UF_BUILDING'],
            'filter' => $filter,
        ])->fetch();
        if (empty($userData)) {
            throw new ObjectNotFoundException('Пользователь не найден или заблокирован');
        }
        return $userData;
    }

    public function getNotifications(): array
    {
        return [];
    }

    public function getProfileData($active = 'Y'): array
    {
        $profileData = [];
        $groups = $this->getGroups();
        $groupUser = $groups['group'];
        # получаем нужные данные
        $userFields = $this->getUserFields($active);
        if ($groupUser == 'mun') {
            # получение муниципалитета
            $mun = $this->getMunicipality($userFields, $active);
            $profileData['MUN'] = $mun['mun']['UF_NAME'];
            $profileData['MUN_ID'] = $mun['mun']['ID'];
        } elseif ($groupUser != 'region') {
            # для остальных ролей
            $organization = $this->getOrganizationAndClass($userFields, $active);
            $profileData['ORG_NAME'] = $organization['organization']['UF_NAME'];
            # получение класса для учащегося или классного руководителя
            if ($groupUser == 'edu') {
                $profileData['CLASS'] = $organization['class'];
            } elseif ($groupUser == 'class') {
                $profileData['CLASS'] = $organization['classes'];
            }
        }
        # получение общих данных
        $profileData['ROLE_NAME'] = $this->getRoleName($groupUser);
        $profileData['EMAIL'] = $userFields['EMAIL'];
        $profileData['PHONE'] = $userFields['PERSONAL_PHONE'];
        if ($groupUser == 'edu') {
            $profileData['LIMITS'] = Limitations::getUserLimitsString($this, $active);
        }
        $profileData['LAST_NAME'] = $userFields['LAST_NAME'];
        $profileData['NAME'] = $userFields['NAME'];
        $profileData['SECOND_NAME'] = $userFields['SECOND_NAME'];
        //        $profileData['FIO'] = $userFields['LAST_NAME'].' '.$userFields['NAME'].' '.$userFields['SECOND_NAME'];
        $profileData['PERSONAL_BIRTHDAY'] = (empty($userFields['PERSONAL_BIRTHDAY'])) ? null : $userFields['PERSONAL_BIRTHDAY']->toString();
        return $profileData;
    }

    public function editProfileData(array $data)
    {
        $groups = $this->getGroups();
        $groupUser = $groups['group'];
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $this->editFields)) {
                if ($key == 'PERSONAL_BIRTHDAY') {
                    $date = date('d.m.Y', strtotime($value));
                    if (strtotime($date) >= strtotime(date('d.m.Y'))) {
                        throw new \Exception('Проверьте указанную дату рождения', 400);
                    }
                    $value = new DateTime($date);
                }
                $result[$key] = $value;
            }
        }
        try {
            $editResult = new \CUser();
            $editResult->Update($this->getUserID(), $result);
            if (!empty($editResult->LAST_ERROR)) {
                throw new \Exception($editResult->LAST_ERROR, 400);
            }
            return ['error' => false];
        } catch (\Exception $e) {
            throw new \Exception('Ошибка при внесении изменений.', 400);
        }
    }

    public function getUserID(): int
    {
        return $this->userID;
    }

    # множественное получение ролей
    public static function getUserRoles($userList = [], $filterRoles = [])
    {
        $filter = ['USER_ID' => $userList];
        if (!empty($filterRoles)) $filter['GROUP.STRING_ID'] = $filterRoles;
        $groupsObj = \Bitrix\Main\UserGroupTable::getList([
            'select' => ['USER_ID', 'GROUP_ID', 'GROUP.STRING_ID', 'GROUP.NAME'],
            'filter' => $filter,
        ])->fetchCollection();
        $res = [];
        if (!empty($groupsObj)) {
            foreach ($groupsObj as $group) {
                $res[$group?->getUserId()] = [
                    'ID' => $group->getGroup()->getId(),
                    'STRING_ID' => $group->getGroup()->getStringId(),
                    'ROLE_NAME' => $group->getGroup()->getName(),
                ];
            }
        }
        return $res;
    }

    public static function getUserByGroup($group, $filter)
    {
        $filter['GROUPS_ID'] = $group;
        $users = CUser::getList($by = 'ID', $order = 'ASC', $filter, ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME']);
        $arUsers = [];
        while ($user = $users->fetch()) {
            $tmp['ID'] = $user['ID'];
            $tmp['FULL_NAME'] = $user['LAST_NAME'] . ' ' . $user['NAME'] . ' ' . $user['SECOND_NAME'];
            $arUsers[] = $tmp;
        }
        return ['data' => $arUsers];
    }

    public function checkNORMALUserGroups(array $userGroups): bool
    {
        $userGroupIds = [];
        # получение ID групп пользователей
        $arUsersGroups = $this->getGroupListId($userGroups);
        foreach ($userGroups as $userGroup) {
            if (in_array($userGroup, $this->groupList)) {
                $userGroupIds[] = $arUsersGroups[$userGroup];
            }
        }

        return \CSite::InGroup($userGroupIds);
    }

    public function getStudents($class = '', $build = '')
    {
        $this->checkUserGroups(['class', 'region', 'library', 'mun', 'director', 'admin']);
        $orgClass = $this->getOrganizationAndClass();
        $group = $this->getGroups()['group'];
        if (in_array($group, ['region', 'library', 'mun', 'director', 'admin']) && empty($class)) {
            throw new \Exception('Укажите класс.', 400);
        }
        if (in_array($group, ['region', 'mun', 'admin']) && empty($build)) {
            throw new \Exception('Укажите образовательную организацию.', 400);
        }
        $filter = [
            'UF_BUILDING' => (empty($build) || in_array($group, ['library', 'class', 'director'])) ? $orgClass['builds'][0] : $build,
            'UF_CLASS' => (empty($class) || $group == 'class') ? $orgClass['class'] : $class,
            'GROUPS.GROUP_ID' => self::getGroupId('edu')
        ];
        $users = UserTable::getList([
            'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME'],
            'filter' => $filter,
        ])->fetchAll();
        return ['data' => $users];
    }

    public function getOrganization(): array
    {
        return $this->organization;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getMun(): array
    {
        return $this->mun;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getOrganizationsByMun($mun)
    {
        $this->checkUserGroups(['mun', 'region']);
        $group = $this->getGroups()['group'];
        if ($group == 'mun' && $this->getMunicipality()['mun']['ID'] != $mun) {
            throw new AccessDeniedException('У вас нет доступа к данному разделу');
        }
        $result = OrganizationsTable::getList([
            'select' => ['ID', 'UF_NAME'],
            'filter' => ['UF_MUN_ID' => $mun]
        ])->fetchAll();
        return ['data' => $result];
    }

    public function getBuildsByOrg($org)
    {
        $this->checkUserGroups(['mun', 'region', 'director', 'admin', 'library']);
        $group = $this->getGroups()['group'];
        if (in_array($group, ['director', 'admin', 'library']) && $this->getOrganizationAndClass()['organization']['ID'] != $org) {
            throw new AccessDeniedException('У вас нет доступа к данному разделу');
        }
        if ($group == 'mun') {
            $orgFlag = OrganizationsTable::getList([
                'select' => ['ID'],
                'filter' => [
                    'UF_MUN_ID' => $this->getMunicipality()['mun']['ID'],
                    'ID' => $org
                ],
            ])->fetch();
            if (empty($orgFlag)) {
                throw new AccessDeniedException('У вас нет доступа к данному разделу');
            }
        }
        $result = BuildingsTable::getList([
            'select' => ['ID', 'UF_NUMBER','UF_NAME'],
            'filter' => ['UF_ORG_ID' => $org]
        ])->fetchAll();
        return ['data' => $result];
    }

    public function getMunicipalities()
    {
        $result=[];
        if (in_array($this->getGroup(),['mun','region','director'])) {
            $muns = \Webizi\Models\MunicipalitiesTable::getList([
                'select' => ['*'],
            ])->fetchAll();
            foreach ($muns as $mun) {
                $result[] = ['NAME' => $mun['UF_NAME'], 'ID' => $mun['ID']];
            }
        }
        return ['data' => $result];
    }
}
