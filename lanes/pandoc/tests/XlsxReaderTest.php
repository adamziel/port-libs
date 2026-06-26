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
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/><Override PartName="/xl/tables/table1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.table+xml"/><Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/><Override PartName="/xl/comments1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdOffice" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"><dc:title>XLSX Reader Demo</dc:title><dc:creator>Port Libs</dc:creator><dc:description>Bounded XLSX reader smoke.</dc:description><cp:keywords>sheets,import</cp:keywords><dcterms:created>2026-06-25T00:00:00Z</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0"?>
<workbook
  xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Main" sheetId="1" r:id="rIdSheet1"/>
    <sheet name="Secondary" sheetId="2" state="hidden" r:id="rIdSheet2"/>
  </sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rIdSheet2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rIdShared" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rIdTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/></Relationships>');
        $zip->addFromString('xl/theme/theme1.xml', <<<'XML'
<?xml version="1.0"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Demo Theme">
  <a:themeElements><a:clrScheme name="Demo"><a:dk1><a:srgbClr val="000000"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F2937"/></a:dk2><a:lt2><a:srgbClr val="F3F4F6"/></a:lt2><a:accent1><a:srgbClr val="4472C4"/></a:accent1><a:accent2><a:srgbClr val="ED7D31"/></a:accent2><a:accent3><a:srgbClr val="A5A5A5"/></a:accent3><a:accent4><a:srgbClr val="FFC000"/></a:accent4><a:accent5><a:srgbClr val="5B9BD5"/></a:accent5><a:accent6><a:srgbClr val="70AD47"/></a:accent6></a:clrScheme></a:themeElements>
</a:theme>
XML);
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
  <numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd h:mm"/><numFmt numFmtId="165" formatCode="&quot;$&quot;#,##0.00;[Red](&quot;$&quot;#,##0.00)"/></numFmts>
  <fonts count="5"><font/><font><b/></font><font><i/><u/></font><font><strike/><color rgb="FFFF0000"/></font><font><name val="Aptos"/><sz val="11"/><color theme="4" tint="0.5"/></font></fonts>
  <fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill></fills>
  <borders count="2"><border/><border><left style="thin"><color rgb="FF666666"/></left><right style="thin"><color rgb="FF666666"/></right><top style="thin"><color rgb="FF666666"/></top><bottom style="thin"><color rgb="FF666666"/></bottom></border></borders>
  <cellXfs count="9">
    <xf fontId="0" numFmtId="0"/>
    <xf fontId="1" numFmtId="0" applyFont="1"/>
    <xf fontId="2" numFmtId="0" applyFont="1"/>
    <xf fontId="0" numFmtId="14" applyNumberFormat="1"/>
    <xf fontId="0" numFmtId="10" applyNumberFormat="1"/>
    <xf fontId="3" fillId="2" numFmtId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
    <xf fontId="0" numFmtId="164" applyNumberFormat="1"/>
    <xf fontId="4" borderId="1" numFmtId="165" applyFont="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment vertical="bottom"/></xf>
    <xf fontId="4" borderId="1" numFmtId="12" applyFont="1" applyBorder="1" applyNumberFormat="1"/>
  </cellXfs>
