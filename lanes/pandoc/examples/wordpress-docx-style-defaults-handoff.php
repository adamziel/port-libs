<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML],
    ['name' => 'word/styles.xml', 'data' => <<<'XML'
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:i/>
        <w:highlight w:val="yellow"/>
        <w:lang w:val="en-US"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:jc w:val="center"/>
        <w:spacing w:before="120" w:after="60"/>
        <w:keepNext/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:styleId="ReviewerOverride">
    <w:name w:val="Reviewer Override"/>
    <w:pPr>
      <w:jc w:val="end"/>
      <w:pageBreakBefore/>
    </w:pPr>
    <w:rPr>
      <w:b/>
      <w:i w:val="0"/>
      <w:highlight w:val="none"/>
      <w:lang w:val="fr-FR"/>
    </w:rPr>
  </w:style>
</w:styles>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Defaulted reviewer note.</w:t></w:r></w:p>
    <w:p>
      <w:pPr><w:pStyle w:val="ReviewerOverride"/></w:pPr>
      <w:r><w:t>Override reviewer note.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$document = (new DocxReader())->readDocument($package);
$blocks = (new WordPressBlockWriter())->write($document);

if (in_array('--self-test', $argv, true)) {
    $defaultParagraph = $document->children[0] ?? null;
    if (!$defaultParagraph instanceof AstNode || $defaultParagraph->type !== 'paragraph') {
        throw new RuntimeException('DOCX style-defaults example did not import the default paragraph');
    }

    $defaultSpan = $defaultParagraph->children[0] ?? null;
    if (!$defaultSpan instanceof AstNode || !in_array('docx-align-center', $defaultSpan->attr('classes', []), true)) {
        throw new RuntimeException('DOCX style-defaults example did not expose document default paragraph alignment');
    }

    $defaultRun = $defaultSpan->children[0] ?? null;
    if (!$defaultRun instanceof AstNode || !in_array('docx-highlight-yellow', $defaultRun->attr('classes', []), true)) {
        throw new RuntimeException('DOCX style-defaults example did not expose document default run highlighting');
    }

    $overrideSpan = $document->children[1]->children[0] ?? null;
    if (!$overrideSpan instanceof AstNode || !in_array('docx-align-end', $overrideSpan->attr('classes', []), true)) {
        throw new RuntimeException('DOCX style-defaults example did not expose paragraph style override alignment');
    }

    $overrideRun = $overrideSpan->children[0] ?? null;
    if (!$overrideRun instanceof AstNode || in_array('docx-highlight-yellow', $overrideRun->attr('classes', []), true)) {
        throw new RuntimeException('DOCX style-defaults example did not suppress default highlight through paragraph style');
    }

    if (!str_contains($blocks, 'class="docx-paragraph-align docx-align-center docx-paragraph-spacing docx-keep-next"')) {
        throw new RuntimeException('DOCX style-defaults example did not render default paragraph metadata to WordPress blocks');
    }
    if (!str_contains($blocks, '<strong>Override reviewer note.</strong>')) {
        throw new RuntimeException('DOCX style-defaults example did not render override run style to WordPress blocks');
    }

    echo "wordpress-docx-style-defaults-handoff self-test passed\n";
    return;
}

echo json_encode([
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
