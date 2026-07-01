<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'carries DOCX ZIP source record fixed fields onto loaded package parts' => static function (TestRunner $t): void {
        $zip = ZipPackage::fromParts(
            docx_zip_source_record_fixed_field_fixture_parts(),
            'docx package part fixed-field review'
        );
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $zipEntry = $package['zipPackage']['byPackagePath']['word/document.xml'];
        $part = $package['parts']['word/document.xml'];

        $t->same('ZIP fixed field carryover.', $document->children[0]->attr('text'));

        foreach ([
            'localFixedHeaderOffset',
            'localFixedHeaderLength',
            'localVersionNeededToExtractOffset',
            'localCompressionMethodOffset',
            'localCrc32Offset',
            'centralVersionNeededToExtract',
            'localVersionNeededToExtract',
            'centralCrc32Hex',
            'localFixedHeaderCrc32Hex',
            'centralCompressedSize',
            'localFixedHeaderCompressedSize',
            'centralUncompressedSize',
            'localFixedHeaderUncompressedSize',
            'localHeaderFixedFieldsMatchCentralDirectory',
            'localHeaderFixedFieldIssues',
            'centralDirectoryFixedHeaderOffset',
            'centralDirectoryVersionMadeBy',
            'centralDirectoryCreatorHostSystem',
            'centralDirectoryCreatorVersion',
            'centralDirectoryVersionNeededToExtract',
            'centralDirectoryCrc32Hex',
            'centralDirectoryInternalAttributes',
            'centralDirectoryExternalAttributes',
            'centralDirectoryFixedFieldsMatchEntryMetadata',
            'centralDirectoryFixedFieldIssues',
            'madeByHostSystem',
            'madeByHostSystemName',
            'madeByVersion',
            'versionMadeBy',
            'versionNeededToExtract',
            'creatorVersionMeetsNeeded',
            'externalAttributes',
            'externalAttributesHex',
            'dosAttributes',
            'dosAttributeNames',
            'hasDosHiddenAttribute',
            'hasDosArchiveAttribute',
            'internalFileAttributes',
            'internalFileAttributesHex',
            'internalAttributeNames',
            'hasTextInternalAttribute',
            'hasPlatformAttributeProvenance',
            'platformAttributeIssues',
        ] as $field) {
            $t->same($zipEntry[$field], $part[$field] ?? null, "{$field} copied to loaded part");
        }

        $t->same(10, $part['madeByHostSystem']);
        $t->same('windows-ntfs', $part['madeByHostSystemName']);
        $t->same(20, $part['madeByVersion']);
        $t->same((10 << 8) | 20, $part['versionMadeBy']);
        $t->same(20, $part['versionNeededToExtract']);
        $t->same(true, $part['creatorVersionMeetsNeeded']);
        $t->same(10, $part['centralDirectoryCreatorHostSystem']);
        $t->same(20, $part['centralDirectoryCreatorVersion']);
        $t->same(20, $part['centralDirectoryVersionNeededToExtract']);
        $t->same(0x00000022, $part['externalAttributes']);
        $t->same('00000022', $part['externalAttributesHex']);
        $t->same(['hidden', 'archive'], $part['dosAttributeNames']);
        $t->same(0x0001, $part['internalFileAttributes']);
        $t->same('0001', $part['internalFileAttributesHex']);
        $t->same(['apparently-text'], $part['internalAttributeNames']);
        $t->same(['dos-hidden-attribute', 'internal-text-attribute'], $part['platformAttributeIssues']);
        $t->same(true, $part['localHeaderFixedFieldsMatchCentralDirectory']);
        $t->same(true, $part['centralDirectoryFixedFieldsMatchEntryMetadata']);
        $t->same(false, array_key_exists('contents', $part));
    },
];

/**
 * @return list<array{name:string, data:string, compressionMethod:int, creatorHostSystem?:int, externalAttributes?:int, internalAttributes?:int}>
 */
function docx_zip_source_record_fixed_field_fixture_parts(): array
{
    return [
        ['name' => '[Content_Types].xml', 'compressionMethod' => 0, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        ],
        ['name' => '_rels/.rels', 'compressionMethod' => 8, 'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/document.xml',
            'compressionMethod' => 8,
            'creatorHostSystem' => 10,
            'externalAttributes' => 0x00000022,
            'internalAttributes' => 0x0001,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP fixed field carryover.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
    ];
}
