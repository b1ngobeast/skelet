<?php

namespace Webizi;

use PhpOffice\PhpSpreadsheet\Style\Color;
use Webizi\Models\MonitoringTable;
use Webizi\Models\VisitingsTable;
use Webizi\Report\TransferActReport;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php');

use Bitrix\Main\AccessDeniedException;
use Bitrix\Main\Loader;
use CUtil;
use Bitrix\Main\UserTable;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard\DateTime as SprDateTime;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Webizi\Books\Book;
use Webizi\Helpers\EnumHelper;
use Webizi\Helpers\PaginationHelper;
use Webizi\Models\BooksTable;
use Webizi\Models\CatalogBookTable;
use Webizi\Models\DataForReportsTable;
use Webizi\Models\EventsTable;
use Webizi\Models\MunicipalitiesTable;
use Webizi\Models\NewArrivalsTable;
use Webizi\Models\OrganizationsTable;
use Webizi\Models\ReadCardsTable;
use Webizi\Models\ReportsTable;
use Webizi\Models\TypeEventsTable;
use Webizi\Users\User;
use Webizi\Models\TeachHistoryTable;
use Bitrix\Main\Type\DateTime;
use PhpOffice\PhpWord\TemplateProcessor;
use Webizi\Models\BuildingsTable;

Loader::includeModule('highloadblock');

use \Bitrix\Highloadblock\HighloadBlockTable as HL;

class Report
{
    protected array $params = [];
    private User $user;
    # типы отчетов
    public array $typesList = [];

    # типы среза
    public array $srezList = [];

    # тип среза
    public string $srez = "";
    # тип отчета
    public string $type = "";

    public array $filter = [];
    # связка роль - доступный срез
    private array $srezRole = [
        'edu' => [],
        'class' => ['org'],
        'library' => ['org'],
        'admin' => ['org'],
        'director' => ['org'],
        'mun' => ['mun', 'org'],
        'region' => ['mun', 'org', 'region'],
    ];

    protected array $filteredFields = [
        'UF_SREZ' => 'UF_SREZ',
        'UF_TYPE' => 'UF_TYPE',
        'UF_NAME' => 'UF_NAME',
    ];
    protected array $sortFields = [
        'UF_CREATE' => 'UF_CREATE',
        'UF_NAME' => 'UF_NAME',
        'UF_PERIOD_START' => 'UF_PERIOD_START',
        'UF_PERIOD_END' => 'UF_PERIOD_END',
        'UF_YEAR' => 'UF_YEAR',
    ];

    private array $withExtraParams = [
        'monitoring_staff',
        'report_indicators_year',
        'report_lib_work',
        'learn_books',
        'form_learn_books',
        'info_learn_books_region',
        'form_monitoring_staff_lib',
    ];

    //    protected array $months = [
    //        '01'=>''
    //    ];

    public array $enums = [];

    public function __construct(string $type = '', string $srez = '', array $params = [])
    {
        $enums = EnumHelper::getListXmlIdIdXml(ReportsTable::getUfId(), ['UF_TYPE', 'UF_SREZ'], true);
        $enumsData = EnumHelper::getListXmlIdIdXml(DataForReportsTable::getUfId(), ['UF_TYPE'], true);
        $this->user = new User();
        $this->enums = $enums;
        if (!empty($type) && !empty($srez) && !empty($params)) {
            $this->srezList = $enums['xml_list']['UF_SREZ'];
            $this->typesList = $enums['xml_list']['UF_TYPE'];
            if (!in_array($srez, haystack: $this->srezRole[$this->user->getGroups()['group']])) {
                throw new AccessDeniedException('Нет доступа к формированию отчета в таком разрезе');
            }
            if (!in_array($type, $this->typesList) || !in_array($srez, $this->srezList)) {
                throw new Exception('Ошибка, проверьте выбранные данные', 400);
            }
            $this->type = $type;
            $this->srez = $srez;
            $this->params = $params;
            $this->createFilterForReport();
        }
        $filter = array_merge($this->filter, ['UF_TYPE' => $enumsData['xml_id']['UF_TYPE'][$this->type], 'UF_YEAR' => $this->params['YEAR']]);
        $this->extraParams = DataForReportsTable::getList([
            'select' => [
                'UF_JSON',
                'BUILD_ID' => 'UF_BUILDING',
                'MUN' => 'BUILD.ORG.UF_MUN_ID'
            ],
            'filter' => $filter,
        ])->fetchAll();
    }

    private function createFilterForReport()
    {
        $orgFilter = $this->params['BUILD'];
        $this->user->checkUserGroups(['class', 'library', 'admin', 'director', 'mun', 'region']);
        if (in_array($this->user->getGroup(), ['class', 'library', 'admin', 'director'])) {
            $org = $this->user->getOrganizationAndClass();
            if (in_array($this->user->getGroup(), ['class', 'library', 'admin', 'director'])) {
                if (!in_array($orgFilter, $org['builds'])) {
                    $orgFilter = $org['builds'][0];
                }
//            } elseif (in_array($this->user->getGroup(), ['admin', 'director'])) {
//                if (!in_array($orgFilter, $org['builds'])) {
//                    $orgFilter = $org['builds'][0];
//                }
            }
        }

        $group = $this->user->getGroup();
        if ($group == 'mun') {
            $mun = $this->user->getMunicipality()['mun']['ID'];
        }
//        echo json_encode($this->params['BUILD']);exit();
        $this->filter = match ($this->srez) {
            'org' => ['BUILD_ID' => $orgFilter],
            'mun' => ['MUN' => !empty($this->params['MUN']) && $group == 'region' ? $this->params['MUN'] : $mun],
            'region' => [],
        };
    }

    private function buildFilter(array $data)
    {
        $filter = [];
        foreach ($data as $field => $value) {
            if (in_array($field, array_keys($this->filteredFields))) {
                if ($field == 'UF_SREZ' || $field == 'UF_TYPE') {
                    $filter[$this->filteredFields[$field]] = $this->enums['xml_id'][$field][$value];
                } else {
                    $filter[$this->filteredFields[$field]] = htmlspecialchars(trim($value));
                }
            }
        }
        $filter['UF_USER_ID'] = $this->user->getUserID();
        return $filter;
    }

    private function buildPagination(array $data)
    {
        $nav = PaginationHelper::getNav([
            'pageSize' => (int)$data['pageSize'],
            "page" => (int)$data['page'],
        ]);
        return $nav;
    }

    private function buildOrder(array $data)
    {
        $order = (in_array($data['order'], array_keys($this->sortFields))) ? $this->sortFields[$data['order']] : 'ID';
        $sort = $data['sort'] ?? 'ASC';

        return [$order => $sort];
    }

