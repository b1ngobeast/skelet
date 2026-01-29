<?php

namespace Webizi;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\UserTable;
use Bitrix\Main\Type\DateTime;
use \Bitrix\Main\Loader;
use Webizi\Integrations\ElectronicSchoolAuth;
use Webizi\Models\TeachHistoryTable;
use Webizi\Users\User;

Loader::includeModule('highloadblock');

use \Bitrix\Highloadblock\HighloadBlockTable as HL;

class AuthRequest
{
    private $codeHLBlock = 'UsersCode';
    private $docsHLBlock = 'UserDocs';
    private $idErrorHLBlock = 'EsiaErrors';
    //    private $userOld18 = 2;
//    private $userYoung18 = 1;


    public function __construct($data)
    {
        $this->getdata = json_decode($data, true);
    }

    public function checkInfo()
    {
        try {
            $data = $this->getdata;

            if ($userId = $this->findUser($data['_ESIA']['CONTACT_INFO'], $data['_ESIA']['ESIA_ID'])) {
                $this->updateUser($userId, $data['_ESIA']['CONTACT_INFO'], $data['_ESIA']['PERSON_INFO'], $data['_ESIA']['ESIA_ID']);
            } else {
                $userId = $this->createUser($data['_ESIA']['PERSON_INFO'], $data['_ESIA']['ESIA_ID']);
            }

            if ($userId) {
                $this->refreshDocs($userId, $data['_ESIA']['DOC_INFO']);
            }

            $code = $this->generateCode($userId);

            if (isset($userId) && isset($code)) {
                $result = [
                    'status' => true,
                    'data' => [
                        'code' => $code
                    ],
                    'error' => false
                ];
            } else {
                $result = [
                    'status' => false,
                    'data' => [],
                    'error' => 'Ошибка авторизации'
                ];
            }

            return json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $th) {

            $data = [
                'UF_DATE' => new DateTime(),
                'UF_LOG' => $th->getMessage(),
            ];

            $rsData = HL::compileEntity($this->idErrorHLBlock)->getDataClass()::add($data);
            echo '<h3>' . $th->getMessage() . '</h3>';
        }
    }

    private function findUser(array $contactInfo, $esiaId): ?int
    {
        #ЭЛ ШКОЛА
        $auth = new ElectronicSchoolAuth();
        $resElSchool = $auth->getUserByEsiaId($esiaId);
        global $emailAndPhone;
        $emailAndPhone = $this->getEmailAndPhone($contactInfo);
        $findResult = UserTable::getList([
            'select' => ['ID'],
            'filter' => [
                [
                    'LOGIC' => 'OR',
                    '=EMAIL' => $emailAndPhone['EMAIL'],
                    '=LOGIN' => $emailAndPhone['EMAIL'],
                    '=PERSONAL_PHONE' => $emailAndPhone['PERSONAL_PHONE'],
                    '=UF_EL_SCHOOL_ID' => $resElSchool['personId']
                ]
            ]
        ])->fetch();

        return $findResult['ID'] ? (int)$findResult['ID'] : null;
    }

    function getEmailAndPhone(array $contactInfo): array
    {
        $finded = [];
        for ($i = 0, $l = count($contactInfo); $i < $l; $i++) {
            if ($contactInfo[$i]['type'] === 'EML' && !array_key_exists('EMAIL', (array)$finded)) {
                $finded['EMAIL'] = strtolower($contactInfo[$i]['value']);
            } elseif ($contactInfo[$i]['type'] === 'MBT' && !array_key_exists('PERSONAL_PHONE', (array)$finded)) {
                $finded['PERSONAL_PHONE'] = $contactInfo[$i]['value'];
            }
        }
        if (count($finded) !== 2) {
            throw new \Exception(
                'Для входа на портал Ваша учетная запись в ЕСИА должна иметь подтвержденную электронную почту и номер телефона'
            );
        }
        return $finded;
    }

    private function updateUser(int $userId, array $contactInfo, array $personInfo, int $esiaId): void
    {
        $converted = $this->convertUserData($personInfo, $esiaId);
        global $emailAndPhone;
        #ЭЛ ШКОЛА
        $auth = new ElectronicSchoolAuth();
        $resElSchool = [];
        $resElSchool = $auth->getUserByEsiaId($esiaId);
        $moreData['UF_EL_SCHOOL_ID'] = $resElSchool['personId'];
        if ($resElSchool['ROLE'] == 'edu') {
            $moreData['UF_BUILDING'] = $resElSchool['CLASS']['UF_BUILDING'];
            $moreData['UF_CLASS'] = $resElSchool['CLASS']['UF_CLASS'];
        }
        ###
        $user = new \CUser;
        $user->Update($userId, array_merge($converted, $emailAndPhone, $moreData)); // $moredata
    }

    private function createUser($personInfo, $esiaId): ?int
    {
        $converted = $this->convertUserData($personInfo, $esiaId);
        global $emailAndPhone;
        $user = new User(1);
        #ЭЛ ШКОЛА
        $auth = new ElectronicSchoolAuth();
        $resElSchool = [];
        $resElSchool = $auth->getUserByEsiaId($esiaId);

        $moreData = [
            'ACTIVE' => 'Y',
            "GROUP_ID" => array($user->getRolesStringId()[$resElSchool['ROLE']]),
            'PASSWORD' => \Bitrix\Main\Security\Random::getString(21, true),
            'UF_EL_SCHOOL_ID' => $resElSchool['personId'],
            //'UF_USER_CATEGORY' => $this->checkAgeGroup($personInfo['birthDate'])
        ];
        if ($resElSchool['ROLE'] == 'edu') {
            $moreData['UF_BUILDING'] = $resElSchool['CLASS']['UF_BUILDING'];
            $moreData['UF_CLASS'] = $resElSchool['CLASS']['UF_CLASS'];
        }
        ###
        $moreData['CONFIRM_PASSWORD'] = $moreData['PASSWORD'];
        $userData = array_merge($converted, $moreData, $emailAndPhone);
        $userData['LOGIN'] = $userData['EMAIL'];

        $userId = (new \CUser())->add($userData);
        return $userId ? (int)$userId : null;
    }

