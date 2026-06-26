<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XlsxReader;
use PortLibs\Pandoc\ZipPackage;

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

$buildReviewXlsxPackage = static function (): string {
    return ZipPackage::build([
        [
            'name' => '_rels/.rels',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'xl/workbook.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <workbookPr date1904="1" filterPrivacy="1" backupFile="1" showObjects="placeholders" codeName="ThisWorkbook"/>
  <calcPr calcId="191029" calcMode="manual" fullCalcOnLoad="1" forceFullCalc="0" iterate="1"/>
  <definedNames>
    <definedName name="VisibleRange">Visible!$A$1:$C$3</definedName>
  </definedNames>
  <externalReferences>
    <externalReference r:id="rIdExternalBook"/>
  </externalReferences>
  <sheets>
    <sheet name="Visible" sheetId="1" r:id="rIdSheet1"/>
    <sheet name="Hidden Audit" sheetId="2" state="hidden" r:id="rIdSheet2"/>
    <sheet name="Very Hidden" sheetId="3" state="veryHidden" r:id="rIdSheet3"/>
  </sheets>
</workbook>
XML,
        ],
        [
            'name' => 'xl/_rels/workbook.xml.rels',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rIdSheet2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rIdSheet3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
  <Relationship Id="rIdSharedStrings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rIdExternalBook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/externalLink" Target="externalLinks/externalLink1.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'xl/sharedStrings.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="5" uniqueCount="5">
  <si><t>Formula</t></si>
  <si><t>Result</t></si>
  <si><t>Error</t></si>
  <si><t>Hidden value</t></si>
  <si><t>Very hidden value</t></si>
</sst>
XML,
        ],
        [
            'name' => 'xl/worksheets/sheet1.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>
    <row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>
    <row r="2"><c r="A2"><f>1+1</f><v>999</v></c><c r="B2" t="e"><f>A2/0</f><v>#DIV/0!</v></c><c r="C2" t="str"><f>&quot;cached&quot;</f><v>Cached string</v></c></row>
  </sheetData>
  <autoFilter ref="A1:C3"/>
  <tableParts count="1">
    <tablePart r:id="rIdTable1"/>
  </tableParts>
</worksheet>
XML,
        ],
        [
            'name' => 'xl/worksheets/_rels/sheet1.xml.rels',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdTable1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'xl/tables/table1.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="VisibleTable" displayName="VisibleTable" ref="A1:C3" headerRowCount="1" totalsRowShown="0">
  <autoFilter ref="A1:C3"/>
  <tableColumns count="3">
    <tableColumn id="1" name="Formula"/>
    <tableColumn id="2" name="Result"/>
    <tableColumn id="3" name="Error"/>
  </tableColumns>
</table>
XML,
        ],
        [
            'name' => 'xl/worksheets/sheet2.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" t="s"><v>3</v></c></row>
  </sheetData>
</worksheet>
XML,
        ],
        [
            'name' => 'xl/worksheets/sheet3.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" t="s"><v>4</v></c></row>
  </sheetData>
</worksheet>
XML,
        ],
        [
            'name' => 'xl/externalLinks/externalLink1.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<externalLink xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>
XML,
        ],
    ]);
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

    'reports hidden sheets workbook metadata formulas errors and table filters without evaluation' => static function (TestRunner $t) use ($buildReviewXlsxPackage): void {
        $document = (new XlsxReader())->read($buildReviewXlsxPackage());
        $review = $document->attr('xlsx');
        $sheets = $review['sheets'];
        $visibleSheet = $sheets[0];
        $visibleTable = $document->children[1];
        $visibleBodyCells = $visibleTable->children[1]->children[0]->children;

        $t->same(3, $review['sheetCount'] ?? null);
        $t->same('emit-all-sheets-record-visibility', $review['hiddenSheetPolicy'] ?? null);
        $t->same(2, $review['hiddenSheetCount'] ?? null);
        $t->same(1, $review['veryHiddenSheetCount'] ?? null);
        $t->same(['visible', 'hidden', 'veryHidden'], array_column($sheets, 'state'));
        $t->same(true, $sheets[1]['hidden'] ?? null);
        $t->same(false, $sheets[1]['veryHidden'] ?? null);
        $t->same(true, $sheets[2]['veryHidden'] ?? null);
        $t->same('Hidden Audit', $document->children[2]->attr('text'));
        $t->same('Very Hidden', $document->children[4]->attr('text'));

        $t->same(true, $review['workbookProperties']['date1904'] ?? null);
        $t->same(true, $review['workbookProperties']['filterPrivacy'] ?? null);
        $t->same(true, $review['workbookProperties']['backupFile'] ?? null);
        $t->same('placeholders', $review['workbookProperties']['showObjects'] ?? null);
        $t->same(true, $review['workbookProperties']['codeNamePresent'] ?? null);
        $t->same(true, $review['calculationProperties']['present'] ?? null);
        $t->same(191029, $review['calculationProperties']['calcId'] ?? null);
        $t->same('manual', $review['calculationProperties']['calcMode'] ?? null);
        $t->same(true, $review['calculationProperties']['fullCalcOnLoad'] ?? null);
        $t->same(false, $review['calculationProperties']['forceFullCalc'] ?? null);
        $t->same(true, $review['calculationProperties']['iterate'] ?? null);
        $t->same(1, $review['definedNameCount'] ?? null);
        $t->same(1, $review['externalReferenceCount'] ?? null);

        $t->same('cached-values-only-no-formula-evaluation', $review['formulaPolicy'] ?? null);
        $t->same(3, $review['formulaCellCount'] ?? null);
        $t->same(3, $review['formulaCachedValueCount'] ?? null);
        $t->same(1, $review['errorCellCount'] ?? null);
        $t->same(3, $visibleSheet['formulaCellCount'] ?? null);
        $t->same(1, $visibleSheet['errorCellCount'] ?? null);
        $t->same('A2', $visibleSheet['formulaDiagnostics'][0]['ref'] ?? null);
        $t->same('normal', $visibleSheet['formulaDiagnostics'][0]['formulaType'] ?? null);
        $t->same('number', $visibleSheet['formulaDiagnostics'][0]['cachedValueType'] ?? null);
        $t->same(3, $visibleSheet['formulaDiagnostics'][0]['formulaTextBytes'] ?? null);
        $t->same(hash('sha256', '1+1'), $visibleSheet['formulaDiagnostics'][0]['formulaSha256'] ?? null);
        $t->true(!array_key_exists('formulaText', $visibleSheet['formulaDiagnostics'][0]), 'Formula diagnostics should not expose formula text');
        $t->same('B2', $visibleSheet['errorDiagnostics'][0]['ref'] ?? null);
        $t->same('#DIV/0!', $visibleSheet['errorDiagnostics'][0]['code'] ?? null);
        $t->same(true, $visibleSheet['errorDiagnostics'][0]['fromFormula'] ?? null);
        $t->same('999.0', $visibleBodyCells[0]->attr('text'));
        $t->same('number', $visibleBodyCells[0]->attr('xlsxValueType'));
        $t->same('#DIV/0!', $visibleBodyCells[1]->attr('text'));
        $t->same('error', $visibleBodyCells[1]->attr('xlsxValueType'));
        $t->same('Cached string', $visibleBodyCells[2]->attr('text'));

        $t->same(1, $review['tablePartCount'] ?? null);
        $t->same(1, $review['autoFilterCount'] ?? null);
        $t->same(['A1:C3'], $visibleSheet['autoFilterRanges'] ?? null);
        $t->same([], $visibleSheet['tablePartDiagnostics'] ?? null);
        $t->same('rIdTable1', $visibleSheet['tableParts'][0]['relationshipId'] ?? null);
        $t->same('xl/tables/table1.xml', $visibleSheet['tableParts'][0]['partName'] ?? null);
        $t->same(true, $visibleSheet['tableParts'][0]['available'] ?? null);
        $t->same(1, $visibleSheet['tableParts'][0]['id'] ?? null);
        $t->same('VisibleTable', $visibleSheet['tableParts'][0]['displayName'] ?? null);
        $t->same('A1:C3', $visibleSheet['tableParts'][0]['ref'] ?? null);
        $t->same('A1:C3', $visibleSheet['tableParts'][0]['autoFilterRef'] ?? null);
        $t->same(3, $visibleSheet['tableParts'][0]['columnCount'] ?? null);
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
