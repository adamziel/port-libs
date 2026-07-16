<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX package parts by raw extension' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_package_part_raw_extension_fixture_parts());
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $identity = $package['packageIdentity'];
        $parts = $package['parts'];
        $rawExtensions = docx_package_part_raw_extension_index_by(
            $summary['partRawExtensions'],
            'rawPartExtensionKey'
        );
        $identityEntries = docx_package_part_raw_extension_index_by(
            $identity['packageEntries'],
            'partName'
        );

        $expectedCounts = [
            '(none)' => 1,
            'PNG' => 1,
            'PnG' => 1,
            'XML' => 1,
            'bin' => 1,
            'rels' => 2,
            'xml' => 3,
        ];

        $t->same('Raw extension fixture.', $document->children[0]->attr('text'));
        $t->same(count($expectedCounts), $summary['partRawExtensionCount']);
        $t->same($expectedCounts, $summary['partRawExtensionCounts']);
        $t->same(1, $summary['extensionlessPackagePartCount']);
        $t->same(3, $summary['partRawExtensionUppercasePartCount']);
        $t->same(3, $summary['partRawExtensionNormalizedPartCount']);
        $t->same(['word/media/IMAGE.PNG'], $summary['partNamesByPartRawExtension']['PNG']);
        $t->same(['word/media/icon.PnG'], $summary['partNamesByPartRawExtension']['PnG']);
        $t->same(['customXml/item.XML'], $summary['partNamesByPartRawExtension']['XML']);
        $t->same(['customXml/extensionless'], $summary['partNamesByPartRawExtension']['(none)']);

        $t->same($summary['partRawExtensionCount'], $identity['partRawExtensionCount']);
        $t->same($summary['partRawExtensionCounts'], $identity['partRawExtensionCounts']);
        $t->same($summary['partNamesByPartRawExtension'], $identity['partNamesByPartRawExtension']);
        $t->same($summary['extensionlessPackagePartCount'], $identity['extensionlessPackagePartCount']);
        $t->same($summary['partRawExtensionUppercasePartCount'], $identity['partRawExtensionUppercasePartCount']);
        $t->same($summary['partRawExtensionNormalizedPartCount'], $identity['partRawExtensionNormalizedPartCount']);
        $t->same($summary['partRawExtensions'], $identity['partRawExtensions']);

        $png = $rawExtensions['PNG'];
        $t->same('PNG', $png['rawPartExtension']);
        $t->same(false, $png['extensionlessPackagePart']);
        $t->same(1, $png['partCount']);
        $t->same(1, $png['uppercasePartCount']);
        $t->same(1, $png['normalizedPartCount']);
        $t->same(['png' => 1], $png['partExtensionCounts']);
        $t->same(['default' => 1], $png['contentTypeSourceCounts']);
        $t->same(['image/png' => 1], $png['contentTypeBaseCounts']);
        $t->same(['document-relationship-target' => 1], $png['roleCounts']);
        $t->same('word/media/IMAGE.PNG', $png['largestPart']['partName']);
        $t->same('PNG', $png['largestPart']['rawPartExtension']);
        $t->same('png', $png['largestPart']['partExtension']);
        $t->same(true, $png['largestPart']['partExtensionHasUppercase']);
        $t->same(true, $png['largestPart']['partExtensionWasNormalized']);
        $t->same(false, array_key_exists('contents', $png['largestPart']));

        $mixedPng = $rawExtensions['PnG'];
        $t->same(['png' => 1], $mixedPng['partExtensionCounts']);
        $t->same(['word/media/icon.PnG'], $mixedPng['partNames']);
        $t->same(1, $mixedPng['uppercasePartCount']);
        $t->same(1, $mixedPng['normalizedPartCount']);

        $upperXml = $rawExtensions['XML'];
        $t->same(['xml' => 1], $upperXml['partExtensionCounts']);
        $t->same(['default' => 1], $upperXml['contentTypeSourceCounts']);
        $t->same(['customXml/item.XML'], $upperXml['partNames']);
        $t->same(1, $upperXml['uppercasePartCount']);
        $t->same(1, $upperXml['normalizedPartCount']);

        $extensionless = $rawExtensions['(none)'];
        $t->same(null, $extensionless['rawPartExtension']);
        $t->same(true, $extensionless['extensionlessPackagePart']);
        $t->same(['(none)' => 1], $extensionless['partExtensionCounts']);
        $t->same(['override' => 1], $extensionless['contentTypeSourceCounts']);
        $t->same(['application/octet-stream' => 1], $extensionless['contentTypeBaseCounts']);
        $t->same('customXml/extensionless', $extensionless['largestPart']['partName']);

        $t->same('PNG', $parts['word/media/IMAGE.PNG']['rawPartExtension']);
        $t->same('png', $parts['word/media/IMAGE.PNG']['partExtension']);
        $t->same(true, $parts['word/media/IMAGE.PNG']['partExtensionHasUppercase']);
        $t->same(true, $parts['word/media/IMAGE.PNG']['partExtensionWasNormalized']);
        $t->same(null, $parts['customXml/extensionless']['rawPartExtension']);
        $t->same(null, $parts['customXml/extensionless']['partExtension']);

        $t->same('PNG', $identityEntries['word/media/IMAGE.PNG']['rawPartExtension']);
        $t->same('png', $identityEntries['word/media/IMAGE.PNG']['partExtension']);
        $t->same(true, $identityEntries['word/media/IMAGE.PNG']['partExtensionHasUppercase']);
        $t->same(true, $identityEntries['word/media/IMAGE.PNG']['partExtensionWasNormalized']);
        $t->same(false, $identityEntries['word/media/IMAGE.PNG']['extensionlessPackagePart']);
        $t->same(null, $identityEntries['customXml/extensionless']['rawPartExtension']);
        $t->same(true, $identityEntries['customXml/extensionless']['extensionlessPackagePart']);
    },
];

/**
 * @return array<string, string>
 */
function docx_package_part_raw_extension_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/extensionless" ContentType="application/octet-stream"/>
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
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/IMAGE.PNG"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Raw extension fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/media/IMAGE.PNG' => str_repeat('P', 128),
        'word/media/icon.PnG' => str_repeat('i', 64),
        'customXml/item.XML' => '<item>upper xml</item>',
        'customXml/item.xml' => '<item>lower xml</item>',
        'customXml/data.bin' => 'binary payload',
        'customXml/extensionless' => 'extensionless payload',
    ];
}

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function docx_package_part_raw_extension_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}
