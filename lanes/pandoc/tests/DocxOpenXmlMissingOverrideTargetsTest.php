<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes missing DOCX content-type override targets by package role and path' => static function (TestRunner $t): void {
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/media/missing-preview.png" ContentType="image/png; profile=preview"/>
  <Override PartName="/word/_rels/missing-comments.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
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
    <w:p><w:r><w:t>Missing override target review.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ];

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $summary = $package['summary'];
        $items = [];
        foreach ($contentTypesPart['missingOverrideTargetItems'] as $item) {
            $items[$item['partName']] = $item;
        }

        $t->same(4, $contentTypesPart['missingOverrideTargetCount']);
        $t->same($contentTypesPart['missingOverrideTargetCount'], $summary['contentTypeMissingOverrideTargetCount']);
        $t->same($contentTypesPart['missingOverrideTargetItems'], $summary['contentTypeMissingOverrideTargetItems']);
        $t->same([
            'word/_rels/missing-comments.xml.rels',
            'word/charts/chart1.xml',
            'word/comments.xml',
            'word/media/missing-preview.png',
        ], $contentTypesPart['missingOverrideTargetPartNames']);
        $t->same($contentTypesPart['missingOverrideTargetPartNames'], $summary['contentTypeMissingOverrideTargetPartNames']);
        $t->same([
            'chart-part' => 1,
            'comments' => 1,
            'content-type-override-target' => 4,
            'missing-package-part' => 4,
            'missing-relationship-source' => 1,
            'relationship-part' => 1,
        ], $contentTypesPart['missingOverrideTargetRoleCounts']);
        $t->same($contentTypesPart['missingOverrideTargetRoleCounts'], $summary['contentTypeMissingOverrideTargetRoleCounts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.drawingml.chart+xml' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml' => 1,
            'application/vnd.openxmlformats-package.relationships+xml' => 1,
            'image/png' => 1,
        ], $contentTypesPart['missingOverrideTargetContentTypeBaseCounts']);
        $t->same(['profile' => 1], $contentTypesPart['missingOverrideTargetContentTypeParameterNameCounts']);
        $t->same(1, $contentTypesPart['missingOverrideTargetParameterizedCount']);
        $t->same(['word' => 4], $contentTypesPart['missingOverrideTargetTopLevelSegmentCounts']);
        $t->same([
            'word' => 1,
            'word/_rels' => 1,
            'word/charts' => 1,
            'word/media' => 1,
        ], $contentTypesPart['missingOverrideTargetDirectoryCounts']);
        $t->same([
            'png' => 1,
            'rels' => 1,
            'xml' => 2,
        ], $contentTypesPart['missingOverrideTargetExtensionCounts']);

        $comments = $items['word/comments.xml'];
        $t->same(['content-type-override-target', 'missing-package-part', 'comments'], $comments['roles']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $comments['contentTypeBase']);
        $t->same('word', $comments['directory']);
        $t->same('xml', $comments['partExtension']);

        $chart = $items['word/charts/chart1.xml'];
        $t->same(['content-type-override-target', 'missing-package-part', 'chart-part'], $chart['roles']);
        $t->same('word/charts', $chart['directory']);
        $t->same(2, $chart['directoryDepth']);

        $image = $items['word/media/missing-preview.png'];
        $t->same(['content-type-override-target', 'missing-package-part'], $image['roles']);
        $t->same(['profile' => 'preview'], $image['contentTypeParameterMap']);
        $t->same('content-type-override-missing-target-no-bytes', $image['byteExposurePolicy']);
        $t->same('content-type-override-missing-target-metadata-only', $image['reviewPolicy']);

        $relationships = $items['word/_rels/missing-comments.xml.rels'];
        $t->same([
            'content-type-override-target',
            'missing-package-part',
            'relationship-part',
            'missing-relationship-source',
        ], $relationships['roles']);
        $t->same('word/missing-comments.xml', $relationships['relationshipSource']);
        $t->same(false, $relationships['relationshipSourceExists']);
        $t->same([
            'override-target-missing-part',
            'relationship-override-source-missing',
        ], $relationships['issues']);

        $t->same(false, isset($package['parts']['word/comments.xml']));
        $t->same(false, isset($package['parts']['word/charts/chart1.xml']));
        $t->same(false, isset($summary['roleCounts']['comments']));
        $t->same(false, isset($summary['roleCounts']['chart-part']));
    },
];
