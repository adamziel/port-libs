<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx main document package identity for reviewer handoff' => static function (TestRunner $t): void {
        $cases = [
            [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                'document',
                false,
                false,
                [],
            ],
            [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml; profile=review-template',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml',
                'template',
                true,
                false,
                [],
            ],
            [
                'application/vnd.ms-word.document.macroEnabled.main+xml',
                'application/vnd.ms-word.document.macroenabled.main+xml',
                'macro-enabled-document',
                false,
                true,
                [],
            ],
            [
                'application/vnd.ms-word.template.macroEnabledTemplate.main+xml; profile=macro-template',
                'application/vnd.ms-word.template.macroenabledtemplate.main+xml',
                'macro-enabled-template',
                true,
                true,
                [],
            ],
            [
                'application/vnd.example.review+xml; profile=wrong-main-document',
                'application/vnd.example.review+xml',
                'unknown',
                false,
                false,
                ['unexpected-main-document-content-type'],
            ],
        ];

        foreach ($cases as [$contentType, $contentTypeBase, $formatKind, $template, $macroEnabled, $issueCodes]) {
            $document = (new DocxOpenXmlReader())->readPackage(docx_package_identity_fixture_parts($contentType));
            $repeatDocument = (new DocxOpenXmlReader())->readPackage(docx_package_identity_fixture_parts($contentType));
            $changedDocument = (new DocxOpenXmlReader())->readPackage(docx_package_identity_fixture_parts($contentType . '; identity-review=changed'));
            $docx = $document->attr('docx');
            $package = $docx['packageProvenance'];
            $identity = $docx['documentPackageIdentity'];
            $repeatIdentity = $repeatDocument->attr('docx')['documentPackageIdentity'];
            $changedIdentity = $changedDocument->attr('docx')['documentPackageIdentity'];
            $summary = $package['summary'];

            $t->same($identity, $package['documentPackageIdentity']);
            $t->same(2, $identity['identityVersion']);
            $t->same('docx-main-document-package-identity', $identity['reviewPolicy']);
            $t->same('docx-openxml-main-document', $identity['packageType']);
            $t->same('word/document.xml', $identity['partName']);
            $t->same(3, $identity['packagePartCount']);
            $t->same(1, $identity['packageRelationshipPartCount']);
            $t->same(1, $identity['packageRelationshipCount']);
            $t->same(1, $identity['packageRelationshipRecordCount']);
            $t->same(0, $identity['packageMissingContentTypePartCount']);
            $t->same('_rels/.rels', $identity['rootRelationshipsPart']);
            $t->same(true, $identity['rootRelationshipsPartExists']);
            $t->same(1, $identity['rootRelationshipCount']);
            $t->same(1, $identity['rootRelationshipRecordCount']);
            $t->same(1, $identity['rootInternalRelationshipCount']);
            $t->same(0, $identity['rootExternalRelationshipCount']);
            $t->same(1, $identity['rootExistingRelationshipTargetCount']);
            $t->same(0, $identity['rootMissingRelationshipTargetCount']);
            $t->same(0, $identity['rootMissingRelationshipContentTypeTargetCount']);
            $t->same(true, $identity['rootOfficeDocumentRelationshipFound']);
            $t->same('rDocument', $identity['rootOfficeDocumentRelationshipId']);
            $t->same('word/document.xml', $identity['rootOfficeDocumentRelationshipTarget']);
            $t->same('', $identity['rootOfficeDocumentRelationshipTargetMode']);
            $t->same('word/document.xml', $identity['rootOfficeDocumentRelationshipResolvedTarget']);
            $t->same('word/document.xml', $identity['rootOfficeDocumentRelationshipTargetPart']);
            $t->same(false, $identity['rootOfficeDocumentRelationshipExternal']);
            $t->same('', $identity['rootOfficeDocumentRelationshipTargetReferenceSuffix']);
            $t->same('word/_rels/document.xml.rels', $identity['documentRelationshipsPart']);
            $t->same(false, $identity['documentRelationshipsPartExists']);
            $t->same(0, $identity['documentRelationshipCount']);
            $t->same(0, $identity['documentRelationshipRecordCount']);
            $t->same(0, $identity['documentInternalRelationshipCount']);
            $t->same(0, $identity['documentExternalRelationshipCount']);
            $t->same(0, $identity['documentExistingRelationshipTargetCount']);
            $t->same(0, $identity['documentMissingRelationshipTargetCount']);
            $t->same(0, $identity['documentMissingRelationshipContentTypeTargetCount']);
            $t->same(0, $identity['documentRelationshipTargetPartCount']);
            $t->same([], $identity['documentRelationshipTargetParts']);
            $t->same([], $identity['documentRelationshipContentTypeBases']);
            $t->same($contentType, $identity['contentType']);
            $t->same($contentTypeBase, $identity['contentTypeBase']);
            $t->same('override', $identity['contentTypeSource']);
            $t->same($formatKind, $identity['formatKind']);
            $t->same($template, $identity['template']);
            $t->same($macroEnabled, $identity['macroEnabled']);
            $t->same($issueCodes === [], $identity['validContentType']);
            $t->same(count($issueCodes), $identity['issueCount']);
            $t->same($issueCodes, $identity['issueCodes']);
            $t->same(str_contains($contentType, ';'), $identity['contentTypeHasParameters']);
            $t->same(false, $identity['canExposeBytes']);
            $t->same('docx-main-document-package-identity-metadata-only', $identity['byteExposurePolicy']);
            $t->same(64, strlen($identity['identitySha256']));
            $t->true($identity['identityPayloadByteLength'] > 0);
            $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
            $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);

            $t->same($identity['reviewPolicy'], $summary['documentPackageIdentityReviewPolicy']);
            $t->same($identity['packagePartCount'], $summary['documentPackageIdentityPackagePartCount']);
            $t->same($identity['packageRelationshipPartCount'], $summary['documentPackageIdentityPackageRelationshipPartCount']);
            $t->same($identity['packageRelationshipCount'], $summary['documentPackageIdentityPackageRelationshipCount']);
            $t->same($identity['packageRelationshipRecordCount'], $summary['documentPackageIdentityPackageRelationshipRecordCount']);
            $t->same($identity['rootRelationshipCount'], $summary['documentPackageIdentityRootRelationshipCount']);
            $t->same($identity['rootRelationshipRecordCount'], $summary['documentPackageIdentityRootRelationshipRecordCount']);
            $t->same($identity['rootOfficeDocumentRelationshipId'], $summary['documentPackageIdentityRootOfficeDocumentRelationshipId']);
            $t->same($identity['rootOfficeDocumentRelationshipTargetPart'], $summary['documentPackageIdentityRootOfficeDocumentRelationshipTargetPart']);
            $t->same($identity['documentRelationshipsPart'], $summary['documentPackageIdentityDocumentRelationshipsPart']);
            $t->same($identity['documentRelationshipsPartExists'], $summary['documentPackageIdentityDocumentRelationshipsPartExists']);
            $t->same($identity['documentRelationshipCount'], $summary['documentPackageIdentityDocumentRelationshipCount']);
            $t->same($identity['documentRelationshipRecordCount'], $summary['documentPackageIdentityDocumentRelationshipRecordCount']);
            $t->same($identity['documentRelationshipTargetPartCount'], $summary['documentPackageIdentityDocumentRelationshipTargetPartCount']);
            $t->same($identity['documentRelationshipTargetParts'], $summary['documentPackageIdentityDocumentRelationshipTargetParts']);
            $t->same($identity['contentType'], $summary['documentContentType']);
            $t->same($identity['contentTypeBase'], $summary['documentContentTypeBase']);
            $t->same($identity['contentTypeSource'], $summary['documentContentTypeSource']);
            $t->same($identity['contentTypeHasParameters'], $summary['documentContentTypeHasParameters']);
            $t->same($identity['contentTypeParameterCount'], $summary['documentContentTypeParameterCount']);
            $t->same($identity['contentTypeParameterMap'], $summary['documentContentTypeParameterMap']);
            $t->same($identity['identityVersion'], $summary['documentPackageIdentityVersion']);
            $t->same($identity['identitySha256'], $summary['documentPackageIdentitySha256']);
            $t->same($identity['identityPayloadByteLength'], $summary['documentPackageIdentityPayloadByteLength']);
            $t->same($identity['byteExposurePolicy'], $summary['documentPackageIdentityByteExposurePolicy']);
            $t->same($identity['canExposeBytes'], $summary['documentPackageIdentityCanExposeBytes']);
            $t->same($identity['formatKind'], $summary['documentFormatKind']);
            $t->same($identity['template'], $summary['documentTemplate']);
            $t->same($identity['macroEnabled'], $summary['documentMacroEnabled']);
            $t->same($identity['validContentType'], $summary['documentContentTypeValid']);
            $t->same($identity['issueCount'], $summary['documentContentTypeIssueCount']);
            $t->same($identity['issueCodes'], $summary['documentContentTypeIssueCodes']);
        }
    },
    'includes DOCX package relationship context in main document package identity' => static function (TestRunner $t): void {
        $parts = docx_package_identity_relationship_context_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $changedParts = $parts;
        $changedParts['word/_rels/document.xml.rels'] = str_replace(
            'media/missing.png',
            'media/other-missing.png',
            $changedParts['word/_rels/document.xml.rels'],
        );
        $changedDocument = (new DocxOpenXmlReader())->readPackage($changedParts);
        $docx = $document->attr('docx');
        $identity = $docx['documentPackageIdentity'];
        $summary = $docx['packageProvenance']['summary'];
        $changedIdentity = $changedDocument->attr('docx')['documentPackageIdentity'];

        $t->same(2, $identity['identityVersion']);
        $t->same(7, $identity['packagePartCount']);
        $t->same(2, $identity['packageRelationshipPartCount']);
        $t->same(6, $identity['packageRelationshipCount']);
        $t->same(6, $identity['packageRelationshipRecordCount']);
        $t->same(0, $identity['packageMissingContentTypePartCount']);

        $t->same('_rels/.rels', $identity['rootRelationshipsPart']);
        $t->same(true, $identity['rootRelationshipsPartExists']);
        $t->same(2, $identity['rootRelationshipCount']);
        $t->same(2, $identity['rootRelationshipRecordCount']);
        $t->same(2, $identity['rootInternalRelationshipCount']);
        $t->same(0, $identity['rootExternalRelationshipCount']);
        $t->same(2, $identity['rootExistingRelationshipTargetCount']);
        $t->same(0, $identity['rootMissingRelationshipTargetCount']);
        $t->same(0, $identity['rootMissingRelationshipContentTypeTargetCount']);
        $t->same(true, $identity['rootOfficeDocumentRelationshipFound']);
        $t->same('rDocument', $identity['rootOfficeDocumentRelationshipId']);
        $t->same('word/document.xml?profile=review#main', $identity['rootOfficeDocumentRelationshipTarget']);
        $t->same('', $identity['rootOfficeDocumentRelationshipTargetMode']);
        $t->same('word/document.xml?profile=review#main', $identity['rootOfficeDocumentRelationshipResolvedTarget']);
        $t->same('word/document.xml', $identity['rootOfficeDocumentRelationshipTargetPart']);
        $t->same('?profile=review#main', $identity['rootOfficeDocumentRelationshipTargetReferenceSuffix']);

        $t->same('word/_rels/document.xml.rels', $identity['documentRelationshipsPart']);
        $t->same(true, $identity['documentRelationshipsPartExists']);
        $t->same(4, $identity['documentRelationshipCount']);
        $t->same(4, $identity['documentRelationshipRecordCount']);
        $t->same(3, $identity['documentInternalRelationshipCount']);
        $t->same(1, $identity['documentExternalRelationshipCount']);
        $t->same(2, $identity['documentExistingRelationshipTargetCount']);
        $t->same(1, $identity['documentMissingRelationshipTargetCount']);
        $t->same(0, $identity['documentMissingRelationshipContentTypeTargetCount']);
        $t->same(3, $identity['documentRelationshipTargetPartCount']);
        $t->same([
            'word/media/review.png',
            'word/media/missing.png',
            'word/settings.xml',
        ], $identity['documentRelationshipTargetParts']);
        $t->same([
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml',
        ], $identity['documentRelationshipContentTypeBases']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);
        $t->true(!array_key_exists('contents', $identity), 'document package identity must not expose package bytes');

        $t->same($identity['packagePartCount'], $summary['documentPackageIdentityPackagePartCount']);
        $t->same($identity['packageRelationshipCount'], $summary['documentPackageIdentityPackageRelationshipCount']);
        $t->same($identity['rootRelationshipCount'], $summary['documentPackageIdentityRootRelationshipCount']);
        $t->same($identity['rootOfficeDocumentRelationshipTargetPart'], $summary['documentPackageIdentityRootOfficeDocumentRelationshipTargetPart']);
        $t->same($identity['documentRelationshipCount'], $summary['documentPackageIdentityDocumentRelationshipCount']);
        $t->same($identity['documentRelationshipTargetParts'], $summary['documentPackageIdentityDocumentRelationshipTargetParts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_identity_fixture_parts(string $documentContentType): array
{
    return [
        '[Content_Types].xml' => <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="{$documentContentType}"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package identity fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
    ];
}

/**
 * @return array<string, string>
 */
function docx_package_identity_relationship_context_fixture_parts(): array
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
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml?profile=identity#settings"/>
  <Relationship Id="rLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" TargetMode="External" Target="https://example.test/review"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package identity relationship context fixture.</w:t></w:r></w:p>
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
  <dc:title>Package identity relationship context</dc:title>
</cp:coreProperties>
XML,
        'word/media/review.png' => "\x89PNG\r\n\x1a\nrelationship-context",
    ];
}
