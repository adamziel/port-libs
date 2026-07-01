<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XlsxReader;
use PortLibs\Pandoc\ZipPackage;

$tinyPngBytes = static function (): string {
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to decode tiny PNG fixture');
    }

    return $bytes;
};

$tinyBmpBytes = static function (): string {
    $pixelBytes = str_repeat("\0", 24);

    return 'BM'
        . pack('VvvV', 78, 0, 0, 54)
        . pack('V3v2V6', 40, 2, 3, 1, 24, 0, strlen($pixelBytes), 2835, 2835, 0, 0)
        . $pixelBytes;
};

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

$buildStyleCommentImageXlsxPackage = static function () use ($tinyPngBytes, $tinyBmpBytes): string {
    return ZipPackage::build([
        [
            'name' => '[Content_Types].xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bmp" ContentType="image/bmp"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/comments/comment1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml"/>
  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
</Types>
XML,
        ],
        [
            'name' => '_rels/.rels',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'xl/workbook.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Parity" sheetId="1" r:id="rSheet"/>
  </sheets>
</workbook>
XML,
        ],
        [
            'name' => 'xl/_rels/workbook.xml.rels',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSheet" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rSharedStrings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
  <Relationship Id="rStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'xl/styles.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="$#,##0.00;($#,##0.00)"/>
    <numFmt numFmtId="165" formatCode="[h]:mm:ss"/>
  </numFmts>
  <fonts count="2">
    <font><u val="double"/><color rgb="FF336699" tint="-0.25"/><sz val="11"/><name val="Aptos"/><family val="2"/><charset val="1"/><scheme val="minor"/><vertAlign val="superscript"/></font>
    <font><b/><strike/><name val="Aptos"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFCC00" tint="0.4"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border diagonalUp="1" outline="0">
      <left style="thin"><color rgb="FF112233"/></left>
      <right style="double"><color indexed="64"/></right>
      <top style="dashed"><color theme="4" tint="-0.25"/></top>
      <bottom style="medium"><color auto="1"/></bottom>
      <diagonal style="hair"><color rgb="FFABCDEF"/></diagonal>
    </border>
  </borders>
  <cellStyleXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    <xf numFmtId="18" fontId="1" fillId="2" borderId="1" quotePrefix="1" pivotButton="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1" applyProtection="1">
      <alignment horizontal="center" vertical="top" indent="2" relativeIndent="1" shrinkToFit="1" readingOrder="2" justifyLastLine="1"/>
      <protection locked="0" hidden="1"/>
    </xf>
  </cellStyleXfs>
  <cellXfs count="6">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    <xf numFmtId="164" fontId="0" fillId="2" borderId="1" applyFont="1" applyFill="1" applyNumberFormat="1">
      <alignment horizontal="right" vertical="center" wrapText="1" textRotation="45"/>
      <protection locked="0" hidden="1"/>
    </xf>
    <xf numFmtId="14" fontId="1" fillId="0" borderId="0" applyFont="1" applyNumberFormat="1"/>
    <xf numFmtId="165" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/>
    <xf numFmtId="9" fontId="0" fillId="0" borderId="0" applyNumberFormat="1"/>
    <xf xfId="1" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1" applyProtection="1"/>
  </cellXfs>
  <cellStyles count="2">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
    <cellStyle name="Attention Time" xfId="1" builtinId="40" customBuiltin="1"/>
  </cellStyles>
</styleSheet>
XML,
        ],
        [
            'name' => 'xl/sharedStrings.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="7" uniqueCount="7">
  <si><t>Metric</t></si>
  <si><t>Revenue</t></si>
  <si><t>Date</t></si>
  <si><t>Elapsed</t></si>
  <si><t>Share</t></si>
  <si><t>Commented</t></si>
  <si><t>Meeting</t></si>
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
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="B1" t="s"><v>1</v></c>
      <c r="C1" t="s"><v>2</v></c>
      <c r="D1" t="s"><v>3</v></c>
      <c r="E1" t="s"><v>4</v></c>
      <c r="F1" t="s"><v>5</v></c>
      <c r="G1" t="s"><v>6</v></c>
    </row>
    <row r="2">
      <c r="A2" t="s"><v>1</v></c>
      <c r="B2" s="1"><v>-1234.5</v></c>
      <c r="C2" s="2"><v>45306</v></c>
      <c r="D2" s="3"><v>1.5</v></c>
      <c r="E2" s="4"><v>0.125</v></c>
      <c r="F2" t="inlineStr"><is><t>Has note</t></is></c>
      <c r="G2" s="5"><v>0.645833333333333</v></c>
    </row>
  </sheetData>
  <drawing r:id="rDrawing"/>
</worksheet>
XML,
        ],
        [
            'name' => 'xl/worksheets/_rels/sheet1.xml.rels',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments/comment1.xml"/>
  <Relationship Id="rDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'xl/comments/comment1.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <authors><author>Reviewer</author></authors>
  <commentList>
    <comment ref="B2" authorId="0"><text><r><t>Check revenue</t></r></text></comment>
  </commentList>
</comments>
XML,
        ],
        [
            'name' => 'xl/drawings/drawing1.xml',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"
          xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <xdr:twoCellAnchor>
    <xdr:from><xdr:col>1</xdr:col><xdr:colOff>1000</xdr:colOff><xdr:row>1</xdr:row><xdr:rowOff>2000</xdr:rowOff></xdr:from>
    <xdr:to><xdr:col>3</xdr:col><xdr:colOff>3000</xdr:colOff><xdr:row>4</xdr:row><xdr:rowOff>4000</xdr:rowOff></xdr:to>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="2" name="Logo" descr="Quarter logo"/></xdr:nvPicPr>
      <xdr:blipFill><a:blip r:embed="rImage"/></xdr:blipFill>
      <xdr:spPr/>
    </xdr:pic>
  </xdr:twoCellAnchor>
  <xdr:oneCellAnchor>
    <xdr:from><xdr:col>4</xdr:col><xdr:colOff>9525</xdr:colOff><xdr:row>2</xdr:row><xdr:rowOff>19050</xdr:rowOff></xdr:from>
    <xdr:ext cx="95250" cy="190500"/>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="3" name="Inline logo" descr="Inline placement"/></xdr:nvPicPr>
      <xdr:blipFill><a:blip r:embed="rImage"/></xdr:blipFill>
      <xdr:spPr/>
    </xdr:pic>
  </xdr:oneCellAnchor>
  <xdr:absoluteAnchor>
    <xdr:pos x="9525" y="19050"/>
    <xdr:ext cx="28575" cy="38100"/>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="4" name="Absolute logo" descr="Absolute placement"/></xdr:nvPicPr>
      <xdr:blipFill><a:blip r:embed="rImage"/></xdr:blipFill>
      <xdr:spPr/>
    </xdr:pic>
  </xdr:absoluteAnchor>
</xdr:wsDr>
XML,
        ],
        [
            'name' => 'xl/drawings/_rels/drawing1.xml.rels',
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>
</Relationships>
XML,
        ],
        [
            'name' => 'xl/media/image1.png',
            'data' => $tinyPngBytes(),
        ],
        [
            'name' => 'xl/media/image2.bmp',
            'data' => $tinyBmpBytes(),
        ],
    ]);
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
  <cols>
    <col min="2" max="2" width="18.5" style="4" hidden="1" customWidth="1" bestFit="1" outlineLevel="1" collapsed="1"/>
    <col min="3" max="3" width="10"/>
  </cols>
  <sheetData>
    <row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>
    <row r="2" ht="24.5" s="3" hidden="1" customHeight="1" outlineLevel="2" collapsed="1"><c r="A2"><f>1+1</f><v>999</v></c><c r="B2" t="e"><f>A2/0</f><v>#DIV/0!</v></c><c r="C2" t="str"><f>&quot;cached&quot;</f><v>Cached string</v></c></row>
  </sheetData>
  <autoFilter ref="A1:C3">
    <filterColumn colId="1" hiddenButton="1" showButton="0">
      <filters blank="0"><filter val="Result"/></filters>
    </filterColumn>
    <filterColumn colId="2">
      <customFilters and="1">
        <customFilter operator="notEqual" val="#DIV/0!"/>
      </customFilters>
    </filterColumn>
    <sortState ref="A2:C3" caseSensitive="0">
      <sortCondition ref="A2:A3" descending="1"/>
    </sortState>
  </autoFilter>
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
<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="VisibleTable" displayName="VisibleTable" ref="A1:C3" headerRowCount="1" totalsRowCount="1" totalsRowShown="0" published="1">
  <autoFilter ref="A1:C3">
    <filterColumn colId="0">
      <dynamicFilter type="thisYear"/>
    </filterColumn>
  </autoFilter>
  <tableColumns count="3">
    <tableColumn id="1" name="Formula" totalsRowFunction="sum"/>
    <tableColumn id="2" name="Result" totalsRowLabel="Total"/>
    <tableColumn id="3" name="Error" dataDxfId="5"/>
  </tableColumns>
  <tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="1" showRowStripes="1" showColumnStripes="0"/>
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

$buildFeatureMetadataXlsxPackage = static function (): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-feature-metadata-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary XLSX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary XLSX package');
    }

    $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
  <Override PartName="/xl/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml; profile=quarterly"/>
  <Override PartName="/xl/charts/bad-chart.xml" ContentType="application/xml"/>
  <Override PartName="/xl/pivotTables/pivotTable1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.pivotTable+xml"/>
  <Override PartName="/xl/pivotTables/missingPivot.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.pivotTable+xml"/>
  <Override PartName="/xl/pivotCache/pivotCacheDefinition1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.pivotCacheDefinition+xml"/>
  <Override PartName="/xl/pivotCache/pivotCacheRecords1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.pivotCacheRecords+xml"/>
  <Override PartName="/xl/slicers/slicer1.xml" ContentType="application/vnd.ms-excel.slicer+xml"/>
  <Override PartName="/xl/slicerCaches/slicerCache1.xml" ContentType="application/vnd.ms-excel.slicerCache+xml"/>
