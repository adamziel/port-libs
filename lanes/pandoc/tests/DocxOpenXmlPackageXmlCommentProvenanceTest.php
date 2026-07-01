<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml comments without exposing text' => static function (TestRunner $t): void {
        $reviewPrologComment = 'hidden-payload-alpha review packet state';
        $reviewInnerComment = 'hidden-payload-beta nested review value';
        $settingsComment = 'hidden-payload-gamma settings doc var';
        $parts = docx_package_xml_comment_fixture_parts(
            $reviewPrologComment,
            $reviewInnerComment,
            $settingsComment,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $reviewPart = $package['parts']['customXml/comment-review.xml'];
        $settingsPart = $package['parts']['word/settings.xml'];
        $expectedByteLength = strlen($reviewPrologComment) + strlen($reviewInnerComment) + strlen($settingsComment);

        $t->same(2, $summary['partXmlCommentPartCount']);
        $t->same(3, $summary['partXmlCommentCount']);
        $t->same($expectedByteLength, $summary['partXmlCommentByteLength']);
        $t->same([0 => 1, 2 => 1, 3 => 1], $summary['partXmlCommentParentDepthCounts']);
        $t->same(['customXml/comment-review.xml', 'word/settings.xml'], $summary['partXmlCommentPartNames']);
        $t->same(false, $summary['partXmlCommentsTruncated']);

        $t->same(2, $reviewPart['xmlCommentCount']);
        $t->same(strlen($reviewPrologComment) + strlen($reviewInnerComment), $reviewPart['xmlCommentByteLength']);
        $t->same([0 => 1, 2 => 1], $reviewPart['xmlCommentParentDepthCounts']);
        $t->same(false, $reviewPart['xmlCommentsTruncated']);
        $t->same('/', $reviewPart['xmlComments'][0]['parentPath']);
        $t->same(0, $reviewPart['xmlComments'][0]['parentDepth']);
        $t->same(strlen($reviewPrologComment), $reviewPart['xmlComments'][0]['byteLength']);
        $t->same(sprintf('%08x', crc32($reviewPrologComment)), $reviewPart['xmlComments'][0]['crc32']);
        $t->same(hash('sha256', $reviewPrologComment), $reviewPart['xmlComments'][0]['sha256']);
        $t->same('/review:packet/review:value', $reviewPart['xmlComments'][1]['parentPath']);
        $t->same(2, $reviewPart['xmlComments'][1]['parentDepth']);
        $t->same(strlen($reviewInnerComment), $reviewPart['xmlComments'][1]['byteLength']);
        $t->same(hash('sha256', $reviewInnerComment), $reviewPart['xmlComments'][1]['sha256']);

        $t->same(1, $settingsPart['xmlCommentCount']);
        $t->same(strlen($settingsComment), $settingsPart['xmlCommentByteLength']);
        $t->same([3 => 1], $settingsPart['xmlCommentParentDepthCounts']);
        $t->same('/w:settings/w:docVars/w:docVar', $settingsPart['xmlComments'][0]['parentPath']);
        $t->same(3, $settingsPart['xmlComments'][0]['parentDepth']);
        $t->same(sprintf('%08x', crc32($settingsComment)), $settingsPart['xmlComments'][0]['crc32']);
        $t->same(hash('sha256', $settingsComment), $settingsPart['xmlComments'][0]['sha256']);

        $t->same('customXml/comment-review.xml', $summary['partXmlComments'][0]['partName']);
        $t->same('/', $summary['partXmlComments'][0]['parentPath']);
        $t->same('customXml/comment-review.xml', $summary['partXmlComments'][1]['partName']);
        $t->same('/review:packet/review:value', $summary['partXmlComments'][1]['parentPath']);
        $t->same('word/settings.xml', $summary['partXmlComments'][2]['partName']);
        $t->same('/w:settings/w:docVars/w:docVar', $summary['partXmlComments'][2]['parentPath']);

        $t->true(!isset($reviewPart['xmlComments'][0]['text']), 'raw XML comment text should not be exposed on part metadata');
        $encodedComments = json_encode([
            $reviewPart['xmlComments'],
            $settingsPart['xmlComments'],
            $summary['partXmlComments'],
        ]);
        $t->true(is_string($encodedComments), 'XML comment metadata should encode for review');
        $t->true(!str_contains((string) $encodedComments, 'hidden-payload'), 'raw XML comment text should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_comment_fixture_parts(
    string $reviewPrologComment,
    string $reviewInnerComment,
    string $settingsComment,
): array {
    $reviewXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!--{$reviewPrologComment}-->
<review:packet xmlns:review="urn:docx-comment-review">
  <review:value><!--{$reviewInnerComment}-->safe</review:value>
</review:packet>
XML;
    $settingsXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docVars>
    <w:docVar w:name="Review"><!--{$settingsComment}--></w:docVar>
  </w:docVars>
  <w:updateFields w:val="true"/>
</w:settings>
XML;

    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/comment-review.xml" ContentType="application/xml; profile=comment-review"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
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
  <Relationship Id="rCustomComment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/comment-review.xml"/>
  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Package XML comment provenance fixture.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <dc:title>Package XML comment fixture</dc:title>
</cp:coreProperties>
XML,
        'customXml/comment-review.xml' => $reviewXml,
        'word/settings.xml' => $settingsXml,
    ];
}
