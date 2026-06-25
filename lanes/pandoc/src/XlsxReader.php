<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XlsxReader
{
    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const SS_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const CP_NS = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';
    private const DCTERMS_NS = 'http://purl.org/dc/terms/';

    public function read(string $bytes): AstNode
    {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary XLSX path.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException('Unable to write temporary XLSX package.');
            }

            return $this->readXlsxFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function readXlsxFile(string $path): AstNode
    {
        $package = ZipOpcPackage::open($path, 'XLSX');

        try {
            $entries = $package->entryNames();
            $workbookPath = $this->workbookPath($package);
            $workbookXml = $package->requireRead($workbookPath, 'XLSX package is missing workbook.xml.');
            $workbookRels = $this->relationships($package->read($this->relationshipsPath($workbookPath)) ?? '');
            $coreXml = $package->read('docProps/core.xml') ?? '';

            $sharedStrings = [];
            $sharedStringsPath = $this->relationshipTargetByType($workbookPath, $workbookRels, '/sharedStrings');
            if ($sharedStringsPath !== null) {
                $sharedStrings = $this->sharedStrings($package->read($sharedStringsPath) ?? '');
            }

            $styles = ['fonts' => [], 'cellFonts' => []];
            $stylesPath = $this->relationshipTargetByType($workbookPath, $workbookRels, '/styles');
            if ($stylesPath !== null) {
                $styles = $this->styles($package->read($stylesPath) ?? '');
            }

            $sheets = [];
            foreach ($this->sheetReferences($workbookXml) as $index => $reference) {
                $relationship = $workbookRels[$reference['relationshipId']] ?? null;
                if (!is_array($relationship) || ($relationship['target'] ?? '') === '') {
                    continue;
                }
                if (($relationship['mode'] ?? '') === 'External') {
                    continue;
                }
                $sheetPath = $this->resolveRelationshipTarget($workbookPath, (string) $relationship['target']);
                $sheetXml = $package->read($sheetPath);
                if (!is_string($sheetXml)) {
                    continue;
                }
                $sheet = $this->sheet(
                    $sheetXml,
                    $reference['name'],
                    $index + 1,
                    $reference['sheetId'],
                    $sheetPath,
                    $sharedStrings,
                    $styles
                );
                $sheets[] = $sheet;
            }
        } finally {
            $package->close();
        }

        $metadata = $coreXml !== '' ? $this->coreProperties($this->loadXml($coreXml, 'XLSX core properties')) : [];
        $metadata['xlsxWorkbookPath'] = $workbookPath;
        $metadata['xlsxSheetCount'] = count($sheets);
        $metadata['xlsxPackageEntries'] = count($entries);
        $metadata['xlsxSharedStringCount'] = count($sharedStrings);
        $metadata['xlsxStyleFontCount'] = count($styles['fonts']);

        if ($sheets === []) {
            $sheets[] = new AstNode('paragraph', ['text' => 'No readable XLSX sheets were found.'], [
                new AstNode('text', ['text' => 'No readable XLSX sheets were found.']),
            ]);
        }

        return new AstNode('document', ['meta' => $metadata], $sheets);
    }

    private function workbookPath(ZipOpcPackage $package): string
    {
        $rels = $this->relationships($package->read('_rels/.rels') ?? '');
        foreach ($rels as $relationship) {
            $type = (string) ($relationship['type'] ?? '');
            $target = (string) ($relationship['target'] ?? '');
            if ($target !== '' && str_contains($type, 'officeDocument') && str_contains($target, 'workbook')) {
                return ltrim(ZipOpcPackage::normalizePath($target), '/');
            }
        }

        return 'xl/workbook.xml';
    }

    /**
     * @return list<array{name:string,sheetId:string,relationshipId:string}>
     */
    private function sheetReferences(string $workbookXml): array
    {
        $dom = $this->loadXml($workbookXml, 'XLSX workbook.xml');
        $references = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'sheet') as $index => $sheet) {
            if (!$sheet instanceof \DOMElement) {
                continue;
            }
            $relationshipId = $this->attr($sheet, self::R_NS, 'id');
            if ($relationshipId === '') {
                continue;
            }
            $references[] = [
                'name' => $sheet->getAttribute('name') !== '' ? $sheet->getAttribute('name') : 'Sheet' . ((int) $index + 1),
                'sheetId' => $sheet->getAttribute('sheetId'),
                'relationshipId' => $relationshipId,
            ];
        }

        return $references;
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{fonts:list<array{bold:bool,italic:bool,underline:bool}>,cellFonts:array<int,int>} $styles
     */
    private function sheet(string $sheetXml, string $name, int $number, string $sheetId, string $sheetPath, array $sharedStrings, array $styles): AstNode
    {
        $cells = $this->sheetCells($sheetXml, $sharedStrings, $styles);
        $children = [
            new AstNode('heading', [
                'id' => 'sheet-' . $number,
                'level' => 2,
                'text' => $name,
            ], [new AstNode('text', ['text' => $name])]),
        ];
        $table = $this->cellsToTable($cells);
        if ($table instanceof AstNode) {
            $children[] = $table;
        }

        return new AstNode('div', [
            'id' => 'sheet-' . $number . '-content',
            'classes' => ['xlsx-sheet'],
            'attributes' => [
                'data-pandoc-source' => 'xlsx',
                'data-xlsx-sheet-number' => (string) $number,
                'data-xlsx-sheet-id' => $sheetId,
                'data-xlsx-sheet-path' => $sheetPath,
            ],
        ], $children);
    }

    /**
     * @param list<string> $sharedStrings
     * @param array{fonts:list<array{bold:bool,italic:bool,underline:bool}>,cellFonts:array<int,int>} $styles
     * @return array<string, array{col:int,row:int,value:string,bold:bool,italic:bool,underline:bool}>
     */
    private function sheetCells(string $sheetXml, array $sharedStrings, array $styles): array
    {
        $dom = $this->loadXml($sheetXml, 'XLSX worksheet');
        $cells = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'c') as $cell) {
            if (!$cell instanceof \DOMElement) {
                continue;
            }
            $ref = $this->parseCellRef($cell->getAttribute('r'));
            if ($ref === null) {
                continue;
            }
            $styleIndex = ctype_digit($cell->getAttribute('s')) ? (int) $cell->getAttribute('s') : null;
            $font = $this->cellFont($styleIndex, $styles);
            $value = $this->cellValue($cell, $sharedStrings);
            if ($value === '' && !$font['bold'] && !$font['italic'] && !$font['underline']) {
                continue;
            }
            $cells[$ref['key']] = [
                'col' => $ref['col'],
                'row' => $ref['row'],
                'value' => $value,
                'bold' => $font['bold'],
                'italic' => $font['italic'],
                'underline' => $font['underline'],
            ];
        }

        return $cells;
    }

    /**
     * @param array<string, array{col:int,row:int,value:string,bold:bool,italic:bool,underline:bool}> $cells
     */
    private function cellsToTable(array $cells): ?AstNode
    {
        if ($cells === []) {
            return null;
        }

        $minCol = min(array_column($cells, 'col'));
        $maxCol = max(array_column($cells, 'col'));
        $minRow = min(array_column($cells, 'row'));
        $maxRow = max(array_column($cells, 'row'));

        $rows = [];
        for ($row = $minRow; $row <= $maxRow; $row++) {
            $rowCells = [];
            $rowEmpty = true;
            for ($col = $minCol; $col <= $maxCol; $col++) {
                $cell = $cells[$col . ':' . $row] ?? null;
                if (is_array($cell) && trim($cell['value']) !== '') {
                    $rowEmpty = false;
                }
                $rowCells[] = $this->tableCell($cell);
            }
            $rows[] = ['empty' => $rowEmpty, 'node' => new AstNode('table_row', [], $rowCells)];
        }

        while ($rows !== [] && (bool) $rows[array_key_last($rows)]['empty']) {
            array_pop($rows);
        }
        if ($rows === []) {
            return null;
        }

        $head = array_shift($rows)['node'];
        $bodyRows = array_map(static fn (array $row): AstNode => $row['node'], $rows);

        return new AstNode('table', [], [
            new AstNode('table_head', [], [$head]),
            new AstNode('table_body', [], $bodyRows),
        ]);
    }

    /**
     * @param array{col:int,row:int,value:string,bold:bool,italic:bool,underline:bool}|null $cell
     */
    private function tableCell(?array $cell): AstNode
    {
        if ($cell === null) {
            return new AstNode('table_cell', ['text' => ''], [new AstNode('plain')]);
        }

        $inlines = $this->cellInlines($cell);

        return new AstNode('table_cell', ['text' => $cell['value']], [new AstNode('plain', ['text' => $cell['value']], $inlines)]);
    }

    /**
     * @param array{value:string,bold:bool,italic:bool,underline:bool} $cell
     * @return list<AstNode>
     */
    private function cellInlines(array $cell): array
    {
        if ($cell['value'] === '') {
            return [];
        }

        $node = new AstNode('text', ['text' => $cell['value']]);
        if ($cell['bold']) {
            $node = new AstNode('strong', [], [$node]);
        }
        if ($cell['italic']) {
            $node = new AstNode('emph', [], [$node]);
        }
        if ($cell['underline']) {
            $node = new AstNode('underline', [], [$node]);
        }

        return [$node];
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function cellValue(\DOMElement $cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $inline = $this->firstChildElementByLocalName($cell, 'is');

            return $inline instanceof \DOMElement ? $this->sharedStringText($inline) : '';
        }

        $value = $this->firstChildElementByLocalName($cell, 'v');
        $text = $value instanceof \DOMElement ? trim($value->textContent) : '';
        if ($type === 's') {
            $index = ctype_digit($text) ? (int) $text : -1;

            return $sharedStrings[$index] ?? '';
        }
        if ($type === 'b') {
            return $text === '1' ? 'TRUE' : 'FALSE';
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $dom = $this->loadXml($xml, 'XLSX sharedStrings.xml');
        $strings = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'si') as $string) {
            if ($string instanceof \DOMElement) {
                $strings[] = $this->sharedStringText($string);
            }
        }

        return $strings;
    }

    private function sharedStringText(\DOMElement $string): string
    {
        $parts = [];
        foreach ($string->getElementsByTagNameNS(self::SS_NS, 't') as $text) {
            if ($text instanceof \DOMElement) {
                $parts[] = $text->textContent;
            }
        }

        return implode('', $parts);
    }

    /**
     * @return array{fonts:list<array{bold:bool,italic:bool,underline:bool}>,cellFonts:array<int,int>}
     */
    private function styles(string $xml): array
    {
        if ($xml === '') {
            return ['fonts' => [], 'cellFonts' => []];
        }
        $dom = $this->loadXml($xml, 'XLSX styles.xml');
        $fonts = [];
        $fontsElement = $dom->getElementsByTagNameNS(self::SS_NS, 'fonts')->item(0);
        if ($fontsElement instanceof \DOMElement) {
            foreach ($fontsElement->childNodes as $font) {
                if (!$font instanceof \DOMElement || $font->localName !== 'font') {
                    continue;
                }
                $fonts[] = [
                    'bold' => $font->getElementsByTagNameNS(self::SS_NS, 'b')->length > 0,
                    'italic' => $font->getElementsByTagNameNS(self::SS_NS, 'i')->length > 0,
                    'underline' => $font->getElementsByTagNameNS(self::SS_NS, 'u')->length > 0,
                ];
            }
        }

        $cellFonts = [];
        $cellFormats = $dom->getElementsByTagNameNS(self::SS_NS, 'cellXfs')->item(0);
        if ($cellFormats instanceof \DOMElement) {
            $index = 0;
            foreach ($cellFormats->childNodes as $format) {
                if (!$format instanceof \DOMElement || $format->localName !== 'xf') {
                    continue;
                }
                if (ctype_digit($format->getAttribute('fontId'))) {
                    $cellFonts[$index] = (int) $format->getAttribute('fontId');
                }
                $index++;
            }
        }

        return ['fonts' => $fonts, 'cellFonts' => $cellFonts];
    }

    /**
     * @param array{fonts:list<array{bold:bool,italic:bool,underline:bool}>,cellFonts:array<int,int>} $styles
     * @return array{bold:bool,italic:bool,underline:bool}
     */
    private function cellFont(?int $styleIndex, array $styles): array
    {
        $fontId = $styleIndex !== null ? ($styles['cellFonts'][$styleIndex] ?? $styleIndex) : null;
        $font = $fontId !== null ? ($styles['fonts'][$fontId] ?? null) : null;
        if (!is_array($font)) {
            return ['bold' => false, 'italic' => false, 'underline' => false];
        }

        return [
            'bold' => (bool) ($font['bold'] ?? false),
            'italic' => (bool) ($font['italic'] ?? false),
            'underline' => (bool) ($font['underline'] ?? false),
        ];
    }

    /**
     * @return array{col:int,row:int,key:string}|null
     */
    private function parseCellRef(string $reference): ?array
    {
        if (preg_match('/^([A-Z]+)([1-9][0-9]*)$/i', $reference, $match) !== 1) {
            return null;
        }

        $col = 0;
        foreach (str_split(strtoupper($match[1])) as $letter) {
            $col = ($col * 26) + (ord($letter) - ord('A') + 1);
        }
        $row = (int) $match[2];

        return ['col' => $col, 'row' => $row, 'key' => $col . ':' . $row];
    }

    /**
     * @return array<string, mixed>
     */
    private function coreProperties(\DOMDocument $dom): array
    {
        $meta = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                continue;
            }
            $key = match ($element->namespaceURI . ' ' . $element->localName) {
                self::DC_NS . ' title' => 'title',
                self::DC_NS . ' creator' => 'author',
                self::DC_NS . ' description' => 'description',
                self::CP_NS . ' keywords' => 'keywords',
                self::DCTERMS_NS . ' created',
                self::DCTERMS_NS . ' modified' => 'date',
                default => '',
            };
            if ($key === '') {
                continue;
            }
            $meta[$key] = $text;
            if ($key === 'title') {
                $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
            }
        }

        return $meta;
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     */
    private function relationshipTargetByType(string $sourcePath, array $relationships, string $typeSuffix): ?string
    {
        foreach ($relationships as $relationship) {
            $type = (string) ($relationship['type'] ?? '');
            $target = (string) ($relationship['target'] ?? '');
            if ($target !== '' && str_ends_with($type, $typeSuffix)) {
                return $this->resolveRelationshipTarget($sourcePath, $target);
            }
        }

        return null;
    }

    /**
     * @return array<string, array{target:string,type:string,mode:string}>
     */
    private function relationships(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $dom = $this->loadXml($xml, 'XLSX relationships');
        $relationships = [];
        foreach ($dom->getElementsByTagNameNS(self::REL_NS, 'Relationship') as $relationship) {
            if (!$relationship instanceof \DOMElement) {
                continue;
            }
            $id = $relationship->getAttribute('Id');
            if ($id === '') {
                continue;
            }
            $relationships[$id] = [
                'target' => $relationship->getAttribute('Target'),
                'type' => $relationship->getAttribute('Type'),
                'mode' => $relationship->getAttribute('TargetMode'),
            ];
        }

        return $relationships;
    }

    private function relationshipsPath(string $partPath): string
    {
        $partPath = ZipOpcPackage::normalizePath($partPath);
        $dir = ZipOpcPackage::dirname($partPath);
        $base = basename($partPath);

        return ($dir === '' ? '' : $dir . '/') . '_rels/' . $base . '.rels';
    }

    private function resolveRelationshipTarget(string $sourcePartPath, string $target): string
    {
        $target = trim($target);
        if ($target === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1 || str_starts_with($target, '//')) {
            return $target;
        }
        if (str_starts_with($target, '/')) {
            return ZipOpcPackage::normalizePath(ltrim($target, '/'));
        }

        return ZipOpcPackage::normalizePath(ZipOpcPackage::dirname($sourcePartPath) . '/' . $target);
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            $message = $errors !== [] ? trim($errors[0]->message) : 'unknown XML parse error';
            throw new \InvalidArgumentException($label . ' is not valid XML: ' . $message);
        }

        return $dom;
    }

    private function firstChildElementByLocalName(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function attr(\DOMElement $element, string $namespace, string $name): string
    {
        if ($element->hasAttributeNS($namespace, $name)) {
            return $element->getAttributeNS($namespace, $name);
        }

        return '';
    }
}
