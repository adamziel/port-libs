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
  <Override PartName="/word/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
</Relationships>
XML],
    ['name' => 'word/theme/theme1.xml', 'data' => <<<'XML'
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="WordPress Review Theme">
  <a:themeElements>
    <a:clrScheme name="WordPress Review Colors">
      <a:dk1><a:sysClr val="windowText" lastClr="111111"/></a:dk1>
      <a:accent1><a:srgbClr val="4472C4"/></a:accent1>
      <a:accent2><a:srgbClr val="ED7D31"/></a:accent2>
      <a:folHlink><a:srgbClr val="954F72"/></a:folHlink>
    </a:clrScheme>
  </a:themeElements>
</a:theme>
XML],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Theme review </w:t></w:r>
      <w:r><w:rPr><w:color w:themeColor="accent1" w:themeTint="33"/></w:rPr><w:t>accent link</w:t></w:r>
      <w:r><w:t xml:space="preserve"> and </w:t></w:r>
      <w:r><w:rPr><w:shd w:val="clear" w:themeFill="accent2" w:themeColor="text1"/></w:rPr><w:t>shaded source</w:t></w:r>
      <w:r><w:t xml:space="preserve"> plus </w:t></w:r>
      <w:r><w:rPr><w:color w:themeColor="followedHyperlink"/></w:rPr><w:t>visited source</w:t></w:r>
      <w:r><w:t>.</w:t></w:r>
    </w:p>
  </w:body>
</w:document>
XML],
]);

$result = (new DocxReader())->readPackage($package);
$document = $result['document'];
$blocks = (new WordPressBlockWriter())->write($document);
$theme = $result['metadata']['docxTheme'] ?? [];
$paragraph = $document->children[0] ?? null;

if (in_array('--self-test', $argv, true)) {
    if (($theme['colors']['byName']['accent1'] ?? null) !== '4472C4') {
        throw new RuntimeException('DOCX theme color handoff did not preserve accent1 palette metadata');
    }

    $accent = $paragraph instanceof AstNode ? ($paragraph->children[1] ?? null) : null;
    if (!$accent instanceof AstNode) {
        throw new RuntimeException('DOCX theme color handoff did not import reviewer spans');
    }

    if (($accent->attr('attributes')['data-docx-theme-color-rgb'] ?? null) !== '4472C4') {
        throw new RuntimeException('DOCX theme color handoff did not resolve accent theme color metadata');
    }

    if (!str_contains($blocks, 'data-docx-theme-color-rgb="4472C4"')) {
        throw new RuntimeException('DOCX theme color handoff did not render theme color metadata into WordPress blocks');
    }

    if (!str_contains($blocks, 'data-docx-shading-theme-fill-rgb="ED7D31"')) {
        throw new RuntimeException('DOCX theme color handoff did not render theme fill metadata into WordPress blocks');
    }

    if (!str_contains($blocks, 'data-docx-theme-color="followedHyperlink" data-docx-theme-color-rgb="954F72"')) {
        throw new RuntimeException('DOCX theme color handoff did not preserve followed-hyperlink theme metadata');
    }

    echo "wordpress-docx-theme-color-handoff self-test passed\n";
    return;
}

echo json_encode([
    'theme' => $theme,
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
