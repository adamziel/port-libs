<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\XlsxReader;

return [
    'reads xlsx workbook sheets shared strings styles and tables into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary XLSX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary XLSX package');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdOffice" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"><dc:title>XLSX Reader Demo</dc:title><dc:creator>Port Libs</dc:creator><dc:description>Bounded XLSX reader smoke.</dc:description><cp:keywords>sheets,import</cp:keywords><dcterms:created>2026-06-25T00:00:00Z</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0"?>
<workbook
  xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Main" sheetId="1" r:id="rIdSheet1"/>
    <sheet name="Secondary" sheetId="2" r:id="rIdSheet2"/>
  </sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rIdSheet2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rIdShared" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <si><t>Person</t></si>
  <si><t>Age</t></si>
  <si><t>Location</t></si>
  <si><r><t>Ada </t></r><r><t>Lovelace</t></r></si>
  <si><r><rPr><b/></rPr><t>Rich</t></r><r><rPr><i/></rPr><t> text</t></r></si>
</sst>
XML);
        $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3"><font/><font><b/></font><font><i/><u/></font></fonts>
  <cellXfs count="3"><xf fontId="0"/><xf fontId="1" applyFont="1"/><xf fontId="2" applyFont="1"/></cellXfs>
</styleSheet>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>
    <row r="1"><c r="A1" t="s" s="1"><v>0</v></c><c r="B1" t="s" s="1"><v>1</v></c><c r="C1" t="s" s="1"><v>2</v></c></row>
    <row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2"><v>36.5</v></c><c r="C2" t="inlineStr"><is><t>London</t></is></c></row>
    <row r="3"><c r="A3" t="b"><v>1</v></c><c r="B3" t="inlineStr" s="2"><is><t>Under italic</t></is></c><c r="C3" t="s"><v>4</v></c></row>
    <row r="4"><c r="A4" t="inlineStr"><is><t>Merged Area</t></is></c><c r="C4" t="inlineStr"><is><t>Open link</t></is></c></row>
    <row r="5"><c r="A5" t="inlineStr"><is><t>Tail cell</t></is></c></row>
  </sheetData>
  <mergeCells count="1"><mergeCell ref="A4:B4"/></mergeCells>
  <hyperlinks><hyperlink ref="C4" r:id="rIdLink" tooltip="XLSX link"/></hyperlinks>
</worksheet>
XML);
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/xlsx" TargetMode="External"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet2.xml', <<<'XML'
<?xml version="1.0"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" t="inlineStr"><is><t>Status</t></is></c></row>
    <row r="2"><c r="A2" t="inlineStr"><is><t>Imported</t></is></c></row>
  </sheetData>
</worksheet>
XML);
        $zip->close();

        try {
            $document = (new XlsxReader())->readXlsxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'xlsx', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('XLSX Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded XLSX reader smoke.', $meta['description']);
        $t->same('sheets,import', $meta['keywords']);
        $t->same('xl/workbook.xml', $meta['xlsxWorkbookPath']);
        $t->same(2, $meta['xlsxSheetCount']);
        $t->same(5, $meta['xlsxSharedStringCount']);
        $t->same(3, $meta['xlsxStyleFontCount']);
        $t->same('div', $document->children[0]->type);
        $t->same(['xlsx-sheet'], $document->children[0]->attr('classes'));
        $t->same('Main', $document->children[0]->children[0]->attr('text'));
        $t->same('Secondary', $document->children[1]->children[0]->attr('text'));
        $table = $document->children[0]->children[1];
        $body = $table->children[1];
        $mergedCell = $body->children[2]->children[0];
        $linkedCell = $body->children[2]->children[1];
        $t->same(2, $mergedCell->attr('colspan'));
        $t->same('Merged Area', $mergedCell->attr('text'));
        $t->same('link', $linkedCell->children[0]->children[0]->type);
        $t->same('https://example.test/xlsx', $linkedCell->children[0]->children[0]->attr('url'));
        $t->contains('class="xlsx-sheet"', $blocks);
        $t->contains('<h2 id="sheet-1">Main</h2>', $blocks);
        $t->contains('<strong>Person</strong>', $blocks);
        $t->contains('Ada Lovelace', $blocks);
        $t->contains('36.5', $blocks);
        $t->contains('TRUE', $blocks);
        $t->contains('<u><em>Under italic</em></u>', $blocks);
        $t->contains('<strong>Rich</strong><em> text</em>', $blocks);
        $t->contains('<td colspan="2">Merged Area</td>', $blocks);
        $t->contains('<a href="https://example.test/xlsx" title="XLSX link">Open link</a>', $blocks);
        $t->contains('Tail cell', $blocks);
        $t->contains('Imported', $converterBlocks);
    },
    'reads xlsx bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-bytes-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary XLSX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary XLSX package');
        }
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdOffice" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Bytes" sheetId="1" r:id="rIdSheet1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Byte XLSX</t></is></c></row></sheetData></worksheet>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary XLSX package');
            }
            $document = PandocConverter::read($bytes, 'xlsx');
        } finally {
            @unlink($path);
        }

        $t->same('div', $document->children[0]->type);
        $t->same('Bytes', $document->children[0]->children[0]->attr('text'));
        $table = $document->children[0]->children[1];
        $t->same('table', $table->type);
        $t->same('Byte XLSX', $table->children[0]->children[0]->children[0]->attr('text'));
    },
];
