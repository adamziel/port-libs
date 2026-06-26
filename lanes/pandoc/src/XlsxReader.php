<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XlsxReader
{
    private const OFFICE_DOCUMENT_RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MAX_XML_PART_BYTES = 8_388_608;

    public function read(string $bytes): AstNode
    {
        $package = ZipPackage::fromString($bytes);

        return $this->readPackage($package, strlen($bytes));
    }

    private function readPackage(ZipPackage $package, int $sourceBytes): AstNode
    {
        $rootRelationships = OpcRelationships::fromPackage($package, '/');
        $workbookRelationship = $this->workbookRelationship($rootRelationships);
        $workbookPart = OpcPackagePath::stripQueryAndFragment($rootRelationships->resolveTarget($workbookRelationship));
        $workbook = $this->loadPackageXml($package, $workbookPart, 'XLSX workbook');
        $workbookInfo = $this->parseWorkbook($workbook);
        $sheets = $workbookInfo['sheets'];
        $workbookRelationships = $this->relationshipsOrEmpty($package, $workbookPart);
        $sharedStrings = $this->readSharedStrings($package, $workbookRelationships);
        $styles = $this->readStyles($package, $workbookRelationships);

        $blocks = [];
        $sheetReviews = [];
        $tableCount = 0;
        foreach ($sheets as $sheet) {
            $relationship = $workbookRelationships->byId($sheet['relationshipId']);
            if (!$relationship instanceof OpcRelationship) {
                throw new \RuntimeException('XLSX sheet relationship not found: ' . $sheet['relationshipId']);
            }
            if ($relationship->isExternal()) {
                throw new \RuntimeException('XLSX external worksheet relationships are not supported');
            }

            $sheetPart = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
            $sheetDocument = $this->loadPackageXml($package, $sheetPart, 'XLSX worksheet ' . $sheet['name']);
            $sheetRelationships = $this->relationshipsOrEmpty($package, $sheetPart);
            $cells = $this->parseSheetCells($sheetDocument, $sharedStrings, $styles, $workbookInfo['date1904'], $sheetRelationships);
            $table = $this->cellsToTable($sheet['name'], $cells);
            $blocks[] = new AstNode('heading', [
                'level' => 2,
                'id' => 'sheet-' . $sheet['index'],
                'text' => $sheet['name'],
            ], [new AstNode('text', ['text' => $sheet['name']])]);
            if ($table instanceof AstNode) {
                $blocks[] = $table;
                $tableCount++;
            }

            $sheetReviews[] = [
                'index' => $sheet['index'],
                'name' => $sheet['name'],
                'relationshipId' => $sheet['relationshipId'],
                'partName' => ltrim($sheetPart, '/'),
                'cellCount' => count($cells),
                'hyperlinkCount' => count(array_filter($cells, static fn (array $cell): bool => ($cell['url'] ?? '') !== '')),
                'mergedCellCount' => count(array_filter($cells, static fn (array $cell): bool => (int) ($cell['colspan'] ?? 1) > 1 || (int) ($cell['rowspan'] ?? 1) > 1)),
                'tableEmitted' => $table instanceof AstNode,
            ];
        }

        return new AstNode('document', [
            'sourceFormat' => 'xlsx',
            'meta' => [],
            'xlsx' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-xlsx-reader',
                'sourceBytes' => $sourceBytes,
                'entryCount' => count($package->names()),
                'workbookPart' => ltrim($workbookPart, '/'),
                'sheetCount' => count($sheets),
                'tableCount' => $tableCount,
                'sharedStringCount' => count($sharedStrings),
                'styleFontCount' => count($styles['fonts']),
                'styleCellFormatCount' => count($styles['cellFormats']),
                'styleCustomNumberFormatCount' => count($styles['customNumberFormats']),
                'date1904' => $workbookInfo['date1904'],
                'sheets' => $sheetReviews,
                'payloadExposurePolicy' => 'xml-text-only',
                'upstreamEvidence' => [
                    'denominator' => 1,
                    'fixtures' => [
                        'test/xlsx-reader/basic.xlsx',
                        'test/xlsx-reader/basic.native',
                    ],
                    'source' => 'Pandoc 912bfa5e src/Text/Pandoc/Readers/Xlsx.hs and src/Text/Pandoc/Readers/Xlsx/{Parse,Sheets,Cells}.hs',
                ],
            ],
        ], $blocks);
    }

    private function workbookRelationship(OpcRelationships $relationships): OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (
                str_contains($relationship->type, 'officeDocument')
                && str_contains($relationship->target, 'workbook')
            ) {
                return $relationship;
            }
        }

        $relationship = $relationships->firstOfType(self::OFFICE_DOCUMENT_RELATIONSHIP);
        if ($relationship instanceof OpcRelationship) {
            return $relationship;
        }

        throw new \RuntimeException('XLSX package does not declare a workbook relationship');
    }

    private function relationshipsOrEmpty(ZipPackage $package, string $sourcePart): OpcRelationships
    {
        if (!OpcRelationships::packageHasRelationshipsForSource($package, $sourcePart)) {
            return new OpcRelationships($sourcePart);
        }

        return OpcRelationships::fromPackage($package, $sourcePart);
    }

    private function loadPackageXml(ZipPackage $package, string $partName, string $label): \DOMDocument
    {
        $xml = $package->read($partName, self::MAX_XML_PART_BYTES);

        return XmlHtmlDom::loadXmlDocument($xml, $label, false);
    }

    /**
     * @return array{
     *     date1904:bool,
     *     sheets:list<array{index:int, name:string, relationshipId:string}>
     * }
     */
    private function parseWorkbook(\DOMDocument $document): array
    {
        $root = XmlHtmlDom::rootElement($document, 'workbook');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XLSX workbook XML must have a workbook root');
        }

        $sheetsElement = $this->firstChildElement($root, 'sheets');
        if (!$sheetsElement instanceof \DOMElement) {
            throw new \RuntimeException('XLSX workbook XML is missing <sheets>');
        }

        $sheets = [];
        $index = 1;
        foreach ($this->childElements($sheetsElement, 'sheet') as $sheetElement) {
            $name = trim($sheetElement->getAttribute('name'));
            if ($name === '') {
                $name = 'Sheet' . $index;
            }
            $relationshipId = $this->relationshipId($sheetElement);
            if ($relationshipId === '') {
                throw new \RuntimeException('XLSX workbook sheet is missing r:id');
            }

            $sheets[] = [
                'index' => $index,
                'name' => $name,
                'relationshipId' => $relationshipId,
            ];
            $index++;
        }

        $workbookProperties = $this->firstChildElement($root, 'workbookPr');

        return [
            'date1904' => $workbookProperties instanceof \DOMElement
                && in_array(strtolower(trim($workbookProperties->getAttribute('date1904'))), ['1', 'true'], true),
            'sheets' => $sheets,
        ];
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipPackage $package, OpcRelationships $workbookRelationships): array
    {
        $relationship = $this->firstRelationshipWithTarget($workbookRelationships, 'sharedStrings');
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return [];
        }

        $part = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
        $document = $this->loadPackageXml($package, $part, 'XLSX shared strings');
        $root = XmlHtmlDom::rootElement($document, 'sst');
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $strings = [];
        foreach ($this->childElements($root, 'si') as $stringElement) {
            $directText = $this->firstChildElement($stringElement, 't');
            if ($directText instanceof \DOMElement) {
                $strings[] = $directText->textContent;
                continue;
            }

            $strings[] = $this->allDescendantText($stringElement);
        }

        return $strings;
    }

    /**
     * @return array{
     *     fonts:list<array{bold:bool, italic:bool}>,
     *     cellFormats:list<array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null}>,
     *     customNumberFormats:array<int, string>
     * }
     */
    private function readStyles(ZipPackage $package, OpcRelationships $workbookRelationships): array
    {
        $empty = [
            'fonts' => [],
            'cellFormats' => [],
            'customNumberFormats' => [],
        ];
        $relationship = $this->firstRelationshipWithTarget($workbookRelationships, 'styles');
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return $empty;
        }

        $part = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
        $document = $this->loadPackageXml($package, $part, 'XLSX styles');
        $root = XmlHtmlDom::rootElement($document, 'styleSheet');
        if (!$root instanceof \DOMElement) {
            return $empty;
        }

        $customNumberFormats = [];
        $numFmts = $this->firstChildElement($root, 'numFmts');
        if ($numFmts instanceof \DOMElement) {
            foreach ($this->childElements($numFmts, 'numFmt') as $formatElement) {
                $id = trim($formatElement->getAttribute('numFmtId'));
                if (preg_match('/^\d+$/', $id) === 1) {
                    $customNumberFormats[(int) $id] = $formatElement->getAttribute('formatCode');
                }
            }
        }

        $fontsList = [];
        $fonts = $this->firstChildElement($root, 'fonts');
        if ($fonts instanceof \DOMElement) {
            foreach ($this->childElements($fonts, 'font') as $fontElement) {
                $fontsList[] = [
                    'bold' => $this->firstChildElement($fontElement, 'b') instanceof \DOMElement,
                    'italic' => $this->firstChildElement($fontElement, 'i') instanceof \DOMElement,
                ];
            }
        }

        $cellFormats = [];
        $cellXfs = $this->firstChildElement($root, 'cellXfs');
        if ($cellXfs instanceof \DOMElement) {
            foreach ($this->childElements($cellXfs, 'xf') as $xfElement) {
                $fontId = trim($xfElement->getAttribute('fontId'));
                $fontId = preg_match('/^\d+$/', $fontId) === 1 ? (int) $fontId : 0;
                $font = $fontsList[$fontId] ?? ['bold' => false, 'italic' => false];
                $numFmtId = trim($xfElement->getAttribute('numFmtId'));
                $numFmtId = preg_match('/^\d+$/', $numFmtId) === 1 ? (int) $numFmtId : null;
                $cellFormats[] = [
                    'bold' => $font['bold'],
                    'italic' => $font['italic'],
                    'numFmtId' => $numFmtId,
                    'formatCode' => $numFmtId === null ? null : ($customNumberFormats[$numFmtId] ?? $this->builtInNumberFormat($numFmtId)),
                ];
            }
        }

        return [
            'fonts' => $fontsList,
            'cellFormats' => $cellFormats,
            'customNumberFormats' => $customNumberFormats,
        ];
    }

    private function firstRelationshipWithTarget(OpcRelationships $relationships, string $needle): ?OpcRelationship
    {
        foreach ($relationships->all() as $relationship) {
            if (str_contains($relationship->target, $needle)) {
                return $relationship;
            }
        }

        return null;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{
     *     fonts:list<array{bold:bool, italic:bool}>,
     *     cellFormats:list<array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null}>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @return array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }>
     */
    private function parseSheetCells(\DOMDocument $document, array $sharedStrings, array $styles, bool $date1904, OpcRelationships $relationships): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XLSX worksheet XML must have a worksheet root');
        }

        $hyperlinks = $this->parseHyperlinks($root, $relationships);
        $mergeRegions = $this->parseMergeRegions($root);
        $sheetData = $this->firstChildElement($root, 'sheetData');
        if (!$sheetData instanceof \DOMElement) {
            return [];
        }

        $cells = [];
        foreach ($this->childElements($sheetData, 'row') as $rowElement) {
            foreach ($this->childElements($rowElement, 'c') as $cellElement) {
                $cell = $this->parseCell($cellElement, $sharedStrings, $styles, $date1904, $hyperlinks);
                if ($cell !== null) {
                    $cells[$cell['row'] . ':' . $cell['column']] = $cell;
                }
            }
        }

        $this->applyMergeRegions($cells, $mergeRegions);

        return $cells;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{
     *     fonts:list<array{bold:bool, italic:bool}>,
     *     cellFormats:list<array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null}>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @param array<string, array{url:string, title:string}> $hyperlinks
     * @return array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }|null
     */
    private function parseCell(\DOMElement $cellElement, array $sharedStrings, array $styles, bool $date1904, array $hyperlinks): ?array
    {
        $ref = trim($cellElement->getAttribute('r'));
        if ($ref === '') {
            return null;
        }
        $cellRef = $this->parseCellReference($ref);
        if ($cellRef === null) {
            return null;
        }

        $cellType = trim($cellElement->getAttribute('t'));
        $styleIndex = trim($cellElement->getAttribute('s'));
        $styleIndex = preg_match('/^\d+$/', $styleIndex) === 1 ? (int) $styleIndex : null;
        $style = $this->styleForIndex($styleIndex, $styles);
        $valueElement = $this->firstChildElement($cellElement, 'v');
        $rawValue = $valueElement instanceof \DOMElement ? $valueElement->textContent : '';
        $empty = false;
        $valueType = 'text';

        if ($cellType === 's') {
            if (preg_match('/^-?\d+$/', trim($rawValue)) === 1) {
                $index = (int) trim($rawValue);
                if ($index >= 0 && array_key_exists($index, $sharedStrings)) {
                    $text = $sharedStrings[$index];
                } else {
                    $text = '';
                    $empty = true;
                    $valueType = 'empty';
                }
            } else {
                $text = '';
                $empty = true;
                $valueType = 'empty';
            }
        } elseif ($cellType === 'inlineStr') {
            $inlineString = $this->firstChildElement($cellElement, 'is');
            $text = $inlineString instanceof \DOMElement ? $this->allDescendantText($inlineString) : '';
            $empty = $text === '';
            $valueType = $empty ? 'empty' : 'text';
        } elseif (trim($rawValue) === '') {
            $text = '';
            $empty = true;
            $valueType = 'empty';
        } elseif (is_numeric(trim($rawValue))) {
            $number = (float) trim($rawValue);
            if ($this->isDateStyle($style)) {
                $text = $this->formatDateSerial($number, $style, $date1904);
                $valueType = 'date';
            } else {
                $text = $this->formatNumberForStyle($number, $style);
                $valueType = 'number';
            }
        } else {
            $text = $rawValue;
        }

        $hyperlink = $hyperlinks[$ref] ?? ['url' => '', 'title' => ''];

        return [
            'row' => $cellRef['row'],
            'column' => $cellRef['column'],
            'ref' => $ref,
            'valueType' => $valueType,
            'text' => $text,
            'bold' => $style['bold'],
            'italic' => $style['italic'],
            'empty' => $empty,
            'url' => $hyperlink['url'],
            'title' => $hyperlink['title'],
            'colspan' => 1,
            'rowspan' => 1,
            'covered' => false,
        ];
    }

    /**
     * @return array{column:int, row:int}|null
     */
    private function parseCellReference(string $ref): ?array
    {
        if (preg_match('/^([A-Za-z]+)([1-9][0-9]*)$/', $ref, $matches) !== 1) {
            return null;
        }

        $column = 0;
        foreach (str_split($matches[1]) as $char) {
            $column = $column * 26 + (ord(strtoupper($char)) - ord('A') + 1);
        }

        return [
            'column' => $column,
            'row' => (int) $matches[2],
        ];
    }

    private function formatNumber(float $number): string
    {
        if (is_finite($number) && floor($number) === $number) {
            return number_format($number, 1, '.', '');
        }

        $formatted = rtrim(rtrim(sprintf('%.15G', $number), '0'), '.');

        return $formatted === '' ? '0.0' : $formatted;
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function formatNumberForStyle(float $number, array $style): string
    {
        $formatCode = $style['formatCode'];
        if ($formatCode === null || $formatCode === 'General') {
            return $this->formatNumber($number);
        }

        $formatSection = explode(';', $formatCode)[0] ?? $formatCode;
        $isPercent = str_contains($formatSection, '%');
        if ($isPercent) {
            $number *= 100;
        }

        $decimals = null;
        if (preg_match('/\.(0+)/', $formatSection, $matches) === 1) {
            $decimals = strlen($matches[1]);
        } elseif (preg_match('/(^|[^0])0([^0]|$)/', $formatSection) === 1) {
            $decimals = 0;
        }

        $text = $decimals === null
            ? $this->formatNumber($number)
            : number_format($number, $decimals, '.', '');

        return $isPercent ? $text . '%' : $text;
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function isDateStyle(array $style): bool
    {
        $numFmtId = $style['numFmtId'];
        if ($numFmtId !== null && in_array($numFmtId, [14, 15, 16, 17, 22, 27, 30, 36, 45, 46, 47, 50, 57], true)) {
            return true;
        }

        $formatCode = $style['formatCode'];
        if ($formatCode === null) {
            return false;
        }

        $normalized = strtolower($this->numberFormatCodeForDetection($formatCode));
        if ($normalized === '') {
            return false;
        }

        return preg_match('/[ymdhs]/', $normalized) === 1
            && preg_match('/[0#?]/', $normalized) !== 1;
    }

    /**
     * @param array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null} $style
     */
    private function formatDateSerial(float $serial, array $style, bool $date1904): string
    {
        $days = (int) floor($serial);
        $fraction = $serial - $days;
        if ($fraction < 0) {
            $fraction += 1.0;
            $days--;
        }

        $date = new \DateTimeImmutable($date1904 ? '1904-01-01 00:00:00 UTC' : '1899-12-31 00:00:00 UTC');
        if (!$date1904 && $days >= 60) {
            $days--;
        }
        if ($days !== 0) {
            $date = $date->modify(($days >= 0 ? '+' : '') . $days . ' days') ?: $date;
        }

        $seconds = (int) round($fraction * 86400);
        if ($seconds !== 0) {
            $date = $date->modify('+' . $seconds . ' seconds') ?: $date;
        }

        $formatCode = strtolower($this->numberFormatCodeForDetection((string) ($style['formatCode'] ?? '')));
        $hasTime = preg_match('/[hs]/', $formatCode) === 1;

        return $hasTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d');
    }

    private function numberFormatCodeForDetection(string $formatCode): string
    {
        $formatCode = preg_replace('/"[^"]*"/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\[[^\]]+\]/', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\\\\./', '', $formatCode) ?? $formatCode;
        $formatCode = explode(';', $formatCode)[0] ?? $formatCode;

        return trim($formatCode);
    }

    private function builtInNumberFormat(int $id): ?string
    {
        return [
            0 => 'General',
            1 => '0',
            2 => '0.00',
            9 => '0%',
            10 => '0.00%',
            14 => 'm/d/yy',
            15 => 'd-mmm-yy',
            16 => 'd-mmm',
            17 => 'mmm-yy',
            22 => 'm/d/yy h:mm',
            45 => 'mm:ss',
            46 => '[h]:mm:ss',
            47 => 'mmss.0',
        ][$id] ?? null;
    }

    /**
     * @param array{
     *     fonts:list<array{bold:bool, italic:bool}>,
     *     cellFormats:list<array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null}>,
     *     customNumberFormats:array<int, string>
     * } $styles
     * @return array{bold:bool, italic:bool, numFmtId:int|null, formatCode:string|null}
     */
    private function styleForIndex(?int $styleIndex, array $styles): array
    {
        $default = ['bold' => false, 'italic' => false, 'numFmtId' => null, 'formatCode' => null];
        if ($styleIndex === null) {
            return $default;
        }

        if (array_key_exists($styleIndex, $styles['cellFormats'])) {
            return $styles['cellFormats'][$styleIndex];
        }

        $font = $styles['fonts'][$styleIndex] ?? null;
        if (is_array($font)) {
            return [
                'bold' => (bool) ($font['bold'] ?? false),
                'italic' => (bool) ($font['italic'] ?? false),
                'numFmtId' => null,
                'formatCode' => null,
            ];
        }

        return $default;
    }

    /**
     * @param array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool,
     *     url:string,
     *     title:string,
     *     colspan:int,
     *     rowspan:int,
     *     covered:bool
     * }> $cells
     */
    private function cellsToTable(string $sheetName, array $cells): ?AstNode
    {
        if ($cells === []) {
            return null;
        }

        $rows = array_column($cells, 'row');
        $columns = array_column($cells, 'column');
        $minRow = min($rows);
        $maxRow = max($rows);
        $minColumn = min($columns);
        $maxColumn = max($columns);

        $grid = [];
        for ($row = $minRow; $row <= $maxRow; $row++) {
            $gridRow = [];
            for ($column = $minColumn; $column <= $maxColumn; $column++) {
                $gridRow[] = $cells[$row . ':' . $column] ?? null;
            }
            $grid[] = $gridRow;
        }

        $header = array_shift($grid);
        if (!is_array($header)) {
            return null;
        }
        while ($grid !== [] && $this->isEmptyRow($grid[count($grid) - 1])) {
            array_pop($grid);
        }

        return new AstNode('table', [
            'caption' => '',
            'alignments' => array_fill(0, count($header), 'default'),
            'xlsxSheetName' => $sheetName,
        ], [
            new AstNode('table_head', [], [
                $this->tableRow($header, true),
            ]),
            new AstNode('table_body', [], array_map(
                fn (array $row): AstNode => $this->tableRow($row, false),
                $grid
            )),
        ]);
    }

    /**
     * @param list<array<string, mixed>|null> $row
     */
    private function tableRow(array $row, bool $header): AstNode
    {
        $cells = [];
        foreach ($row as $columnIndex => $cell) {
            if (is_array($cell) && ($cell['covered'] ?? false) === true) {
                continue;
            }
            $text = is_array($cell) ? (string) $cell['text'] : '';
            $attrs = [
                'header' => $header,
                'text' => $text,
                'sourceCell' => is_array($cell) ? (string) $cell['ref'] : null,
                'sourceColumn' => $columnIndex,
                'xlsxValueType' => is_array($cell) ? (string) $cell['valueType'] : 'empty',
            ];
            if (is_array($cell) && (int) ($cell['colspan'] ?? 1) > 1) {
                $attrs['colspan'] = (int) $cell['colspan'];
            }
            if (is_array($cell) && (int) ($cell['rowspan'] ?? 1) > 1) {
                $attrs['rowspan'] = (int) $cell['rowspan'];
            }

            $cells[] = new AstNode('table_cell', $attrs, [
                new AstNode('plain', [], $this->cellInlines(is_array($cell) ? $cell : null)),
            ]);
        }

        return new AstNode('table_row', ['header' => $header], $cells);
    }

    /**
     * @param array<string, mixed>|null $cell
     * @return list<AstNode>
     */
    private function cellInlines(?array $cell): array
    {
        if ($cell === null || ($cell['empty'] ?? true) === true) {
            return [];
        }

        $inlines = [new AstNode('text', ['text' => (string) $cell['text']])];
        if (($cell['bold'] ?? false) === true) {
            $inlines = [new AstNode('strong', [], $inlines)];
        }
        if (($cell['italic'] ?? false) === true) {
            $inlines = [new AstNode('emph', [], $inlines)];
        }
        if (($cell['url'] ?? '') !== '') {
            $inlines = [new AstNode('link', [
                'url' => (string) $cell['url'],
                'title' => (string) ($cell['title'] ?? ''),
            ], $inlines)];
        }

        return $inlines;
    }

    /**
     * @return array<string, array{url:string, title:string}>
     */
    private function parseHyperlinks(\DOMElement $worksheet, OpcRelationships $relationships): array
    {
        $hyperlinksElement = $this->firstChildElement($worksheet, 'hyperlinks');
        if (!$hyperlinksElement instanceof \DOMElement) {
            return [];
        }

        $hyperlinks = [];
        foreach ($this->childElements($hyperlinksElement, 'hyperlink') as $hyperlinkElement) {
            $ref = trim($hyperlinkElement->getAttribute('ref'));
            $refs = [];
            $cellReference = $this->parseCellReference($ref);
            if ($cellReference !== null) {
                $refs[] = $ref;
            } else {
                $range = $this->parseCellRange($ref);
                if ($range !== null) {
                    for ($row = $range['firstRow']; $row <= $range['lastRow']; $row++) {
                        for ($column = $range['firstColumn']; $column <= $range['lastColumn']; $column++) {
                            $refs[] = $this->cellReferenceFromCoordinates($row, $column);
                        }
                    }
                }
            }
            if ($refs === []) {
                continue;
            }

            $url = '';
            $relationshipId = $this->relationshipId($hyperlinkElement);
            if ($relationshipId !== '') {
                $relationship = $relationships->byId($relationshipId);
                if ($relationship instanceof OpcRelationship) {
                    if ($relationship->isExternal()) {
                        $preflight = $relationship->externalTargetPreflight();
                        if ($preflight['allowed']) {
                            $url = $relationship->target;
                        }
                    } else {
                        $url = ltrim(OpcPackagePath::stripQueryAndFragment($relationships->resolveTarget($relationship)), '/');
                    }
                }
            }

            $location = trim($hyperlinkElement->getAttribute('location'));
            if ($url === '' && $location !== '') {
                $url = '#' . $location;
            }
            if ($url === '') {
                continue;
            }

            foreach ($refs as $cellRef) {
                $hyperlinks[$cellRef] = [
                    'url' => $url,
                    'title' => trim($hyperlinkElement->getAttribute('tooltip')),
                ];
            }
        }

        return $hyperlinks;
    }

    /**
     * @return list<array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}>
     */
    private function parseMergeRegions(\DOMElement $worksheet): array
    {
        $mergeCellsElement = $this->firstChildElement($worksheet, 'mergeCells');
        if (!$mergeCellsElement instanceof \DOMElement) {
            return [];
        }

        $regions = [];
        foreach ($this->childElements($mergeCellsElement, 'mergeCell') as $mergeCellElement) {
            $range = $this->parseCellRange(trim($mergeCellElement->getAttribute('ref')));
            if ($range === null) {
                continue;
            }
            if ($range['firstRow'] === $range['lastRow'] && $range['firstColumn'] === $range['lastColumn']) {
                continue;
            }
            $regions[] = $range;
        }

        return $regions;
    }

    /**
     * @param array<string, array<string, mixed>> $cells
     * @param list<array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}> $mergeRegions
     */
    private function applyMergeRegions(array &$cells, array $mergeRegions): void
    {
        foreach ($mergeRegions as $region) {
            $topLeftKey = $region['firstRow'] . ':' . $region['firstColumn'];
            if (!isset($cells[$topLeftKey])) {
                continue;
            }

            $cells[$topLeftKey]['colspan'] = $region['lastColumn'] - $region['firstColumn'] + 1;
            $cells[$topLeftKey]['rowspan'] = $region['lastRow'] - $region['firstRow'] + 1;

            for ($row = $region['firstRow']; $row <= $region['lastRow']; $row++) {
                for ($column = $region['firstColumn']; $column <= $region['lastColumn']; $column++) {
                    if ($row === $region['firstRow'] && $column === $region['firstColumn']) {
                        continue;
                    }

                    $cells[$row . ':' . $column] = [
                        'row' => $row,
                        'column' => $column,
                        'ref' => $this->cellReferenceFromCoordinates($row, $column),
                        'valueType' => 'empty',
                        'text' => '',
                        'bold' => false,
                        'italic' => false,
                        'empty' => true,
                        'url' => '',
                        'title' => '',
                        'colspan' => 1,
                        'rowspan' => 1,
                        'covered' => true,
                    ];
                }
            }
        }
    }

    /**
     * @return array{firstRow:int, firstColumn:int, lastRow:int, lastColumn:int}|null
     */
    private function parseCellRange(string $range): ?array
    {
        if (preg_match('/^([A-Za-z]+[1-9][0-9]*):([A-Za-z]+[1-9][0-9]*)$/', $range, $matches) !== 1) {
            return null;
        }

        $first = $this->parseCellReference($matches[1]);
        $last = $this->parseCellReference($matches[2]);
        if ($first === null || $last === null) {
            return null;
        }

        return [
            'firstRow' => min($first['row'], $last['row']),
            'firstColumn' => min($first['column'], $last['column']),
            'lastRow' => max($first['row'], $last['row']),
            'lastColumn' => max($first['column'], $last['column']),
        ];
    }

    private function cellReferenceFromCoordinates(int $row, int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(ord('A') + ($column % 26)) . $letters;
            $column = intdiv($column, 26);
        }

        return $letters . $row;
    }

    /**
     * @param list<array<string, mixed>|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell === null || ($cell['empty'] ?? false) === true) {
                continue;
            }
            if (trim((string) ($cell['text'] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function relationshipId(\DOMElement $element): string
    {
        $id = $element->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
        if ($id !== '') {
            return $id;
        }
        if ($element->hasAttribute('r:id')) {
            return $element->getAttribute('r:id');
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === 'id') {
                return $attribute->value;
            }
        }

        return '';
    }

    /**
     * @return list<\DOMElement>
     */
    private function childElements(\DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function firstChildElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function allDescendantText(\DOMElement $element): string
    {
        $texts = [];
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === 't') {
                $text = $child->textContent;
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return implode('', $texts);
    }
}
