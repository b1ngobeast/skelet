<?php

namespace Webizi;

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php');

use CModule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard\DateTime;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Webizi\Books\Book;
use Webizi\Books\Catalog;
use Webizi\Helpers\EnumHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Highloadblock\HighloadBlockTable as HL;
use Webizi\Models\AuthorsTable;
use Webizi\Models\FpuTable;
use Webizi\Models\GenresTable;
use Webizi\Models\ProvidersTable;
use Webizi\Models\PublicationsTable;
use Webizi\Models\SignProductsTable;
use Webizi\Models\TypeInstanceTable;
use Webizi\Services\OrderingBooksService;
use Webizi\Users\User;
use Bitrix\Main\Type\Date;

//composer self-update --version
class ExcelParse
{
    public static function exportFile($data, $table, $header = []): array
    {
        CModule::IncludeModule('highloadblock');
        $dateObj = new \DateTime();
        $date = $dateObj->format('d-m-Y_H-i-s');
        $newFilePath = '/upload/export/' . $table . '_' . $date . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;
        if (!empty($header)) {
            $currentColumn = 1;
            foreach ($header as $key => $value) {
                $worksheet->setCellValue("A" . $rowIndex, $key . ':');
                $worksheet->getStyle("A" . $rowIndex)->getFont()->setBold(true);
                $worksheet->setCellValue("B" . $rowIndex, $value);
                $worksheet->getStyle("B" . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $rowIndex++;
            }
            $rowIndex++;
            $worksheet->setCellValue("A" . $rowIndex, 'Список книг');
            $worksheet->getStyle("A" . $rowIndex)->getFont()->setBold(true);
            $rowIndex++;
        }
        $firstRow = true;
        foreach ($data as $item) {
            $currentColumn = 1;
            foreach ($item as $key => $value) {
                if ($key == 'ID') {
                    continue;
                }
                $columnLetter = Coordinate::stringFromColumnIndex($currentColumn);
                $value = is_array($value) ? $value['VALUE'] : $value;
                $worksheet->setCellValue($columnLetter . $rowIndex, $value);
                $worksheet->getStyle($columnLetter . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                if ($firstRow) {
                    $worksheet->getStyle($columnLetter . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
                    $worksheet->getStyle($columnLetter . $rowIndex)->getFont()->setBold(true);
                }
                $currentColumn++;
            }
            $worksheet->getRowDimension($rowIndex)->setRowHeight(-1);
            $rowIndex++;
            $firstRow = false;
        }

        $highestColumnLetter = $worksheet->getHighestColumn(); // Например, 'Z'
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumnLetter); // Например, 26

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $columnLetter = Coordinate::stringFromColumnIndex($col);
            $worksheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($_SERVER['DOCUMENT_ROOT'] . $newFilePath);
        return ['path' => /*$_SERVER['SERVER_NAME'] . */$newFilePath];
    }

    public static function prepareCatalogsBeforeImport($filePath, $cells, $reason = false, $order = false)
    {
        # Организация юзера
        $user = new User();
        $org = $user->getOrganizationAndClass()['organization'];
        # Справочники
        $bookLists = EnumHelper::getListIdXmlValue('Books', ['UF_STATUS_POSITION', 'UF_STATUS_CONDITION'], true);
        $catalogLists = EnumHelper::getListIdXmlValue('CatalogBook', ['UF_FORM_PUBLICATION'], true);
        $bookLists['UF_STATUS_POSITION'] = array_column($bookLists['UF_STATUS_POSITION'], 'VALUE', 'XML_ID');
        $bookLists['UF_STATUS_CONDITION'] = array_column($bookLists['UF_STATUS_CONDITION'], 'VALUE', 'XML_ID');
        $catalogLists['UF_FORM_PUBLICATION'] = array_column($catalogLists['UF_FORM_PUBLICATION'], 'VALUE', 'XML_ID');
        $refBook['UF_STATUS_POSITION'] = $bookLists['UF_STATUS_POSITION'];
        $refBook['UF_STATUS_CONDITION'] = $bookLists['UF_STATUS_CONDITION'];
        $refBook['UF_FORM_PUBLICATION'] = $catalogLists['UF_FORM_PUBLICATION'];
        $refBook['UF_AUTHOR'] = AuthorsTable::getList([
            'select' => ['*'],
        ])->fetchAll();
        $refBook['UF_AUTHOR'] = array_column($refBook['UF_AUTHOR'], 'UF_NAME', 'ID');
        $refBook['UF_GENRE'] = GenresTable::getList([
            'select' => ['*'],
        ])->fetchAll();
        $refBook['UF_GENRE'] = array_column($refBook['UF_GENRE'], 'UF_NAME', 'ID');
        $refBook['UF_SIGN_PRODUCT'] = SignProductsTable::getList([
            'select' => ['*'],
        ])->fetchAll();
        $refBook['UF_SIGN_PRODUCT'] = array_column($refBook['UF_SIGN_PRODUCT'], 'UF_CATEGORY', 'ID');
        $refBook['UF_PUBLICATION'] = PublicationsTable::getList([
            'select' => ['*'],
        ])->fetchAll();
        $refBook['UF_PUBLICATION'] = array_column($refBook['UF_PUBLICATION'], 'UF_NAME', 'ID');

        $refBook['UF_TYPE_INSTANCE'] = TypeInstanceTable::getList([
            'select' => ['*'],
        ])->fetchAll();
        $refBook['UF_TYPE_INSTANCE'] = array_column($refBook['UF_TYPE_INSTANCE'], 'UF_TYPE', 'ID');
        $refBook['UF_PROVIDER'] = ProvidersTable::getList([
            'select' => ['*'],
        ])->fetchAll();
        $refBook['UF_PROVIDER'] = array_column($refBook['UF_PROVIDER'], 'UF_NAME', 'ID');
        $refBook['UF_FPU'] = FpuTable::getList([
            'select' => ['ID', 'UF_NAME'],
        ])->fetchAll();
        $refBook['UF_FPU'] = array_column($refBook['UF_FPU'], 'UF_NAME', 'ID');
        # Разбор файла
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0);
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestColumn());
        $startRow = 2;
        $data = [];
        $fpuCell = $order ? 'R' : 'X';
        for ($row = $startRow; $row <= $highestRow; $row++) {
            $elemData = [];
            foreach ($cells as $key => $value) {
                $fpuFlag = true;
                $cellValue = $worksheet->getCell($key . $row)->getFormattedValue();
                switch ($value['TYPE']) {
                    case 'bool':
                        $cellValue = trim(mb_strtolower($cellValue)) == 'да' ? 1 : 0;
                        break;
                    case 'ref':
                        if ($value['NAME'] == 'UF_FPU') {
                            $fpuFlag = trim(mb_strtolower($worksheet->getCell($fpuCell . $row)->getFormattedValue())) == 'да' ? true : false;
                        }
                        if (/*$fpuFlag && */!empty($refBook[$value['NAME']]) && $value['NAME']!='UF_PROVIDER') {
                            $cellValue = array_search($cellValue, $refBook[$value['NAME']]);
                        }
                        break;
                }
//                if ($value['NAME']=='UF_PROVIDER') {
//                    echo json_encode($cellValue);exit();
//                }
                if ($order) {
                    if (empty($cellValue) && !is_numeric($cellValue) && !in_array($key, ['B', 'F', 'I', 'J', 'K', 'N', 'P', 'Q', 'R', 'S'])) {

                        throw new \Exception('Неверно введены данные. Проверьте ячейку ' . $key . $row, 400);
                    }
                } else {
                    if (empty($cellValue) && !is_numeric($cellValue) && !in_array($key, ['B', 'F', 'L', 'M', 'N', 'U', 'V', 'W', 'X', 'Y','S'])) {
                        throw new \Exception('Неверно введены данные. Проверьте ячейку ' . $key . $row, 400);
                    }
                }

                if ($value['CATALOG'] == 'Y') {
                    if ($value['NAME'] == 'AUTHOR_NAME') {
                        $elemData['CATALOG']['UF_AUTHOR'] = array_search($cellValue, $refBook['UF_AUTHOR']);
                    }
                    if ($fpuFlag) {
                        $elemData['CATALOG'][$value['NAME']] = $cellValue;
                    }
                } else {
                    $elemData['BOOK'][$value['NAME']] = $cellValue != '#NULL!' ? $cellValue : '';
                }
            }
            $elemData['UF_ORG_ID'] = $org['ID'];
            $dateTimeTmp = (new \DateTime(date('d.m.Y H:i:s')))->format('d.m.Y H:i:s');
            $elemData["BOOK"]['UF_DATE_CREATE'] = (new Date($dateTimeTmp));
            $elemData["BOOK"]['UF_CREATE_BY_USER'] = $user->getUserID();
            $catalogString = implode('/', $elemData["CATALOG"]);

            if (!empty($data[$catalogString]) && !empty($elemData['BOOK'])) {
                $data[$catalogString]['BOOKS'][] = $elemData['BOOK'];
            } else {
                $data[$catalogString]['CATALOG'] = $elemData['CATALOG'];
                if (!empty($elemData['BOOK'])) {
                    $data[$catalogString]['BOOKS'][] = $elemData['BOOK'];
                }
            }
        }
        return $data;
    }

