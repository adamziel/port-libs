<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

return [
    'summarizes docx zip entry comments by inventory role root and extension' => static function (TestRunner $t): void {
        $parts = docx_zip_entry_comment_inventory_fixture_parts();
        $entryComments = [
            '[Content_Types].xml' => 'content-types review',
            'docProps/core.xml' => 'core props audit',
            'word/document.xml' => 'document package review',
            'word/media/review.png' => "media review\x01control",
        ];

        $package = docx_zip_entry_comment_inventory_zip_package($parts, $entryComments);
        $document = (new DocxOpenXmlReader())->readZipPackage($package);
        $packageProvenance = $document->attr('docx')['packageProvenance'];
        $summary = $packageProvenance['summary'];
        $comments = $packageProvenance['zipEntryComments'];
        $byRoot = [];
        foreach ($comments['directoryRootSummaries'] as $rootSummary) {
            $byRoot[$rootSummary['directoryRoot']] = $rootSummary;
        }
        $byExtension = [];
        foreach ($comments['packagePartExtensionSummaries'] as $extensionSummary) {
            $byExtension[$extensionSummary['partExtensionKey']] = $extensionSummary;
        }
        $byRole = [];
        foreach ($comments['roleSummaries'] as $roleSummary) {
            $byRole[$roleSummary['role']] = $roleSummary;
        }
        $entriesByPartName = [];
        foreach ($comments['entries'] as $commentEntry) {
            $entriesByPartName[$commentEntry['partName']] = $commentEntry;
        }

        $expectedCommentByteLength = array_sum(array_map('strlen', $entryComments));

        $t->same(4, $summary['zipEntryCommentCount']);
        $t->same(4, $summary['zipEntryCommentSummaryCount']);
        $t->same($expectedCommentByteLength, $summary['zipEntryCommentByteLength']);
        $t->same(1, $summary['zipEntryCommentIssueEntryCount']);
        $t->same(['entry-comment-control-bytes'], $summary['zipEntryCommentIssueCodes']);
        $t->same($summary['zipEntryCommentRoleSummaries'], $comments['roleSummaries']);
        $t->same($summary['zipEntryCommentDirectoryRootSummaries'], $comments['directoryRootSummaries']);
        $t->same($summary['zipEntryCommentPackagePartExtensionSummaries'], $comments['packagePartExtensionSummaries']);
        $t->same(['[Content_Types].xml', 'word/document.xml', 'docProps/core.xml', 'word/media/review.png'], $comments['commentedEntryNames']);
        $t->same('docx-zip-entry-comment-metadata-only', $comments['byteExposurePolicy']);
        $t->same(false, $comments['canExposeBytes']);

        $t->same(['/', 'docProps/', 'word/'], $summary['zipEntryCommentDirectoryRoots']);
        $t->same(1, $byRoot['/']['entryCount']);
        $t->same(1, $byRoot['docProps/']['entryCount']);
        $t->same(2, $byRoot['word/']['entryCount']);
        $t->same(strlen($entryComments['word/document.xml']) + strlen($entryComments['word/media/review.png']), $byRoot['word/']['commentByteLength']);
        $t->same(['word/document.xml', 'word/media/review.png'], $byRoot['word/']['partNames']);
        $t->same([
            'document-relationship-target' => 1,
            'office-document' => 1,
            'root-relationship-target' => 1,
        ], $byRoot['word/']['roleCounts']);
        $t->same('word/document.xml', $byRoot['word/']['largestCommentedPart']['partName']);

        $t->same(['png', 'xml'], $summary['zipEntryCommentPackagePartExtensions']);
        $t->same(3, $byExtension['xml']['entryCount']);
        $t->same(1, $byExtension['png']['entryCount']);
        $t->same(['[Content_Types].xml', 'docProps/core.xml', 'word/document.xml'], $byExtension['xml']['partNames']);
        $t->same(['/' => 1, 'docProps/' => 1, 'word/' => 1], $byExtension['xml']['directoryRootCounts']);
        $t->same(['word/media/review.png'], $byExtension['png']['partNames']);
        $t->same(['entry-comment-control-bytes'], $byExtension['png']['issueCodes']);
        $t->same('word/media/review.png', $byExtension['png']['largestCommentedPart']['partName']);
        $t->same(sprintf('%08x', crc32($entryComments['word/media/review.png'])), $byExtension['png']['largestCommentedPart']['commentCrc32']);
        $t->same(hash('sha256', $entryComments['word/media/review.png']), $byExtension['png']['largestCommentedPart']['commentSha256']);

        $t->same(2, $byRole['root-relationship-target']['entryCount']);
        $t->same(['docProps/core.xml', 'word/document.xml'], $byRole['root-relationship-target']['partNames']);
        $t->same(1, $byRole['content-types']['entryCount']);
        $t->same(1, $byRole['office-document']['entryCount']);
        $t->same(1, $byRole['document-relationship-target']['entryCount']);
        $t->same(['word/media/review.png'], $byRole['document-relationship-target']['partNames']);

        $documentEntry = $entriesByPartName['word/document.xml'];
        $t->same('word/document.xml', $documentEntry['partName']);
        $t->same('word/', $documentEntry['directoryRoot']);
        $t->same('xml', $documentEntry['partExtensionKey']);
        $t->same(hash('sha256', $entryComments['word/document.xml']), $documentEntry['commentSha256']);
        $t->true(!isset($documentEntry['comment']), 'raw ZIP entry comment text should not be exposed in DOCX comment rollup');
        $t->true(!isset($documentEntry['rawComment']), 'raw ZIP entry comment bytes should not be exposed in DOCX comment rollup');
    },
];

/**
 * @return array<string, string>
 */
function docx_zip_entry_comment_inventory_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>ZIP entry comment provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>ZIP entry comment fixture</dc:title>
</cp:coreProperties>
XML,
        'word/media/review.png' => 'fake png bytes for comment review',
    ];
}

/**
 * @param array<string, string> $parts
 * @param array<string, string> $entryComments
 */
function docx_zip_entry_comment_inventory_zip_package(array $parts, array $entryComments): ZipPackage
{
    $body = '';
    $central = '';
    $entryCount = 0;

    foreach ($parts as $name => $contents) {
        $crc32 = (int) sprintf('%u', crc32($contents));
        $offset = strlen($body);
        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            0,
            0,
            0,
            $crc32,
            strlen($contents),
            strlen($contents),
            strlen($name),
            0,
        );
        $body .= $name . $contents;

        $comment = $entryComments[$name] ?? '';
        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            0,
            0,
            0,
            $crc32,
            strlen($contents),
            strlen($contents),
            strlen($name),
            0,
            strlen($comment),
            0,
            0,
            0,
            $offset,
        );
        $central .= $name . $comment;
        ++$entryCount;
    }

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), strlen($body), 0)
    );
}
