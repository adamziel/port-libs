<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes DOCX relationship source base name stems for package review' => static function (TestRunner $t): void {
        $sharedXml = '<shared-source>xml</shared-source>';
        $customSharedBin = str_repeat('S', 87);
        $documentBin = str_repeat('D', 41);
        $parts = [
            '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
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
            'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Relationship source stem fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
            'word/document.bin' => $documentBin,
            'word/shared.xml' => $sharedXml,
            'customXml/shared.bin' => $customSharedBin,
            'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocumentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/document.png"/>
</Relationships>
XML,
            'word/_rels/document.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocumentBinImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/document-bin.png"/>
</Relationships>
XML,
            'word/_rels/shared.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSharedXmlImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared-xml.png"/>
</Relationships>
XML,
            'customXml/_rels/shared.bin.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSharedBinImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../word/media/shared-bin.png"/>
</Relationships>
XML,
            'word/_rels/shared.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingSharedSourceImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-shared-source.png"/>
</Relationships>
XML,
            'word/media/document.png' => 'document image bytes',
            'word/media/document-bin.png' => 'document bin image bytes',
            'word/media/shared-xml.png' => 'shared xml image bytes',
            'word/media/shared-bin.png' => 'shared bin image bytes',
            'word/media/missing-shared-source.png' => 'missing shared source image bytes',
        ];

        $summary = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx')['packageProvenance']['summary'];
        $stems = [];
        foreach ($summary['relationshipSourceBaseNameStems'] as $stem) {
            $stems[$stem['sourceBaseNameStemKey']] = $stem;
        }

        $t->same(3, $summary['relationshipSourceBaseNameStemCount']);
        $t->same([
            '/' => 1,
            'document' => 2,
            'shared' => 3,
        ], $summary['relationshipSourceBaseNameStemCounts']);
        $t->same([
            '/' => 1,
            'document' => 2,
            'shared' => 2,
        ], $summary['relationshipSourceExistingBaseNameStemCounts']);
        $t->same(['shared' => 1], $summary['relationshipSourceNonExistingBaseNameStemCounts']);
        $t->same(2, $summary['duplicateRelationshipSourceBaseNameStemCount']);
        $t->same(['document', 'shared'], $summary['duplicateRelationshipSourceBaseNameStems']);

        $document = $stems['document'];
        $t->same(2, $document['sourceCount']);
        $t->same(2, $document['existingSourceCount']);
        $t->same(0, $document['nonExistingSourceCount']);
        $t->same(['document.bin' => 1, 'document.xml' => 1], $document['baseNameCounts']);
        $t->same(['bin' => 1, 'xml' => 1], $document['sourcePartExtensionCounts']);
        $t->same(['default' => 1, 'override' => 1], $document['sourceContentTypeSourceCounts']);
        $t->same(['word/document.bin', 'word/document.xml'], $document['sourceParts']);
        $t->same(['word/_rels/document.bin.rels', 'word/_rels/document.xml.rels'], $document['relationshipParts']);
        $t->same(2, $document['baseNameVariantCount']);
        $t->same(2, $document['extensionVariantCount']);

        $shared = $stems['shared'];
        $t->same(3, $shared['sourceCount']);
        $t->same(2, $shared['existingSourceCount']);
        $t->same(1, $shared['nonExistingSourceCount']);
        $t->same(3, $shared['relationshipCount']);
        $t->same(3, $shared['relationshipRecordCount']);
        $t->same(strlen($sharedXml) + strlen($customSharedBin), $shared['existingSourceByteLength']);
        $t->same(['shared' => 1, 'shared.bin' => 1, 'shared.xml' => 1], $shared['baseNameCounts']);
        $t->same(['(none)' => 1, 'bin' => 1, 'xml' => 1], $shared['sourcePartExtensionCounts']);
        $t->same(['missing-source' => 1, 'package-part' => 2], $shared['relationshipSourceKindCounts']);
        $t->same([
            '(missing)' => 1,
            'application/octet-stream' => 1,
            'application/xml' => 1,
        ], $shared['sourceContentTypeBaseCounts']);
        $t->same(['(missing)' => 1, 'default' => 2], $shared['sourceContentTypeSourceCounts']);
        $t->same(['customXml' => 1, 'word' => 2], $shared['sourceDirectoryCounts']);
        $t->same(['package-part' => 2], $shared['sourceRoleCounts']);
        $t->same(['customXml/shared.bin', 'word/shared', 'word/shared.xml'], $shared['sourceParts']);
        $t->same(['customXml/shared.bin', 'word/shared.xml'], $shared['existingSourceParts']);
        $t->same(['word/shared'], $shared['nonExistingSourceParts']);
        $t->same([
            'customXml/_rels/shared.bin.rels',
            'word/_rels/shared.rels',
            'word/_rels/shared.xml.rels',
        ], $shared['relationshipParts']);
        $t->same(['application/octet-stream', 'application/xml'], $shared['contentTypes']);
        $t->same(3, $shared['baseNameVariantCount']);
        $t->same(3, $shared['extensionVariantCount']);
        $t->same('customXml/shared.bin', $shared['largestExistingSourcePart']['sourcePart']);
        $t->same('shared.bin', $shared['largestExistingSourcePart']['sourceBaseName']);
        $t->same('shared', $shared['largestExistingSourcePart']['sourceBaseNameStem']);
        $t->same('bin', $shared['largestExistingSourcePart']['sourcePartExtension']);
        $t->same(strlen($customSharedBin), $shared['largestExistingSourcePart']['sourceBytes']);
        $t->same(hash('sha256', $customSharedBin), $shared['largestExistingSourcePart']['sourceSha256']);
    },
];
