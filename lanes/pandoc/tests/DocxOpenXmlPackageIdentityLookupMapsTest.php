<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package identity lookup map mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries docx package basename lookup maps through package identity' => static function (TestRunner $t): void {
        $parts = docx_package_identity_lookup_maps_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];

        $repeatIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_package_identity_lookup_maps_fixture_parts())
            ->attr('docx')['packageIdentity'];
        $changedParts = $parts;
        $changedParts['customXml/media/review.png'] = 'changed custom review png bytes';
        $changedIdentity = (new DocxOpenXmlReader())
            ->readPackage($changedParts)
            ->attr('docx')['packageIdentity'];

        $t->same($identity, $docx['packageIdentity']);
        $t->same(1, $identity['identityVersion']);
        $t->same('docx-package-identity', $identity['reviewPolicy']);
        $t->same('docx-openxml-package', $identity['packageType']);
        $t->same(10, $identity['packageEntryCount']);
        $t->same(10, $identity['partCount']);
        $t->same(strlen(implode('', $parts)), $identity['packageByteLength']);
        $t->same(false, $identity['canExposeBytes']);
        $t->same('docx-package-identity-metadata-only', $identity['byteExposurePolicy']);
        $t->true(strlen($identity['identitySha256']) === 64);
        $t->true($identity['identityPayloadByteLength'] > 0);
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);

        $t->same($identity['reviewPolicy'], $summary['packageIdentityReviewPolicy']);
        $t->same($identity['identityVersion'], $summary['packageIdentityVersion']);
        $t->same($identity['identitySha256'], $summary['packageIdentitySha256']);
        $t->same($identity['identityPayloadByteLength'], $summary['packageIdentityPayloadByteLength']);
        $t->same($identity['byteExposurePolicy'], $summary['packageIdentityByteExposurePolicy']);
        $t->same($identity['canExposeBytes'], $summary['packageIdentityCanExposeBytes']);
        $t->same($identity['packageEntryCount'], $summary['packageIdentityEntryCount']);

        $t->same([
            'customXml/media/review.png',
            'customXml/review.png',
            'word/media/review.png',
        ], $identity['entryNamesByPackageBasename']['review.png']);
        $t->same([
            'customXml/Review.PNG',
            'customXml/media/review.png',
            'customXml/review.png',
            'word/media/review.png',
        ], $identity['entryNamesByPackageCaseFoldedBasename']['review.png']);
        $t->same([
            'customXml/media/review.png',
            'word/media/review.png',
        ], $identity['entryNamesByPackageDirectoryBaseName']['media']);
        $t->same([
            'customXml/Media/cover.png',
            'word/Media/upper.png',
        ], $identity['entryNamesByPackageDirectoryBaseName']['Media']);
        $t->same([
            'customXml/Media/cover.png',
            'customXml/media/review.png',
            'word/Media/upper.png',
            'word/media/review.png',
        ], $identity['entryNamesByPackageCaseFoldDirectoryBaseName']['media']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
        ], $identity['entryNamesByPackageDirectoryBaseName']['_rels']);

        $entriesByName = [];
        foreach ($identity['packageEntries'] as $entry) {
            $entriesByName[$entry['partName']] = $entry;
        }
        $reviewEntry = $entriesByName['customXml/media/review.png'];
        $t->same('customXml/media/review.png', $reviewEntry['partName']);
        $t->same('media', $reviewEntry['directoryBaseName']);
        $t->same('review.png', $reviewEntry['baseName']);
        $t->same(hash('sha256', $parts['customXml/media/review.png']), $reviewEntry['sha256']);
        $t->same(false, array_key_exists('contents', $reviewEntry));
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_package_identity_lookup_maps_fixture_parts(): array
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
  <Relationship Id="rReviewPng" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package identity lookup map fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => 'document review png bytes',
        'customXml/review.png' => 'custom review png bytes',
        'customXml/Review.PNG' => 'upper review png bytes',
        'customXml/media/review.png' => 'custom media review png bytes',
        'customXml/Media/cover.png' => 'custom media cover png bytes',
        'word/Media/upper.png' => 'word upper media png bytes',
    ];
}