</Types>
XML);
    $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Feature Metadata" sheetId="1" r:id="rSheet"/>
  </sheets>
  <pivotCaches>
    <pivotCache cacheId="1" r:id="rPivotCache"/>
  </pivotCaches>
</workbook>
XML);
    $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSheet" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rPivotCache" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotCacheDefinition" Target="pivotCache/pivotCacheDefinition1.xml"/>
  <Relationship Id="rSlicerCache" Type="http://schemas.microsoft.com/office/2007/relationships/slicerCache" Target="slicerCaches/slicerCache1.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" t="inlineStr"><is><t>Report</t></is></c></row>
  </sheetData>
</worksheet>
XML);
    $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
  <Relationship Id="rPivotTable" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotTable" Target="../pivotTables/pivotTable1.xml"/>
  <Relationship Id="rMissingPivot" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotTable" Target="../pivotTables/missingPivot.xml"/>
  <Relationship Id="rSlicer" Type="http://schemas.microsoft.com/office/2007/relationships/slicer" Target="../slicers/slicer1.xml"/>
  <Relationship Id="rExternalSlicer" Type="http://schemas.microsoft.com/office/2007/relationships/slicer" Target="https://example.test/slicer.xml?view=west#slicer" TargetMode="External"/>
</Relationships>
XML);
    $zip->addFromString('xl/drawings/drawing1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"
          xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
          xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <xdr:twoCellAnchor>
    <xdr:graphicFrame>
      <a:graphic>
        <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
          <c:chart r:id="rChart"/>
        </a:graphicData>
      </a:graphic>
    </xdr:graphicFrame>
  </xdr:twoCellAnchor>
</xdr:wsDr>
XML);
    $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart1.xml?series=1#main"/>
  <Relationship Id="rBadChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/bad-chart.xml"/>
  <Relationship Id="rMissingChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/missing-chart.xml"/>
  <Relationship Id="rExternalChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="https://example.test/chart.xml?remote=1#chart" TargetMode="External"/>
