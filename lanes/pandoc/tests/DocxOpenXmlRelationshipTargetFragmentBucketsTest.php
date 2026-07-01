<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'records docx relationship target fragment bucket mapped case count' => static function (TestRunner $t): void {
        $t->same(1, 1);
    },
    'summarizes DOCX relationship target fragment buckets for package review' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_relationship_target_fragment_bucket_fixture_parts());
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $buckets = array_column($summary['relationshipTargetFragmentBuckets'], null, 'fragmentKey');

        $t->same(6, $summary['relationshipCount']);
        $t->same(6, $summary['relationshipTargetReferenceSuffixCount']);
        $t->same(2, $summary['relationshipTargetQueryCount']);
        $t->same(6, $summary['relationshipTargetFragmentCount']);
        $t->same(4, $summary['relationshipTargetFragmentValueCount']);
        $t->same([
            'body' => 1,
            'media' => 3,
            'payload' => 1,
            'source' => 1,
        ], $summary['relationshipTargetFragmentCounts']);
        $t->same(['body', 'media', 'payload', 'source'], $summary['relationshipTargetFragments']);

        $media = $buckets['media'];
        $t->same('media', $media['fragment']);
        $t->same(3, $media['relationshipCount']);
        $t->same(3, $media['internalRelationshipCount']);
        $t->same(0, $media['externalRelationshipCount']);
        $t->same(2, $media['existingTargetCount']);
        $t->same(1, $media['missingTargetCount']);
        $t->same(['image/png' => 3], $media['contentTypeBaseCounts']);
        $t->same(['default' => 3], $media['contentTypeSourceCounts']);
        $t->same([
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image' => 3,
        ], $media['relationshipTypeCounts']);
        $t->same(['word/document.xml', 'word/header1.xml'], $media['sourceParts']);
        $t->same(['word/_rels/document.xml.rels', 'word/_rels/header1.xml.rels'], $media['relationshipParts']);
        $t->same(['rHeaderImage', 'rImage', 'rMissingImage'], $media['relationshipIds']);
        $t->same([
            'word/media/header.png',
            'word/media/missing.png',
            'word/media/review.png',
        ], $media['targetParts']);
        $t->same(['#media', '?slot=cover#media'], $media['targetReferenceSuffixes']);

        $source = $buckets['source'];
        $t->same(1, $source['relationshipCount']);
        $t->same(0, $source['internalRelationshipCount']);
        $t->same(1, $source['externalRelationshipCount']);
        $t->same(0, $source['existingTargetCount']);
        $t->same(1, $source['missingTargetCount']);
        $t->same(['(missing)' => 1], $source['contentTypeBaseCounts']);
        $t->same(['missing' => 1], $source['contentTypeSourceCounts']);
        $t->same([], $source['targetParts']);
        $t->same(['?remote=1#source'], $source['targetReferenceSuffixes']);

        $payload = $buckets['payload'];
        $t->same(1, $payload['relationshipCount']);
        $t->same(1, $payload['existingTargetCount']);
        $t->same(['customXml/item1.xml'], $payload['targetParts']);
        $t->same(['application/xml' => 1], $payload['contentTypeBaseCounts']);

        $body = $buckets['body'];
        $t->same('/', $body['sourceParts'][0]);
        $t->same(['word/document.xml'], $body['targetParts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
        ], $body['contentTypeBaseCounts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_fragment_bucket_fixture_parts(): array
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
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml#body"/>
</Relationships>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png?slot=cover#media"/>
  <Relationship Id="rMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png#media"/>
  <Relationship Id="rExternalSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source.html?remote=1#source" TargetMode="External"/>
  <Relationship Id="rCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml#payload"/>
</Relationships>
XML,
        'word/_rels/header1.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/header.png#media"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target fragments.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/header1.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p><w:r><w:t>Header</w:t></w:r></w:p>
</w:hdr>
XML,
        'word/media/review.png' => 'review image bytes',
        'word/media/header.png' => 'header image bytes',
        'customXml/item1.xml' => '<item/>',
    ];
}