    public function getList($data)
    {
        $order = $this->buildOrder($data);
        $nav = $this->buildPagination($data);
        $filter = $this->buildFilter($data);
        $reports = ReportsTable::getList([
            'select' => ['*'],
            'filter' => $filter,
            'order' => $order,
            'offset' => $nav->getOffset(),
            'limit' => $nav->getLimit(),
            'count_total' => true,
            'data_doubling' => false,
        ]);
        $nav->setRecordCount($reports->getCount());
        $result = [];
        foreach ($reports->fetchCollection() as $report) {
            $result[] = [
                'UF_PERIOD_START' => $report['UF_PERIOD_START']?->toString(),
                'UF_PERIOD_END' => $report['UF_PERIOD_END']?->toString(),
                'UF_CREATE' => $report['UF_CREATE']?->toString(),
                'UF_NAME' => $report['UF_NAME'],
                'UF_YEAR' => $report['UF_YEAR'],
                'UF_SREZ' => $this->enums['value']['UF_SREZ'][$report['UF_SREZ']],
                'UF_TYPE' => $this->enums['value']['UF_TYPE'][$report['UF_TYPE']],
                'UF_FILE' => \CFile::getPath($report['UF_FILE']),
            ];
        }
        return [
            'data' => $result,
            'pagination' => PaginationHelper::setResultNav($nav)
        ];
    }

    public function getTypeList($data)
    {
        $isParams = false;
        if ($data['EXTRA_PARAMS']=='true') $isParams = true;
        $result = EnumHelper::getListIdXmlValue(ReportsTable::getUfId(), ['UF_TYPE'], true)['UF_TYPE'];
        $group = $this->user->getGroup();
        $srezList = $this->srezRole[$group];
        if (empty($srezList)) {
            return ['data' => ''];
        }
        $res = [];
        //        echo json_encode($srezList);exit();
        foreach ($result as $el) {
            if ($isParams && !in_array($el['XML_ID'], $this->withExtraParams)) {
                continue;
            }
            if ($el['XML_ID'] == 'transfer_act') continue;

            if (($el['XML_ID'] == 'form_monitoring_staff_lib' || $el['XML_ID']=='fpu_order_mun' || $el['XML_ID']=='fpu_svod') && in_array($group, ['class', 'library', 'admin', 'director'])
                && !($el['XML_ID'] == 'form_monitoring_staff_lib' && $group=='admin' && $isParams)
            ) {
                continue;
            }

            if ($el['XML_ID'] == 'fpu_svod' && $group=='mun') continue;

            $res[] = [
                'VALUE' => $el['VALUE'],
                'XML_ID' => $el['XML_ID'],
                'WITH_EXTRA_PARAMS' => in_array($el['XML_ID'], $this->withExtraParams)
            ];
        }
        return ['data' => $res];
    }

    public function getSrezList($report = '')
    {
        $group = $this->user->getGroup();
        foreach ($this->srezRole[$group] as $srez) {
            $idEnum = $this->enums['xml_id']['UF_SREZ'][$srez];
            if ($report == 'form_monitoring_staff_lib' && in_array($srez, ['org', 'region'])) {
                continue;
            }

            if ($report == 'fpu_order_mun' && $srez!='mun' || $report=='fpu_svod' && $srez!='region') {
                continue;
            }

            if ($report == 'diary' && in_array($srez, ['mun', 'region'])) {
                continue;
            }
            $result[] = [
                'VALUE' => $this->enums['value']['UF_SREZ'][$idEnum],
                'XML_ID' => $srez,
            ];
        }
        return ['data' => $result];
    }


    protected function createExcel($spreadsheet): string
    {
        global $USER;
        # сбор файла excel
        $writer = new Xlsx($spreadsheet);
        $path = '/upload/reports/' . $USER->GetID() . '-' . date('d-m-Y-H-i-s') . '.xlsx';
        $writer->save($_SERVER["DOCUMENT_ROOT"] . $path);
        return $path;
    }

    private function createWord(TemplateProcessor $template): string
    {
        global $USER;
        # сбор файла word
        $fileName = '/upload/reports/' . $USER->GetID() . "-" . date("d-m-Y-H-i-s") . '.docx';
        $filePath = $_SERVER["DOCUMENT_ROOT"] . $fileName;
        $template->saveAs($filePath);
        //        $phpWord = \PhpOffice\PhpWord\IOFactory::load();
        //        $phpWord->setDefaultFontName('Times New Roman');
        //        $phpWord->addFontStyle('Font', array('size' => 14));

        return $filePath;
    }

    private function removeFile($filePath)
    {
        unlink($filePath);
    }

    private function saveReport($file)
    {
        global $USER;
    }

    public function createReport()
    {
        # дневник читателя
        switch ($this->type) {
            case "diary":
                # собираем Excel таблицу
                $data = [];
                # получаем наименование организации
                if (!empty($this->user->getOrganization())) {
                    $org = $this->user->getOrganization();
                } elseif (!in_array($this->user->getGroup(), ['mun', 'region'])) {
                    $org = BuildingsTable::getList([
                        'select' => ['UF_NAME'],
                        'filter' => ['ID' => $this->filter['BUILD_ID']]
                    ])->fetch();
                }
                $data['ORG_NAME'] = $org['UF_NAME'];
                # определяем период по учебному году
//                $studyYear = explode("-", (string)$this->params['PERIOD']);
                $data['YEAR_START'] = (int)$this->params['YEAR'];
                $data['YEAR_END'] = (int)$this->params['YEAR'] + 1;
                $data['PERIOD_START'] = date('d.m.Y H:i:s', strtotime('01.09.' . $data['YEAR_START'] . ' 00:00:00'));
//                $data['PERIOD_START_OLD'] = date('d.m.Y H:i:s',strtotime('01.09.' . $data['YEAR_START']-1 . ' 00:00:00'));
                $data['PERIOD_END'] = date('d.m.Y H:i:s', strtotime('31.08.' . $data['YEAR_END'] . ' 23:59:59'));
//                $data['PERIOD_END_OLD'] = date('d.m.Y H:i:s',strtotime('31.08.' . $data['YEAR_END']-1 . ' 23:59:59'));
//                                echo json_encode($data);
//                                exit();
                # получение кол-ва выдачи книг по дням месяца в учебном году
                $data['READERS'] = $this->getReadersAndBookIssue($data['PERIOD_START'], $data['PERIOD_END'], false);
//                echo json_encode(44);exit();
//                $data['READERS_OLD'] = $this->getReadersAndBookIssue($data['PERIOD_START_OLD'], $data['PERIOD_END_OLD'],false);
                # cбор данных о посещаемости и новых читателях
                $data['VISITINGS'] = $this->getVisitingsAndReaders($data['YEAR_START']);

                $data['EVENTS'] = $this->getEventsForDiary($data['PERIOD_START'], $data['PERIOD_END']);
                # сбор дневника библиотеки
                $data['PATH'] = $this->generateDiary($data);
//                print_r($data);exit();
                break;
            case "lib_fond":
                $data['PATH'] = $this->getProvisionLibrary();
                break;
            case "report_indicators_year":
                $data['PATH'] = $this->getMainIndicators();
                break;
            case "report_lib_work":
                $data['PATH'] = $this->getReportLibWork();
                break;
            case "form_learn_books":
                $data['PATH'] = $this->getFormLearnBooks();
                break;
            case "info_learn_books_region":
                $data['PATH'] = $this->getInfoLearnBooks();
                break;
            case "form_monitoring_staff_lib":
                $data['YEAR_START'] = (int)$this->params['YEAR'];
                $data['YEAR_END'] = (int)$this->params['YEAR'] + 1;
                $data['PERIOD_START'] = date('d.m.Y H:i:s', strtotime('01.09.' . $data['YEAR_START'] . ' 00:00:00'));
                $data['PERIOD_END'] = date('d.m.Y H:i:s', strtotime('31.08.' . $data['YEAR_END'] . ' 23:59:59'));
                $data['PATH'] = $this->getFormMonitoringStaffLib($data);
                break;
            case "monitoring_staff":
                $data['PATH'] = $this->getMonitoringStaff();
                break;
            case "learn_books":
                $data['PATH'] = $this->getLearnBooks();
                break;
             case "form_monitoring_learn_books":
                 $data['PATH'] = $this->getMonitoringLearnBooks();
                 break;
            case "transfer_act":
                $transferActRep = new TransferActReport($this->params);
                $data['PATH'] = $transferActRep->generate();
                break;
            case "form_monitoring_book":
                $data['PATH'] = $this->generateMonitoringBooks();
                break;
            default:
                break;
        }
        $fileArray = \CFile::MakeFileArray($data['PATH']);
        $addData = [
            'UF_PERIOD_START' => $this->params['PERIOD_START'],
            'UF_PERIOD_END' => $this->params['PERIOD_END'],
            'UF_YEAR' => $this->params['YEAR'],
            'UF_NAME' => $this->params['NAME'],
            'UF_SREZ' => $this->srez,
            'UF_TYPE' => $this->type,
        ];
        $res = ReportsTable::add($addData);
        if (!$res->isSuccess()) {
            throw new Exception($res->getErrorMessages()[0]);
        }
        $res = HL::compileEntity('Reports')->getDataClass()::update($res->getId(), ['UF_FILE' => $fileArray]);

        return $data;
    }