</styleSheet>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <cols><col min="3" max="3" width="22" hidden="1" customWidth="1"/></cols>
  <sheetData>
    <row r="1"><c r="A1" t="s" s="1"><v>0</v></c><c r="B1" t="s" s="1"><v>1</v></c><c r="C1" t="s" s="1"><v>2</v></c></row>
    <row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2"><v>36.5</v></c><c r="C2" t="inlineStr"><is><t>London</t></is></c></row>
    <row r="3"><c r="A3" t="b"><v>1</v></c><c r="B3" t="inlineStr" s="2"><is><t>Under italic</t></is></c><c r="C3" t="s"><v>4</v></c></row>
    <row r="4"><c r="A4" t="inlineStr"><is><t>Merged Area</t></is></c><c r="C4" t="inlineStr"><is><t>Open link</t></is></c></row>
    <row r="5"><c r="A5" t="inlineStr"><is><t>Tail cell</t></is></c><c r="B5" s="3"><v>46199</v></c><c r="C5" s="4"><v>0.125</v></c></row>
    <row r="6" hidden="1" ht="24" customHeight="1"><c r="A6" t="inlineStr" s="5"><is><t>Styled cell</t></is></c><c r="B6" s="6"><v>46199.5</v></c></row>
    <row r="7"><c r="A7" t="inlineStr"><is><t>Formats</t></is></c><c r="B7" s="7"><v>-1234.5</v></c><c r="C7" s="8"><v>2.25</v></c></row>
    <row r="8"><c r="A8" t="inlineStr"><is><t>Formula row</t></is></c><c r="B8"><f>SUM(B2:B7)</f><v>38</v></c><c r="C8" t="e"><f t="array" ref="C8:C9" si="4">1/0</f><v>#DIV/0!</v></c></row>
  </sheetData>
  <mergeCells count="1"><mergeCell ref="A4:B4"/></mergeCells>
  <autoFilter ref="A1:C8"/>
  <hyperlinks><hyperlink ref="C4" r:id="rIdLink" tooltip="XLSX link"/></hyperlinks>
  <drawing r:id="rIdDrawing"/>
  <tableParts count="1"><tablePart r:id="rIdTable"/></tableParts>
