<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$outputDir = $repoRoot . '/.upstream-cache/pandoc-reader-targeted-ooxml-samples';

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, "Usage: php tools/pandoc-reader-targeted-ooxml-samples.php [--output-dir=PATH]\n");
        exit(0);
    }
    if (str_starts_with($argument, '--output-dir=')) {
        $outputDir = normalizePath($repoRoot, substr($argument, strlen('--output-dir=')));
        continue;
    }
    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

ensureDirectory($outputDir);

$docxPath = $outputDir . '/word-diagram-smartart.docx';
$xlsxPath = $outputDir . '/xlsx-formulas.xlsx';

writeDocxDiagramSample($docxPath);
writeXlsxFormulaSample($xlsxPath);

$manifest = [
    'title' => 'Targeted OOXML actual output: DOCX diagrams and XLSX formulas',
    'description' => 'Synthetic OOXML packages for checking actual local-port output against pandoc output.',
    'entries' => [
        [
            'id' => 'word-diagram-smartart',
            'format' => 'docx',
            'path' => relativePath($repoRoot, $docxPath),
            'pandocFormat' => 'docx',
            'sourceKind' => 'synthetic-ooxml',
            'description' => 'DOCX paragraph containing a DrawingML diagram graphicData payload.',
            'features' => ['docx', 'drawingml', 'diagram', 'smartart-placeholder'],
            'sha256' => hash_file('sha256', $docxPath),
            'readerOptions' => [],
        ],
        [
            'id' => 'xlsx-formulas',
            'format' => 'xlsx',
            'path' => relativePath($repoRoot, $xlsxPath),
            'pandocFormat' => 'xlsx',
            'sourceKind' => 'synthetic-ooxml',
            'description' => 'XLSX worksheet with cached formulas, one uncached formula, and one formula error cell.',
            'features' => ['xlsx', 'formulas', 'cached-values', 'uncached-formula', 'formula-error'],
            'sha256' => hash_file('sha256', $xlsxPath),
            'readerOptions' => [],
        ],
    ],
];

$manifestPath = $outputDir . '/manifest.json';
file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"
);

fwrite(STDOUT, "Wrote {$docxPath}\n");
fwrite(STDOUT, "Wrote {$xlsxPath}\n");
fwrite(STDOUT, "Wrote {$manifestPath}\n");

function writeDocxDiagramSample(string $path): void
{
    $parts = [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/diagrams/data1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml"/>
  <Override PartName="/word/diagrams/layout1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramLayout+xml"/>
  <Override PartName="/word/diagrams/quickStyle1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml"/>
  <Override PartName="/word/diagrams/colors1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramColors+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDiagramData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="diagrams/data1.xml"/>
  <Relationship Id="rIdDiagramLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="diagrams/layout1.xml"/>
  <Relationship Id="rIdDiagramQuickStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle" Target="diagrams/quickStyle1.xml"/>
  <Relationship Id="rIdDiagramColors" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors" Target="diagrams/colors1.xml"/>
</Relationships>
XML,
        'word/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Normal">
    <w:name w:val="Normal"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="heading 1"/>
    <w:basedOn w:val="Normal"/>
    <w:qFormat/>
    <w:pPr><w:outlineLvl w:val="0"/></w:pPr>
  </w:style>
</w:styles>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:r><w:t>Word diagram sample</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Before diagram </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:extent cx="2743200" cy="1371600"/>
            <wp:docPr id="1" name="SmartArt Process Diagram" descr="Three step process diagram"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram">
                <dgm:relIds r:dm="rIdDiagramData" r:lo="rIdDiagramLayout" r:qs="rIdDiagramQuickStyle" r:cs="rIdDiagramColors"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r><w:t xml:space="preserve"> after diagram.</w:t></w:r>
    </w:p>
    <w:sectPr>
      <w:pgSz w:w="12240" w:h="15840"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
    </w:sectPr>
  </w:body>
</w:document>
XML,
        'word/diagrams/data1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<dgm:dataModel xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"/>
XML,
        'word/diagrams/layout1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<dgm:layoutDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="process"/>
XML,
        'word/diagrams/quickStyle1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<dgm:styleDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="simple"/>
XML,
        'word/diagrams/colors1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<dgm:colorsDef xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram" uniqueId="accent"/>
XML,
    ];

    writeZip($path, $parts);
}

