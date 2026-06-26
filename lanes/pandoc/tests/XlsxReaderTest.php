<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XlsxReader;

$buildXlsxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary XLSX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary XLSX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Main" sheetId="1" r:id="rId1"/>
    <sheet name="Secondary" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>
XML);
    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId6" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Aptos Narrow"/></font>
    <font><b/><name val="Aptos Narrow"/></font>
  </fonts>
</styleSheet>
XML);
    $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="19" uniqueCount="13">
  <si><t>Person</t></si>
  <si><t>Age</t></si>
  <si><t>Location</t></si>
  <si><t>Anton Antich</t></si>
  <si><t>Switzerland</t></si>
  <si><t>James Bond</t></si>
  <si><t>Moscow</t></si>
  <si><t>Just a random cell</t></si>
  <si><t>Row Labels</t></si>
  <si><t>(blank)</t></si>
  <si><t>Grand Total</t></si>
  <si><t>Column Labels</t></si>
  <si><t>Sum of Age</t></si>
</sst>
XML);
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" s="1" t="s"><v>0</v></c><c r="B1" s="1" t="s"><v>1</v></c><c r="C1" s="1" t="s"><v>2</v></c></row>
    <row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2"><v>23</v></c><c r="C2" t="s"><v>4</v></c></row>
    <row r="3"><c r="A3" t="s"><v>5</v></c><c r="B3"><v>35</v></c><c r="C3" t="s"><v>6</v></c></row>
    <row r="6"><c r="A6" t="s"><v>7</v></c></row>
  </sheetData>
</worksheet>
XML);
    $zip->addFromString('xl/worksheets/sheet2.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" s="2" t="s"><v>12</v></c><c r="B1" s="2" t="s"><v>11</v></c></row>
    <row r="2"><c r="A2" s="2" t="s"><v>8</v></c><c r="B2" t="s"><v>6</v></c><c r="C2" t="s"><v>4</v></c><c r="D2" t="s"><v>9</v></c><c r="E2" t="s"><v>10</v></c></row>
    <row r="3"><c r="A3" s="3" t="s"><v>3</v></c><c r="B3" s="4"/><c r="C3" s="4"><v>23</v></c><c r="D3" s="4"/><c r="E3" s="4"><v>23</v></c></row>
    <row r="4"><c r="A4" s="3" t="s"><v>5</v></c><c r="B4" s="4"><v>35</v></c><c r="C4" s="4"/><c r="D4" s="4"/><c r="E4" s="4"><v>35</v></c></row>
    <row r="5"><c r="A5" s="3" t="s"><v>9</v></c><c r="B5" s="4"/><c r="C5" s="4"/><c r="D5" s="4"/><c r="E5" s="4"/></row>
    <row r="6"><c r="A6" s="3" t="s"><v>10</v></c><c r="B6" s="4"><v>35</v></c><c r="C6" s="4"><v>23</v></c><c r="D6" s="4"/><c r="E6" s="4"><v>58</v></c></row>
  </sheetData>
</worksheet>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary XLSX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

$buildFormattedXlsxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-formatted-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary XLSX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary XLSX package');
    }

    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Formats" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);
    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="1">
    <numFmt numFmtId="164" formatCode="yyyy-mm-dd"/>
  </numFmts>
  <fonts count="3">
    <font><name val="Aptos"/></font>
    <font><b/><name val="Aptos"/></font>
    <font><i/><name val="Aptos"/></font>
  </fonts>
  <cellXfs count="4">
    <xf numFmtId="0" fontId="0"/>
    <xf numFmtId="0" fontId="1" applyFont="1"/>
    <xf numFmtId="2" fontId="0" applyNumberFormat="1"/>
    <xf numFmtId="164" fontId="2" applyFont="1" applyNumberFormat="1"/>
  </cellXfs>
</styleSheet>
XML);
    $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="7" uniqueCount="7">
  <si><t>Merged Header</t></si>
  <si><t>Link</t></si>
  <si><r><t>North</t></r><r><t>wind</t></r></si>
  <si><t>Amount</t></si>
  <si><t>Date</t></si>
  <si><t>Inline</t></si>
  <si><t>Unused</t></si>
