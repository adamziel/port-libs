<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$package = ZipPackage::fromParts([
    ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
</Types>
XML],
    ['name' => '_rels/.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML],
    ['name' => 'word/_rels/settings.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMergeSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/mailMergeSource" Target="file:///C:/legacy/review-source.xlsx" TargetMode="External"/>
  <Relationship Id="rIdMergeHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/mailMergeHeaderSource" Target="../mailmerge/header-source.xml"/>
</Relationships>
XML],
    ['name' => 'word/settings.xml', 'data' => <<<'XML'
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:mailMerge>
    <w:mainDocumentType w:val="email"/>
    <w:destination w:val="email"/>
    <w:dataType w:val="native"/>
    <w:connectString w:val="Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:\legacy\review-source.xlsx;Mode=Read"/>
    <w:query w:val="SELECT * FROM [SourcePackets$]"/>
    <w:dataSource r:id="rIdMergeSource"/>
    <w:headerSource r:id="rIdMergeHeader"/>
    <w:viewMergedData/>
    <w:linkToQuery/>
    <w:doNotSuppressBlankLines w:val="0"/>
    <w:activeRecord w:val="2"/>
    <w:mailSubject w:val="Review import packet"/>
  </w:mailMerge>
</w:settings>
XML],
    ['name' => 'mailmerge/header-source.xml', 'data' => '<headers><field name="ReviewerEmail"/><field name="SourcePacket"/></headers>'],
    ['name' => 'word/document.xml', 'data' => <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Mail-merge packet body remains visible.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
]);

$result = (new DocxReader())->readPackage($package);
$mailMerge = $result['importReport']['settings']['mailMerge'] ?? [];
$blocks = (new WordPressBlockWriter())->write($result['document']);

if (in_array('--self-test', $argv, true)) {
    if (($mailMerge['mainDocumentType'] ?? null) !== 'email' || ($mailMerge['destination'] ?? null) !== 'email') {
        throw new RuntimeException('DOCX mail-merge settings handoff did not preserve document type and destination');
    }
    if (($mailMerge['dataSource']['externalTargetAllowed'] ?? null) !== false || ($mailMerge['dataSource']['issues'] ?? []) !== ['external-target-unsafe-scheme']) {
        throw new RuntimeException('DOCX mail-merge settings handoff did not flag the external file data source');
    }
    if (($mailMerge['headerSource']['targetPart'] ?? null) !== '/mailmerge/header-source.xml' || ($mailMerge['headerSource']['exists'] ?? null) !== true) {
        throw new RuntimeException('DOCX mail-merge settings handoff did not preserve the header-source part');
    }
    if (!isset($mailMerge['connectStringSha256']) || isset($mailMerge['connectString'])) {
        throw new RuntimeException('DOCX mail-merge settings handoff exposed a raw connection string');
    }
    if (!str_contains($blocks, '<p>Mail-merge packet body remains visible.</p>')) {
        throw new RuntimeException('DOCX mail-merge settings handoff did not render the document body');
    }

    echo "wordpress-docx-mail-merge-settings-handoff self-test passed\n";
    return;
}

echo json_encode([
    'mailMerge' => $mailMerge,
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