</worksheet>
XML);
        $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/xlsx" TargetMode="External"/><Relationship Id="rIdDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/><Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments1.xml"/><Relationship Id="rIdTable" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table1.xml"/></Relationships>');
        $zip->addFromString('xl/tables/table1.xml', <<<'XML'
<?xml version="1.0"?>
<table
  xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
  id="1"
  name="PeopleTable"
  displayName="People Table"
  ref="A1:C8"
  headerRowCount="1"
  totalsRowCount="0">
  <autoFilter ref="A1:C8"/>
  <tableColumns count="3"><tableColumn id="1" name="Person"/><tableColumn id="2" name="Age"/><tableColumn id="3" name="Location"/></tableColumns>
  <tableStyleInfo name="TableStyleMedium2" showFirstColumn="0" showLastColumn="0" showRowStripes="1" showColumnStripes="0"/>
</table>
XML);
        $zip->addFromString('xl/comments1.xml', <<<'XML'
<?xml version="1.0"?>
<comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <authors><author>Reviewer</author></authors>
  <commentList><comment ref="A2" authorId="0"><text><t>Check person label</t></text></comment></commentList>
</comments>
XML);
        $zip->addFromString('xl/drawings/drawing1.xml', <<<'XML'
<?xml version="1.0"?>
<xdr:wsDr
  xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <xdr:twoCellAnchor>
    <xdr:from><xdr:col>1</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>6</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>
    <xdr:to><xdr:col>3</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>12</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="2" name="Chart image" descr="Quarter chart"/></xdr:nvPicPr>
      <xdr:blipFill><a:blip r:embed="rIdImage"/></xdr:blipFill>
      <xdr:spPr/>
    </xdr:pic>
    <xdr:clientData/>
  </xdr:twoCellAnchor>
</xdr:wsDr>
XML);
        $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/></Relationships>');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);
        if (!is_string($image)) {
            throw new RuntimeException('Unable to decode PNG fixture');
        }
        $zip->addFromString('xl/media/image1.png', $image);
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
        $t->same(5, $meta['xlsxStyleFontCount']);
        $t->same('div', $document->children[0]->type);
        $t->same(['xlsx-sheet'], $document->children[0]->attr('classes'));
        $t->same('Main', $document->children[0]->children[0]->attr('text'));
        $t->same('Secondary', $document->children[1]->children[0]->attr('text'));
        $t->same('hidden', $document->children[1]->attr('attributes')['data-xlsx-sheet-state']);
        $table = $document->children[0]->children[1];
        $tableAttrs = $table->attr('htmlAttributes');
        $t->same('A1:C8', $tableAttrs['data-xlsx-auto-filter-ref']);
        $t->same('1', $tableAttrs['data-xlsx-table-count']);
        $t->same('PeopleTable', $tableAttrs['data-xlsx-table-names']);
        $t->same('People Table', $tableAttrs['data-xlsx-table-display-names']);
        $t->same('A1:C8', $tableAttrs['data-xlsx-table-refs']);
        $t->same('TableStyleMedium2', $tableAttrs['data-xlsx-table-style-names']);
        $t->same('Person,Age,Location', $tableAttrs['data-xlsx-table-columns']);
        $t->same('true', $tableAttrs['data-xlsx-table-show-row-stripes']);
        $body = $table->children[1];
        $commentedCell = $body->children[0]->children[0];
        $mergedCell = $body->children[2]->children[0];
        $linkedCell = $body->children[2]->children[1];
        $dateCell = $body->children[3]->children[1];
        $percentCell = $body->children[3]->children[2];
        $styledCell = $body->children[4]->children[0];
        $dateTimeCell = $body->children[4]->children[1];
        $currencyCell = $body->children[5]->children[1];
        $fractionCell = $body->children[5]->children[2];
        $formulaCell = $body->children[6]->children[1];
        $errorCell = $body->children[6]->children[2];
        $image = $document->children[0]->children[2]->children[0];
        $t->same('A2', $commentedCell->attr('htmlAttributes')['data-xlsx-ref']);
        $t->same('Reviewer', $commentedCell->attr('htmlAttributes')['data-xlsx-comment-author']);
        $t->same('Check person label', $commentedCell->attr('htmlAttributes')['data-xlsx-comment']);
        $t->same(2, $mergedCell->attr('colspan'));
        $t->same('Merged Area', $mergedCell->attr('text'));
        $t->same('link', $linkedCell->children[0]->children[0]->type);
        $t->same('https://example.test/xlsx', $linkedCell->children[0]->children[0]->attr('url'));
        $t->same('2026-06-26', $dateCell->attr('text'));
        $t->same('date', $dateCell->attr('htmlAttributes')['data-xlsx-value-type']);
        $t->same('m/d/yy', $dateCell->attr('htmlAttributes')['data-xlsx-number-format']);
        $t->same('12.50%', $percentCell->attr('text'));
        $t->same('number', $percentCell->attr('htmlAttributes')['data-xlsx-value-type']);
        $t->same('Styled cell', $styledCell->attr('text'));
        $t->same('strikeout', $styledCell->children[0]->children[0]->type);
        $t->same('background-color:#DBEAFE;color:#FF0000;text-align:center', $styledCell->attr('htmlAttributes')['style']);
        $t->same('true', $styledCell->attr('htmlAttributes')['data-xlsx-row-hidden']);
        $t->same('24', $styledCell->attr('htmlAttributes')['data-xlsx-row-height']);
        $t->same('2026-06-26 12:00:00', $dateTimeCell->attr('text'));
        $t->same('($1,234.50)', $currencyCell->attr('text'));
        $t->same('Aptos', $currencyCell->attr('htmlAttributes')['data-xlsx-font-name']);
        $t->same('11', $currencyCell->attr('htmlAttributes')['data-xlsx-font-size']);
        $t->same('"$"#,##0.00;[Red]("$"#,##0.00)', $currencyCell->attr('htmlAttributes')['data-xlsx-number-format']);
        $t->contains('color:#A2B9E2', $currencyCell->attr('htmlAttributes')['style']);
        $t->contains('border-left:1px solid #666666', $currencyCell->attr('htmlAttributes')['style']);
        $t->same('2 1/4', $fractionCell->attr('text'));
        $t->same('true', $fractionCell->attr('htmlAttributes')['data-xlsx-column-hidden']);
        $t->same('22', $fractionCell->attr('htmlAttributes')['data-xlsx-column-width']);
        $t->same('38', $formulaCell->attr('text'));
        $t->same('SUM(B2:B7)', $formulaCell->attr('htmlAttributes')['data-xlsx-formula']);
        $t->same('number', $formulaCell->attr('htmlAttributes')['data-xlsx-value-type']);
        $t->same('#DIV/0!', $errorCell->attr('text'));
        $t->same('error', $errorCell->attr('htmlAttributes')['data-xlsx-value-type']);
        $t->same('1/0', $errorCell->attr('htmlAttributes')['data-xlsx-formula']);
        $t->same('array', $errorCell->attr('htmlAttributes')['data-xlsx-formula-type']);
        $t->same('C8:C9', $errorCell->attr('htmlAttributes')['data-xlsx-formula-ref']);
        $t->same('4', $errorCell->attr('htmlAttributes')['data-xlsx-formula-shared-index']);
        $t->same('figure', $document->children[0]->children[2]->type);
        $t->same('image', $image->type);
        $t->same('xl/media/image1.png', $image->attr('url'));
        $t->same('Quarter chart', $image->attr('alt'));
        $t->same('B7', $image->attr('attributes')['data-xlsx-anchor']);
        $t->same('D13', $image->attr('attributes')['data-xlsx-anchor-to']);
        $t->contains('class="xlsx-sheet"', $blocks);
        $t->contains('data-xlsx-table-count="1"', $blocks);
        $t->contains('data-xlsx-table-style-names="TableStyleMedium2"', $blocks);
        $t->contains('<h2 id="sheet-1">Main</h2>', $blocks);
        $t->contains('<strong>Person</strong>', $blocks);
        $t->contains('Ada Lovelace', $blocks);
        $t->contains('36.5', $blocks);
        $t->contains('TRUE', $blocks);
        $t->contains('<u><em>Under italic</em></u>', $blocks);
        $t->contains('<strong>Rich</strong><em> text</em>', $blocks);
        $t->contains('data-xlsx-ref="A4" data-xlsx-row="4" data-xlsx-column="A" data-xlsx-raw-value="Merged Area" data-xlsx-value-type="string" colspan="2">Merged Area</td>', $blocks);
        $t->contains('<a href="https://example.test/xlsx" title="XLSX link">Open link</a>', $blocks);
        $t->contains('Tail cell', $blocks);
        $t->contains('data-xlsx-raw-value="46199" data-xlsx-value-type="date" data-xlsx-number-format="m/d/yy" data-xlsx-style-index="3">2026-06-26</td>', $blocks);
        $t->contains('data-xlsx-raw-value="0.125" data-xlsx-value-type="number" data-xlsx-number-format="0.00%" data-xlsx-style-index="4"', $blocks);
        $t->contains('data-xlsx-row-hidden="true" data-xlsx-row-height="24" style="background-color:#DBEAFE;color:#FF0000;text-align:center"><del>Styled cell</del></td>', $blocks);
        $t->contains('data-xlsx-raw-value="46199.5" data-xlsx-value-type="datetime" data-xlsx-number-format="yyyy-mm-dd h:mm" data-xlsx-style-index="6"', $blocks);
        $t->contains('data-xlsx-raw-value="-1234.5" data-xlsx-value-type="number" data-xlsx-number-format="&quot;$&quot;#,##0.00;[Red](&quot;$&quot;#,##0.00)" data-xlsx-style-index="7" data-xlsx-font-name="Aptos" data-xlsx-font-size="11"', $blocks);
        $t->contains('data-xlsx-column-hidden="true" data-xlsx-column-width="22"', $blocks);
        $t->contains('data-xlsx-formula="SUM(B2:B7)">38</td>', $blocks);
        $t->contains('data-xlsx-value-type="error"', $blocks);
        $t->contains('data-xlsx-formula="1/0" data-xlsx-formula-type="array" data-xlsx-formula-ref="C8:C9" data-xlsx-formula-shared-index="4">#DIV/0!</td>', $blocks);
        $t->contains('data-xlsx-anchor-to="D13"', $blocks);
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