</Relationships>
XML);
    $zip->addFromString('xl/charts/chart1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">
  <c:chart><c:title/><c:plotArea/></c:chart>
</c:chartSpace>
XML);
    $zip->addFromString('xl/charts/bad-chart.xml', '<review-chart/>');
    $zip->addFromString('xl/pivotTables/pivotTable1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<pivotTableDefinition xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" name="PivotTable1" cacheId="1"/>
XML);
    $zip->addFromString('xl/pivotCache/pivotCacheDefinition1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<pivotCacheDefinition xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" recordCount="2"/>
XML);
    $zip->addFromString('xl/pivotCache/_rels/pivotCacheDefinition1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rRecords" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/pivotCacheRecords" Target="pivotCacheRecords1.xml"/>
</Relationships>
XML);
    $zip->addFromString('xl/pivotCache/pivotCacheRecords1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<pivotCacheRecords xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2"/>
XML);
    $zip->addFromString('xl/slicers/slicer1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<x:slicers xmlns:x="http://schemas.microsoft.com/office/spreadsheetml/2009/9/main">
  <x:slicer name="Region" cache="RegionCache"/>
</x:slicers>
XML);
    $zip->addFromString('xl/slicerCaches/slicerCache1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<x:slicerCaches xmlns:x="http://schemas.microsoft.com/office/spreadsheetml/2009/9/main">
  <x:slicerCache name="RegionCache"/>
</x:slicerCaches>
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

    'preserves xlsx number styles comments and image media provenance' => static function (TestRunner $t) use ($buildStyleCommentImageXlsxPackage, $tinyPngBytes, $tinyBmpBytes): void {
        $document = (new XlsxReader())->read($buildStyleCommentImageXlsxPackage());
        $review = $document->attr('xlsx');
        $features = $review['featureMetadata'];
        $html = PandocConverter::write($document, 'html');
        $table = $document->children[1];
        $bodyCells = $table->children[1]->children[0]->children;
        $moneyCell = $bodyCells[1];
        $dateCell = $bodyCells[2];
        $elapsedCell = $bodyCells[3];
        $percentCell = $bodyCells[4];
        $timeCell = $bodyCells[6];
        $findPart = static function (array $items, string $partName): array {
            foreach ($items as $item) {
                if (($item['partName'] ?? null) === $partName) {
                    return $item;
                }
            }

            throw new RuntimeException('Missing feature metadata item: ' . $partName);
        };

        $t->same(2, $review['styleCustomNumberFormatCount'] ?? null);
        $t->same(2, $review['styleCellStyleFormatCount'] ?? null);
        $t->same(2, $review['styleNamedCellStyleCount'] ?? null);
        $t->same('Attention Time', $review['cellStyles'][1]['name'] ?? null);
        $t->same(1, $review['cellStyles'][1]['xfId'] ?? null);
        $t->same(40, $review['cellStyles'][1]['builtinId'] ?? null);
        $t->same(true, $review['cellStyles'][1]['customBuiltin'] ?? null);
        $t->same(1, $review['commentCount'] ?? null);
        $t->same(1, $review['sheets'][0]['commentCount'] ?? null);
        $t->same('B2', $review['sheets'][0]['comments'][0]['ref'] ?? null);
        $t->same('Reviewer', $review['sheets'][0]['comments'][0]['author'] ?? null);
        $t->same('Check revenue', $review['sheets'][0]['comments'][0]['text'] ?? null);
        $t->same(hash('sha256', 'Check revenue'), $review['sheets'][0]['comments'][0]['textSha256'] ?? null);

        $t->same('($1,234.50)', $moneyCell->attr('text'));
        $t->same('right', $moneyCell->attr('align'));
        $t->same('-1234.5', $moneyCell->attr('xlsxRawValue'));
        $t->same(-1234.5, $moneyCell->attr('xlsxNumericValue'));
        $t->same('($#,##0.00)', $moneyCell->attr('xlsxNumberFormatSection'));
        $t->same('currency', $moneyCell->attr('xlsxNumberFormatKind'));
        $t->same(1, $moneyCell->attr('xlsxStyleIndex'));
        $t->same('$#,##0.00;($#,##0.00)', $moneyCell->attr('xlsxNumberFormatCode'));
        $t->same('Aptos', $moneyCell->attr('xlsxFontName'));
        $t->same(11.0, $moneyCell->attr('xlsxFontSize'));
        $t->same('rgb:FF336699', $moneyCell->attr('xlsxFontColor'));
        $t->same([
            'source' => 'rgb',
            'value' => 'FF336699',
            'token' => 'rgb:FF336699',
            'argb' => 'FF336699',
            'alpha' => 'FF',
            'rgb' => '336699',
            'tint' => -0.25,
        ], $moneyCell->attr('xlsxFontColorMetadata'));
        $t->same(2, $moneyCell->attr('xlsxFontFamily'));
        $t->same(1, $moneyCell->attr('xlsxFontCharset'));
        $t->same('minor', $moneyCell->attr('xlsxFontScheme'));
        $t->same('double', $moneyCell->attr('xlsxFontUnderlineStyle'));
        $t->same('superscript', $moneyCell->attr('xlsxFontVerticalAlign'));
        $t->same('rgb:FFFFCC00', $moneyCell->attr('xlsxFillForegroundColor'));
        $t->same([
            'source' => 'rgb',
            'value' => 'FFFFCC00',
            'token' => 'rgb:FFFFCC00',
            'argb' => 'FFFFCC00',
            'alpha' => 'FF',
            'rgb' => 'FFCC00',
            'tint' => 0.4,
        ], $moneyCell->attr('xlsxFillForegroundColorMetadata'));
        $t->same(true, $moneyCell->attr('xlsxWrapText'));
        $t->same(45, $moneyCell->attr('xlsxTextRotation'));
        $t->same(false, $moneyCell->attr('xlsxLocked'));
        $t->same(true, $moneyCell->attr('xlsxHidden'));
        $t->same(1, $moneyCell->attr('xlsxCommentCount'));
        $t->same('Check revenue', $moneyCell->attr('xlsxComments')[0]['text'] ?? null);
        $t->same('underline', $moneyCell->children[0]->children[0]->type);

        $t->same('2024-01-15', $dateCell->attr('text'));
        $t->same('date', $dateCell->attr('xlsxValueType'));
        $t->same('45306', $dateCell->attr('xlsxRawValue'));
        $t->same(45306.0, $dateCell->attr('xlsxNumericValue'));
        $t->same('m/d/yy', $dateCell->attr('xlsxNumberFormatSection'));
        $t->same('date', $dateCell->attr('xlsxNumberFormatKind'));
        $t->same('strikeout', $dateCell->children[0]->children[0]->type);
        $t->same('strong', $dateCell->children[0]->children[0]->children[0]->type);
        $t->same('36:00:00', $elapsedCell->attr('text'));
        $t->same('date', $elapsedCell->attr('xlsxValueType'));
        $t->same('[h]:mm:ss', $elapsedCell->attr('xlsxNumberFormatSection'));
        $t->same('elapsed-time', $elapsedCell->attr('xlsxNumberFormatKind'));
        $t->same('13%', $percentCell->attr('text'));
        $t->same('0%', $percentCell->attr('xlsxNumberFormatSection'));
        $t->same('percentage', $percentCell->attr('xlsxNumberFormatKind'));
        $t->same('3:30 PM', $timeCell->attr('text'));
        $t->same('date', $timeCell->attr('xlsxValueType'));
        $t->same('h:mm AM/PM', $timeCell->attr('xlsxNumberFormatSection'));
        $t->same('time', $timeCell->attr('xlsxNumberFormatKind'));
        $t->same(1, $timeCell->attr('xlsxStyleXfId'));
        $t->same('Attention Time', $timeCell->attr('xlsxCellStyleName'));
        $t->same(40, $timeCell->attr('xlsxCellStyleBuiltinId'));
        $t->same(true, $timeCell->attr('xlsxCellStyleCustomBuiltin'));
        $t->same(true, $timeCell->attr('xlsxApplyNumberFormat'));
        $t->same(true, $timeCell->attr('xlsxApplyAlignment'));
        $t->same(true, $timeCell->attr('xlsxQuotePrefix'));
        $t->same(true, $timeCell->attr('xlsxPivotButton'));
        $t->same('center', $timeCell->attr('align'));
        $t->same('top', $timeCell->attr('xlsxVerticalAlign'));
        $t->same(2, $timeCell->attr('xlsxIndent'));
        $t->same(1, $timeCell->attr('xlsxRelativeIndent'));
        $t->same(true, $timeCell->attr('xlsxShrinkToFit'));
        $t->same(2, $timeCell->attr('xlsxReadingOrder'));
        $t->same(true, $timeCell->attr('xlsxJustifyLastLine'));

        $t->contains('<td style="text-align:right"><u>($1,234.50)</u></td>', $html);
        $t->contains('<td><del><strong>2024-01-15</strong></del></td>', $html);
        $t->contains('<td><u>36:00:00</u></td>', $html);
        $t->contains('<td><u>13%</u></td>', $html);
        $t->contains('<td style="text-align:center"><del><strong>3:30 PM</strong></del></td>', $html);

        $t->same(1, $features['byKind']['comments']['count'] ?? null);
        $comments = $findPart($features['byKind']['comments']['items'], 'xl/comments/comment1.xml');
        $t->same('comments', $comments['rootLocalName']);
        $t->same(true, $comments['validRoot']);
        $t->same('xl/worksheets/sheet1.xml', $comments['relationshipRefs'][0]['sourcePart']);

        $t->same(2, $features['byKind']['image']['count'] ?? null);
        $image = $findPart($features['byKind']['image']['items'], 'xl/media/image1.png');
        $t->same('image/png', $image['contentTypeBase']);
        $t->same(strlen($tinyPngBytes()), $image['byteSize']);
        $t->same(hash('sha256', $tinyPngBytes()), $image['sha256']);
        $t->same(1, $image['imageWidthPixels']);
        $t->same(1, $image['imageHeightPixels']);
        $t->same(3, $image['drawingAnchorCount']);
        $t->same('twoCellAnchor', $image['drawingAnchors'][0]['anchorType'] ?? null);
        $t->same('xl/drawings/drawing1.xml', $image['drawingAnchors'][0]['sourcePart'] ?? null);
        $t->same('rImage', $image['drawingAnchors'][0]['relationshipId'] ?? null);
        $t->same('xl/media/image1.png', $image['drawingAnchors'][0]['targetPart'] ?? null);
        $t->same(1, $image['drawingAnchors'][0]['from']['column'] ?? null);
        $t->same(1, $image['drawingAnchors'][0]['from']['row'] ?? null);
        $t->same('B2', $image['drawingAnchors'][0]['fromCell'] ?? null);
        $t->same(3, $image['drawingAnchors'][0]['to']['column'] ?? null);
        $t->same(4, $image['drawingAnchors'][0]['to']['row'] ?? null);
        $t->same('D5', $image['drawingAnchors'][0]['toCell'] ?? null);
        $t->same('Logo', $image['drawingAnchors'][0]['name'] ?? null);
        $t->same('Quarter logo', $image['drawingAnchors'][0]['description'] ?? null);
        $t->same('oneCellAnchor', $image['drawingAnchors'][1]['anchorType'] ?? null);
        $t->same('E3', $image['drawingAnchors'][1]['fromCell'] ?? null);
        $t->same(null, $image['drawingAnchors'][1]['toCell'] ?? null);
        $t->same(1.0, $image['drawingAnchors'][1]['from']['columnOffsetPixels'] ?? null);
        $t->same(2.0, $image['drawingAnchors'][1]['from']['rowOffsetPixels'] ?? null);
        $t->same(['cx' => 95250, 'cy' => 190500], $image['drawingAnchors'][1]['extentEmu'] ?? null);
        $t->same(['width' => 10.0, 'height' => 20.0], $image['drawingAnchors'][1]['extentPixels'] ?? null);
        $t->same('Inline logo', $image['drawingAnchors'][1]['name'] ?? null);
        $t->same('absoluteAnchor', $image['drawingAnchors'][2]['anchorType'] ?? null);
        $t->same(null, $image['drawingAnchors'][2]['fromCell'] ?? null);
        $t->same(['x' => 9525, 'y' => 19050], $image['drawingAnchors'][2]['positionEmu'] ?? null);
        $t->same(['x' => 1.0, 'y' => 2.0], $image['drawingAnchors'][2]['positionPixels'] ?? null);
        $t->same(['width' => 3.0, 'height' => 4.0], $image['drawingAnchors'][2]['extentPixels'] ?? null);
        $t->same('Absolute placement', $image['drawingAnchors'][2]['description'] ?? null);
        $t->same('xl/drawings/drawing1.xml', $image['relationshipRefs'][0]['sourcePart'] ?? null);
        $t->same('xl/media/image1.png', $image['relationshipRefs'][0]['targetPart'] ?? null);
        $bmpImage = $findPart($features['byKind']['image']['items'], 'xl/media/image2.bmp');
        $t->same('image/bmp', $bmpImage['contentTypeBase']);
        $t->same(strlen($tinyBmpBytes()), $bmpImage['byteSize']);
        $t->same(hash('sha256', $tinyBmpBytes()), $bmpImage['sha256']);
        $t->same(2, $bmpImage['imageWidthPixels']);
        $t->same(3, $bmpImage['imageHeightPixels']);
        $t->same(0, $bmpImage['drawingAnchorCount']);
        $t->same([], $bmpImage['issues']);
    },

    'preserves xlsx border style records as bounded cell metadata' => static function (TestRunner $t) use ($buildStyleCommentImageXlsxPackage): void {
        $document = (new XlsxReader())->read($buildStyleCommentImageXlsxPackage());
        $review = $document->attr('xlsx');
        $table = $document->children[1];
        $bodyCells = $table->children[1]->children[0]->children;
        $moneyCell = $bodyCells[1];

        $t->same(2, $review['styleBorderCount'] ?? null);
        $t->same(1, $moneyCell->attr('xlsxBorderId'));
        $t->same('thin', $moneyCell->attr('xlsxBorderLeftStyle'));
        $t->same('rgb:FF112233', $moneyCell->attr('xlsxBorderLeftColor'));
        $t->same('double', $moneyCell->attr('xlsxBorderRightStyle'));
        $t->same('indexed:64', $moneyCell->attr('xlsxBorderRightColor'));
        $t->same('dashed', $moneyCell->attr('xlsxBorderTopStyle'));
        $t->same('theme:4', $moneyCell->attr('xlsxBorderTopColor'));
        $t->same([
            'source' => 'theme',
            'value' => '4',
            'token' => 'theme:4',
            'theme' => 4,
            'tint' => -0.25,
        ], $moneyCell->attr('xlsxBorderTopColorMetadata'));
        $t->same('medium', $moneyCell->attr('xlsxBorderBottomStyle'));
        $t->same('auto:1', $moneyCell->attr('xlsxBorderBottomColor'));
        $t->same([
            'source' => 'auto',
            'value' => '1',
            'token' => 'auto:1',
            'auto' => true,
        ], $moneyCell->attr('xlsxBorderBottomColorMetadata'));
        $t->same('hair', $moneyCell->attr('xlsxBorderDiagonalStyle'));
        $t->same('rgb:FFABCDEF', $moneyCell->attr('xlsxBorderDiagonalColor'));
        $t->same(true, $moneyCell->attr('xlsxBorderDiagonalUp'));
        $t->same(false, $moneyCell->attr('xlsxBorderOutline'));
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
        $t->same(1, $review['hiddenRowCount'] ?? null);
        $t->same(1, $review['hiddenColumnCount'] ?? null);
        $t->same(1, $review['customRowHeightCount'] ?? null);
        $t->same(1, $review['customColumnWidthCount'] ?? null);
        $t->same(1, $visibleSheet['hiddenRowCount'] ?? null);
        $t->same(1, $visibleSheet['hiddenColumnCount'] ?? null);
        $t->same(1, $visibleSheet['customRowHeightCount'] ?? null);
        $t->same(1, $visibleSheet['customColumnWidthCount'] ?? null);
        $t->same(2, count($visibleSheet['columnMetadata'] ?? []));
        $t->same('B', $visibleSheet['columnMetadata'][0]['range'] ?? null);
        $t->same(18.5, $visibleSheet['columnMetadata'][0]['width'] ?? null);
        $t->same(4, $visibleSheet['columnMetadata'][0]['styleIndex'] ?? null);
        $t->same(true, $visibleSheet['columnMetadata'][0]['hidden'] ?? null);
        $t->same(true, $visibleSheet['columnMetadata'][0]['customWidth'] ?? null);
        $t->same(true, $visibleSheet['columnMetadata'][0]['bestFit'] ?? null);
        $t->same(1, $visibleSheet['columnMetadata'][0]['outlineLevel'] ?? null);
        $t->same(true, $visibleSheet['columnMetadata'][0]['collapsed'] ?? null);
        $t->same(1, count($visibleSheet['rowMetadata'] ?? []));
        $t->same(2, $visibleSheet['rowMetadata'][0]['row'] ?? null);
        $t->same(24.5, $visibleSheet['rowMetadata'][0]['height'] ?? null);
        $t->same(3, $visibleSheet['rowMetadata'][0]['styleIndex'] ?? null);
        $t->same(true, $visibleSheet['rowMetadata'][0]['hidden'] ?? null);
        $t->same(true, $visibleSheet['rowMetadata'][0]['customHeight'] ?? null);
        $t->same(2, $visibleSheet['rowMetadata'][0]['outlineLevel'] ?? null);
        $t->same(true, $visibleSheet['rowMetadata'][0]['collapsed'] ?? null);

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
        $t->same(true, $visibleBodyCells[0]->attr('xlsxRowHidden'));
        $t->same(24.5, $visibleBodyCells[0]->attr('xlsxRowHeight'));
        $t->same(3, $visibleBodyCells[0]->attr('xlsxRowStyleIndex'));
        $t->same(2, $visibleBodyCells[0]->attr('xlsxRowOutlineLevel'));
        $t->same(true, $visibleBodyCells[0]->attr('xlsxRowCustomHeight'));
        $t->same(true, $visibleBodyCells[0]->attr('xlsxRowCollapsed'));
        $t->same('#DIV/0!', $visibleBodyCells[1]->attr('text'));
        $t->same('error', $visibleBodyCells[1]->attr('xlsxValueType'));
        $t->same('B', $visibleBodyCells[1]->attr('xlsxColumnRange'));
        $t->same(true, $visibleBodyCells[1]->attr('xlsxColumnHidden'));
        $t->same(18.5, $visibleBodyCells[1]->attr('xlsxColumnWidth'));
        $t->same(4, $visibleBodyCells[1]->attr('xlsxColumnStyleIndex'));
        $t->same(true, $visibleBodyCells[1]->attr('xlsxColumnCustomWidth'));
        $t->same(true, $visibleBodyCells[1]->attr('xlsxColumnBestFit'));
        $t->same(1, $visibleBodyCells[1]->attr('xlsxColumnOutlineLevel'));
        $t->same(true, $visibleBodyCells[1]->attr('xlsxColumnCollapsed'));
        $t->same('Cached string', $visibleBodyCells[2]->attr('text'));
        $t->same('C', $visibleBodyCells[2]->attr('xlsxColumnRange'));
        $t->same(10, $visibleBodyCells[2]->attr('xlsxColumnWidth'));

        $t->same(1, $review['tablePartCount'] ?? null);
        $t->same(1, $review['autoFilterCount'] ?? null);
        $t->same(2, $review['autoFilterDetailCount'] ?? null);
        $t->same(['A1:C3'], $visibleSheet['autoFilterRanges'] ?? null);
        $t->same(2, count($visibleSheet['autoFilters'] ?? []));
        $t->same('worksheet', $visibleSheet['autoFilters'][0]['source'] ?? null);
        $t->same('A1:C3', $visibleSheet['autoFilters'][0]['ref'] ?? null);
        $t->same(2, $visibleSheet['autoFilters'][0]['filterColumnCount'] ?? null);
        $t->same(1, $visibleSheet['autoFilters'][0]['filterColumns'][0]['colId'] ?? null);
        $t->same(true, $visibleSheet['autoFilters'][0]['filterColumns'][0]['hiddenButton'] ?? null);
        $t->same(false, $visibleSheet['autoFilters'][0]['filterColumns'][0]['showButton'] ?? null);
        $t->same('filters', $visibleSheet['autoFilters'][0]['filterColumns'][0]['filterType'] ?? null);
        $t->same(['Result'], $visibleSheet['autoFilters'][0]['filterColumns'][0]['details']['values'] ?? null);
        $t->same(false, $visibleSheet['autoFilters'][0]['filterColumns'][0]['details']['blank'] ?? null);
        $t->same('customFilters', $visibleSheet['autoFilters'][0]['filterColumns'][1]['filterType'] ?? null);
        $t->same(true, $visibleSheet['autoFilters'][0]['filterColumns'][1]['details']['and'] ?? null);
        $t->same('notEqual', $visibleSheet['autoFilters'][0]['filterColumns'][1]['details']['filters'][0]['operator'] ?? null);
        $t->same('#DIV/0!', $visibleSheet['autoFilters'][0]['filterColumns'][1]['details']['filters'][0]['value'] ?? null);
        $t->same('A2:C3', $visibleSheet['autoFilters'][0]['sortState']['ref'] ?? null);
        $t->same(1, $visibleSheet['autoFilters'][0]['sortState']['conditionCount'] ?? null);
        $t->same('A2:A3', $visibleSheet['autoFilters'][0]['sortState']['conditions'][0]['ref'] ?? null);
        $t->same(true, $visibleSheet['autoFilters'][0]['sortState']['conditions'][0]['descending'] ?? null);
        $t->same('table', $visibleSheet['autoFilters'][1]['source'] ?? null);
        $t->same('rIdTable1', $visibleSheet['autoFilters'][1]['tableRelationshipId'] ?? null);
        $t->same('xl/tables/table1.xml', $visibleSheet['autoFilters'][1]['tablePartName'] ?? null);
        $t->same('dynamicFilter', $visibleSheet['autoFilters'][1]['filterColumns'][0]['filterType'] ?? null);
        $t->same('thisYear', $visibleSheet['autoFilters'][1]['filterColumns'][0]['details']['type'] ?? null);
        $t->same([], $visibleSheet['tablePartDiagnostics'] ?? null);
        $t->same('rIdTable1', $visibleSheet['tableParts'][0]['relationshipId'] ?? null);
        $t->same('xl/tables/table1.xml', $visibleSheet['tableParts'][0]['partName'] ?? null);
        $t->same(true, $visibleSheet['tableParts'][0]['available'] ?? null);
        $t->same(1, $visibleSheet['tableParts'][0]['id'] ?? null);
        $t->same('VisibleTable', $visibleSheet['tableParts'][0]['displayName'] ?? null);
        $t->same('A1:C3', $visibleSheet['tableParts'][0]['ref'] ?? null);
        $t->same('A1:C3', $visibleSheet['tableParts'][0]['autoFilterRef'] ?? null);
        $t->same(1, $visibleSheet['tableParts'][0]['totalsRowCount'] ?? null);
        $t->same(true, $visibleSheet['tableParts'][0]['published'] ?? null);
        $t->same(3, $visibleSheet['tableParts'][0]['columnCount'] ?? null);
        $t->same(['Formula', 'Result', 'Error'], $visibleSheet['tableParts'][0]['columnNames'] ?? null);
        $t->same('Formula', $visibleSheet['tableParts'][0]['columns'][0]['name'] ?? null);
        $t->same('sum', $visibleSheet['tableParts'][0]['columns'][0]['totalsRowFunction'] ?? null);
        $t->same('Total', $visibleSheet['tableParts'][0]['columns'][1]['totalsRowLabel'] ?? null);
        $t->same(5, $visibleSheet['tableParts'][0]['columns'][2]['dataDxfId'] ?? null);
        $t->same(1, $visibleSheet['tableParts'][0]['autoFilterColumnCount'] ?? null);
        $t->same('dynamicFilter', $visibleSheet['tableParts'][0]['autoFilterColumns'][0]['filterType'] ?? null);
        $t->same('thisYear', $visibleSheet['tableParts'][0]['autoFilterColumns'][0]['details']['type'] ?? null);
        $t->same('TableStyleMedium2', $visibleSheet['tableParts'][0]['tableStyleInfo']['name'] ?? null);
        $t->same(false, $visibleSheet['tableParts'][0]['tableStyleInfo']['showFirstColumn'] ?? null);
        $t->same(true, $visibleSheet['tableParts'][0]['tableStyleInfo']['showLastColumn'] ?? null);
        $t->same(true, $visibleSheet['tableParts'][0]['tableStyleInfo']['showRowStripes'] ?? null);
        $t->same(false, $visibleSheet['tableParts'][0]['tableStyleInfo']['showColumnStripes'] ?? null);
    },

    'preserves bounded chart pivot and slicer metadata for review without execution' => static function (TestRunner $t) use ($buildFeatureMetadataXlsxPackage): void {
        $document = (new XlsxReader())->read($buildFeatureMetadataXlsxPackage());
        $review = $document->attr('xlsx');
        $features = $review['featureMetadata'];
        $findPart = static function (array $items, string $partName): array {
            foreach ($items as $item) {
                if (($item['partName'] ?? null) === $partName) {
                    return $item;
                }
            }

            throw new RuntimeException('Missing feature metadata item: ' . $partName);
        };
        $findExternal = static function (array $items): array {
            foreach ($items as $item) {
                if (($item['external'] ?? false) === true) {
                    return $item;
                }
            }

            throw new RuntimeException('Missing external feature metadata item');
        };

        $t->same(true, $review['contentTypesAvailable'] ?? null);
        $t->same(null, $review['contentTypesParseError'] ?? null);
        $t->same('xlsx-feature-metadata-only', $features['summary']['readerPolicy'] ?? null);
        $t->same('xlsx-feature-part-bytes-blocked', $features['summary']['byteExposurePolicy'] ?? null);
        $t->same(['chart-rendering', 'pivot-computation', 'formula-evaluation', 'scripting'], $features['summary']['nonGoals'] ?? null);
        $t->same(12, $features['summary']['count'] ?? null);
        $t->same(8, $features['summary']['existingCount'] ?? null);
        $t->same(2, $features['summary']['missingCount'] ?? null);
        $t->same(2, $features['summary']['externalCount'] ?? null);

        $t->same(1, $features['byKind']['drawing']['count'] ?? null);
        $t->same(4, $features['byKind']['chart']['count'] ?? null);
        $t->same(2, $features['byKind']['chart']['existingCount'] ?? null);
        $t->same(1, $features['byKind']['chart']['missingCount'] ?? null);
        $t->same(1, $features['byKind']['chart']['externalCount'] ?? null);
        $t->same(4, $features['byKind']['chart']['relationshipCount'] ?? null);
        $t->same(2, $features['byKind']['pivotTable']['count'] ?? null);
        $t->same(1, $features['byKind']['pivotCacheDefinition']['count'] ?? null);
        $t->same(1, $features['byKind']['pivotCacheRecords']['count'] ?? null);
        $t->same(2, $features['byKind']['slicer']['count'] ?? null);
        $t->same(1, $features['byKind']['slicerCache']['count'] ?? null);

        $drawing = $findPart($features['byKind']['drawing']['items'], 'xl/drawings/drawing1.xml');
        $t->same('drawing-metadata-only', $drawing['reviewPolicy']);
        $t->same('drawing-part-bytes-blocked', $drawing['byteExposurePolicy']);
        $t->same('wsDr', $drawing['rootLocalName']);
        $t->same(true, $drawing['validRoot']);
        $t->same('xl/worksheets/sheet1.xml', $drawing['relationshipRefs'][0]['sourcePart']);

        $chart = $findPart($features['byKind']['chart']['items'], 'xl/charts/chart1.xml');
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.chart+xml; profile=quarterly', $chart['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.chart+xml', $chart['contentTypeBase']);
        $t->same(true, $chart['contentTypeMatchesExpected']);
        $t->same('chartSpace', $chart['rootLocalName']);
        $t->same(true, $chart['validRoot']);
        $t->same('xl/drawings/drawing1.xml', $chart['relationshipRefs'][0]['sourcePart']);
        $t->same('xl/drawings/_rels/drawing1.xml.rels', $chart['relationshipRefs'][0]['relationshipPart']);
        $t->same('xl/charts/chart1.xml', $chart['relationshipRefs'][0]['targetPart']);
        $t->same('series=1', $chart['relationshipRefs'][0]['targetQuery']);
        $t->same('main', $chart['relationshipRefs'][0]['targetFragment']);
        $t->same('chart-metadata-only', $chart['reviewPolicy']);
        $t->same('chart-part-bytes-blocked', $chart['byteExposurePolicy']);

        $badChart = $findPart($features['byKind']['chart']['items'], 'xl/charts/bad-chart.xml');
        $t->same(false, $badChart['contentTypeMatchesExpected']);
        $t->same(false, $badChart['validRoot']);
        $t->same(['unexpected-chart-content-type', 'unexpected-chart-root'], $badChart['issues']);
        $missingChart = $findPart($features['byKind']['chart']['items'], 'xl/charts/missing-chart.xml');
        $t->same(false, $missingChart['exists']);
        $t->contains('missing-chart-part', implode(',', $missingChart['issues']));
        $externalChart = $findExternal($features['byKind']['chart']['items']);
        $t->same('https://example.test/chart.xml?remote=1#chart', $externalChart['externalTarget']);
        $t->same(['external-chart-part'], $externalChart['issues']);

        $pivotTable = $findPart($features['byKind']['pivotTable']['items'], 'xl/pivotTables/pivotTable1.xml');
        $t->same('pivotTableDefinition', $pivotTable['rootLocalName']);
        $t->same(true, $pivotTable['validRoot']);
        $t->same('xl/worksheets/sheet1.xml', $pivotTable['relationshipRefs'][0]['sourcePart']);
        $missingPivot = $findPart($features['byKind']['pivotTable']['items'], 'xl/pivotTables/missingPivot.xml');
        $t->same(false, $missingPivot['exists']);
        $t->same(['missing-pivot-table-part'], $missingPivot['issues']);
        $pivotCache = $findPart($features['byKind']['pivotCacheDefinition']['items'], 'xl/pivotCache/pivotCacheDefinition1.xml');
        $t->same('pivotCacheDefinition', $pivotCache['rootLocalName']);
        $t->same('xl/workbook.xml', $pivotCache['relationshipRefs'][0]['sourcePart']);
        $pivotRecords = $findPart($features['byKind']['pivotCacheRecords']['items'], 'xl/pivotCache/pivotCacheRecords1.xml');
        $t->same('pivotCacheRecords', $pivotRecords['rootLocalName']);
        $t->same('xl/pivotCache/pivotCacheDefinition1.xml', $pivotRecords['relationshipRefs'][0]['sourcePart']);

        $slicer = $findPart($features['byKind']['slicer']['items'], 'xl/slicers/slicer1.xml');
        $t->same('slicers', $slicer['rootLocalName']);
        $t->same(true, $slicer['validRoot']);
        $t->same('xl/worksheets/sheet1.xml', $slicer['relationshipRefs'][0]['sourcePart']);
        $externalSlicer = $findExternal($features['byKind']['slicer']['items']);
        $t->same('https://example.test/slicer.xml?view=west#slicer', $externalSlicer['externalTarget']);
        $t->same('view=west', $externalSlicer['relationshipRefs'][0]['targetQuery']);
        $t->same('slicer', $externalSlicer['relationshipRefs'][0]['targetFragment']);
        $slicerCache = $findPart($features['byKind']['slicerCache']['items'], 'xl/slicerCaches/slicerCache1.xml');
        $t->same('slicerCaches', $slicerCache['rootLocalName']);
        $t->same('xl/workbook.xml', $slicerCache['relationshipRefs'][0]['sourcePart']);
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
