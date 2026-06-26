<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XlsxReader
{
    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const SS_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const XDR_NS = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
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

            $styles = ['fonts' => [], 'fills' => [], 'borders' => [], 'numFmts' => $this->builtinNumberFormats(), 'cellFonts' => [], 'cellFormats' => []];
            $stylesPath = $this->relationshipTargetByType($workbookPath, $workbookRels, '/styles');
            if ($stylesPath !== null) {
                $styles = $this->styles($package->read($stylesPath) ?? '');
            }
            $date1904 = $this->workbookDate1904($workbookXml);

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
                $sheetRels = $this->relationships($package->read($this->relationshipsPath($sheetPath)) ?? '');
                $sheet = $this->sheet(
                    $sheetXml,
                    $reference['name'],
                    $index + 1,
                    $reference['sheetId'],
                    $reference['state'],
                    $sheetPath,
                    $sheetRels,
                    $sharedStrings,
                    $styles,
                    $date1904,
                    $package
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
                return ZipOpcPackage::normalizePathStrict(ltrim($target, '/'));
            }
        }

        return 'xl/workbook.xml';
    }

    /**
     * @return list<array{name:string,sheetId:string,state:string,relationshipId:string}>
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
                'state' => $sheet->getAttribute('state') !== '' ? $sheet->getAttribute('state') : 'visible',
                'relationshipId' => $relationshipId,
            ];
        }

        return $references;
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @param list<array{text:string,inlines:list<AstNode>}> $sharedStrings
     * @param array<string, mixed> $styles
     */
    private function sheet(
        string $sheetXml,
        string $name,
        int $number,
        string $sheetId,
        string $state,
        string $sheetPath,
        array $relationships,
        array $sharedStrings,
        array $styles,
        bool $date1904,
        ZipOpcPackage $package
    ): AstNode
    {
        $cells = $this->sheetCells($sheetXml, $relationships, $sheetPath, $sharedStrings, $styles, $date1904, $package);
        $figures = $this->worksheetDrawingFigures($sheetXml, $relationships, $sheetPath, $package);
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
        foreach ($figures as $figure) {
            $children[] = $figure;
        }

        return new AstNode('div', [
            'id' => 'sheet-' . $number . '-content',
            'classes' => ['xlsx-sheet'],
            'attributes' => [
                'data-pandoc-source' => 'xlsx',
                'data-xlsx-sheet-number' => (string) $number,
                'data-xlsx-sheet-id' => $sheetId,
                'data-xlsx-sheet-state' => $state,
                'data-xlsx-sheet-path' => $sheetPath,
            ],
        ], $children);
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @param list<array{text:string,inlines:list<AstNode>}> $sharedStrings
     * @param array<string, mixed> $styles
     * @return array<string, array<string, mixed>>
     */
    private function sheetCells(
        string $sheetXml,
        array $relationships,
        string $sheetPath,
        array $sharedStrings,
        array $styles,
        bool $date1904,
        ZipOpcPackage $package
    ): array
    {
        $dom = $this->loadXml($sheetXml, 'XLSX worksheet');
        $hyperlinks = $this->worksheetHyperlinks($dom, $relationships, $sheetPath);
        $merges = $this->worksheetMerges($dom);
        $comments = $this->worksheetComments($relationships, $sheetPath, $package);
        $columns = $this->worksheetColumns($dom);
        $rows = $this->worksheetRows($dom);
        $cells = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'c') as $cell) {
            if (!$cell instanceof \DOMElement) {
                continue;
            }
            $ref = $this->parseCellRef($cell->getAttribute('r'));
            if ($ref === null) {
                continue;
            }
            $styleIndex = ctype_digit($cell->getAttribute('s'))
                ? (int) $cell->getAttribute('s')
                : ($rows[$ref['row']]['styleIndex'] ?? $columns[$ref['col']]['styleIndex'] ?? null);
            $style = $this->cellStyle($styleIndex, $styles);
            $content = $this->cellContent($cell, $sharedStrings, $style, $date1904);
            $merge = $merges['topLeft'][$ref['key']] ?? ['colspan' => 1, 'rowspan' => 1];
            $link = $hyperlinks[$ref['key']] ?? null;
            $comment = $comments[$ref['key']] ?? null;
            if (
                $content['text'] === ''
                && !$style['bold']
                && !$style['italic']
                && !$style['underline']
                && !$style['strike']
                && $style['fillColor'] === ''
                && !is_array($link)
                && !is_array($comment)
                && $merge['colspan'] === 1
                && $merge['rowspan'] === 1
            ) {
                continue;
            }
            $cells[$ref['key']] = [
                'col' => $ref['col'],
                'row' => $ref['row'],
                'ref' => $cell->getAttribute('r'),
                'columnName' => $this->columnName($ref['col']),
                'value' => $content['text'],
                'rawValue' => $content['raw'],
                'valueType' => $content['type'],
                'inlines' => $content['inlines'],
                'style' => $style,
                'styleIndex' => $styleIndex,
                'rowHidden' => (bool) ($rows[$ref['row']]['hidden'] ?? false),
                'rowHeight' => (string) ($rows[$ref['row']]['height'] ?? ''),
                'columnHidden' => (bool) ($columns[$ref['col']]['hidden'] ?? false),
                'columnWidth' => (string) ($columns[$ref['col']]['width'] ?? ''),
                'colspan' => $merge['colspan'],
                'rowspan' => $merge['rowspan'],
                'url' => is_array($link) ? (string) ($link['url'] ?? '') : '',
                'title' => is_array($link) ? (string) ($link['title'] ?? '') : '',
                'comment' => is_array($comment) ? (string) ($comment['text'] ?? '') : '',
                'commentAuthor' => is_array($comment) ? (string) ($comment['author'] ?? '') : '',
            ];
        }

        foreach ($merges['topLeft'] as $key => $merge) {
            if (!isset($cells[$key])) {
                $ref = $this->parseCellRef((string) ($merge['ref'] ?? ''));
                if ($ref !== null) {
                    $cells[$key] = [
                        'col' => $ref['col'],
                        'row' => $ref['row'],
                        'value' => '',
                        'rawValue' => '',
                        'valueType' => 'empty',
                        'inlines' => [],
                        'style' => $this->defaultCellStyle(),
                        'colspan' => $merge['colspan'],
                        'rowspan' => $merge['rowspan'],
                        'url' => '',
                        'title' => '',
                    ];
                }
            }
        }
        foreach ($merges['covered'] as $key => $covered) {
            if ($covered !== true) {
                continue;
            }
            if (isset($cells[$key])) {
                $cells[$key]['covered'] = true;
                continue;
            }
            [$col, $row] = array_map('intval', explode(':', $key, 2));
            $cells[$key] = [
                'col' => $col,
                'row' => $row,
                'value' => '',
                'rawValue' => '',
                'valueType' => 'empty',
                'inlines' => [],
                'style' => $this->defaultCellStyle(),
                'covered' => true,
            ];
        }

        return $cells;
    }

    /**
     * @param array<string, array<string, mixed>> $cells
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
                if (is_array($cell) && ($cell['covered'] ?? false) === true) {
                    continue;
                }
                if (is_array($cell) && trim($cell['value']) !== '') {
                    $rowEmpty = false;
                }
                $rowCells[] = $this->tableCell($cell);
            }
            $rows[] = ['empty' => $rowEmpty, 'node' => new AstNode('table_row', [
                'htmlAttributes' => $this->rowHtmlAttributes($row, $cells),
            ], $rowCells)];
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
     * @param array<string, mixed>|null $cell
     */
    private function tableCell(?array $cell): AstNode
    {
        if ($cell === null) {
            return new AstNode('table_cell', ['text' => ''], [new AstNode('plain')]);
        }

        $inlines = $this->cellInlines($cell);
        $attrs = [
            'text' => (string) $cell['value'],
            'colspan' => max(1, (int) ($cell['colspan'] ?? 1)),
            'rowspan' => max(1, (int) ($cell['rowspan'] ?? 1)),
        ];
        $url = (string) ($cell['url'] ?? '');
        $htmlAttributes = $this->cellHtmlAttributes($cell);
        if ($url !== '') {
            $htmlAttributes['data-xlsx-hyperlink'] = $url;
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return new AstNode('table_cell', $attrs, [new AstNode('plain', ['text' => (string) $cell['value']], $inlines)]);
    }

    /**
     * @param array<string, mixed> $cell
     * @return list<AstNode>
     */
    private function cellInlines(array $cell): array
    {
        $inlines = is_array($cell['inlines'] ?? null) ? $cell['inlines'] : [];
        if ($inlines === [] && (string) ($cell['value'] ?? '') !== '') {
            $inlines = [new AstNode('text', ['text' => (string) $cell['value']])];
        }

        $url = (string) ($cell['url'] ?? '');
        if ($url !== '' && $inlines !== []) {
            $inlines = [new AstNode('link', [
                'url' => $url,
                'title' => (string) ($cell['title'] ?? ''),
            ], $inlines)];
        }

        return $inlines;
    }

    /**
     * @param list<array{text:string,inlines:list<AstNode>}> $sharedStrings
     * @param array<string, mixed> $style
     * @return array{text:string,raw:string,type:string,inlines:list<AstNode>}
     */
    private function cellContent(\DOMElement $cell, array $sharedStrings, array $style, bool $date1904): array
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $inline = $this->firstChildElementByLocalName($cell, 'is');

            $item = $inline instanceof \DOMElement
                ? $this->stringItem($inline, $style)
                : ['text' => '', 'inlines' => []];

            return ['text' => $item['text'], 'raw' => $item['text'], 'type' => 'string', 'inlines' => $item['inlines']];
        }

        $value = $this->firstChildElementByLocalName($cell, 'v');
        $text = $value instanceof \DOMElement ? trim($value->textContent) : '';
        if ($type === 's') {
            $index = ctype_digit($text) ? (int) $text : -1;

            $item = $sharedStrings[$index] ?? ['text' => '', 'inlines' => []];
            $item['inlines'] = $this->applyFontToInlines($item['inlines'], $style);

            return ['text' => $item['text'], 'raw' => $text, 'type' => 'string', 'inlines' => $item['inlines']];
        }
        if ($type === 'b') {
            $text = $text === '1' ? 'TRUE' : 'FALSE';

            return ['text' => $text, 'raw' => $text, 'type' => 'boolean', 'inlines' => $this->applyFontToInlines([new AstNode('text', ['text' => $text])], $style)];
        }

        $display = $text;
        $valueType = $text === '' ? 'empty' : 'number';
        if ($text !== '' && is_numeric($text)) {
            if ((bool) ($style['isDate'] ?? false)) {
                $display = $this->formatSerialDate((float) $text, $date1904, (bool) ($style['isDateTime'] ?? false));
                $valueType = (bool) ($style['isDateTime'] ?? false) ? 'datetime' : 'date';
            } else {
                $display = $this->formatNumberValue((float) $text, (string) ($style['numFmtCode'] ?? ''));
            }
        }

        return [
            'text' => $display,
            'raw' => $text,
            'type' => $valueType,
            'inlines' => $this->applyFontToInlines($display === '' ? [] : [new AstNode('text', ['text' => $display])], $style),
        ];
    }

    /**
     * @return list<array{text:string,inlines:list<AstNode>}>
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
                $strings[] = $this->stringItem($string);
            }
        }

        return $strings;
    }

    /**
     * @param array<string, mixed>|null $fallbackFont
     * @return array{text:string,inlines:list<AstNode>}
     */
    private function stringItem(\DOMElement $string, ?array $fallbackFont = null): array
    {
        $inlines = [];
        foreach ($string->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 't') {
                $text = $child->textContent;
                if ($text !== '') {
                    $nodes = [new AstNode('text', ['text' => $text])];
                    $inlines = array_merge($inlines, $fallbackFont !== null ? $this->applyFontToInlines($nodes, $fallbackFont) : $nodes);
                }
                continue;
            }
            if ($child->localName === 'r') {
                $text = '';
                foreach ($child->getElementsByTagNameNS(self::SS_NS, 't') as $runText) {
                    if ($runText instanceof \DOMElement) {
                        $text .= $runText->textContent;
                    }
                }
                if ($text === '') {
                    continue;
                }
                $runFont = $this->richTextRunFont($child, $fallbackFont);
                $inlines = array_merge($inlines, $this->applyFontToInlines([new AstNode('text', ['text' => $text])], $runFont));
            }
        }
        if ($inlines === []) {
            foreach ($string->getElementsByTagNameNS(self::SS_NS, 't') as $text) {
                if ($text instanceof \DOMElement && $text->textContent !== '') {
                    $inlines[] = new AstNode('text', ['text' => $text->textContent]);
                }
            }
            if ($fallbackFont !== null) {
                $inlines = $this->applyFontToInlines($inlines, $fallbackFont);
            }
        }

        return ['text' => $this->plainText($inlines), 'inlines' => $inlines];
    }

    /**
     * @param array<string, mixed>|null $fallbackFont
     * @return array<string, mixed>
     */
    private function richTextRunFont(\DOMElement $run, ?array $fallbackFont): array
    {
        $font = $fallbackFont ?? $this->defaultCellStyle();
        $properties = $this->firstChildElementByLocalName($run, 'rPr');
        if (!$properties instanceof \DOMElement) {
            return $font;
        }

        return [
            'bold' => $properties->getElementsByTagNameNS(self::SS_NS, 'b')->length > 0,
            'italic' => $properties->getElementsByTagNameNS(self::SS_NS, 'i')->length > 0,
            'underline' => $properties->getElementsByTagNameNS(self::SS_NS, 'u')->length > 0,
            'strike' => $properties->getElementsByTagNameNS(self::SS_NS, 'strike')->length > 0,
        ];
    }

    /**
     * @param list<AstNode> $inlines
     * @param array<string, mixed> $font
     * @return list<AstNode>
     */
    private function applyFontToInlines(array $inlines, array $font): array
    {
        if ($inlines === []) {
            return [];
        }
        $bold = (bool) ($font['bold'] ?? false);
        $italic = (bool) ($font['italic'] ?? false);
        $underline = (bool) ($font['underline'] ?? false);
        $strike = (bool) ($font['strike'] ?? false);
        if (!$bold && !$italic && !$underline && !$strike) {
            return $inlines;
        }

        $node = count($inlines) === 1 ? $inlines[0] : new AstNode('span', [], $inlines);
        if ($bold) {
            $node = new AstNode('strong', [], [$node]);
        }
        if ($italic) {
            $node = new AstNode('emph', [], [$node]);
        }
        if ($underline) {
            $node = new AstNode('underline', [], [$node]);
        }
        if ($strike) {
            $node = new AstNode('strikeout', [], [$node]);
        }

        return [$node];
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainText(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            $text .= match ($inline->type) {
                'text', 'code' => (string) $inline->attr('text', ''),
                'linebreak', 'softbreak' => ' ',
                'image' => (string) $inline->attr('alt', ''),
                default => $this->plainText($inline->children),
            };
        }

        return $text;
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return array<string, array{url:string,title:string}>
     */
    private function worksheetHyperlinks(\DOMDocument $dom, array $relationships, string $sheetPath): array
    {
        $hyperlinks = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'hyperlink') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            $ref = $link->getAttribute('ref');
            if ($ref === '') {
                continue;
            }
            $url = '';
            $rid = $this->attr($link, self::R_NS, 'id');
            if ($rid !== '') {
                $relationship = $relationships[$rid] ?? null;
                if (is_array($relationship)) {
                    $url = (string) ($relationship['target'] ?? '');
                    if (($relationship['mode'] ?? '') !== 'External' && $url !== '') {
                        $url = $this->resolveRelationshipTarget($sheetPath, $url);
                    }
                }
            }
            $location = $link->getAttribute('location');
            if ($url === '' && $location !== '') {
                $url = '#' . ltrim($location, '#');
            } elseif ($url !== '' && $location !== '') {
                $url .= '#' . ltrim($location, '#');
            }
            if ($url === '') {
                continue;
            }
            foreach ($this->cellRangeKeys($ref) as $key) {
                $hyperlinks[$key] = [
                    'url' => $url,
                    'title' => $link->getAttribute('tooltip'),
                ];
            }
        }

        return $hyperlinks;
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return array<string, array{text:string,author:string}>
     */
    private function worksheetComments(array $relationships, string $sheetPath, ZipOpcPackage $package): array
    {
        $commentsPath = $this->relationshipTargetByType($sheetPath, $relationships, '/comments');
        if ($commentsPath === null) {
            return [];
        }
        $xml = $package->read($commentsPath);
        if (!is_string($xml) || $xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, 'XLSX comments');
        $authors = [];
        $authorsElement = $dom->getElementsByTagNameNS(self::SS_NS, 'authors')->item(0);
        if ($authorsElement instanceof \DOMElement) {
            foreach ($authorsElement->getElementsByTagNameNS(self::SS_NS, 'author') as $author) {
                if ($author instanceof \DOMElement) {
                    $authors[] = trim($author->textContent);
                }
            }
        }

        $comments = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'comment') as $comment) {
            if (!$comment instanceof \DOMElement) {
                continue;
            }
            $ref = $this->parseCellRef($comment->getAttribute('ref'));
            if ($ref === null) {
                continue;
            }
            $text = '';
            foreach ($comment->getElementsByTagNameNS(self::SS_NS, 't') as $textNode) {
                if ($textNode instanceof \DOMElement) {
                    $text .= $textNode->textContent;
                }
            }
            $authorId = ctype_digit($comment->getAttribute('authorId')) ? (int) $comment->getAttribute('authorId') : -1;
            $comments[$ref['key']] = [
                'text' => trim(preg_replace('/\s+/u', ' ', $text) ?? $text),
                'author' => $authors[$authorId] ?? '',
            ];
        }

        return $comments;
    }

    /**
     * @return array<int, array{hidden:bool,width:string,styleIndex:int|null}>
     */
    private function worksheetColumns(\DOMDocument $dom): array
    {
        $columns = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'col') as $column) {
            if (!$column instanceof \DOMElement) {
                continue;
            }
            $min = ctype_digit($column->getAttribute('min')) ? (int) $column->getAttribute('min') : 0;
            $max = ctype_digit($column->getAttribute('max')) ? (int) $column->getAttribute('max') : $min;
            if ($min < 1 || $max < $min) {
                continue;
            }
            $meta = [
                'hidden' => in_array(strtolower($column->getAttribute('hidden')), ['1', 'true'], true),
                'width' => $column->getAttribute('width'),
                'styleIndex' => ctype_digit($column->getAttribute('style')) ? (int) $column->getAttribute('style') : null,
            ];
            for ($index = $min; $index <= $max; $index++) {
                $columns[$index] = $meta;
            }
        }

        return $columns;
    }

    /**
     * @return array<int, array{hidden:bool,height:string,styleIndex:int|null}>
     */
    private function worksheetRows(\DOMDocument $dom): array
    {
        $rows = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'row') as $row) {
            if (!$row instanceof \DOMElement || !ctype_digit($row->getAttribute('r'))) {
                continue;
            }
            $rows[(int) $row->getAttribute('r')] = [
                'hidden' => in_array(strtolower($row->getAttribute('hidden')), ['1', 'true'], true),
                'height' => $row->getAttribute('ht'),
                'styleIndex' => ctype_digit($row->getAttribute('s')) ? (int) $row->getAttribute('s') : null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return list<AstNode>
     */
    private function worksheetDrawingFigures(string $sheetXml, array $relationships, string $sheetPath, ZipOpcPackage $package): array
    {
        $dom = $this->loadXml($sheetXml, 'XLSX worksheet');
        $figures = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'drawing') as $drawing) {
            if (!$drawing instanceof \DOMElement) {
                continue;
            }
            $relationshipId = $this->attr($drawing, self::R_NS, 'id');
            $relationship = $relationships[$relationshipId] ?? null;
            if (!is_array($relationship) || ($relationship['target'] ?? '') === '' || ($relationship['mode'] ?? '') === 'External') {
                continue;
            }
            $drawingPath = $this->resolveRelationshipTarget($sheetPath, (string) $relationship['target']);
            $drawingXml = $package->read($drawingPath);
            if (!is_string($drawingXml) || $drawingXml === '') {
                continue;
            }
            $drawingRels = $this->relationships($package->read($this->relationshipsPath($drawingPath)) ?? '');
            foreach ($this->drawingFigures($drawingXml, $drawingPath, $drawingRels) as $figure) {
                $figures[] = $figure;
            }
        }

        return $figures;
    }

    /**
     * @param array<string, array{target:string,type:string,mode:string}> $relationships
     * @return list<AstNode>
     */
    private function drawingFigures(string $drawingXml, string $drawingPath, array $relationships): array
    {
        $dom = $this->loadXml($drawingXml, 'XLSX drawing');
        $figures = [];
        foreach ($dom->getElementsByTagNameNS(self::XDR_NS, 'pic') as $picture) {
            if (!$picture instanceof \DOMElement) {
                continue;
            }
            $properties = $this->firstDescendantElementByLocalName($picture, 'cNvPr');
            $name = $properties instanceof \DOMElement ? $properties->getAttribute('name') : '';
            $description = $properties instanceof \DOMElement ? $properties->getAttribute('descr') : '';
            $blip = $this->firstDescendantElementByLocalName($picture, 'blip');
            if (!$blip instanceof \DOMElement) {
                continue;
            }
            $relationshipId = $this->attr($blip, self::R_NS, 'embed');
            if ($relationshipId === '') {
                $relationshipId = $this->attr($blip, self::R_NS, 'link');
            }
            $relationship = $relationships[$relationshipId] ?? null;
            if (!is_array($relationship) || ($relationship['target'] ?? '') === '') {
                continue;
            }
            $url = (string) $relationship['target'];
            if (($relationship['mode'] ?? '') !== 'External') {
                $url = $this->resolveRelationshipTarget($drawingPath, $url);
            }
            if ($url === '') {
                continue;
            }

            $imageAttributes = array_merge([
                'data-pandoc-source' => 'xlsx',
                'data-xlsx-drawing-path' => $drawingPath,
                'data-xlsx-relationship-id' => $relationshipId,
            ], $this->drawingAnchorAttributes($picture));

            $image = new AstNode('image', [
                'url' => $url,
                'alt' => $description !== '' ? $description : $name,
                'title' => $name,
                'attributes' => $imageAttributes,
            ]);
            $figures[] = new AstNode('figure', [
                'classes' => ['xlsx-image'],
                'caption' => '',
            ], [$image]);
        }

        return $figures;
    }

    /**
     * @return array<string, string>
     */
    private function drawingAnchorAttributes(\DOMElement $picture): array
    {
        $anchor = $picture->parentNode;
        while ($anchor instanceof \DOMElement) {
            if ($anchor->namespaceURI === self::XDR_NS && in_array($anchor->localName, ['oneCellAnchor', 'twoCellAnchor', 'absoluteAnchor'], true)) {
                break;
            }
            $anchor = $anchor->parentNode;
        }
        if (!$anchor instanceof \DOMElement) {
            return [];
        }

        $attrs = ['data-xlsx-anchor-type' => $anchor->localName];
        if ($anchor->hasAttribute('editAs')) {
            $attrs['data-xlsx-anchor-edit-as'] = $anchor->getAttribute('editAs');
        }
        $from = $this->firstChildElementByLocalName($anchor, 'from');
        if ($from instanceof \DOMElement) {
            $col = $this->integerChildText($from, 'col');
            $row = $this->integerChildText($from, 'row');
            if ($col !== null && $row !== null) {
                $attrs['data-xlsx-anchor'] = $this->columnName($col + 1) . (string) ($row + 1);
                $attrs['data-xlsx-anchor-column'] = (string) ($col + 1);
                $attrs['data-xlsx-anchor-row'] = (string) ($row + 1);
            }
        }
        $to = $this->firstChildElementByLocalName($anchor, 'to');
        if ($to instanceof \DOMElement) {
            $col = $this->integerChildText($to, 'col');
            $row = $this->integerChildText($to, 'row');
            if ($col !== null && $row !== null) {
                $attrs['data-xlsx-anchor-to'] = $this->columnName($col + 1) . (string) ($row + 1);
                $attrs['data-xlsx-anchor-to-column'] = (string) ($col + 1);
                $attrs['data-xlsx-anchor-to-row'] = (string) ($row + 1);
            }
        }
        $extent = $this->firstChildElementByLocalName($anchor, 'ext');
        if ($extent instanceof \DOMElement) {
            if (ctype_digit($extent->getAttribute('cx'))) {
                $attrs['data-xlsx-extent-cx'] = $extent->getAttribute('cx');
            }
            if (ctype_digit($extent->getAttribute('cy'))) {
                $attrs['data-xlsx-extent-cy'] = $extent->getAttribute('cy');
            }
        }

        return $attrs;
    }

    /**
     * @return array{topLeft: array<string, array{ref:string,colspan:int,rowspan:int}>, covered: array<string, bool>}
     */
    private function worksheetMerges(\DOMDocument $dom): array
    {
        $topLeft = [];
        $covered = [];
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'mergeCell') as $mergeCell) {
            if (!$mergeCell instanceof \DOMElement) {
                continue;
            }
            $ref = $mergeCell->getAttribute('ref');
            $range = $this->cellRange($ref);
            if ($range === null) {
                continue;
            }
            $colspan = $range['endCol'] - $range['startCol'] + 1;
            $rowspan = $range['endRow'] - $range['startRow'] + 1;
            if ($colspan < 2 && $rowspan < 2) {
                continue;
            }
            $key = $range['startCol'] . ':' . $range['startRow'];
            $topLeft[$key] = [
                'ref' => $range['startRef'],
                'colspan' => $colspan,
                'rowspan' => $rowspan,
            ];
            for ($row = $range['startRow']; $row <= $range['endRow']; $row++) {
                for ($col = $range['startCol']; $col <= $range['endCol']; $col++) {
                    $cellKey = $col . ':' . $row;
                    if ($cellKey !== $key) {
                        $covered[$cellKey] = true;
                    }
                }
            }
        }

        return ['topLeft' => $topLeft, 'covered' => $covered];
    }

    /**
     * @return list<string>
     */
    private function cellRangeKeys(string $reference): array
    {
        $range = $this->cellRange($reference);
        if ($range === null) {
            return [];
        }

        $keys = [];
        for ($row = $range['startRow']; $row <= $range['endRow']; $row++) {
            for ($col = $range['startCol']; $col <= $range['endCol']; $col++) {
                $keys[] = $col . ':' . $row;
            }
        }

        return $keys;
    }

    /**
     * @return array{startCol:int,startRow:int,endCol:int,endRow:int,startRef:string}|null
     */
    private function cellRange(string $reference): ?array
    {
        $parts = explode(':', $reference, 2);
        $start = $this->parseCellRef($parts[0] ?? '');
        $end = $this->parseCellRef($parts[1] ?? ($parts[0] ?? ''));
        if ($start === null || $end === null) {
            return null;
        }

        return [
            'startCol' => min($start['col'], $end['col']),
            'startRow' => min($start['row'], $end['row']),
            'endCol' => max($start['col'], $end['col']),
            'endRow' => max($start['row'], $end['row']),
            'startRef' => $parts[0],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function styles(string $xml): array
    {
        if ($xml === '') {
            return ['fonts' => [], 'fills' => [], 'borders' => [], 'numFmts' => $this->builtinNumberFormats(), 'cellFonts' => [], 'cellFormats' => []];
        }
        $dom = $this->loadXml($xml, 'XLSX styles.xml');
        $numFmts = $this->builtinNumberFormats();
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'numFmt') as $numFmt) {
            if (!$numFmt instanceof \DOMElement || !ctype_digit($numFmt->getAttribute('numFmtId'))) {
                continue;
            }
            $numFmts[(int) $numFmt->getAttribute('numFmtId')] = $numFmt->getAttribute('formatCode');
        }

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
                    'strike' => $font->getElementsByTagNameNS(self::SS_NS, 'strike')->length > 0,
                    'color' => $this->firstColor($font),
                    'name' => $this->firstVal($font, 'name'),
                    'size' => $this->firstVal($font, 'sz'),
                ];
            }
        }

        $fills = [];
        $fillsElement = $dom->getElementsByTagNameNS(self::SS_NS, 'fills')->item(0);
        if ($fillsElement instanceof \DOMElement) {
            foreach ($fillsElement->childNodes as $fill) {
                if (!$fill instanceof \DOMElement || $fill->localName !== 'fill') {
                    continue;
                }
                $fills[] = [
                    'color' => $this->fillColor($fill),
                ];
            }
        }

        $borders = [];
        $bordersElement = $dom->getElementsByTagNameNS(self::SS_NS, 'borders')->item(0);
        if ($bordersElement instanceof \DOMElement) {
            foreach ($bordersElement->childNodes as $border) {
                if (!$border instanceof \DOMElement || $border->localName !== 'border') {
                    continue;
                }
                $borders[] = $this->borderStyle($border);
            }
        }

        $cellFonts = [];
        $cellFormats = [];
        $cellFormatsElement = $dom->getElementsByTagNameNS(self::SS_NS, 'cellXfs')->item(0);
        if ($cellFormatsElement instanceof \DOMElement) {
            $index = 0;
            foreach ($cellFormatsElement->childNodes as $format) {
                if (!$format instanceof \DOMElement || $format->localName !== 'xf') {
                    continue;
                }
                $fontId = ctype_digit($format->getAttribute('fontId')) ? (int) $format->getAttribute('fontId') : null;
                $fillId = ctype_digit($format->getAttribute('fillId')) ? (int) $format->getAttribute('fillId') : null;
                $borderId = ctype_digit($format->getAttribute('borderId')) ? (int) $format->getAttribute('borderId') : null;
                $numFmtId = ctype_digit($format->getAttribute('numFmtId')) ? (int) $format->getAttribute('numFmtId') : 0;
                if (ctype_digit($format->getAttribute('fontId'))) {
                    $cellFonts[$index] = $fontId ?? 0;
                }
                $alignment = $this->firstChildElementByLocalName($format, 'alignment');
                $cellFormats[$index] = [
                    'fontId' => $fontId,
                    'fillId' => $fillId,
                    'borderId' => $borderId,
                    'numFmtId' => $numFmtId,
                    'numFmtCode' => $numFmts[$numFmtId] ?? '',
                    'horizontal' => $alignment instanceof \DOMElement ? $alignment->getAttribute('horizontal') : '',
                    'vertical' => $alignment instanceof \DOMElement ? $alignment->getAttribute('vertical') : '',
                ];
                $index++;
            }
        }

        return [
            'fonts' => $fonts,
            'fills' => $fills,
            'borders' => $borders,
            'numFmts' => $numFmts,
            'cellFonts' => $cellFonts,
            'cellFormats' => $cellFormats,
        ];
    }

    /**
     * @param array<string, mixed> $styles
     * @return array<string, mixed>
     */
    private function cellStyle(?int $styleIndex, array $styles): array
    {
        $format = $styleIndex !== null ? ($styles['cellFormats'][$styleIndex] ?? []) : [];
        $fontId = is_array($format) ? ($format['fontId'] ?? null) : null;
        if ($fontId === null && $styleIndex !== null) {
            $fontId = $styles['cellFonts'][$styleIndex] ?? $styleIndex;
        }
        $font = $fontId !== null ? ($styles['fonts'][$fontId] ?? null) : null;
        $fillId = is_array($format) ? ($format['fillId'] ?? null) : null;
        $fill = $fillId !== null ? ($styles['fills'][$fillId] ?? null) : null;
        $borderId = is_array($format) ? ($format['borderId'] ?? null) : null;
        $border = $borderId !== null ? ($styles['borders'][$borderId] ?? null) : null;
        $numFmtCode = is_array($format) ? (string) ($format['numFmtCode'] ?? '') : '';
        $style = $this->defaultCellStyle();
        $style['styleIndex'] = $styleIndex;
        $style['numFmtId'] = is_array($format) ? (int) ($format['numFmtId'] ?? 0) : 0;
        $style['numFmtCode'] = $numFmtCode;
        $style['isDate'] = $this->isDateNumberFormat($numFmtCode, $style['numFmtId']);
        $style['isDateTime'] = $style['isDate'] && $this->isDateTimeNumberFormat($numFmtCode, $style['numFmtId']);
        $style['horizontal'] = is_array($format) ? (string) ($format['horizontal'] ?? '') : '';
        $style['vertical'] = is_array($format) ? (string) ($format['vertical'] ?? '') : '';
        if (is_array($fill)) {
            $style['fillColor'] = (string) ($fill['color'] ?? '');
        }
        if (is_array($border)) {
            $style['border'] = (string) ($border['css'] ?? '');
        }
        if (!is_array($font)) {
            return $style;
        }

        return array_replace($style, [
            'bold' => (bool) ($font['bold'] ?? false),
            'italic' => (bool) ($font['italic'] ?? false),
            'underline' => (bool) ($font['underline'] ?? false),
            'strike' => (bool) ($font['strike'] ?? false),
            'color' => (string) ($font['color'] ?? ''),
            'fontName' => (string) ($font['name'] ?? ''),
            'fontSize' => (string) ($font['size'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultCellStyle(): array
    {
        return [
            'styleIndex' => null,
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'color' => '',
            'fontName' => '',
            'fontSize' => '',
            'fillColor' => '',
            'border' => '',
            'numFmtId' => 0,
            'numFmtCode' => '',
            'isDate' => false,
            'isDateTime' => false,
            'horizontal' => '',
            'vertical' => '',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function builtinNumberFormats(): array
    {
        return [
            0 => 'General',
            1 => '0',
            2 => '0.00',
            3 => '#,##0',
            4 => '#,##0.00',
            9 => '0%',
            10 => '0.00%',
            11 => '0.00E+00',
            12 => '# ?/?',
            13 => '# ??/??',
            14 => 'm/d/yy',
            15 => 'd-mmm-yy',
            16 => 'd-mmm',
            17 => 'mmm-yy',
            18 => 'h:mm AM/PM',
            19 => 'h:mm:ss AM/PM',
            20 => 'h:mm',
            21 => 'h:mm:ss',
            22 => 'm/d/yy h:mm',
            37 => '#,##0 ;(#,##0)',
            38 => '#,##0 ;[Red](#,##0)',
            39 => '#,##0.00;(#,##0.00)',
            40 => '#,##0.00;[Red](#,##0.00)',
            45 => 'mm:ss',
            46 => '[h]:mm:ss',
            47 => 'mmss.0',
            49 => '@',
        ];
    }

    private function isDateNumberFormat(string $formatCode, int $numFmtId): bool
    {
        if (in_array($numFmtId, [14, 15, 16, 17, 18, 19, 20, 21, 22, 27, 30, 36, 45, 46, 47], true)) {
            return true;
        }
        $normalized = strtolower($this->stripNumberFormatLiterals($formatCode));

        return preg_match('/[ymdhHs]/', $normalized) === 1 && preg_match('/[0#?]/', $normalized) !== 1;
    }

    private function isDateTimeNumberFormat(string $formatCode, int $numFmtId): bool
    {
        if (in_array($numFmtId, [18, 19, 20, 21, 22, 45, 46, 47], true)) {
            return true;
        }
        $normalized = strtolower($this->stripNumberFormatLiterals($formatCode));

        return str_contains($normalized, 'h') || str_contains($normalized, 's');
    }

    private function stripNumberFormatLiterals(string $formatCode): string
    {
        $formatCode = preg_replace('/"[^"]*"/u', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\[[^\]]+\]/u', '', $formatCode) ?? $formatCode;
        $formatCode = preg_replace('/\\\\./u', '', $formatCode) ?? $formatCode;

        return $formatCode;
    }

    private function formatSerialDate(float $serial, bool $date1904, bool $dateTime): string
    {
        $days = (int) floor($serial);
        $fraction = $serial - $days;
        $base = $date1904
            ? new \DateTimeImmutable('1904-01-01 00:00:00', new \DateTimeZone('UTC'))
            : new \DateTimeImmutable('1899-12-31 00:00:00', new \DateTimeZone('UTC'));
        if (!$date1904 && $days > 59) {
            $days--;
        }
        $seconds = (int) round($fraction * 86400);
        $date = $base->modify('+' . $days . ' days')->modify('+' . $seconds . ' seconds');

        return $dateTime ? $date->format('Y-m-d H:i:s') : $date->format('Y-m-d');
    }

    private function formatNumberValue(float $value, string $formatCode): string
    {
        $format = $this->stripNumberFormatLiterals($formatCode);
        $negative = $value < 0;
        $absolute = abs($value);
        $currency = $this->currencySymbol($formatCode);
        $parenthesizedNegative = $negative && str_contains($format, '(') && str_contains($format, ')');
        if (preg_match('/[0#?]\s+\\?\/\s*[0#?]/', $format) === 1 && $absolute > 0) {
            return ($negative ? '-' : '') . $this->formatFraction($absolute, str_contains($format, '??') ? 99 : 9);
        }
        if (str_contains($format, '%')) {
            $decimals = $this->decimalPlaces($format);

            return number_format($value * 100, $decimals, '.', '') . '%';
        }
        if (stripos($format, 'E+') !== false || stripos($format, 'E-') !== false) {
            $decimals = $this->decimalPlaces($format);

            return sprintf('%.' . $decimals . 'E', $value);
        }
        if (str_contains($format, '#,##')) {
            $formatted = $currency . number_format($absolute, $this->decimalPlaces($format), '.', ',');

            return $parenthesizedNegative ? '(' . $formatted . ')' : ($negative ? '-' . $formatted : $formatted);
        }
        if (preg_match('/0\.([0]+)/', $format, $match) === 1) {
            $formatted = $currency . number_format($absolute, strlen($match[1]), '.', '');

            return $parenthesizedNegative ? '(' . $formatted . ')' : ($negative ? '-' . $formatted : $formatted);
        }

        return (string) (int) $value === (string) $value ? (string) (int) $value : rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
    }

    private function currencySymbol(string $formatCode): string
    {
        if (str_contains($formatCode, '$')) {
            return '$';
        }
        if (str_contains($formatCode, '€')) {
            return '€';
        }
        if (str_contains($formatCode, '£')) {
            return '£';
        }
        if (str_contains($formatCode, '¥')) {
            return '¥';
        }

        return '';
    }

    private function formatFraction(float $value, int $maxDenominator): string
    {
        $whole = (int) floor($value);
        $fraction = $value - $whole;
        if ($fraction <= 0.0000001) {
            return (string) $whole;
        }

        $bestNumerator = 0;
        $bestDenominator = 1;
        $bestDelta = PHP_FLOAT_MAX;
        for ($denominator = 1; $denominator <= $maxDenominator; $denominator++) {
            $numerator = (int) round($fraction * $denominator);
            $delta = abs($fraction - ($numerator / $denominator));
            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $bestNumerator = $numerator;
                $bestDenominator = $denominator;
            }
        }
        if ($bestNumerator === 0) {
            return (string) $whole;
        }
        if ($bestNumerator === $bestDenominator) {
            return (string) ($whole + 1);
        }

        return ($whole > 0 ? $whole . ' ' : '') . $bestNumerator . '/' . $bestDenominator;
    }

    private function decimalPlaces(string $formatCode): int
    {
        if (preg_match('/\.([0#]+)/', $formatCode, $match) !== 1) {
            return 0;
        }

        return strlen($match[1]);
    }

    private function cellHtmlAttributes(array $cell): array
    {
        $style = is_array($cell['style'] ?? null) ? $cell['style'] : $this->defaultCellStyle();
        $attrs = array_filter([
            'data-xlsx-ref' => (string) ($cell['ref'] ?? ''),
            'data-xlsx-row' => (string) ($cell['row'] ?? ''),
            'data-xlsx-column' => (string) ($cell['columnName'] ?? ''),
            'data-xlsx-raw-value' => (string) ($cell['rawValue'] ?? ''),
            'data-xlsx-value-type' => (string) ($cell['valueType'] ?? ''),
            'data-xlsx-number-format' => (string) ($style['numFmtCode'] ?? '') !== 'General' ? (string) ($style['numFmtCode'] ?? '') : '',
            'data-xlsx-style-index' => ($cell['styleIndex'] ?? null) !== null ? (string) $cell['styleIndex'] : '',
            'data-xlsx-font-name' => (string) ($style['fontName'] ?? ''),
            'data-xlsx-font-size' => (string) ($style['fontSize'] ?? ''),
            'data-xlsx-row-hidden' => ($cell['rowHidden'] ?? false) === true ? 'true' : '',
            'data-xlsx-row-height' => (string) ($cell['rowHeight'] ?? ''),
            'data-xlsx-column-hidden' => ($cell['columnHidden'] ?? false) === true ? 'true' : '',
            'data-xlsx-column-width' => (string) ($cell['columnWidth'] ?? ''),
            'data-xlsx-comment-author' => (string) ($cell['commentAuthor'] ?? ''),
            'data-xlsx-comment' => (string) ($cell['comment'] ?? ''),
        ], static fn (string $value): bool => $value !== '');
        $css = [];
        if (($style['fillColor'] ?? '') !== '') {
            $css[] = 'background-color:' . $style['fillColor'];
        }
        if (($style['color'] ?? '') !== '') {
            $css[] = 'color:' . $style['color'];
        }
        if (in_array((string) ($style['horizontal'] ?? ''), ['left', 'center', 'right'], true)) {
            $css[] = 'text-align:' . $style['horizontal'];
        }
        if (in_array((string) ($style['vertical'] ?? ''), ['top', 'center', 'bottom'], true)) {
            $vertical = (string) $style['vertical'];
            $css[] = 'vertical-align:' . ($vertical === 'center' ? 'middle' : $vertical);
        }
        if (($style['fontName'] ?? '') !== '') {
            $css[] = 'font-family:' . $this->cssString((string) $style['fontName']);
        }
        if (($style['fontSize'] ?? '') !== '') {
            $css[] = 'font-size:' . $style['fontSize'] . 'pt';
        }
        if (($style['border'] ?? '') !== '') {
            foreach (explode(';', (string) $style['border']) as $borderStyle) {
                if ($borderStyle !== '') {
                    $css[] = $borderStyle;
                }
            }
        }
        if ($css !== []) {
            $attrs['style'] = implode(';', $css);
        }

        return $attrs;
    }

    /**
     * @param array<string, array<string, mixed>> $cells
     * @return array<string,string>
     */
    private function rowHtmlAttributes(int $rowNumber, array $cells): array
    {
        $attrs = ['data-xlsx-row' => (string) $rowNumber];
        foreach ($cells as $cell) {
            if (!is_array($cell) || (int) ($cell['row'] ?? 0) !== $rowNumber) {
                continue;
            }
            if (($cell['rowHidden'] ?? false) === true) {
                $attrs['data-xlsx-row-hidden'] = 'true';
            }
            if (($cell['rowHeight'] ?? '') !== '') {
                $attrs['data-xlsx-row-height'] = (string) $cell['rowHeight'];
            }
            break;
        }

        return $attrs;
    }

    private function cssString(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9 _.-]+$/u', $value) === 1) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return 'sans-serif';
    }

    private function firstVal(\DOMElement $element, string $localName): string
    {
        foreach ($element->getElementsByTagNameNS(self::SS_NS, $localName) as $child) {
            if ($child instanceof \DOMElement) {
                return $child->getAttribute('val');
            }
        }

        return '';
    }

    private function firstColor(\DOMElement $element): string
    {
        foreach ($element->getElementsByTagNameNS(self::SS_NS, 'color') as $color) {
            if ($color instanceof \DOMElement) {
                return $this->colorValue($color);
            }
        }

        return '';
    }

    private function firstDescendantElementByLocalName(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function integerChildText(\DOMElement $element, string $localName): ?int
    {
        $child = $this->firstChildElementByLocalName($element, $localName);
        if (!$child instanceof \DOMElement) {
            return null;
        }
        $text = trim($child->textContent);

        return preg_match('/^-?[0-9]+$/', $text) === 1 ? (int) $text : null;
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(ord('A') + ($column % 26)) . $name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function fillColor(\DOMElement $fill): string
    {
        foreach ($fill->getElementsByTagNameNS(self::SS_NS, 'fgColor') as $color) {
            if ($color instanceof \DOMElement) {
                return $this->colorValue($color);
            }
        }
        foreach ($fill->getElementsByTagNameNS(self::SS_NS, 'bgColor') as $color) {
            if ($color instanceof \DOMElement) {
                return $this->colorValue($color);
            }
        }

        return '';
    }

    /**
     * @return array{css:string}
     */
    private function borderStyle(\DOMElement $border): array
    {
        $styles = [];
        foreach (['left', 'right', 'top', 'bottom'] as $side) {
            $edge = $this->firstChildElementByLocalName($border, $side);
            if (!$edge instanceof \DOMElement) {
                continue;
            }
            $style = $edge->getAttribute('style');
            if ($style === '' || $style === 'none') {
                continue;
            }
            $color = '';
            foreach ($edge->getElementsByTagNameNS(self::SS_NS, 'color') as $colorNode) {
                if ($colorNode instanceof \DOMElement) {
                    $color = $this->colorValue($colorNode);
                    break;
                }
            }
            $width = in_array($style, ['medium', 'thick', 'double'], true) ? '2px' : '1px';
            $line = $style === 'dashed' ? 'dashed' : ($style === 'dotted' ? 'dotted' : 'solid');
            $styles[] = 'border-' . $side . ':' . $width . ' ' . $line . ' ' . ($color !== '' ? $color : '#000000');
        }

        return ['css' => implode(';', $styles)];
    }

    private function colorValue(\DOMElement $color): string
    {
        $rgb = strtoupper($color->getAttribute('rgb'));
        if (preg_match('/^[0-9A-F]{8}$/', $rgb) === 1) {
            return '#' . substr($rgb, 2);
        }
        if (preg_match('/^[0-9A-F]{6}$/', $rgb) === 1) {
            return '#' . $rgb;
        }

        return '';
    }

    private function workbookDate1904(string $workbookXml): bool
    {
        $dom = $this->loadXml($workbookXml, 'XLSX workbook.xml');
        foreach ($dom->getElementsByTagNameNS(self::SS_NS, 'workbookPr') as $properties) {
            if ($properties instanceof \DOMElement) {
                return in_array(strtolower($properties->getAttribute('date1904')), ['1', 'true'], true);
            }
        }

        return false;
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
            return ZipOpcPackage::normalizePathStrict(ltrim($target, '/'));
        }

        return ZipOpcPackage::normalizePathStrict(ZipOpcPackage::dirname($sourcePartPath) . '/' . $target);
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