    private function generateMonitoringBooks(): string
    {
        global $USER;
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/monitoring_template.xlsx');
        $worksheet = $spreadsheet->getSheet(0);

        $filter = [];
        if ($this->srez == 'org') {
            $filter['BUILD.ID'] = $this->filter['BUILD_ID'];
        } elseif ($this->srez == 'mun') {
            $filter['BUILD.ORG.UF_MUN_ID'] = $this->filter['MUN'];
        }

        $booksStatus = EnumHelper::getListXmlId(BooksTable::getUfId(), ['UF_STATUS_POSITION'], false);
        # получение каталога
        $catalogs = CatalogBookTable::getList([
            'select' => [
                'ID',
                'AUTHOR.UF_NAME',
                'UF_NAME',
                'BOOK.ID',
            ],
            'filter' => [
                'BOOK.UF_STATUS_POSITION' => [$booksStatus['in_library'], $booksStatus['issued'], $booksStatus['overdue_for_refund'],
                    $booksStatus['booked'], $booksStatus['long_issue']],
                $filter
            ]
        ])->fetchCollection();

        $resCatalog = [];
        foreach ($catalogs as $catalog) {
//            $resCatalog[]
        }

        $path = $_SERVER["DOCUMENT_ROOT"] . '/upload/reports/' . $USER->GetID() . '-' . date('d-m-Y-H-i-s') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        return $path;
    }

    private function generateDiary($data): string
    {
        global $USER;
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/diary_template.xls');
        $worksheet = $spreadsheet->getSheet(0);
        $worksheet->setCellValue('A19', $data['ORG_NAME']);
        $worksheet->setCellValue('A22', $data['YEAR_START'] . ' - ' . $data['YEAR_END'] . ' уч. год');
        # заполняем мероприятия
        if (!empty($data['EVENTS'])) {
            $worksheet = $spreadsheet->getSheet(13);
            $start = 8;
            foreach ($data['EVENTS'] as $event) {
                $worksheet->setCellValue('A' . $start, $event['NUMBER']);
                $worksheet->setCellValue('B' . $start, $event['UF_DATE_START']);
                $worksheet->setCellValue('C' . $start, $event['UF_NAME']);
                $worksheet->setCellValue('E' . $start, $event['TYPE']);
                $worksheet->setCellValue('G' . $start, $event['UF_QUANTITY_USERS_FACT']);
                $start++;
            }
        }
        $months = [
            '1' => 'Январь',
            '2' => 'Февраль',
            '3' => 'Март',
            '4' => 'Апреля',
            '5' => 'Май',
            '6' => 'Июнь',
            '7' => 'Июль',
            '8' => 'Август',
            '9' => 'Сентябрь',
            '10' => 'Октябрь',
            '11' => 'Ноябрь',
            '12' => 'Декабрь'
        ];
        # интерпретация ББК значений в ячейки отчета
        $bkkInter = [
            "EST" => "S",
            "TECH" => "T",
            "SOC" => "U",
            "TEACH" => "V",
            "LIT" => "W",
            "ART" => "X",
            "SPORT" => "Y",
            "XUD" => "Z",
            "TEXTBOOK" => "AA",
            "CHILD" => "AB",
        ];
        # заполняем остальные листы перебором
        $month = 9;
        for ($list = 1; $list < 12; $list++) {
            $worksheet = $spreadsheet->getSheet($list);
            if ($month >= 9) {
                $stringData = $months[$month] . ' ' . $data['YEAR_START'];
                $year = $data['YEAR_START'];
            } else {
                $stringData = $months[$month] . ' ' . $data['YEAR_END'];
                $year = $data['YEAR_END'];
            }
            $worksheet->setCellValue('A4', $stringData);
            $worksheet->setCellValue('Q4', $stringData);
            # вернуть как было, когда установят пакет composer
//            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $daysInMonth = 30;
            $startRow = 13;
            $worksheet->getStyle("A" . $startRow . ':A' . ($daysInMonth + $startRow - 1))
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFFFFF00');
            $worksheet->getStyle("Q" . $startRow . ':Q' . ($daysInMonth + $startRow - 1))
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFFFFF00');
            for ($day = 1; $day <= $daysInMonth; $day++) {
                if ($month < 10) {
                    $validMonth = "0" . $month;
                } else {
                    $validMonth = $month;
                }
                if ($day < 10) {
                    $validDay = "0" . $day;
                } else {
                    $validDay = $day;
                }
                $date = new \DateTime(date('Y-m-d H:i:s', strtotime("$year-$month-$day")));
                $isWeekend = ($date->format('N') >= 6);
                # заполнение данных
                $worksheet->setCellValue("A" . $startRow, $day);
                # заполняем посещаемость
                if ($isWeekend) {
                    $worksheet->getStyle("B" . $startRow . ':P' . $startRow)
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB('FFC6EFCE');
                    $worksheet->getStyle("R" . $startRow . ':AB' . $startRow)
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB('FFC6EFCE');
                }
                if (!empty($data['VISITINGS'][$month][$day]) && is_array($data['VISITINGS'][$month][$day]['DATA'])) {
                    if (count($data['VISITINGS'][$month][$day]['DATA']) > 14) {
                        $data['VISITINGS'][$month][$day]['DATA'] = array_slice($data['VISITINGS'][$month][$day]['DATA'], 0, 14);
                    }
                    $worksheet->fromArray($data['VISITINGS'][$month][$day]['DATA'], null, "B" . $startRow);
                }
                if (!empty($data['READERS'][$month][$day]) && is_array($data['READERS'][$month][$day])) {
                    foreach ($data['READERS'][$month][$day] as $bbk => $val) {
//                        if ($bbk!='ALL') {
//                        }
                        if (in_array($bbk, array_keys($bkkInter))) {
//                            echo json_encode($bbk);exit();
                            $worksheet->setCellValue($bkkInter[$bbk] . $startRow, (int)$val);
                        }
                    }
                }
                # заполнение книговыдачи
                $startRow++;
            }
            # заполнение перерегистрации
            $startRow += 3;
            if (!empty($data['VISITINGS'][$month]["newRegister"]) && is_array($data['VISITINGS'][$month]["newRegister"])) {
                if (count($data['VISITINGS'][$month]['newRegister']) > 13) {
                    $data['VISITINGS'][$month]['newRegister'] = array_slice($data['VISITINGS'][$month]['newRegister'], 0, 13);
                }
                $worksheet->fromArray($data['VISITINGS'][$month]['newRegister'], null, "C" . $startRow);
            }
            if ($month == 12) {
                $month = 1;
            } else {
                $month++;
            }
        }
//        foreach ()
//        $worksheet = $spreadsheet->getSheet(1);
//        $worksheet->
        $path = $_SERVER["DOCUMENT_ROOT"] . '/upload/reports/' . $USER->GetID() . '-' . date('d-m-Y-H-i-s') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        return $path;
    }

