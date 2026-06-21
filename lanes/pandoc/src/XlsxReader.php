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
        $sheets = $this->parseWorkbook($workbook);
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
            $cells = $this->parseSheetCells($sheetDocument, $sharedStrings, $styles);
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
                'styleFontCount' => count($styles),
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
     * @return list<array{index:int, name:string, relationshipId:string}>
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

        return $sheets;
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
     * @return list<array{bold:bool, italic:bool}>
     */
    private function readStyles(ZipPackage $package, OpcRelationships $workbookRelationships): array
    {
        $relationship = $this->firstRelationshipWithTarget($workbookRelationships, 'styles');
        if (!$relationship instanceof OpcRelationship || $relationship->isExternal()) {
            return [];
        }

        $part = OpcPackagePath::stripQueryAndFragment($workbookRelationships->resolveTarget($relationship));
        $document = $this->loadPackageXml($package, $part, 'XLSX styles');
        $root = XmlHtmlDom::rootElement($document, 'styleSheet');
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $fonts = $this->firstChildElement($root, 'fonts');
        if (!$fonts instanceof \DOMElement) {
            return [];
        }

        $styles = [];
        foreach ($this->childElements($fonts, 'font') as $fontElement) {
            $styles[] = [
                'bold' => $this->firstChildElement($fontElement, 'b') instanceof \DOMElement,
                'italic' => $this->firstChildElement($fontElement, 'i') instanceof \DOMElement,
            ];
        }

        return $styles;
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
     * @param list<array{bold:bool, italic:bool}> $styles
     * @return array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool
     * }>
     */
    private function parseSheetCells(\DOMDocument $document, array $sharedStrings, array $styles): array
    {
        $root = XmlHtmlDom::rootElement($document, 'worksheet');
        if (!$root instanceof \DOMElement) {
            throw new \RuntimeException('XLSX worksheet XML must have a worksheet root');
        }

        $sheetData = $this->firstChildElement($root, 'sheetData');
        if (!$sheetData instanceof \DOMElement) {
            return [];
        }

        $cells = [];
        foreach ($this->childElements($sheetData, 'row') as $rowElement) {
            foreach ($this->childElements($rowElement, 'c') as $cellElement) {
                $cell = $this->parseCell($cellElement, $sharedStrings, $styles);
                if ($cell !== null) {
                    $cells[$cell['row'] . ':' . $cell['column']] = $cell;
                }
            }
        }

        return $cells;
    }

    /**
     * @param list<string> $sharedStrings
     * @param list<array{bold:bool, italic:bool}> $styles
     * @return array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool
     * }|null
     */
    private function parseCell(\DOMElement $cellElement, array $sharedStrings, array $styles): ?array
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
        } elseif (trim($rawValue) === '') {
            $text = '';
            $empty = true;
            $valueType = 'empty';
        } elseif (is_numeric(trim($rawValue))) {
            $text = $this->formatNumber((float) trim($rawValue));
            $valueType = 'number';
        } else {
            $text = $rawValue;
        }

        $style = $styleIndex !== null && array_key_exists($styleIndex, $styles)
            ? $styles[$styleIndex]
            : ['bold' => false, 'italic' => false];

        return [
            'row' => $cellRef['row'],
            'column' => $cellRef['column'],
            'ref' => $ref,
            'valueType' => $valueType,
            'text' => $text,
            'bold' => $style['bold'],
            'italic' => $style['italic'],
            'empty' => $empty,
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
     * @param array<string, array{
     *     row:int,
     *     column:int,
     *     ref:string,
     *     valueType:string,
     *     text:string,
     *     bold:bool,
     *     italic:bool,
     *     empty:bool
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
            $text = is_array($cell) ? (string) $cell['text'] : '';
            $cells[] = new AstNode('table_cell', [
                'header' => $header,
                'text' => $text,
                'sourceCell' => is_array($cell) ? (string) $cell['ref'] : null,
                'sourceColumn' => $columnIndex,
                'xlsxValueType' => is_array($cell) ? (string) $cell['valueType'] : 'empty',
            ], [
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

        return $inlines;
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

        return implode(' ', $texts);
    }
}
