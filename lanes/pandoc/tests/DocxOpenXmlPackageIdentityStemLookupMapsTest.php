<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

$tests = [
    'records docx package identity stem lookup map mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'carries docx package directory base-name stem lookup maps through package identity' => static function (TestRunner $t): void {
        $parts = docx_package_identity_stem_lookup_maps_fixture_parts();
        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];

        $repeatIdentity = (new DocxOpenXmlReader())
            ->readPackage(docx_package_identity_stem_lookup_maps_fixture_parts())
            ->attr('docx')['packageIdentity'];
        $changedParts = $parts;
        $changedParts['customXml/media/raw.bin'] = 'changed raw review bytes';
        $changedIdentity = (new DocxOpenXmlReader())
            ->readPackage($changedParts)
            ->attr('docx')['packageIdentity'];

        $t->same($identity, $docx['packageIdentity']);
        $t->same(5, $identity['packageDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            'MEDIA' => 1,
            '_rels' => 2,
            'media' => 3,
            'word' => 1,
        ], $identity['packageDirectoryBaseNameStemCounts']);
        $t->same(4, $identity['packageCaseFoldDirectoryBaseNameStemCount']);
        $t->same([
            '/' => 1,
            '_rels' => 2,
            'media' => 4,
            'word' => 1,
        ], $identity['packageCaseFoldDirectoryBaseNameStemCounts']);
        $t->same([
            'customXml/media.raw/data.xml',
            'customXml/media/raw.bin',
            'word/media.assets/review.png',
        ], $identity['entryNamesByPackageDirectoryBaseNameStem']['media']);
        $t->same([
            'word/MEDIA.assets/upper.png',
        ], $identity['entryNamesByPackageDirectoryBaseNameStem']['MEDIA']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
        ], $identity['entryNamesByPackageDirectoryBaseNameStem']['_rels']);
        $t->same([
            'customXml/media.raw/data.xml',
            'customXml/media/raw.bin',
            'word/MEDIA.assets/upper.png',
            'word/media.assets/review.png',
        ], $identity['entryNamesByPackageCaseFoldDirectoryBaseNameStem']['media']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
        ], $identity['entryNamesByPackageCaseFoldDirectoryBaseNameStem']['_rels']);
        $t->same($summary['partDirectoryBaseNameStemCount'], $identity['packageDirectoryBaseNameStemCount']);
        $t->same($summary['partCaseFoldDirectoryBaseNameStemCount'], $identity['packageCaseFoldDirectoryBaseNameStemCount']);
        $t->same($identity['identitySha256'], $repeatIdentity['identitySha256']);
        $t->true($identity['identitySha256'] !== $changedIdentity['identitySha256']);

        $entriesByName = [];
        foreach ($identity['packageEntries'] as $entry) {
            $entriesByName[$entry['partName']] = $entry;
        }

        $reviewEntry = $entriesByName['word/media.assets/review.png'];
        $upperEntry = $entriesByName['word/MEDIA.assets/upper.png'];
        $customRawEntry = $entriesByName['customXml/media/raw.bin'];
        $relationshipEntry = $entriesByName['word/_rels/document.xml.rels'];

        $t->same('media', $reviewEntry['directoryBaseNameStem']);
        $t->same('media', $reviewEntry['caseFoldDirectoryBaseNameStem']);
        $t->same('MEDIA', $upperEntry['directoryBaseNameStem']);
        $t->same('media', $upperEntry['caseFoldDirectoryBaseNameStem']);
        $t->same('media', $customRawEntry['directoryBaseNameStem']);
        $t->same('media', $customRawEntry['caseFoldDirectoryBaseNameStem']);
        $t->same('_rels', $relationshipEntry['directoryBaseNameStem']);
        $t->same('_rels', $relationshipEntry['caseFoldDirectoryBaseNameStem']);
        $t->same(hash('sha256', $parts['word/media.assets/review.png']), $reviewEntry['sha256']);
        $t->same(false, array_key_exists('contents', $reviewEntry));
    },
];

return $tests;

/**
 * @return array<string, string>
 */
function docx_package_identity_stem_lookup_maps_fixture_parts(): array
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
  <Relationship Id="rMediaStem" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media.assets/review.png"/>
  <Relationship Id="rUpperMediaStem" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="MEDIA.assets/upper.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package identity stem lookup map fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media.assets/review.png' => str_repeat('R', 31),
        'word/MEDIA.assets/upper.png' => str_repeat('U', 43),
        'customXml/media.raw/data.xml' => '<data/>',
        'customXml/media/raw.bin' => 'raw bytes',
    ];
}