    private function convertUserData($personInfo, $esiaId): array
    {
        $target = [
            'NAME' => 'firstName',
            'LAST_NAME' => 'lastName',
            'SECOND_NAME' => 'middleName',
            'PERSONAL_BIRTHDAY' => 'birthDate',
            'PERSONAL_GENDER' => 'gender'
        ];

        foreach ($target as $key => &$value) {
            if (array_key_exists($value, (array)$personInfo)) {
                $value = $personInfo[$value];
            } else {
                unset($target[$key]);
            }
        }

        $target['UF_USER_ESIA_ID'] = $esiaId;
        $target['UF_SNILS'] = $personInfo['snils'];

        /* TODO: ЗАПИСАТЬ ВСЕ ДАННЫЕ ЮЗВЕРЯ */

        return $target;
    }

    private function generateCode(int $userId)
    {

        $data = [
            'UF_USER' => $userId,
            'UF_DATE' => new DateTime(),
            'UF_CODE' => \Bitrix\Main\Security\Random::getString(48, true)
        ];

        $rsData = HL::compileEntity($this->codeHLBlock)->getDataClass()::add($data);
        return $data['UF_CODE'];
    }

    public function checkCode(string $code)
    {
        $arUser = [];
        $rsData = HL::compileEntity($this->codeHLBlock)->getDataClass()::getList([
            'select' => ['*'],
            'filter' => [
                'UF_CODE' => $code
            ]
        ]);
        while ($arData = $rsData->Fetch()) {
            $arUser = $arData;
        }
        if (count($arUser) > 0) {
            (new \CUser())->authorize($arUser['UF_USER']);
            $rsData = HL::compileEntity($this->codeHLBlock)->getDataClass()::delete($arUser['ID']);
            LocalRedirect('/', true);
        } else {
            return false;
        }
    }

    //    public function checkAgeGroup(string $birthDate = '') : int
//    {
//        if(isset($birthDate) && !empty($birthDate)){
//            $birthTime = strtotime($birthDate.' +18 years');
//            if(strtotime('now') >  $birthTime){
//                return $this->userOld18;
//            }else{
//                return $this->userYoung18;
//            }
//        }else{
//            return $this->userOld18;
//        }
//    }

    public function refreshDocs(int $userId, $docInfo): void
    {

        $target = [
            //            'id' => 'UF_ID_DOC',
//            'type' => 'UF_TYPE',
//            'vrfStu' => 'UF_VRFSTU',
//            'series' => 'UF_SERIES',
//            'number' => 'UF_NUMBER',
//            'issueDate' => 'UF_ISSUEDATE',
//            'issueId' => 'UF_ISSUEID',
//            'issuedBy' => 'UF_ISSUEDBY',
//            'eTag' => 'UF_ETAG',
//            'stateFacts' => 'UF_STATEFACTS',
//            'actNo' => 'UF_ACTNO',
//            'actDate' => 'UF_ACTDATE',
//            'verifiedOn' => 'UF_VERIFIEDON',
//            'updatedOn' => 'UF_UPDATEDON',
//            'guid' => 'UF_GUID',
//            'status' => 'UF_STATUS',
//            'actRecordFound' => 'UF_ACTRECORDFOUND',
//            'needToSetDefaultCert' => 'UF_NEEDTOSETDEFAULTCERT',
//            'updateCerts' => 'UF_UPDATECERTS',
//            'fake' => 'UF_FAKE'
        ];

        $rsData = HL::compileEntity($this->docsHLBlock)->getDataClass()::getList([
            'select' => ['ID', 'UF_USER_ID'],
            'filter' => [
                'UF_USER_ID' => $userId
            ]
        ]);
        while ($arData = $rsData->Fetch()) {
            if ($arData['UF_USER_ID'] == $userId) {
                $result = HL::compileEntity($this->docsHLBlock)->getDataClass()::Delete($arData['ID']);
                if (!$result->isSuccess()) {
                    AddMessage2Log('Авторизация госУслуг, удаление: ' . $result->getErrorMessages());
                    $data = [
                        'UF_DATE' => new DateTime(),
                        'UF_LOG' => $result->getErrorMessages()[0],
                    ];
                    HL::compileEntity($this->idErrorHLBlock)->getDataClass()::add($data);
                }
            }
        }

        foreach ($docInfo as $doc) {
            $docArray = array();
            if (isset($doc) && is_array($doc) && !empty($doc) && isset($doc['id'])) {
                foreach ($doc as $keyDateDoc => $valueDateDoc) {
                    $docArray[$target[$keyDateDoc]] = $valueDateDoc;
                }
                $docArray['UF_USER_ID'] = $userId;
            }
            $result = HL::compileEntity($this->docsHLBlock)->getDataClass()::add($docArray);
            if (!$result->isSuccess()) {
                AddMessage2Log('Авторизация госУслуг, добавление: ' . $result->getErrorMessages());

                $data = [
                    'UF_DATE' => new DateTime(),
                    'UF_LOG' => $result->getErrorMessages()[0],
                ];
                HL::compileEntity($this->idErrorHLBlock)->getDataClass()::add($data);
            }
        }
    }
}