<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'carries duplicate DOCX package part digests into package identity' => static function (TestRunner $t): void {
        $sharedPayload = str_repeat('DOCX-DUPLICATE-DIGEST-', 6);
        $parts = docx_package_identity_duplicate_digest_fixture_parts($sharedPayload);
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $repeatDocument = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $repeatIdentity = $repeatDocument->attr('docx')['packageProvenance']['packageIdentity'];
        $sharedSha256 = hash('sha256', $sharedPayload);
        $digestFields = [
            'duplicatePartDigestGroupCount' => 'packageIdentityDuplicatePartDigestGroupCount',
            'duplicatePartDigestPartCount' => 'packageIdentityDuplicatePartDigestPartCount',
            'duplicatePartDigestByteLength' => 'packageIdentityDuplicatePartDigestByteLength',
            'duplicatePartDigestSha256Values' => 'packageIdentityDuplicatePartDigestSha256Values',
            'duplicatePartDigestPartNames' => 'packageIdentityDuplicatePartDigestPartNames',
            'duplicatePartDigests' => 'packageIdentityDuplicatePartDigests',
        ];

        foreach ($digestFields as $field => $summaryMirror) {
            $t->same($summary[$field], $identity[$field], "{$field} identity handoff");
            $t->same($identity[$field], $summary[$summaryMirror], "{$field} summary mirror");
        }

        $t->same($identity, $document->attr('docx')['packageIdentity']);
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->same('docx-package-identity-metadata-only', $identity['byteExposurePolicy']);
        $t->same(false, $identity['canExposeBytes']);
        $t->same(1, $identity['duplicatePartDigestGroupCount']);
        $t->same(3, $identity['duplicatePartDigestPartCount']);
        $t->same(3 * strlen($sharedPayload), $identity['duplicatePartDigestByteLength']);
        $t->same([$sharedSha256], $identity['duplicatePartDigestSha256Values']);
        $t->same([
            'customXml/shared.payload',
            'word/media/copy.png',
            'word/media/shared.png',
        ], $identity['duplicatePartDigestPartNames']);

        $digest = docx_package_identity_duplicate_digest_index_by(
            $identity['duplicatePartDigests'],
            'sha256'
        )[$sharedSha256];
        $t->same($sharedSha256, $digest['sha256']);
        $t->same([sprintf('%08x', crc32($sharedPayload))], $digest['crc32Values']);
        $t->same(3, $digest['partCount']);
        $t->same(3 * strlen($sharedPayload), $digest['byteLength']);
        $t->same(['customXml', 'word/media'], $digest['directories']);
        $t->same(['default' => 2, 'missing' => 1], $digest['contentTypeSourceCounts']);
        $t->same(['(missing)' => 1, 'image/png' => 2], $digest['contentTypeBaseCounts']);
        $t->same(['document-relationship-target' => 2, 'package-part' => 1], $digest['roleCounts']);
        $t->same([
            'customXml/shared.payload',
            'word/media/copy.png',
            'word/media/shared.png',
        ], $digest['partNames']);
        $t->same('customXml/shared.payload', $digest['largestPart']['partName']);
        $t->same($sharedSha256, $digest['largestPart']['sha256']);
        $t->same('(missing)', $digest['largestPart']['contentTypeBase'] === '' ? '(missing)' : $digest['largestPart']['contentTypeBase']);
        $t->same('missing', $digest['largestPart']['contentTypeSource']);
        $t->same(['package-part'], $digest['largestPart']['roles']);
        $t->same(false, array_key_exists('contents', $digest));
        $t->same(false, array_key_exists('contents', $digest['largestPart']));
    },
];

/**
 * @return array<string, string>
 */
function docx_package_identity_duplicate_digest_fixture_parts(string $sharedPayload): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSharedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared.png"/>
  <Relationship Id="rCopiedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/copy.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>DOCX duplicate digest identity.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/shared.png' => $sharedPayload,
        'word/media/copy.png' => $sharedPayload,
        'customXml/shared.payload' => $sharedPayload,
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_identity_duplicate_digest_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}
