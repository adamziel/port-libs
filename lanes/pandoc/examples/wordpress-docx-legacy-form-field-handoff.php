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
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Reviewer </w:t></w:r>
      <w:r>
        <w:fldChar w:fldCharType="begin">
          <w:ffData>
            <w:name w:val="ReviewerName"/>
            <w:enabled/>
            <w:textInput>
              <w:type w:val="regular"/>
              <w:default w:val="Migration Desk"/>
              <w:maxLength w:val="32"/>
            </w:textInput>
          </w:ffData>
        </w:fldChar>
      </w:r>
      <w:r><w:instrText xml:space="preserve"> FORMTEXT </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Migration Desk</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> confirmed </w:t></w:r>
      <w:r>
        <w:fldChar w:fldCharType="begin">
          <w:ffData>
            <w:name w:val="ApprovalStatus"/>
            <w:ddList>
              <w:default w:val="1"/>
              <w:result w:val="2"/>
              <w:listEntry w:val="Draft"/>
              <w:listEntry w:val="Approved for staging"/>
              <w:listEntry w:val="Published"/>
            </w:ddList>
          </w:ffData>
        </w:fldChar>
      </w:r>
      <w:r><w:instrText xml:space="preserve"> FORMDROPDOWN </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Approved for staging</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'class="docx-field docx-field-formtext docx-form-field docx-form-field-text',
        'data-docx-form-field-name="ReviewerName"',
        'data-docx-form-field-text-max-length="32">Migration Desk</span>',
        'class="docx-field docx-field-formdropdown docx-form-field docx-form-field-dropdown',
        'data-docx-form-field-dropdown-entry-2="Approved for staging"',
    ] as $expected) {
        if (!str_contains($blocks, $expected)) {
            throw new RuntimeException('DOCX legacy form-field handoff did not preserve expected WordPress block metadata: ' . $expected);
        }
    }

    echo "wordpress-docx-legacy-form-field-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