    private function getEventsForDiary($start, $end): array
    {
        $res = [];
        $events = EventsTable::getList([
            'select' => ['*', 'TYPE.UF_NAME'],
            'filter' => [
                'UF_BUILDING' => $this->filter['BUILD_ID'],
                '>=UF_DATE_START' => $start,
                '<=UF_DATE_START' => $end,
            ],
            'order' => ['UF_DATE_START' => 'ASC']
        ])->fetchCollection();
        if (empty($events)) return $res;
        $nums = 1;
        foreach ($events as $event) {
            $res[] = [
                'NUMBER' => $nums++,
                'UF_DATE_START' => (!empty($event->get('UF_DATE_START'))) ? date('d.m.Y', strtotime($event->get('UF_DATE_START')->toString())) : null,
                'UF_NAME' => $event->get('UF_NAME'),
                'TYPE' => $event->getType()?->get('UF_NAME'),
                'UF_QUANTITY_USERS_FACT' => $event->get('UF_QUANTITY_USERS_FACT'),
            ];
        }
        return $res;
    }

    private function getVisitingsAndReaders($year): array
    {
        $visitings = VisitingsTable::getList([
            'select' => ['*'],
            'filter' => [
                'UF_YEAR' => [$year, $year + 1],
                'UF_BUILD' => $this->filter['BUILD_ID']
            ]
        ])->fetchCollection();
        if (empty($visitings)) {
            return [];
        }
        $res = [];
        foreach ($visitings as $visiting) {
            $json = json_decode($visiting->get('UF_JSON'), true);
            $json = json_decode($json, true);
//            print_r($json);exit();
            if ((int)$visiting->get('UF_YEAR') == $year) {
                # берем только месяца 9-12
                if (in_array((int)$visiting->get('UF_MONTH'), range(9, 12, 1))) {
                    foreach ($json['startMonth'] as $days) {
                        $res[$visiting->get('UF_MONTH')][$days['ID']] = [
                            'IS_HOLIDAY' => (bool)$days['IS_HOLIDAY'],
                            'DATA' => (array)$days['value'],
                        ];
                    }
                    $res[$visiting->get('UF_MONTH')]['newRegister'] = $json['allMonth'];
                }
            } else {
                # берем только месяца 1-8
                if (in_array($visiting->get('UF_MONTH'), range(1, 8, 1))) {
                    foreach ($json['startMonth'] as $days) {
                        $res[$visiting->get('UF_MONTH')][$days['ID']] = [
                            'IS_HOLIDAY' => (bool)$days['IS_HOLIDAY'],
                            'DATA' => (array)$days['value'],
                        ];
                    }
                    $res[$visiting->get('UF_MONTH')]['newRegister'] = $json['allMonth'];
                }
            }
        }
//        echo json_encode($res);exit();
        return $res;
    }

    # получение читателей по дням/месяцам и получение книговыдаче в таких же срезах
    private function getReadersAndBookIssue($periodStart, $periodEnd, bool $isOld): array
    {
//        echo json_encode($this->filter);exit();
        if ($isOld) {
            $select = ['ID'];
        } else {
            $select = [
                'ID',
                'UF_DATE_TAKE',
                'UF_BOOK',
                'UF_USER_READ',
                'BOOK.ID',
                'USER.ID',
                'BOOK.BBK.UF_CODE',
//                'BOOK.BBK.UF_SHORT_CODE',
                'BOOK.CATALOG.UF_TEXTBOOK',
                'BOOK.CATALOG.CLASSES'
            ];
        }
        $readCards = ReadCardsTable::getList([
            'select' => $select,
            'filter' => [
                '>=UF_DATE_TAKE' => $periodStart,
                '<=UF_DATE_TAKE' => $periodEnd,
                'BOOK.CATALOG.UF_BUILDING' => $this->filter['BUILD_ID']
            ]
        ]);
//        echo json_encode(44);exit();
//        echo json_encode($res);
//        exit();
        $res = [];
        if (!$isOld) {
            foreach ($readCards->fetchCollection() as $readCard) {
                $month = (int)date('m', strtotime($readCard->getUfDateTake()->toString()));
                $day = (int)date('d', strtotime($readCard->getUfDateTake()->toString()));
                $res['COUNT']++;
                $res[$month]['COUNT']++;
                $res[$month][$day]['ALL']++;
                if (str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "2") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "72")) {
                    $res[$month][$day]['EST']++;
                } elseif (str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "3") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "4") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "5")) {
                    $res[$month][$day]['TECH']++;
                } elseif (str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "6") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "7") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "86") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "87") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "88") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "9")) {
                    $res[$month][$day]['SOC']++;
                } elseif (str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "74") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "88.8")) {
                    $res[$month][$day]['TEACH']++;
                } elseif (str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "83") ||
                    str_starts_with((string)$readCard->getBook()?->getBbk()?->get('UF_CODE'), "81")) {
                    $res[$month][$day]['LIT']++;
                } elseif (str_starts_with($readCard->getBook()?->getBbk()?->get('UF_CODE'), "85")) {
                    $res[$month][$day]['ART']++;
                } elseif (str_starts_with($readCard->getBook()?->getBbk()?->get('UF_CODE'), "75")) {
                    $res[$month][$day]['SPORT']++;
                } elseif (str_starts_with($readCard->getBook()?->getBbk()?->get('UF_CODE'), "84")) {
                    $res[$month][$day]['XUD']++;
                } elseif ($readCard->getBook()?->getCatalog()?->get('UF_TEXTBOOK')) {
                    $res[$month][$day]['TEXTBOOK']++;
                } elseif (in_array($readCard->getBook()?->getCatalog()?->getClasses()?->getUfClassList(), [1, 2])) {
                    $res[$month][$day]['CHILD']++;
                }
            }
        } else {
            foreach ($readCards->fetchCollection() as $readCard) {
                $month = (int)date('m', strtotime($readCard->getUfDateTake()->toString()));
                $res['COUNT']++;
                $res[$month]['COUNT']++;
            }
        }