function writeXlsxFormulaSample(string $path): void
{
    $parts = [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML,
        'xl/_rels/workbook.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdSharedStrings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML,
        'xl/workbook.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <calcPr calcId="191029" calcMode="auto"/>
  <sheets>
    <sheet name="Formulas" sheetId="1" r:id="rIdSheet1"/>
  </sheets>
</workbook>
XML,
        'xl/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>
XML,
        'xl/sharedStrings.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="15" uniqueCount="15">
  <si><t>item</t></si>
  <si><t>qty</t></si>
  <si><t>unit_price</t></si>
  <si><t>total</t></si>
  <si><t>note</t></si>
  <si><t>notebooks</t></si>
  <si><t>cached multiplication</t></si>
  <si><t>pens</t></si>
  <si><t>cached decimal multiplication</t></si>
  <si><t>subtotal</t></si>
  <si><t>cached SUM formula</t></si>
  <si><t>uncached</t></si>
  <si><t>formula has no cached value</t></si>
  <si><t>error</t></si>
  <si><t>formula error cache</t></si>
</sst>
XML,
        'xl/worksheets/sheet1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="A1:E6"/>
  <sheetData>
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="B1" t="s"><v>1</v></c>
      <c r="C1" t="s"><v>2</v></c>
      <c r="D1" t="s"><v>3</v></c>
      <c r="E1" t="s"><v>4</v></c>
    </row>
    <row r="2">
      <c r="A2" t="s"><v>5</v></c>
      <c r="B2"><v>3</v></c>
      <c r="C2"><v>4</v></c>
      <c r="D2"><f>B2*C2</f><v>12</v></c>
      <c r="E2" t="s"><v>6</v></c>
    </row>
    <row r="3">
      <c r="A3" t="s"><v>7</v></c>
      <c r="B3"><v>10</v></c>
      <c r="C3"><v>1.5</v></c>
      <c r="D3"><f>B3*C3</f><v>15</v></c>
      <c r="E3" t="s"><v>8</v></c>
    </row>
    <row r="4">
      <c r="A4" t="s"><v>9</v></c>
      <c r="D4"><f>SUM(D2:D3)</f><v>27</v></c>
      <c r="E4" t="s"><v>10</v></c>
    </row>
    <row r="5">
      <c r="A5" t="s"><v>11</v></c>
      <c r="D5"><f>D4*2</f></c>
      <c r="E5" t="s"><v>12</v></c>
    </row>
    <row r="6">
      <c r="A6" t="s"><v>13</v></c>
      <c r="D6" t="e"><f>1/0</f><v>#DIV/0!</v></c>
      <c r="E6" t="s"><v>14</v></c>
    </row>
  </sheetData>
</worksheet>
XML,
    ];

    writeZip($path, $parts);
}

/**
 * @param array<string, string> $parts
 */
function writeZip(string $path, array $parts): void
{
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException("Unable to replace {$path}");
    }
    ensureDirectory(dirname($path));
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) {
        throw new RuntimeException("Unable to create {$path}");
    }
    foreach ($parts as $name => $bytes) {
        if (!$zip->addFromString($name, $bytes)) {
            $zip->close();
            throw new RuntimeException("Unable to add {$name} to {$path}");
        }
    }
    $zip->close();
}

function ensureDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("Unable to create directory {$path}");
    }
}

function normalizePath(string $repoRoot, string $path): string
{
    if ($path === '') {
        return $repoRoot;
    }
    if ($path[0] === '/') {
        return $path;
    }

    return $repoRoot . '/' . $path;
}

function relativePath(string $base, string $path): string
{
    $base = rtrim($base, '/') . '/';
    if (str_starts_with($path, $base)) {
        return substr($path, strlen($base));
    }

    return $path;
}