    public static function importOrderBooks($filePath, $cells, $orderReason)
    {
        # Организация юзера
        $user = new User();
        $org = $user->getOrganizationAndClass()['organization'];
        $data = self::prepareCatalogsBeforeImport($filePath, $cells, true, true);
        $result = [];
        $result['UF_ORDER_REASON'] = $orderReason;
        unset($data['UF_ORDER_REASON']);
        $tmpRes = [];
        foreach ($data as $item) {
            //print_r($item);
            $tmpRes['UF_QUANTITY'] = $item['CATALOG']['UF_QUANTITY'];
            unset($item['CATALOG']['UF_QUANTITY']);
            $catalog = Catalog::addCatalog($org, $item['CATALOG']);
            $tmpRes['UF_CATALOG'] = $catalog['catalog'];
            $result['PART'][] = $tmpRes;
        }
        $res = (new OrderingBooksService)->add($result);
        return ['data' => $res];
    }

    public static function importNewBooks($filePath, $cells)
    {
        # Организация юзера
        $user = new User();
        $org = $user->getOrganizationAndClass()['organization'];
        $data = self::prepareCatalogsBeforeImport($filePath, $cells, false, false);
        $booksData = [];
        foreach ($data as $item) {
            $catalog = Catalog::addCatalog($org, $item['CATALOG']);
            foreach ($item['BOOKS'] as &$book) {
                $book['UF_CATALOG_ID'] = $catalog['catalog'];
                $booksData[] = $book;
            }
        }
        $res = Book::addBookMulti($booksData);
        return ['data' => $res];
    }
}