//        echo json_encode($res);exit();
        return $res;
    }

    # Пример вызова
    #$report = new Report('lib_fond', 'region', ['YEAR' => 2025]);
    #$report->createReport();
    private function getProvisionLibrary()
    {
        $statuses = EnumHelper::getListXmlId(BooksTable::getUfId(), ['UF_STATUS_POSITION'], true)['UF_STATUS_POSITION'];
        unset($statuses['transfer']);
        unset($statuses['archive']);
        $statuses = array_values($statuses);
        $books = BooksTable::getList([
            'select' => [
                '*',
                'UF_BBK_NAME' => 'BBK.UF_SHORT_CODE',
                'TYPE_INSTANCE' => 'CATALOG.TYPE_INSTANCE.UF_TYPE',
                'BUILD_ID' => 'CATALOG.BUILD.ID',
                'MUN' => 'CATALOG.BUILD.ORG.UF_MUN_ID',
            ],
            'filter' => $this->filter,
        ])->fetchAll();

        $filterMap = ReportMapping::getProvisionLibraryFilters($this->params['YEAR']);
        $firstFilter = $filterMap['FIRST'];
        $secondFilter = $filterMap['SECOND'];
        $result = [];
        foreach ($firstFilter as $letter => $firFil) {
            foreach ($secondFilter as $number => $secFil) {
                $result[$letter . $number] = count(array_filter($books, function ($book) use ($firFil, $secFil) {
                    $first = match ($firFil['TYPE']) {
                        'date' => !empty($book[$firFil['FIELD']]) && $book[$firFil['FIELD']]->format('YY') == $firFil['VALUE'],
                        'array' => in_array($book[$firFil['FIELD']], $firFil['VALUE']),
                        default => $book[$firFil['FIELD']] == $firFil['VALUE']
                    };
                    if (!empty($secFil)) {
                        $second = match ($secFil['TYPE']) {
                            'substr' => is_numeric(stripos($book[$firFil['FIELD']], $secFil['VALUE'])) && stripos($book[$firFil['FIELD']], $secFil['VALUE']) == 0,
                            default => $book[$secFil['FIELD']] == $secFil['VALUE'],
                        };
                    } else {
                        $second = true;
                    }
                    return $first && $second;
                }));
            }
        }
        $templateSpreadsheet = IOFactory::load($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/lib_fond_template.xlsx');
        $spreadsheet = clone $templateSpreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        foreach ($result as $key => $value) {
            $worksheet->setCellValue($key, $value);
        }
        $path = $this->createExcel($spreadsheet);
        return $path;
    }

    private function getMainIndicators()
    {
        $manualParams = [
            'YEAR',
        ];
        $events = $this->getEvents();
        $newArrivals = NewArrivalsTable::getList([
            'select' => [
                '*',
                'BUILD_ID' => 'BUILD.ID',
                'MUN' => 'BUILD.ORG.UF_MUN_ID',
                'UF_DATE_F' => 'UF_DATE_ADD',
            ],
            'filter' => $this->filter,
        ])->fetchAll();
        $result['VISITS'] = array_sum(array_column($events, 'UF_QUANTITY_USERS'));
        $result['NEW_BOOKS'] = array_sum(array_column($newArrivals, 'UF_QUANTITY'));
        foreach ($manualParams as $ind) {
            $result[$ind] = $this->params[$ind];
        }
        $countPercent = 0;
//        echo json_encode($this->extraParams);exit();
        foreach ($this->extraParams as $item) {
            $json = json_decode($item['UF_JSON'], true);
            foreach ($json['fields'] as $key => $value) {
                if ($key == 'STAFF_PERCENT') {
                    $countPercent++;
                }
                $result[$key] += $value;
            }
        }
        if ($countPercent != 0) {
            $result['STAFF_PERCENT'] = $result['STAFF_PERCENT'] / $countPercent;
        }
        $templateProcessor = new TemplateProcessor($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/report_indicators_year_template.docx');
        $result = $this->checkValueEmpty($result, $templateProcessor);
        $templateProcessor->setValues($result);
        $path = $this->createWord($templateProcessor);
        #todo: придумать как проставлять пустоту в setValues
        return $path;
    }

    private function checkValueEmpty($data, TemplateProcessor $templateProcessor)
    {
        $variables = $templateProcessor->getVariables();
        foreach ($variables as $key) {
            if (empty($data[$key])) {
                $data[$key] = "";
            }
        }
        return $data;
    }

    private function getReportLibWork()
    {
        #todo: добавить параметры для ручного ввода
        $manualParams = [];
        $groups = $this->user->getRolesStringId();
        $filterMap = ReportMapping::getLibWorkFilters();
        $filterForUsers = $filterMap['USERS'];
        $filterStudents = $filterMap['STUDENTS'];

        $users = $this->getAllStudents();
        foreach ($filterForUsers as $key => $fil) {
            $filteredUsers[$key] = array_filter($users, function ($user) use ($fil) {
                $flag = match ($fil['TYPE']) {
                    'array' => in_array($user[$fil['FIELD']], $fil['VALUE']),
                    default => $user[$fil['FIELD']] == $fil['VALUE'],
                };
                return $flag;
            });
            $result[$key] = count($filteredUsers[$key]);
        }
        foreach ($filterStudents as $key => $fil) {
            $result[$key] = count(array_filter($filteredUsers['STUDENTS'], function ($user) use ($fil) {
                $class = intval(preg_replace('/[^0-9]/', '', $user['UF_CLASS']));
                if ($class >= $fil['START'] && $class <= $fil['END']) {
                    return true;
                }
                return false;
            }));
        }
        $statuses = EnumHelper::getListXmlId(BooksTable::getUfId(), ['UF_STATUS_POSITION'], true);
        $statusesCatalog = EnumHelper::getListXmlId(CatalogBookTable::getUfId(), ['UF_FORM_PUBLICATION'], true);
        unset($statuses['UF_STATUS_POSITION']['transfer']);
        unset($statuses['UF_STATUS_POSITION']['archive']);
        $allBooks = BooksTable::getList([
            'select' => [
                'ID',
                'UF_FORM_PUBLICATION' => 'CATALOG.UF_FORM_PUBLICATION'
            ],
            'filter' => [
                'UF_STATUS_POSITION' => $statuses['UF_STATUS_POSITION']
            ]
        ])->fetchAll();
        $result['ALL_BOOKS'] = count($allBooks);
        $result['NON_TRAD_MEDIA'] = count(array_filter($allBooks, function ($book) use ($statusesCatalog) {
            return $book['UF_FORM_PUBLICATION'] == $statusesCatalog['UF_FORM_PUBLICATION']['electronic'];
        }));

        $events = $this->getEvents();
        $result['EVENTS'] = count($events);
        $types = TypeEventsTable::getList([
            'select' => ['*']
        ])->fetchAll();
        foreach ($types as $type) {
            $key = mb_strtoupper(CUtil::translit($type['UF_NAME'], 'ru'));
            $result[$key] = count(array_filter($events, function ($event) use ($type) {
                return $event['UF_TYPE'] == $type['ID'];
            }));
        }
        foreach ($this->extraParams as $item) {
            $json = json_decode($item['UF_JSON'], true);
            foreach ($json['fields'] as $key => $value) {
                $result[$key] += $value;
            }
        }
        switch ($this->srez) {
            case 'org':
                $org = BuildingsTable::getList([
                    'select' => [
                        'UF_SHORT_NAME',
                        'ORG_NAME' => 'ORG.UF_NAME',
                    ],
                    'filter' => ['ID' => $this->filter['BUILD_ID']],
                ])->fetch();
                $result['TABLE_HEAD'] = 'Отчёт о работе библиотеки ' . $org['ORG_NAME'] . (!empty($org['UF_SHORT_NAME']) ? '. ' . $org['UF_SHORT_NAME'] : '') . ' за ' . $this->params['YEAR'] . '-' . $this->params['YEAR'] + 1 . ' учебный год';
                break;
            case 'mun':
                $mun = MunicipalitiesTable::getList([
                    'select' => [
                        'UF_NAME',
                    ],
                    'filter' => [
                        'ID' => $this->filter['MUN'],
                    ]
                ])->fetch();
                $result['TABLE_HEAD'] = 'Отчёт о работе библиотек в муниципальном округе ' . $mun['UF_NAME'] . ' за ' . $this->params['YEAR'] . '-' . $this->params['YEAR'] + 1 . ' учебный год';
                break;
            case 'region':
                $result['TABLE_HEAD'] = 'Отчёт о работе библиотек в регионе' . ' за ' . $this->params['YEAR'] . '-' . $this->params['YEAR'] + 1 . ' учебный год';
                break;
        }

        $this->filter['>=UF_DATE_F'] = new DateTime('01.09.' . $this->params['YEAR']);
        $this->filter['<=UF_DATE_F'] = new DateTime('31.08.' . $this->params['YEAR'] + 1);

        $result['ARCHIEVED'] = count(BooksTable::getList([
            'select' => [
                'ID',
                'BUILD_ID' => 'CATALOG.UF_BUILDING',
                'MUN' => 'CATALOG.BUILD.ORG.UF_MUN_ID',
                'UF_DATE_F' => 'UF_DATE_ARCHIVE'
            ],
            'filter' => [
                $this->filter,
            ]
        ])->fetchAll());
        $result['ISSUANSE'] = count(ReadCardsTable::getList([
            'select' => [
                'UF_DATE_F' => 'UF_DATE_TAKE',
                'BUILD_ID' => 'BOOK.CATALOG.UF_BUILDING',
                'MUN' => 'BOOK.CATALOG.BUILD.ORG.UF_MUN_ID',
            ],
            'filter' => [
                $this->filter,
            ]
        ])->fetchAll());
        foreach ($manualParams as $par) {
            $result[$par] = $this->params[$par];
        }
        $templateProcessor = new TemplateProcessor($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/report_lib_work_template.docx');
        $result = $this->checkValueEmpty($result, $templateProcessor);
        $templateProcessor->setValues($result);
        $path = $this->createWord($templateProcessor);
        return $path;
    }

    private function getFormLearnBooks()
    {
        $manualParams = [
            'COMMENT',
            'REASON_LACK',
        ];
        switch ($this->srez) {
            case 'org':
                $org = BuildingsTable::getList([
                    'select' => [
                        'UF_SHORT_NAME',
                        'ORG_NAME' => 'ORG.UF_NAME',
                    ],
                    'filter' => ['ID' => $this->filter['BUILD_ID']],
                ])->fetch();
                $result['SREZ'] = $org['ORG_NAME'] . (!empty($org['UF_SHORT_NAME']) ? '. ' . $org['UF_SHORT_NAME'] : '');
                $result['COLUMN_SREZ'] = 'Общеобразовательное учреждение';
                break;
            case 'mun':
                $mun = MunicipalitiesTable::getList([
                    'select' => [
                        'UF_NAME',
                    ],
                    'filter' => [
                        'ID' => $this->filter['MUN'],
                    ]
                ])->fetch();
                $result['SREZ'] = $mun['UF_NAME'];
                $result['COLUMN_SREZ'] = 'Муниципальное образование';
                break;
            case 'region':
                $result['SREZ'] = 'Вадимирская область';
                $result['COLUMN_SREZ'] = 'Регион';
                break;
        }
        $groups = $this->user->getRolesStringId();
        if ($this->srez == 'mun') {
            $builds = BuildingsTable::getList([
                'select' => [
                    '*',
                    'MUN' => 'ORG.UF_MUN_ID',
                ],
                'filter' => $this->filter,
            ])->fetchAll();
            $buildIds = array_column($builds, 'ID');
            $this->filter = ['BUILD_ID' => $buildIds];
        }
        $filterUsers = array_merge($this->filter, ['GROUP' => $groups['edu']]);
        $result['ALL_STUDENTS'] = count(UserTable::getList([
            'select' => [
                'ID',
                'GROUP' => 'GROUPS.GROUP_ID',
                'UF_CLASS',
                'BUILD_ID' => 'UF_BUILDING'
            ],
            'filter' => $filterUsers,
        ])->fetchAll());
        $result['YEARS'] = $this->params['YEAR'] . '-' . $this->params['YEAR'] + 1;
        foreach ($this->extraParams as $item) {
            $json = json_decode($item['UF_JSON'], true);
            foreach ($json['fields'] as $key => $value) {
                $result[$key] += $value;
            }
        }
        foreach ($manualParams as $par) {
            $result[$par] = $this->params[$par];
        }
        $templateProcessor = new TemplateProcessor($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/form_learn_books_template.docx');
        $result = $this->checkValueEmpty($result, $templateProcessor);
        $templateProcessor->setValues($result);
        $path = $this->createWord($templateProcessor);
        return $path;
    }

    private function getInfoLearnBooks()
    {
        $readCards = ReadCardsTable::getList([
            'select' => [
                '*',
                'BUILD_ID' => 'BOOK.CATALOG.UF_BUILDING',
                'MUN' => 'BOOK.CATALOG.BUILD.ORG.UF_MUN_ID',
                'UF_FORM_PUBLICATION' => 'BOOK.CATALOG.UF_FORM_PUBLICATION',
            ],
            'filter' => $this->filter
        ])->fetchAll();
        $userIds = array_column($readCards, 'UF_USER_READ');

        $readers = UserTable::getList([
            'select' => [
                'ID',
                'GROUP' => 'GROUPS.GROUP_ID',
                'UF_CLASS',
                'BUILD_ID' => 'UF_BUILDING',
            ],
            'filter' => [
                'ID' => $userIds,
            ]
        ])->fetchAll();


        $result = [];
        for ($i = 1; $i <= 11; $i++) {
            $result[$i . 'students'] = 0;
            $result[$i . 'paper'] = 0;
            $result[$i . 'electronic'] = 0;
        }
        $result['allstudents'] = 0;
        $result['allpaper'] = 0;
        $result['allelectronic'] = 0;
        $allStudents = $this->getAllStudents();
        foreach ($allStudents as $student) {
            $class = intval(preg_replace('/[^0-9]/', '', $student['UF_CLASS']));
            if (!empty($class)) {
                $result['allstudents']++;
                $result[$class . 'students']++;
            }
        }
        $statusesCatalog = EnumHelper::getListIdXmlValue(CatalogBookTable::getUfId(), ['UF_FORM_PUBLICATION'], true);

        foreach ($readers as $reader) {
            $class = intval(preg_replace('/[^0-9]/', '', $reader['UF_CLASS']));
            $readers[$reader['ID']] = $class;
        }
        foreach ($readCards as $readCard) {
            $class = $readers[$readCard['UF_USER_READ']];
            if (!empty($class)) {
                $result['all' . $statusesCatalog['UF_FORM_PUBLICATION'][$readCard['UF_FORM_PUBLICATION']]['XML_ID']]++;
                $result[$class . $statusesCatalog['UF_FORM_PUBLICATION'][$readCard['UF_FORM_PUBLICATION']]['XML_ID']]++;
            }
        }
        $result['YEAR'] = $this->params['YEAR'];
        $result['NEXT_YEAR'] = $this->params['YEAR'] + 1;

        switch ($this->srez) {
            case 'org':
                $org = BuildingsTable::getList([
                    'select' => [
                        'UF_SHORT_NAME',
                        'ORG_NAME' => 'ORG.UF_NAME',
                    ],
                    'filter' => ['ID' => $this->filter['BUILD_ID']],
                ])->fetch();
                $result['HEAD'] = $org['ORG_NAME'] . (!empty($org['UF_SHORT_NAME']) ? '. ' . $org['UF_SHORT_NAME'] : '');
                break;
            case 'mun':
                $mun = MunicipalitiesTable::getList([
                    'select' => [
                        'UF_NAME',
                    ],
                    'filter' => [
                        'ID' => $this->filter['MUN'],
                    ]
                ])->fetch();
                $result['HEAD'] = $mun['UF_NAME'];
                break;
            case 'region':
                $result['HEAD'] = 'Владимирская область';
                break;
        }

        foreach ($this->extraParams as $item) {
            $json = json_decode($item['UF_JSON'], true);
            foreach ($json['fields'] as $key => $value) {
                $result[$key] += $value;
                if (str_contains($key, 'provide_learn_books')) {
                    $result['allbooks'] += $value;
                }
                if (str_contains($key, 'provide_notebooks')) {
                    $result['allnotebooks'] += $value;
                }
            }
        }
        $templateProcessor = new TemplateProcessor($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/info_learn_books_region_template.docx');
        $result = $this->checkValueEmpty($result, $templateProcessor);
        $templateProcessor->setValues($result);
        $path = $this->createWord($templateProcessor);
        return $path;
    }

    private function getLearnBooks()
    {
        $manualParams = [
            'FIO',
            'CONTACT_PHONE',
            'YEAR',
        ];
        $result = [];
        foreach ($this->extraParams as $item) {
            $json = json_decode($item['UF_JSON'], true);
            foreach ($json['fields'] as $key => $value) {
                $result[$key] += $value;
            }
        }
        foreach ($manualParams as $par) {
            $result[$par] = $this->params[$par];
        }
        $result['YEAR'] = $this->params['YEAR'];
        $templateProcessor = new TemplateProcessor($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/learn_books_template.docx');
        $result = $this->checkValueEmpty($result, $templateProcessor);
        $templateProcessor->setValues($result);
        $path = $this->createWord($templateProcessor);
        return $path;
    }

    private function getMonitoringLearnBooks()
    {

    }

    private function getFormMonitoringStaffLib($data)
    {
        $this->user->checkUserGroups(['mun', 'region']);
        $filter = [];
        $munId = 0;
        if ($this->user->getGroup() == 'mun') {
            $filter = ['ID' => $this->user->getMunicipality()['mun']['ID'],];
            $munId = $this->user->getMunicipality()['mun']['ID'];
        } elseif ($this->user->getGroup() == 'region') {
            $filter = ['ID' => $this->params['MUN']];
            $munId = $this->params['MUN'];
        }
        $manualParams = [
            'FIO' => 'A7',
            'CONTACT_PHONE' => 'A8',
            'EMAIL' => 'A9',
        ];
        $result = [];
        foreach ($manualParams as $key => $value) {
            $result[$value] = $this->params[$key];
        }

        # получение доп параметров
        $monitoring = MonitoringTable::getList([
            'select'=>[
                'ID','UF_JSON','UF_YEAR','BUILD.ORG.MUN.UF_NAME'
            ] ,
            'filter' => [
                'BUILD.ORG.UF_MUN_ID'=>$munId,
                'UF_YEAR'=>$this->params['YEAR'],
            ],
        ])->fetchCollection();
        $info = [];
        if (!empty($monitoring)) {
            $munName = "";
            $reportFields = ReportMapping::getTypeMonitoringFields();
            $reportCells = ReportMapping::getCellsMonitoringFields();
            foreach ($monitoring as $item) {
                $jsonData = json_decode($item->get('UF_JSON'),true);
                foreach ($jsonData as $key => $value) {
                    if ($reportFields[$key]=='multiselect') {
                        foreach ($value as $elem) {
                            $info[$reportCells[$elem]]++;
                        }
                    } elseif ($reportFields[$key]=='square') {
                        $row = 30;
                        if ($key=='COM_ZONE_SQUARE') $row=35;
                        if ((int)$value<=30) {
                            $info['D'.$row]++;
                        } elseif ((int)$value<=50) {
                            $info['E'.$row]++;
                        } elseif ((int)$value<=70) {
                            $info['F'.$row]++;
                        } elseif ((int)$value<=100) {
                            $info['G'.$row]++;
                        } else {
                            $info['H'.$row]++;
                        }
                    } elseif ($reportFields[$key]=='place' || $reportFields[$key]=='computer') {
                        $list = ['place'=>['I','J','K','L','M'],'computer'=>['N','O','P','Q','R']];
                        if ((int)$value<=5) {
                            $info[$list[$reportFields[$key]][0].'30']++;
                        } elseif ((int)$value<=10) {
                            $info[$list[$reportFields[$key]][1].'30']++;
                        } elseif ((int)$value<=30) {
                            $info[$list[$reportFields[$key]][2].'30']++;
                        } elseif ((int)$value<=50) {
                            $info[$list[$reportFields[$key]][3].'30']++;
                        } else {
                            $info[$list[$reportFields[$key]][4].'30']++;
                        }
                    } elseif ($reportFields[$key]=='table') {
                        foreach ($value as $elem=>$val) {
                            $info[$key][$elem]+=$val;
                        }
                    } elseif ($reportFields[$key]=='percent') {
                        $list = ['PERCENT_ART_BOOKS_PROGRAM' => ['J44', 'J45', 'J46', 'J47', 'J48'], 'PERCENT_ART_BOOKS' => ['J51', 'J52', 'J53', 'J54', 'J55']];
                        if ((int)$value == 0) {
                            $info[$list[$key][4]]++;
                        } elseif ((int)$value <= 25) {
                            $info[$list[$key][0]]++;
                        } elseif ((int)$value <= 50) {
                            $info[$list[$key][1]]++;
                        } elseif ((int)$value <= 75) {
                            $info[$list[$key][2]]++;
                        } else {
                            $info[$list[$key][3]]++;
                        }
                    } else {
                        if ($value) {
                            $info[$reportCells[$key]]++;
                        }
                    }
                }
                if (empty($munName)) {
                    $munName = $item->getBuild()?->getOrg()?->getMun()?->get('UF_NAME');
                }
            }
            $result['A5'] = $munName;
        }
        # получение мероприятий
        $events = EventsTable::getList([
            'select'=>[
                'ID','UF_DATE_START','BUILD.ORG.UF_MUN_ID','TYPE.UF_XML_ID'
            ],
            'filter'=>[
                'BUILD.ORG.UF_MUN_ID'=>$munId,
                '>=UF_DATE_START'=>$data['PERIOD_START'],
                '<=UF_DATE_START'=>$data['PERIOD_END'],
            ]
        ])->fetchCollection();
//        foreach ($events->getUfDateStartList()as $date) {
//            $res[] = $date->toString();
//        }
//        echo json_encode($res);exit();
        $eventRes = [];
        foreach ($events as $event) {
            switch ($event->getType()?->get('UF_XML_ID')) {
                case 'book_ex':
                    $eventRes['B50']++;
                    break;
                case 'conversations':case 'lib_lessons':
                    $eventRes['B52']++;
                    break;
//                case 'lit_games':case 'matinees':case 'lit_muz':case 'contests':case 'projects':
//                    $eventRes['B54']++;
//                    break;
                case 'read_conf':
                    $eventRes['B51']++;
                    break;
                case 'quizzes':
                    $eventRes['B49']++;
                    break;
                default:
                    $eventRes['B54']++;
                    break;
            }
            $eventRes['B47']++;
        }

        $spreadsheet = IOFactory::load($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/form_monitoring_staff_lib_template.xlsx');
//        $spreadsheet = clone $templateSpreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        foreach ($info as $key => $value) {
            if ($reportFields[$key]=='table') {
                $col = 2;
                switch ($key) {
                    case 'MAIN_LIB':
                        $row = 72;
                        break;
                    case 'DOP_LIB':
                        $row = 73;
                        break;
                    case 'MAIN_TEACHER_LIB':
                        $row = 74;
                        break;
                    case 'DOP_TEACHER_LIB':
                        $row = 75;
                        break;
                    case 'BOSS_LIB':
                        $row = 76;
                        break;
                }
                foreach ($value as $elem=>$val) {
                    $worksheet->setCellValue(Coordinate::stringFromColumnIndex($col).$row,$val);
                    $col++;
                }
            } else {
                $worksheet->setCellValue($key, $value);
            }
        }
        foreach ($eventRes as $key => $value) {
            $worksheet->setCellValue($key,$value);
        }
        $path = $this->createExcel($spreadsheet);
        return $path;
    }

    private function getMonitoringStaff()
    {
        $manualParams = [
            'FIO' => 'CJ14',
            'CONTACT_PHONE' => 'BD17',
            'EMAIL' => 'CR17',
            'JOB_TITLE' => 'BD14'
        ];
        $result = [];
        $dateTime = new DateTime();
        $result['DR17'] = (int)$dateTime->format('d');
        $result['DY17'] = (int)$dateTime->format('m');
        $result['EM17'] = (int)str_replace('20', '', $dateTime->format('Y'));
        foreach ($manualParams as $key => $value) {
            $result[$value] = $this->params[$key];
        }
        foreach ($this->extraParams['fields'] as $item) {
            $json = json_decode($item['UF_JSON'], true);
            foreach ($json as $key => $value) {
                $result[$key] += $value;
            }
        }
        $templateSpreadsheet = IOFactory::load($_SERVER["DOCUMENT_ROOT"] . '/local/report_templates/monitoring_staff_template.xls');
        $spreadsheet = clone $templateSpreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        foreach ($result as $key => $value) {
            $worksheet->setCellValue($key, $value);
        }
        $path = $this->createExcel($spreadsheet);
        return $path;
    }
    ###
    ### Методы - хелперы
    ###
    private function getEvents()
    {
        if (!empty($this->params['YEAR'])) {
            $nextYear = $this->params['YEAR'] + 1;
            $yearStart = new DateTime('01.09.' . $this->params['YEAR']);
            $yearEnd = new DateTime('31.08.' . $nextYear);
        }
        if (!empty($this->params['PERIOD_START']) && !empty($this->params['PERIOD_END'])) {
            $yearStart = new DateTime($this->params['PERIOD_START']);
            $yearEnd = new DateTime($this->params['PERIOD_END']);
        }
        $this->filter['>=UF_DATE_F'] = $yearStart;
        $this->filter['<=UF_DATE_F'] = $yearEnd;
        $events = EventsTable::getList([
            'select' => [
                '*',
                'BUILD_ID' => 'BUILD.ID',
                'MUN' => 'BUILD.ORG.UF_MUN_ID',
                'UF_DATE_F' => 'UF_DATE_START',
            ],
            'filter' => $this->filter,
        ])->fetchAll();
        return $events;
    }

    private function getAllStudents()
    {
        if ($this->srez == 'mun') {
            $builds = BuildingsTable::getList([
                'select' => [
                    '*',
                    'MUN' => 'ORG.UF_MUN_ID',
                ],
                'filter' => $this->filter,
            ])->fetchAll();
            $buildIds = array_column($builds, 'ID');
            $this->filter = ['BUILD_ID' => $buildIds];
        }
        $allStudents = UserTable::getList([
            'select' => [
                'ID',
                'GROUP' => 'GROUPS.GROUP_ID',
                'UF_CLASS',
                'BUILD_ID' => 'UF_BUILDING',
            ],
            'filter' => $this->filter,
        ])->fetchAll();
        return $allStudents;
    }
}
