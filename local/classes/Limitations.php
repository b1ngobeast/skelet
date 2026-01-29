<?php

namespace Webizi;

use Bitrix\Crm\Integration\Intranet\CustomSection\Page;
use Bitrix\Main\AccessDeniedException;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\Test\Typography\AuthorTable;
use Bitrix\Main\UserTable;
use Webizi\Helpers\EnumHelper;
use Webizi\Models\AuthorsTable;
use Bitrix\Main\ORM\Fields\ArrayField;
use Webizi\Models\CatalogBookTable;
use Webizi\Models\GenresTable;
use Bitrix\Main\Type\DateTime;
use Webizi\Models\LimitationsHistoryTable;
use Webizi\Models\LimitationsTable;
use Webizi\Models\SignProductsTable;
use Webizi\Services\OrganizationsService;
use Webizi\Users\User;

class Limitations
{
    protected User $user;
    protected array $levelList = [
        'region',
        'mun',
        'org',
        'class',
        'edu'
    ];
    protected array $typeUserList = [
        'region',
        'mun',
        'director',
        'admin',
        'library',
        'class'
    ];
    protected array $statusList = [
        'active',
        'deleted',
    ];
    protected string $level;
    protected string $typeUser;
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->setLevelLimitAndType();
    }

    private function setLevelLimitAndType()
    {
        $userGroup = $this->user->getGroups()['group'];
        $this->typeUser = $userGroup;
        if (!in_array($userGroup, $this->typeUserList)) throw new AccessDeniedException("Нет доступа к данному разделу");
        $this->level = match ($userGroup) {
            'region', 'mun', 'class' => $userGroup,
            'admin', 'director', 'library' => 'org',
            default => throw new AccessDeniedException("Нет доступа к данному разделу"),
        };
    }

    public function getList($data){
        if (!in_array($data['level'], $this->levelList)) throw new \Exception('Проверьте внесенные данные',400);
        if (!empty($data['userId']) && $data['level']=='edu') {
            # получение организации ребенка
            $child = new User($data['userId']);
            try {
                $childData = $child->getOrganizationAndClass();
            } catch (AccessDeniedException $e) {
                throw new \Exception("Проверьте указанные данные",400);
            }
        }

        # получаем список уже существующих ограничений
        if ($this->level=='mun') {
            $userMun = $this->user->getMunicipality()['mun']['ID'];
            if (!empty($data['mun']) && $data['mun'] != $userMun) throw new \Exception('Недостаточно прав',400);
            if (!empty($data['org']) && !OrganizationsService::checkOrgMun($userMun, $data['org'])) throw new \Exception('Недостаточно прав',400);
            if (!empty($childData) && $userMun!=$childData['organization']['UF_MUN_ID']) throw new \Exception('Недостаточно прав',400);
            if (empty($data['mun'])) $data['mun'] = $userMun;
        } elseif (in_array($this->level,['class','org'])) {
            $userOrgClass = $this->user->getOrganizationAndClass();
            $userOrg = $userOrgClass['organization']['BUILD_ID'];
            $userBuild = $userOrgClass['builds'];
            $userClass = $userOrgClass['classes'];
            if (!empty($data['org']) && $data['org'] != $userOrg  && !in_array($data['org'], (array)$userBuild)) throw new \Exception('Недостаточно прав',400);
            if (!empty($data['class']) && $this->level=='class' && !in_array($data['class'],$userClass)) throw new \Exception('Недостаточно прав',400);
            if (!empty($childData) && ($userOrg!=$childData['organization']['BUILD_ID'] || ($this->level=='class' && $userClass!=$childData['class']))) throw new \Exception('Недостаточно прав',400);
            if (empty($data['org'])) $data['org'] = $userOrg;
            if (empty($data['class'])) $data['class'] = $userClass;
        }
        $filter=[];
        $filterHistory=[];
        switch ($data['level']) {
            case "region":
                $filterHistory['UF_REGION'] = 1;
                if ($this->typeUser!='region') throw new \Exception('Недостаточно прав',400);
                break;
            case "mun":
                //if(!isset($data['mun']) || empty($data['mun'])) throw new \Exception('Не достаточно данных',400);
                $filterHistory['UF_MUN_ID'] = $data['mun'];
                if (!in_array($this->typeUser,['region','mun'])) throw new \Exception('Недостаточно прав',400);
                break;
            case "org":
                //if(!isset($data['org']) || empty($data['org'])) throw new \Exception('Не достаточно данных',400);
                $filterHistory['UF_BUILDING'] = $data['org'];
                if (in_array($this->typeUser,['class'])) throw new \Exception('Недостаточно прав',400);
                break;
            case "class":
                //if(!isset($data['org']) || empty($data['org'])) throw new \Exception('Не достаточно данных',400);
                if(!isset($data['class']) || empty($data['class'])) throw new \Exception('Не достаточно данных',400);
                $filterHistory['UF_BUILDING'] = $data['org'];
                $filterHistory['UF_CLASS'] = $data['class'];
                break;
            case 'edu':
                if(!isset($data['userId']) || empty($data['userId'])) throw new \Exception('Не достаточно данных',400);
                $filter['ID']=$data['userId'];
                if ($this->level=='class'){
                    $filter['UF_CLASS'] = $userClass;
                    $filter['UF_BUILDING'] = $userOrg;
                } elseif ($this->level=='org') {
                    $filter['UF_BUILDING'] = $userOrg;
                }
                break;
        }

         if ($data['level']!='edu') {
            $limitHistory = LimitationsHistoryTable::getList([
                'select'=>['*'],
                'filter'=>$filterHistory
            ])->fetchAll();
            $arrayParamsReference = ['UF_LIMIT_AUTHORS', 'UF_LIMIT_BOOKS', 'UF_LIMIT_GENRES', 'UF_LIMIT_SIGNS'];
            $arrayParamsReferenceValue = [];
            foreach($limitHistory as $keyLimit => $valueLimit){
                foreach($arrayParamsReference as $reference){
                    foreach($valueLimit[$reference] as $valueReferenceID){
                        $arrayParamsReferenceValue[$reference][] = $valueReferenceID;
                    }
                    $arrayParamsReferenceValue[$reference] = array_unique((array)$arrayParamsReferenceValue[$reference]);
                }
            }

            $map = LimitationsHistoryTable::getMap();
            $mapArray = [];
            $instanceArray = [];
            foreach($map as $key => $mapValue){
                if($mapValue instanceof ArrayField){
                    $arrayArrayFields[] = $mapValue->getColumnName();
                    //$mapArray[$mapValue->getColumnName()] = $mapValue->getParameter('UFID');
                    if($mapValue->getColumnName() == 'UF_LIMIT_SIGNS'){
                        $selectArray = ['ID', 'UF_CATEGORY'];
                    }else{
                        $selectArray = ['ID', 'UF_NAME'];
                    }
                    $arrayInstance = $mapValue->getParameter('UFID')::getList([
                        'select' => $selectArray,
                        'filter' => ['ID' => $arrayParamsReferenceValue[$mapValue->getColumnName()]]
                    ])->fetchAll();
                    foreach($arrayInstance as $valueInstance){
                        $instanceArray[$mapValue->getColumnName()][$valueInstance['ID']]['ID'] = $valueInstance['ID'];
                        if($mapValue->getColumnName() == 'UF_LIMIT_SIGNS'){
                            $instanceArray[$mapValue->getColumnName()][$valueInstance['ID']]['NAME'] = $valueInstance['UF_CATEGORY'];
                        }else{
                            $instanceArray[$mapValue->getColumnName()][$valueInstance['ID']]['NAME'] = $valueInstance['UF_NAME'];
                        }
                    }   
                }
            }
            foreach($limitHistory as $keyLimit => $valueLimit){
                $limitArray = [
                    'ID'=>$valueLimit['ID'],
                    'UF_LIMIT_AUTHORS'=>[],
                    'UF_LIMIT_BOOKS'=>[],
                    'UF_LIMIT_GENRES'=>[],
                    'UF_LIMIT_SIGNS'=>[],
                    'level'=>'',
                    'mun'=>null,
                    'org'=>null,
                    'class'=>null,
                ];
//                $limitArray['ID'] = $valueLimit['ID'];
                /*$limitArray['UF_REGION'] = $valueLimit['UF_REGION'];
                $limitArray['UF_TIMESTAMP'] = $valueLimit['UF_TIMESTAMP']->toString();
                $limitArray['UF_CLASS'] = $valueLimit['UF_CLASS'];
                $limitArray['UF_MUN_ID'] = $valueLimit['UF_MUN_ID'] ? (int)$valueLimit['UF_MUN_ID'] : null;
                $limitArray['UF_BUILDING'] = $valueLimit['UF_BUILDING'] ? (int)$valueLimit['UF_BUILDING'] : null;*/
                foreach($arrayArrayFields as $nameArrayFields){
                    foreach($valueLimit[$nameArrayFields] as $idArrayValue){
                        $limitArray[$nameArrayFields][] = (object)$instanceArray[$nameArrayFields][$idArrayValue];
                    }
                }

                $res['data'] = $limitArray;
                break;
            }
            if (empty($res['data'])) {
                $res['data'] = [
                    'ID'=> '',
                    'level'=> '',
                    'mun'=> null,
                    'org'=> null,
                    'class'=> null,
                    'UF_LIMIT_AUTHORS'=> [],
                    'UF_LIMIT_BOOKS'=> [],
                    'UF_LIMIT_GENRES'=> [],
                    'UF_LIMIT_SIGNS'=> [],
                ];
            }
        }
        return $res;
    }

    # добавление ограничения в Limitations и LimitationsHistory
    public function addLimit($data)
    {
//        echo json_encode($data['level']);exit();
        if (!in_array($data['level'], $this->levelList)) throw new \Exception('Проверьте внесенные данные', 400);

        if (!empty($data['userId']) && $data['level'] == 'edu') {
            # получение организации ребенка
            $child = new User($data['userId']);
            try {
                $childData = $child->getOrganizationAndClass();
            } catch (AccessDeniedException $e) {
                throw new \Exception("Проверьте указанные данные", 400);
            }
        }
        $updateData = [
            'UF_LIMIT_AUTHORS' => array_column($data['UF_LIMIT_AUTHORS'] ?? [], 'ID'),
            'UF_LIMIT_BOOKS' => array_column($data['UF_LIMIT_BOOKS'] ?? [], 'ID'),
            'UF_LIMIT_GENRES' => array_column($data['UF_LIMIT_GENRES'] ?? [], 'ID'),
            'UF_LIMIT_SIGNS' => array_column($data['UF_LIMIT_SIGNS'] ?? [], 'ID'),
        ];
        # получаем список уже существующих ограничений
        if ($this->level == 'mun') {
            $userMun = $this->user->getMunicipality()['mun']['ID'];
            if (!empty($data['mun']) && $data['mun'] != $userMun) throw new \Exception('Невозможно установить ограничение с указанным фильтром',400);
            if (!empty($data['org']) && !OrganizationsService::checkOrgMun($userMun, $data['org'])) throw new \Exception('Невозможно установить ограничение с указанным фильтром',400);
            if (!empty($childData) && $userMun!=$childData['organization']['UF_MUN_ID']) throw new \Exception('Невозможно установить ограничение с указанным фильтром',400);
            if (empty($data['mun'])) $data['mun'] = $userMun;
        } elseif (in_array($this->level,['class','org'])) {
            $userOrgClass = $this->user->getOrganizationAndClass();
            $userOrg = $userOrgClass['organization']['BUILD_ID'];
            $userBuild = $userOrgClass['builds'];
            $userClass = $userOrgClass['classes'];
//            echo json_encode(in_array($data['class'],$userClass));exit();
            if (!empty($data['org']) && $data['org'] != $userOrg && !in_array($data['org'], (array)$userBuild)) throw new \Exception('Невозможно установить ограничение с указанным фильтром',400);
            if (!empty($data['class']) && $this->level=='class' && !in_array($data['class'],$userClass)) throw new \Exception('Невозможно установить ограничение с указанным фильтром1',400);
            if (!empty($childData) && ($userOrg!=$childData['organization']['BUILD_ID'] || ($this->level=='class' && $userClass!=$childData['class']))) throw new \Exception('Невозможно установить ограничение с указанным фильтром',400);
            if (empty($data['org'])) $data['org'] = $userOrg;
            if (empty($data['class'])) $data['class'] = $userClass;
        }
        $filter = [];
        $filterHistory = [];
        switch ($data['level']) {
            case "region":
                $filterHistory['UF_REGION'] = 1;
                $updateData['UF_REGION'] = 1;
                $updateData['UF_MUN_ID'] = null;
                $updateData['UF_BUILDING'] = null;
                if ($this->typeUser != 'region') throw new \Exception('Невозможно установить ограничение с указанным фильтром', 400);
                break;
            case "mun":
                $filterHistory['UF_MUN_ID'] = $data['mun'];
                $updateData['UF_MUN_ID'] = $data['mun'];
                $updateData['UF_BUILDING'] = null;
                $updateData['UF_REGION'] = 0;
                if (!in_array($this->typeUser, ['region', 'mun'])) throw new \Exception('Невозможно установить ограничение с указанным фильтром', 400);
                break;
            case "org":
                $filterHistory['UF_BUILDING'] = $data['org'];
                $updateData['UF_BUILDING'] = $data['org'];
                $updateData['UF_REGION'] = 0;
                $updateData['UF_MUN_ID'] = null;
                if (in_array($this->typeUser,['class'])) throw new \Exception('Невозможно установить ограничение с указанным фильтром',400);
                break;
            case "class":
                $filterHistory['UF_BUILDING'] = $data['org'];
                $filterHistory['UF_CLASS'] = $data['class'];
                $updateData['UF_BUILDING'] = $data['org'];
                $updateData['UF_CLASS'] = $data['class'];
                $updateData['UF_REGION'] = 0;
                $updateData['UF_MUN_ID'] = null;
                break;
            case 'edu':
                $filter['ID'] = $data['userId'];
                $updateData['UF_REGION'] = 0;
                $updateData['UF_MUN_ID'] = null;
                if ($this->level == 'class') {
                    $filter['UF_CLASS'] = $userClass;
                    $filter['UF_BUILDING'] = $userOrg;
                    $updateData['UF_CLASS'] = $userClass;
                    $updateData['UF_BUILDING'] = $userOrg;
                } elseif ($this->level=='org') {
                    $filter['UF_BUILDING'] = $userOrg;
                    $updateData['UF_BUILDING'] = $userOrg;
                }
                break;
        }
        $updateData['UF_TIMESTAMP'] = new DateTime();
        if ($data['level'] != 'edu') {
            # подразумевается что будут отправлены также старые поля
            $limitHistory = LimitationsHistoryTable::getList([
                'select' => ['ID'],
                'filter' => $filterHistory
            ])->fetch();
            $updateData['UF_USER_ID'] = $this->user->getUserID();
            # изменяем данные
            if (empty($limitHistory)) {
                #BUG не вносится дата и время, класс, ID орги и ID муна
                $add = LimitationsHistoryTable::add($updateData);
                if (!$add->isSuccess()) {
                    throw new \Exception('Ошибка при добавлении ограничений', 400);
                } else {
                    return ['error' => false];
                }
            } else {
                $update = LimitationsHistoryTable::update($limitHistory['ID'], $updateData);
                if (!$update->isSuccess()) {
                    throw new \Exception('Ошибка при добавлении ограничений', 400);
                } else {
                    return ['error' => false];
                }
            }
        } else {
            # подразумевается что будут отправлены также старые поля
            $filter['GROUPS.GROUP_ID'] = User::getGroupId('edu');
            # получаем users id для получения ограничений
            $users = UserTable::getList([
                'select' => ['GROUPS.GROUP_ID'],
                'filter' => $filter,
            ])->fetchObject();
            if (empty($users)) throw new \Exception('Невозможно установить ограничения, не найдены обучающиеся', 400);
            # получение id ограничения

            $limits = LimitationsTable::getList([
                'select' => ['*'],
                'filter' => ['UF_LIMITED_USER' => $users->getId()],
            ])->fetch();
            # изменяем данные
            $updateData['UF_USER_WHO_RESTRICTS'] = $this->user->getUserID();
            if (empty($limits)) {
                #BUG не вносится, кого ограничивают
                $add = LimitationsTable::add($updateData);
                if (!$add->isSuccess()) {
                    throw new \Exception('Ошибка при добавлении ограничений', 400);
                } else {
                    return ['error' => false];
                }
            } else {
                $update = LimitationsTable::update($limits['ID'], $updateData);
                if (!$update->isSuccess()) {
                    throw new \Exception('Ошибка при добавлении ограничений', 400);
                } else {
                    return ['error' => false];
                }
            }
        }
    }

    # получить для редактирования список
    //    public function getLimitsForEdit($level,$class="",$userId="",$orgId="",$munId="")
    //    {
    //        if (!in_array($this->levelList,$level)) throw new \Exception("Не указаны данные",400);
    //
    //        # id может быть организации, муна, ребенка(user_id) если пусто то регион
    //        $filter = [];
    //        switch ($level) {
    //            case "region":
    //                break;
    //            case "mun":
    //                $filter['UF_MUN_ID']=$munId;
    //                break;
    //            case "org":
    //                $filter['UF_ORG_ID']=$orgId;
    //                break;
    //            case "class":
    //                $filter['UF_ORG_ID']=$orgId;
    //                $filter['UF_CLASS']=$class;
    //                break;
    //            case 'edu':
    //                $filter['ID']=$userId;
    //                break;
    //        }
    //        $filter['GROUPS.GROUP_ID']='edu';
    //        # получаем users id для получения ограничений
    //        $users = UserTable::getList([
    //            'select'=>['GROUPS.GROUP_ID'],
    //            'filter' => $filter,
    //        ])->fetchCollection();
    //
    //        if (empty($users)) throw new \Exception('Невозможно установить ограничения, не найдены обучающиеся',400);
    //
    //        if ($this->level!='region') {
    //            # получаем массив доступности редактирования исходя из иерархии
    //            $editArray = $this->getEditArray($users);
    //        }
    //
    //        $limits = LimitationsTable::getList([
    //            'select'=>['*'],
    //            'filter'=>['UF_LIMITED_USER'=>$users->getIdList()]
    //        ]);
    //        $result = [];
    //        while ($limit = $limits->fetch()) {
    //            if (empty($editArray)) {
    //                $result['AUTHORS'] = array_map(function($author) {
    //                    return [$author, 'edit' => true];
    //                }, $limit['UF_LIMIT_AUTHORS']);
    //                $result['GENRES'] = array_map(function($author) {
    //                    return [$author, 'edit' => true];
    //                }, $limit['UF_LIMIT_GENRES']);
    //                $result['BOOKS'] = array_map(function($author) {
    //                    return [$author, 'edit' => true];
    //                }, $limit['UF_LIMIT_BOOKS']);
    //                $result['SIGNS'] = array_map(function($author) {
    //                    return [$author, 'edit' => true];
    //                }, $limit['UF_LIMIT_SIGNS']);
    //            } else {
    //
    //            }
    //        }
    //        return $result;
    //    }

    //    protected function getEditArray($users)
    //    {
    //        $status = EnumHelper::getListXmlId(LimitationsHistoryTable::getUfId(),['UF_STATUS'],false);
    //        $limitHistory = LimitationsHistoryTable::getList([
    //            'select'=>['*','MUN_ID'=>'ORG.UF_MUN_ID'],
    //            'filter' => [
    //                'UF_STATUS'=>$status['active'],
    //                'LOGIC'=>'OR',
    //                [
    //                    'UF_REGION'=>1
    //                ],
    //                [
    //                    'UF_EDU'=>$users->getIdList(),
    //                ],
    //                [
    //                    'UF_ORG_ID'=>$users->getUfOrgIdList()
    //                ],
    //                [
    //                    'UF_MUN_ID'=>$users->getUfMunIdList()
    //                ]
    //            ],
    //        ]);
    //        $editArray = [
    //            'edit'=>[
    //                'AUTHORS'=>[],
    //                'SIGNS'=>[],
    //                'BOOKS'=>[],
    //                'GENRES'=>[]
    //            ],
    //            'not_edit'=>[
    //                'AUTHORS'=>[],
    //                'SIGNS'=>[],
    //                'BOOKS'=>[],
    //                'GENRES'=>[]
    //            ],
    //        ];
    //        if ($this->level=='mun') {
    //            $userMun = $this->user->getMunicipality()['mun']['ID'];
    //        } elseif (in_array($this->level,['class','org'])) {
    //            $userOrg = $this->user->getOrganizationAndClass()['organization']['ID'];
    //            $userClass = $this->user->getOrganizationAndClass()['class'];
    //        }
    //        while ($history = $limitHistory->fetch()) {
    //            if ($history['UF_REGION']) {
    //                array_push($editArray['not_edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                array_push($editArray['not_edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                array_push($editArray['not_edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                array_push($editArray['not_edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //            } elseif (!empty($history['UF_MUN_ID'])) {
    //                if ($this->level=='mun' && $userMun==$history['UF_MUN_ID']) {
    //                    array_push($editArray['edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                    array_push($editArray['edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                    array_push($editArray['edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                    array_push($editArray['edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //                } else {
    //                    array_push($editArray['not_edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                    array_push($editArray['not_edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                    array_push($editArray['not_edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                    array_push($editArray['not_edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //                }
    //            } elseif (!empty($history['UF_ORG_ID']) && empty($history['UF_CLASS'])) {
    //                if (($this->level=='mun' && $userMun==$history['MUN_ID']) || ($this->level=='org' && $userOrg==$history['UF_ORG_ID'])) {
    //                    array_push($editArray['edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                    array_push($editArray['edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                    array_push($editArray['edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                    array_push($editArray['edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //                } else {
    //                    array_push($editArray['not_edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                    array_push($editArray['not_edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                    array_push($editArray['not_edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                    array_push($editArray['not_edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //                }
    //            } elseif (!empty($history['UF_CLASS'])) {
    //                if (($this->level=='mun' && $userMun==$history['MUN_ID']) || ($this->level=='org' && $userOrg==$history['UF_ORG_ID']) ||
    //                    ($this->level=='class' && $userClass==$history['UF_CLASS'] && $userOrg==$history['UF_ORG_ID'])
    //                ) {
    //                    array_push($editArray['edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                    array_push($editArray['edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                    array_push($editArray['edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                    array_push($editArray['edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //                } else {
    //                    array_push($editArray['not_edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                    array_push($editArray['not_edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                    array_push($editArray['not_edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                    array_push($editArray['not_edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //                }
    //            } elseif (!empty($history['UF_EDU'])) {
    //                array_push($editArray['edit']['AUTHORS'], ...$history['UF_LIMIT_AUTHORS']);
    //                array_push($editArray['edit']['SIGNS'], ...$history['UF_LIMIT_SIGNS']);
    //                array_push($editArray['edit']['BOOKS'], ...$history['UF_LIMIT_BOOKS']);
    //                array_push($editArray['edit']['GENRES'], ...$history['UF_LIMIT_GENRES']);
    //            }
    //        }
    //        return $editArray;
    //    }

    /**
     * @param User $user
     * @param $catalogObj указываем объект, а не массив
     * @return bool
     */
    public static function checkUserLimits(User $user, $catalogObj, $limits = []): bool
    {
        if (empty($limits)) {
            $limits = self::getUserLimits($user);
        }
        # проверяем соответствие авторов
        if (
            in_array($catalogObj->get('UF_AUTHOR'), (array)$limits['UF_LIMIT_AUTHORS']) || in_array($catalogObj->getId(), (array)$limits['UF_LIMIT_BOOKS']) ||
            in_array($catalogObj->get('UF_SIGN_PRODUCT'), (array)$limits['UF_LIMIT_SIGNS']) || in_array($catalogObj->get('UF_GENRE'), (array)$limits['UF_LIMIT_GENRES'])
        ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param User $user
     * @param $catalogCollection указываем коллекцию, а не объект
     * @return bool
     */
    public static function checkUserLimitsCollection(User $user, $catalogCollection): bool
    {
        $limits = self::getUserLimits($user);
        # проверяем соответствие авторов
        foreach ($catalogCollection as $catalogObj) {
            if (
                in_array($catalogObj->get('UF_AUTHOR'), (array)$limits['UF_LIMIT_AUTHORS']) || in_array($catalogObj->getId(), (array)$limits['UF_LIMIT_BOOKS']) ||
                in_array($catalogObj->get('UF_SIGN_PRODUCT'), (array)$limits['UF_LIMIT_SIGNS']) || in_array($catalogObj->get('UF_GENRE'), (array)$limits['UF_LIMIT_GENRES'])
            ) {
                return false;
            }
        }
        return true;
    }

    public static function getUserLimitsString(User $user, $active = 'Y'): array
    {
        $result = [
            'AUTHORS' => [],
            'GENRES' => [],
            'SIGNS' => [],
            'BOOKS' => []
        ];
        $limits = self::getUserLimits($user, $active);
        if (!empty($limits)) {
            if (!empty($limits['UF_LIMIT_AUTHORS'])) {
                $authors = AuthorsTable::getList([
                    'select' => ['*'],
                    'filter' => ['ID' => $limits['UF_LIMIT_AUTHORS']]
                ])->fetchCollection();
                if (!empty($authors)) {
                    $result['AUTHORS'] = $authors->getUfNameList();
                }
            }
            if (!empty($limits['UF_LIMIT_SIGNS'])) {
                $signs = SignProductsTable::getList([
                    'select' => ['*'],
                    'filter' => ['ID' => $limits['UF_LIMIT_SIGNS']]
                ])->fetchCollection();
                if (!empty($signs)) {
                    $result['SIGNS'] = $signs->getUfCategoryList();
                }
            }
            if (!empty($limits['UF_LIMIT_BOOKS'])) {
                $books = CatalogBookTable::getList([
                    'select' => ['*'],
                    'filter' => ['ID' => $limits['UF_LIMIT_BOOKS']]
                ])->fetchCollection();
                if (!empty($authors)) {
                    $result['BOOKS'] = $books->getUfNameList();
                }
            }
            if (!empty($limits['UF_LIMIT_GENRES'])) {
                $genres = GenresTable::getList([
                    'select' => ['*'],
                    'filter' => ['ID' => $limits['UF_LIMIT_GENRES']]
                ])->fetchCollection();
                if (!empty($genres)) {
                    $result['GENRES'] = $genres->getUfNameList();
                }
            }
            //            $result['BOOKS'] = $limits['UF_LIMIT_BOOKS'];
            //            $result['GENRES'] = $limits['UF_LIMIT_GENRES'];
        }
        return $result;
    }

    public static function getUserLimits(User $user, $active = 'Y')
    {
        $limits = LimitationsTable::getList([
            'select' => ['*'],
            'filter' => ['UF_LIMITED_USER' => $user->getUserID()]
        ])->fetch();

        # получаем организацию, муниципалитет и класс ребенка
        $childData = $user->getOrganizationAndClass([], $active);
        $limitationHistory = LimitationsHistoryTable::getList([
            'select' => ['*'],
            'filter' => [
                'LOGIC' => 'OR',
                'UF_MUN_ID' => $childData['organization']['UF_MUN_ID'],
                'UF_REGION' => 1,
                ['UF_ORG_ID' => $childData['organization']['ID'], 'UF_CLASS' => ""],
                ['UF_CLASS' => $childData['class'], 'UF_ORG_ID' => $childData['organization']['ID'],]
            ],
        ]);
        if (empty($limits)) {
            $limits = [
                'UF_LIMIT_AUTHORS' => [],
                'UF_LIMIT_BOOKS' => [],
                'UF_LIMIT_GENRES' => [],
                'UF_LIMIT_SIGNS' => [],
            ];
        }
        while ($history  = $limitationHistory->fetch()) {
            array_push($limits['UF_LIMIT_AUTHORS'], ...(array)$history['UF_LIMIT_AUTHORS']);
            array_push($limits['UF_LIMIT_BOOKS'], ...(array)$history['UF_LIMIT_BOOKS']);
            array_push($limits['UF_LIMIT_GENRES'], ...(array)$history['UF_LIMIT_GENRES']);
            array_push($limits['UF_LIMIT_SIGNS'], ...(array)$history['UF_LIMIT_SIGNS']);
        }

        return (array)$limits;
    }

    # функционал добавления ограничений

}
