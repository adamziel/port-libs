<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx document package identity target map mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries docx document package identity relationship target maps' => static function (TestRunner $t): void {
        $parts = docx_document_package_identity_target_maps_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $identity = $docx['documentPackageIdentity'];
        $summary = $docx['packageProvenance']['summary'];

        $changedParts = $parts;
        $changedParts['word/_rels/document.xml.rels'] = str_replace(
            'media/missing-preview.PNG',
            'media/other-missing-preview.PNG',
            $changedParts['word/_rels/document.xml.rels'],
        );
        $changedIdentity = (new DocxOpenXmlReader())->readPackage($changedParts)->attr('docx')['documentPackageIdentity'];

        $t->same(3, $identity['identityVersion']);
        $t->same([
            'docProps' => 1,
            'word' => 1,
        ], $identity['rootRelationshipTargetDirectoryCounts']);
        $t->same([
            'core.xml' => 1,
            'document.xml' => 1,
        ], $identity['rootRelationshipTargetBaseNameCounts']);
        $t->same([
            'core.xml' => 1,
            'document.xml' => 1,
        ], $identity['rootRelationshipTargetCaseFoldBaseNameCounts']);
        $t->same(['xml' => 2], $identity['rootRelationshipTargetPartExtensionCounts']);
        $t->same([
            'docProps/core.xml',
        ], $identity['rootRelationshipTargetPartsByCaseFoldBaseName']['core.xml']);

        $t->same(4, $identity['documentRelationshipTargetPartCount']);
        $t->same([
            'word' => 1,
            'word/media' => 3,
        ], $identity['documentRelationshipTargetDirectoryCounts']);
        $t->same([
            'Preview.PNG' => 1,
            'missing-preview.PNG' => 1,
            'preview.png' => 1,
            'settings.xml' => 1,
        ], $identity['documentRelationshipTargetBaseNameCounts']);
        $t->same([
            'missing-preview.png' => 1,
            'preview.png' => 2,
            'settings.xml' => 1,
        ], $identity['documentRelationshipTargetCaseFoldBaseNameCounts']);
        $t->same([
            'png' => 3,
            'xml' => 1,
        ], $identity['documentRelationshipTargetPartExtensionCounts']);
        $t->same([
            'preview.png' => 2,
            'settings.xml' => 1,
        ], $identity['documentRelationshipTargetExistingCaseFoldBaseNameCounts']);
        $t->same([
            'missing-preview.png' => 1,
        ], $identity['documentRelationshipTargetMissingCaseFoldBaseNameCounts']);
        $t->same([
            'word/media/Preview.PNG',
            'word/media/preview.png',
        ], $identity['documentRelationshipTargetPartsByCaseFoldBaseName']['preview.png']);
        $t->same([
            'word/settings.xml',
        ], $identity['documentRelationshipTargetPartsByCaseFoldBaseName']['settings.xml']);
        $t->true(!isset($identity['documentRelationshipTargetPartsByCaseFoldBaseName']['review']));

        $t->same(
            $identity['rootRelationshipTargetDirectoryCounts'],
            $summary['documentPackageIdentityRootRelationshipTargetDirectoryCounts']
        );
        $t->same(
            $identity['rootRelationshipTargetCaseFoldBaseNameCounts'],
            $summary['documentPackageIdentityRootRelationshipTargetCaseFoldBaseNameCounts']
        );
        $t->same(
            $identity['rootRelationshipTargetPartsByCaseFoldBaseName'],
            $summary['documentPackageIdentityRootRelationshipTargetPartsByCaseFoldBaseName']
        );
        $t->same(
            $identity['documentRelationshipTargetDirectoryCounts'],
            $summary['documentPackageIdentityDocumentRelationshipTargetDirectoryCounts']
        );
        $t->same(
            $identity['documentRelationshipTargetCaseFoldBaseNameCounts'],
            $summary['documentPackageIdentityDocumentRelationshipTargetCaseFoldBaseNameCounts']
        );
        $t->same(
            $identity['documentRelationshipTargetPartExtensionCounts'],
            $summary['documentPackageIdentityDocumentRelationshipTargetPartExtensionCounts']
        );
        $t->same(
            $identity['documentRelationshipTargetPartsByCaseFoldBaseName'],
            $summary['documentPackageIdentityDocumentRelationshipTargetPartsByCaseFoldBaseName']
        );
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);
        $t->true(!array_key_exists('contents', $identity), 'document package identity target maps must stay metadata-only');
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_document_package_identity_target_maps_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml?profile=review#main"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPreviewUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Preview.PNG"/>
  <Relationship Id="rPreviewLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/preview.png?variant=copy#asset"/>
  <Relationship Id="rMissingPreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-preview.PNG"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml?profile=identity#settings"/>
  <Relationship Id="rExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" TargetMode="External" Target="https://example.test/review"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Document package identity target map fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/settings.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Document package identity target map fixture</dc:title>
</cp:coreProperties>
XML,
        'word/media/Preview.PNG' => 'upper preview bytes',
        'word/media/preview.png' => 'lower preview bytes',
    ];
}
