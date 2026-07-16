<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship target case-fold base name stems for package review' => static function (TestRunner $t): void {
        $parts = docx_relationship_target_casefold_base_name_stem_fixture_parts();
        $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $imageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $stems = [];
        foreach ($summary['relationshipTargetCaseFoldBaseNameStems'] as $stem) {
            $stems[$stem['targetCaseFoldBaseNameStemKey']] = $stem;
        }
        $allRelationshipIds = array_merge(...array_column($summary['relationshipTargetCaseFoldBaseNameStems'], 'relationshipIds'));

        $t->same(3, $summary['relationshipTargetCaseFoldBaseNameStemCount']);
        $t->same([
            'data' => 3,
            'document' => 1,
            'image' => 2,
        ], $summary['relationshipTargetCaseFoldBaseNameStemCounts']);
        $t->same([
            'data' => 2,
            'document' => 1,
            'image' => 2,
        ], $summary['relationshipTargetExistingCaseFoldBaseNameStemCounts']);
        $t->same(['data' => 1], $summary['relationshipTargetMissingCaseFoldBaseNameStemCounts']);
        $t->same(2, $summary['duplicateRelationshipTargetCaseFoldBaseNameStemCount']);
        $t->same(5, $summary['duplicateRelationshipTargetCaseFoldBaseNameStemRelationshipCount']);
        $t->same(5, $summary['duplicateRelationshipTargetCaseFoldBaseNameStemTargetCount']);
        $t->same(['data', 'image'], $summary['duplicateRelationshipTargetCaseFoldBaseNameStems']);
        $t->same(['data', 'document', 'image'], array_column($summary['relationshipTargetCaseFoldBaseNameStems'], 'targetCaseFoldBaseNameStemKey'));

        $data = $stems['data'];
        $t->same('data', $data['targetCaseFoldBaseNameStem']);
        $t->same(3, $data['baseNameStemVariantCount']);
        $t->same(3, $data['baseNameVariantCount']);
        $t->same(3, $data['extensionVariantCount']);
        $t->same(3, $data['targetPartVariantCount']);
        $t->same(3, $data['relationshipCount']);
        $t->same(2, $data['existingTargetCount']);
        $t->same(1, $data['missingTargetCount']);
        $t->same(1, $data['missingContentTypeTargetCount']);
        $t->same(1, $data['parameterizedTargetCount']);
        $t->same(1, $data['extensionlessTargetCount']);
        $t->same(
            strlen($parts['customXml/Data.XML']) + strlen($parts['word/media/data.bin']),
            $data['existingTargetByteLength']
        );
        $t->same(['DATA' => 1, 'Data' => 1, 'data' => 1], $data['baseNameStemCounts']);
        $t->same(['DATA' => 1, 'Data.XML' => 1, 'data.bin' => 1], $data['baseNameCounts']);
        $t->same(['(none)' => 1, 'bin' => 1, 'xml' => 1], $data['targetPartExtensionCounts']);
        $t->same(['2' => 1, '3' => 2], $data['targetPathDepthCounts']);
        $t->same(['customXml' => 1, 'word/media' => 1, 'word/raw' => 1], $data['targetDirectoryCounts']);
        $t->same(['default' => 1, 'missing' => 1, 'override' => 1], $data['contentTypeSourceCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
            'application/xml' => 1,
        ], $data['contentTypeBaseCounts']);
        $t->same([$customXmlRel => 2, $imageRel => 1], $data['relationshipTypeCounts']);
        $t->same(['word/document.xml', 'word/header/header1.xml'], $data['sourceParts']);
        $t->same(['word/_rels/document.xml.rels', 'word/header/_rels/header1.xml.rels'], $data['relationshipParts']);
        $t->same(['rDataMissingRaw', 'rDataOverride', 'rHeaderDataBin'], $data['relationshipIds']);
        $t->same([$customXmlRel, $imageRel], $data['relationshipTypes']);
        $t->same([
            'application/octet-stream',
            'application/xml; profile=target-casefold-stem',
        ], $data['contentTypes']);
        $t->same(['customXml/Data.XML', 'word/media/data.bin', 'word/raw/DATA'], $data['targetParts']);
        $t->same(['customXml/Data.XML', 'word/media/data.bin'], $data['existingTargetParts']);
        $t->same(['word/raw/DATA'], $data['missingTargetParts']);
        $t->same('word/media/data.bin', $data['largestExistingTargetPart']['targetPart']);
        $t->same('data.bin', $data['largestExistingTargetPart']['targetBaseName']);
        $t->same('data.bin', $data['largestExistingTargetPart']['targetCaseFoldBaseName']);
        $t->same('data', $data['largestExistingTargetPart']['targetBaseNameStem']);
        $t->same('data', $data['largestExistingTargetPart']['targetCaseFoldBaseNameStem']);
        $t->same('bin', $data['largestExistingTargetPart']['targetPartExtension']);
        $t->same(strlen($parts['word/media/data.bin']), $data['largestExistingTargetPart']['targetBytes']);
        $t->same(hash('sha256', $parts['word/media/data.bin']), $data['largestExistingTargetPart']['targetSha256']);
        $t->same('application/octet-stream', $data['largestExistingTargetPart']['targetContentTypeBase']);
        $t->same('default', $data['largestExistingTargetPart']['targetContentTypeSource']);

        $image = $stems['image'];
        $t->same(2, $image['baseNameStemVariantCount']);
        $t->same(2, $image['relationshipCount']);
        $t->same(2, $image['existingTargetCount']);
        $t->same(0, $image['missingTargetCount']);
        $t->same(['Image' => 1, 'image' => 1], $image['baseNameStemCounts']);
        $t->same(['Image.PNG' => 1, 'image.png' => 1], $image['baseNameCounts']);
        $t->same(['png' => 2], $image['targetPartExtensionCounts']);
        $t->same(['word/media' => 2], $image['targetDirectoryCounts']);
        $t->same(['default' => 2], $image['contentTypeSourceCounts']);
        $t->same(['image/png' => 2], $image['contentTypeBaseCounts']);
        $t->same(['document-relationship-target' => 2], $image['roleCounts']);
        $t->same(['word/media/Image.PNG', 'word/media/image.png'], $image['targetParts']);
        $t->same('word/media/Image.PNG', $image['largestExistingTargetPart']['targetPart']);
        $t->same('Image.PNG', $image['largestExistingTargetPart']['targetBaseName']);
        $t->same('image.png', $image['largestExistingTargetPart']['targetCaseFoldBaseName']);
        $t->same('Image', $image['largestExistingTargetPart']['targetBaseNameStem']);
        $t->same('image', $image['largestExistingTargetPart']['targetCaseFoldBaseNameStem']);
        $t->true(
            !in_array('rExternalImageStem', $allRelationshipIds, true),
            'external targets should not enter internal case-fold basename stem buckets'
        );
        $t->same(1, $summary['externalRelationshipCount']);
    },
];

/**
 * @return array<string, string>
 */
function docx_relationship_target_casefold_base_name_stem_fixture_parts(): array
{
    $dataXml = '<data>' . str_repeat('case-fold target stem ', 3) . '</data>';
    $dataBin = str_repeat('B', 113);
    $imageLower = 'lower image case-fold stem bytes';
    $imageUpper = str_repeat('I', 47);

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/customXml/Data.XML" ContentType="application/xml; profile=target-casefold-stem"/>
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
  <Relationship Id="rImageLower" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/>
  <Relationship Id="rImageUpper" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Image.PNG"/>
  <Relationship Id="rDataOverride" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="/customXml/Data.XML?profile=case#stem"/>
  <Relationship Id="rDataMissingRaw" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="raw/DATA?missing=1#raw"/>
  <Relationship Id="rExternalImageStem" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/media/IMAGE.PNG" TargetMode="External"/>
</Relationships>
XML,
        'word/header/_rels/header1.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderDataBin" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/data.bin"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship target case-fold basename stem fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'word/header/header1.xml' => '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>',
        'customXml/Data.XML' => $dataXml,
        'word/media/data.bin' => $dataBin,
        'word/media/image.png' => $imageLower,
        'word/media/Image.PNG' => $imageUpper,
    ];
}