</sst>
XML);
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>
    <row r="1">
      <c r="A1" s="1" t="s"><v>0</v></c>
      <c r="D1" s="1" t="s"><v>1</v></c>
    </row>
    <row r="2">
      <c r="A2" t="s"><v>2</v></c>
      <c r="B2" s="2"><v>12</v></c>
      <c r="C2" s="3"><v>45306</v></c>
      <c r="D2" t="inlineStr"><is><r><t>Inline</t></r><r><t xml:space="preserve"> value</t></r></is></c>
    </row>
  </sheetData>
  <mergeCells count="1">
    <mergeCell ref="A1:C1"/>
  </mergeCells>
  <hyperlinks>
    <hyperlink ref="D1" r:id="rIdHyperlink" tooltip="Open source"/>
  </hyperlinks>
</worksheet>
XML);
    $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHyperlink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/report" TargetMode="External"/>
</Relationships>
XML);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary XLSX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

return [
    'matches pinned upstream xlsx reader basic fixture semantics' => static function (TestRunner $t) use ($buildXlsxPackage): void {
        $document = (new XlsxReader())->read($buildXlsxPackage());
        $review = $document->attr('xlsx');
        $native = PandocConverter::write($document, 'native');
        $blocks = (new WordPressBlockWriter())->write($document);
        $mainTable = $document->children[1];
        $secondaryTable = $document->children[3];
        $mainHeaderCells = $mainTable->children[0]->children[0]->children;
        $mainRows = $mainTable->children[1]->children;
        $secondaryRows = $secondaryTable->children[1]->children;

        $t->same('xlsx', $document->attr('sourceFormat'));
        $t->same([], $document->attr('meta'));
        $t->same(1, $review['upstreamEvidence']['denominator'] ?? null);
        $t->same(['test/xlsx-reader/basic.xlsx', 'test/xlsx-reader/basic.native'], $review['upstreamEvidence']['fixtures'] ?? null);
        $t->same(2, $review['sheetCount'] ?? null);
        $t->same(2, $review['tableCount'] ?? null);
        $t->same(13, $review['sharedStringCount'] ?? null);
        $t->same(2, $review['styleFontCount'] ?? null);
        $t->same('heading', $document->children[0]->type);
        $t->same(2, $document->children[0]->attr('level'));
        $t->same('sheet-1', $document->children[0]->attr('id'));
        $t->same('Main', $document->children[0]->attr('text'));
        $t->same('heading', $document->children[2]->type);
        $t->same('sheet-2', $document->children[2]->attr('id'));
        $t->same('Secondary', $document->children[2]->attr('text'));

        $t->same(['Person', 'Age', 'Location'], array_map(static fn (AstNode $cell): string => (string) $cell->attr('text'), $mainHeaderCells));
        $t->same('strong', $mainHeaderCells[0]->children[0]->children[0]->type);
        $t->same('Anton Antich', $mainRows[0]->children[0]->attr('text'));
        $t->same('23.0', $mainRows[0]->children[1]->attr('text'));
        $t->same('Switzerland', $mainRows[0]->children[2]->attr('text'));
        $t->same('James Bond', $mainRows[1]->children[0]->attr('text'));
        $t->same('35.0', $mainRows[1]->children[1]->attr('text'));
        $t->same('', $mainRows[2]->children[0]->attr('text'));
        $t->same('', $mainRows[3]->children[0]->attr('text'));
        $t->same('Just a random cell', $mainRows[4]->children[0]->attr('text'));

        $t->same('Sum of Age', $secondaryTable->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Column Labels', $secondaryTable->children[0]->children[0]->children[1]->attr('text'));
        $t->same('Row Labels', $secondaryRows[0]->children[0]->attr('text'));
        $t->same('Moscow', $secondaryRows[0]->children[1]->attr('text'));
        $t->same('Grand Total', $secondaryRows[4]->children[0]->attr('text'));
        $t->same('58.0', $secondaryRows[4]->children[4]->attr('text'));

        $t->contains('Header 2 ( "sheet-1" , [  ] , [  ] ) [ Str "Main" ]', $native);
        $t->contains('Plain [ Strong [ Str "Person" ] ]', $native);
        $t->contains('Plain [ Str "Anton" , Space , Str "Antich" ]', $native);
        $t->contains('Plain [ Str "23.0" ]', $native);
        $t->contains('Plain [  ]', $native);
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('<th><strong>Person</strong></th>', $blocks);
        $t->contains('<td>Just a random cell</td>', $blocks);
    },

    'reads formatted cells hyperlinks rich strings and merged spans from real xlsx style records' => static function (TestRunner $t) use ($buildFormattedXlsxPackage): void {
        $document = (new XlsxReader())->read($buildFormattedXlsxPackage());
        $review = $document->attr('xlsx');
        $native = PandocConverter::write($document, 'native');
        $html = PandocConverter::write($document, 'html');
        $table = $document->children[1];
        $headerCells = $table->children[0]->children[0]->children;
        $bodyCells = $table->children[1]->children[0]->children;
        $mergedHeader = $headerCells[0];
        $linkedHeader = $headerCells[1];
        $link = $linkedHeader->children[0]->children[0];

        $t->same(1, $review['sheetCount'] ?? null);
        $t->same(1, $review['tableCount'] ?? null);
        $t->same(7, $review['sharedStringCount'] ?? null);
        $t->same(3, $review['styleFontCount'] ?? null);
        $t->same(4, $review['styleCellFormatCount'] ?? null);
        $t->same(1, $review['styleCustomNumberFormatCount'] ?? null);
        $t->same(false, $review['date1904'] ?? null);
        $t->same(1, $review['sheets'][0]['hyperlinkCount'] ?? null);
        $t->same(1, $review['sheets'][0]['mergedCellCount'] ?? null);

        $t->same(2, count($headerCells));
        $t->same('Merged Header', $mergedHeader->attr('text'));
        $t->same(3, $mergedHeader->attr('colspan'));
        $t->same('strong', $mergedHeader->children[0]->children[0]->type);
        $t->same('D1', $linkedHeader->attr('sourceCell'));
        $t->same('link', $link->type);
        $t->same('https://example.test/report', $link->attr('url'));
        $t->same('Open source', $link->attr('title'));
        $t->same('strong', $link->children[0]->type);

        $t->same('Northwind', $bodyCells[0]->attr('text'));
        $t->same('12.00', $bodyCells[1]->attr('text'));
        $t->same('number', $bodyCells[1]->attr('xlsxValueType'));
        $t->same('2024-01-15', $bodyCells[2]->attr('text'));
        $t->same('date', $bodyCells[2]->attr('xlsxValueType'));
        $t->same('emph', $bodyCells[2]->children[0]->children[0]->type);
        $t->same('Inline value', $bodyCells[3]->attr('text'));

        $t->contains('Cell ( "" , [  ] , [  ] ) AlignDefault (RowSpan 1) (ColSpan 3)', $native);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Strong [ Str "Link" ] ] ( "https://example.test/report" , "Open source" )', $native);
        $t->contains('<th colspan="3"><strong>Merged Header</strong></th>', $html);
        $t->contains('<a href="https://example.test/report" title="Open source"><strong>Link</strong></a>', $html);
        $t->contains('<td>12.00</td>', $html);
        $t->contains('<td><em>2024-01-15</em></td>', $html);
    },

    'reads xlsx bytes through the converter input path' => static function (TestRunner $t) use ($buildXlsxPackage): void {
        $document = PandocConverter::read($buildXlsxPackage(), 'xlsx');
        $html = PandocConverter::write($document, 'html');

        $t->same('xlsx', $document->attr('sourceFormat'));
        $t->same('Main', $document->children[0]->attr('text'));
        $t->same('table', $document->children[1]->type);
        $t->contains('<h2 id="sheet-1">Main</h2>', $html);
        $t->contains('<th><strong>Person</strong></th>', $html);
        $t->contains('<td>58.0</td>', $html);
    },

    'rejects xlsx packages without a workbook relationship' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-empty-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary XLSX path');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary XLSX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary XLSX package');
            }
        } finally {
            @unlink($path);
        }

        $t->throws(RuntimeException::class, static fn (): AstNode => (new XlsxReader())->read($bytes));
    },
];
