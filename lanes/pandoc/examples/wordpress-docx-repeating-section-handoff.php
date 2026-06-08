<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml">
  <w:body>
    <w:sdt>
      <w:sdtPr>
        <w:id w:val="410"/>
        <w:alias w:val="Import Checklist"/>
        <w:tag w:val="import_checklist"/>
        <w15:repeatingSection>
          <w15:sectionTitle w15:val="Checklist row"/>
          <w15:doNotAllowInsertDeleteSection/>
        </w15:repeatingSection>
      </w:sdtPr>
      <w:sdtContent>
        <w:sdt>
          <w:sdtPr>
            <w:id w:val="411"/>
            <w:alias w:val="Checklist Item"/>
            <w:tag w:val="checklist_item"/>
            <w15:repeatingSectionItem/>
          </w:sdtPr>
          <w:sdtContent>
            <w:p><w:r><w:t>Review imported media captions.</w:t></w:r></w:p>
          </w:sdtContent>
        </w:sdt>
      </w:sdtContent>
    </w:sdt>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'class="docx-content-control docx-content-control-repeating-section"',
        'data-docx-sdt-repeating-section-title="Checklist row"',
        'data-docx-sdt-repeating-section-do-not-allow-insert-delete="true"',
        'class="docx-content-control docx-content-control-repeating-section-item"',
        'data-docx-sdt-repeating-section-item="true"',
        '<p>Review imported media captions.</p>',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX repeating-section handoff did not preserve expected WordPress block metadata: ' . $expected);
        }
    }

    echo "wordpress-docx-repeating-section-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
