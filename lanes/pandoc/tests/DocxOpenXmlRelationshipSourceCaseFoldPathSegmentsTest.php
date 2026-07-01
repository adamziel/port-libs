<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source case-fold path segment variants' => static function (TestRunner $t): void {
        $parts = docx_relationship_source_casefold_path_segment_fixture_parts();

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $groups = [];
        foreach ($summary['relationshipSourceCaseFoldPathSegments'] as $group) {
            $groups[$group['caseFoldSegment']] = $group;
        }

        $t->same(8, $summary['relationshipSourceCount']);
        $t->same(7, $summary['relationshipSourceCaseFoldPathSegmentCount']);
        $t->same(17, $summary['relationshipSourceCaseFoldPathSegmentOccurrenceCount']);
        $t->same([
            '_rels' => 1,
            'document.xml' => 2,
            'document.xml.rels' => 1,
            'media' => 2,
            'missing.xml' => 2,
            'source.xml' => 2,
            'word' => 7,
        ], $summary['relationshipSourceCaseFoldPathSegmentCounts']);
        $t->same(4, $summary['duplicateRelationshipSourceCaseFoldPathSegmentCount']);
        $t->same(13, $summary['duplicateRelationshipSourceCaseFoldPathSegmentSourceCount']);
        $t->same(13, $summary['duplicateRelationshipSourceCaseFoldPathSegmentRelationshipCount']);
        $t->same(13, $summary['duplicateRelationshipSourceCaseFoldPathSegmentOccurrenceCount']);
        $t->same(['media', 'missing.xml', 'source.xml', 'word'], $summary['duplicateRelationshipSourceCaseFoldPathSegments']);
        $t->same([
            '_rels',
            'document.xml',
            'document.xml.rels',
            'media',
            'missing.xml',
            'source.xml',
            'word',
        ], array_column($summary['relationshipSourceCaseFoldPathSegments'], 'caseFoldSegment'));

        $word = $groups['word'];
        $t->same(2, $word['segmentVariantCount']);
        $t->same(7, $word['occurrenceCount']);
        $t->same(7, $word['sourceCount']);
        $t->same(5, $word['existingSourceCount']);
        $t->same(2, $word['nonExistingSourceCount']);
        $t->same(7, $word['relationshipCount']);
        $t->same(['Word' => 2, 'word' => 5], $word['segmentCounts']);
        $t->same([0 => 7], $word['pathSegmentIndexCounts']);
        $t->same([2 => 4, 3 => 3], $word['sourcePathDepthCounts']);
        $t->same(['Word' => 2, 'word' => 5], $word['sourceTopLevelSegmentCounts']);
        $t->same(['missing-source' => 2, 'package-part' => 4, 'relationship-part' => 1], $word['relationshipSourceKindCounts']);
        $t->same([
            'Word' => 2,
            'word' => 2,
            'word/Media' => 1,
            'word/_rels' => 1,
            'word/media' => 1,
        ], $word['sourceDirectoryCounts']);
        $t->same([
            'Word/document.xml',
            'Word/missing.xml',
            'word/Media/source.xml',
            'word/Missing.xml',
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/SOURCE.XML',
        ], $word['sourceParts']);
        $t->same('Word/document.xml', $word['largestExistingSourcePart']['sourcePart']);
        $t->same(['Word', 'document.xml'], $word['largestExistingSourcePart']['sourcePathSegments']);
        $t->same(['word', 'document.xml'], $word['largestExistingSourcePart']['caseFoldPathSegments']);

        $media = $groups['media'];
        $t->same(2, $media['segmentVariantCount']);
        $t->same(2, $media['sourceCount']);
        $t->same(2, $media['existingSourceCount']);
        $t->same(['Media' => 1, 'media' => 1], $media['segmentCounts']);
        $t->same([1 => 2], $media['pathSegmentIndexCounts']);
        $t->same(['word/Media' => 1, 'word/media' => 1], $media['sourceDirectoryCounts']);
        $t->same(['application/xml' => 2], $media['sourceContentTypeBaseCounts']);
        $t->same(['default' => 2], $media['sourceContentTypeSourceCounts']);
        $t->same(['word/Media/source.xml', 'word/media/SOURCE.XML'], $media['sourceParts']);
        $t->same('word/media/SOURCE.XML', $media['largestExistingSourcePart']['sourcePart']);
        $t->same(['word', 'media', 'SOURCE.XML'], $media['largestExistingSourcePart']['sourcePathSegments']);
        $t->same(['word', 'media', 'source.xml'], $media['largestExistingSourcePart']['caseFoldPathSegments']);

        $sourceXml = $groups['source.xml'];
        $t->same(2, $sourceXml['segmentVariantCount']);
        $t->same(['SOURCE.XML' => 1, 'source.xml' => 1], $sourceXml['segmentCounts']);
        $t->same([2 => 2], $sourceXml['pathSegmentIndexCounts']);
        $t->same(['SOURCE.XML' => 1, 'source.xml' => 1], $sourceXml['sourceBaseNameCounts']);
        $t->same(['word/Media/source.xml', 'word/media/SOURCE.XML'], $sourceXml['sourceParts']);

        $missing = $groups['missing.xml'];
        $t->same(2, $missing['segmentVariantCount']);
        $t->same(2, $missing['sourceCount']);
        $t->same(0, $missing['existingSourceCount']);
        $t->same(2, $missing['nonExistingSourceCount']);
        $t->same(['Missing.xml' => 1, 'missing.xml' => 1], $missing['segmentCounts']);
        $t->same(['missing-source' => 2], $missing['relationshipSourceKindCounts']);
        $t->same(['(missing)' => 2], $missing['sourceContentTypeBaseCounts']);
        $t->same(['(missing)' => 2], $missing['sourceContentTypeSourceCounts']);
        $t->same(['Word/missing.xml', 'word/Missing.xml'], $missing['sourceParts']);
        $t->same(null, $missing['largestExistingSourcePart']);

        $rels = $groups['_rels'];
        $t->same(1, $rels['sourceCount']);
        $t->same(['relationship-part' => 1], $rels['relationshipSourceKindCounts']);
        $t->same(['office-document-relationships' => 1, 'relationship-part' => 1], $rels['sourceRoleCounts']);
        $t->same(['word/_rels/document.xml.rels'], $rels['sourceParts']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_source_casefold_path_segment_fixture_parts(): array
{
    $lowerDocument = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Lower source.</w:t></w:r></w:p></w:body>
</w:document>
XML;
    $upperDocument = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body><w:p><w:r><w:t>'
        . str_repeat('Upper source payload. ', 40)
        . '</w:t></w:r></w:p></w:body></w:document>';
    $mediaSource = '<source>' . str_repeat('mixed media source ', 4) . '</source>';
    $upperMediaSource = '<source>' . str_repeat('upper media source ', 12) . '</source>';

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="XML" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        'word/document.xml' => $lowerDocument,
        'Word/document.xml' => $upperDocument,
        'word/Media/source.xml' => $mediaSource,
        'word/media/SOURCE.XML' => $upperMediaSource,
        'word/media/lower.png' => 'lower image',
        'word/media/upper.png' => 'upper image',
        'word/media/from-media.png' => 'media source image',
        'word/media/from-upper-media.png' => 'upper media source image',
        'word/media/missing-lower.png' => 'missing lower source target',
        'word/media/missing-upper.png' => 'missing upper source target',
        'word/media/from-relationship-source.png' => 'relationship source image',
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLowerImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/lower.png"/>
</Relationships>
XML,
        'Word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/upper.png"/>
</Relationships>
XML,
        'word/Media/_rels/source.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMediaImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../media/from-media.png"/>
</Relationships>
XML,
        'word/media/_rels/SOURCE.XML.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rUpperMediaImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../from-upper-media.png"/>
</Relationships>
XML,
        'word/_rels/Missing.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-lower.png"/>
</Relationships>
XML,
        'Word/_rels/missing.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/missing-upper.png"/>
</Relationships>
XML,
        'word/_rels/_rels/document.xml.rels.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rRelationshipSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/from-relationship-source.png"/>
</Relationships>
XML,
    ];
}
