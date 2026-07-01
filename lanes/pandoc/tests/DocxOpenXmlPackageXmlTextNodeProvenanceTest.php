<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;

return [
    'summarizes docx package xml text nodes without exposing text' => static function (TestRunner $t): void {
        $documentText = 'Package XML text node provenance fixture.';
        $packetText = 'Alpha';
        $hiddenValueText = 'hidden-payload-alpha text value';
        $whitespaceText = '   ';
        $mixedLeftText = 'Beta';
        $mixedRightText = 'gamma';
        $parts = docx_package_xml_text_node_fixture_parts(
            $documentText,
            $packetText,
            $hiddenValueText,
            $whitespaceText,
            $mixedLeftText,
            $mixedRightText,
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $documentPart = $package['parts']['word/document.xml'];
        $reviewPart = $package['parts']['customXml/text-review.xml'];
        $expectedByteLength = strlen($documentText)
            + strlen($packetText)
            + strlen($hiddenValueText)
            + strlen($whitespaceText)
            + strlen($mixedLeftText)
            + strlen($mixedRightText);
        $expectedNonWhitespaceByteLength = $expectedByteLength - strlen($whitespaceText);

        $nodesByPart = static function (array $nodes): array {
            $byPart = [];
            foreach ($nodes as $node) {
                if (!is_array($node) || !is_string($node['partName'] ?? null)) {
                    continue;
                }

                $byPart[$node['partName']][] = $node;
            }

            return $byPart;
        };
        $summaryNodesByPart = $nodesByPart($summary['partXmlTextNodes']);

        $t->same(2, $summary['partXmlTextNodePartCount']);
        $t->same(6, $summary['partXmlTextNodeCount']);
        $t->same($expectedByteLength, $summary['partXmlTextNodeByteLength']);
        $t->same(5, $summary['partXmlTextNodeNonWhitespaceCount']);
        $t->same($expectedNonWhitespaceByteLength, $summary['partXmlTextNodeNonWhitespaceByteLength']);
        $t->same(1, $summary['partXmlTextNodeWhitespaceOnlyCount']);
        $t->same(['customXml/text-review.xml', 'word/document.xml'], $summary['partXmlTextNodePartNames']);
        $t->same(false, $summary['partXmlTextNodesTruncated']);

        $t->same(1, $documentPart['xmlTextNodeCount']);
        $t->same(strlen($documentText), $documentPart['xmlTextNodeByteLength']);
        $t->same(1, $documentPart['xmlTextNodeNonWhitespaceCount']);
        $t->same(0, $documentPart['xmlTextNodeWhitespaceOnlyCount']);
        $t->same('/w:document/w:body/w:p/w:r/w:t', $documentPart['xmlTextNodes'][0]['parentPath']);
        $t->same(5, $documentPart['xmlTextNodes'][0]['parentDepth']);
        $t->same(strlen($documentText), $documentPart['xmlTextNodes'][0]['byteLength']);
        $t->same(strlen($documentText), $documentPart['xmlTextNodes'][0]['trimmedByteLength']);
        $t->same(false, $documentPart['xmlTextNodes'][0]['whitespaceOnly']);
        $t->same(hash('sha256', $documentText), $documentPart['xmlTextNodes'][0]['sha256']);

        $t->same(5, $reviewPart['xmlTextNodeCount']);
        $t->same($expectedByteLength - strlen($documentText), $reviewPart['xmlTextNodeByteLength']);
        $t->same(4, $reviewPart['xmlTextNodeNonWhitespaceCount']);
        $t->same($expectedNonWhitespaceByteLength - strlen($documentText), $reviewPart['xmlTextNodeNonWhitespaceByteLength']);
        $t->same(1, $reviewPart['xmlTextNodeWhitespaceOnlyCount']);
        $t->same(false, $reviewPart['xmlTextNodesTruncated']);
        $t->same('/review:packet', $reviewPart['xmlTextNodes'][0]['parentPath']);
        $t->same(1, $reviewPart['xmlTextNodes'][0]['parentDepth']);
        $t->same(strlen($packetText), $reviewPart['xmlTextNodes'][0]['byteLength']);
        $t->same(hash('sha256', $packetText), $reviewPart['xmlTextNodes'][0]['sha256']);
        $t->same('/review:packet/review:value', $reviewPart['xmlTextNodes'][1]['parentPath']);
        $t->same(hash('sha256', $hiddenValueText), $reviewPart['xmlTextNodes'][1]['sha256']);
        $t->same('/review:packet/review:empty', $reviewPart['xmlTextNodes'][2]['parentPath']);
        $t->same(true, $reviewPart['xmlTextNodes'][2]['whitespaceOnly']);
        $t->same(0, $reviewPart['xmlTextNodes'][2]['trimmedByteLength']);
        $t->same(sprintf('%08x', crc32($whitespaceText)), $reviewPart['xmlTextNodes'][2]['crc32']);
        $t->same('/review:packet/review:mixed', $reviewPart['xmlTextNodes'][3]['parentPath']);
        $t->same(hash('sha256', $mixedLeftText), $reviewPart['xmlTextNodes'][3]['sha256']);
        $t->same(4, $reviewPart['xmlTextNodes'][4]['index']);
        $t->same(hash('sha256', $mixedRightText), $reviewPart['xmlTextNodes'][4]['sha256']);

        $t->same(1, count($summaryNodesByPart['word/document.xml']));
        $t->same(5, count($summaryNodesByPart['customXml/text-review.xml']));
        $t->same(hash('sha256', $documentText), $summaryNodesByPart['word/document.xml'][0]['sha256']);
        $t->same('/review:packet/review:value', $summaryNodesByPart['customXml/text-review.xml'][1]['parentPath']);
        $t->true(!isset($reviewPart['xmlTextNodes'][1]['text']), 'raw XML text should not be exposed on part metadata');
        $encodedTextNodes = json_encode([
            $documentPart['xmlTextNodes'],
            $reviewPart['xmlTextNodes'],
            $summary['partXmlTextNodes'],
        ]);
        $t->true(is_string($encodedTextNodes), 'XML text-node metadata should encode for review');
        $t->true(!str_contains((string) $encodedTextNodes, 'hidden-payload'), 'raw XML text should not appear in package metadata');
    },
];

/**
 * @return array<string, string>
 */
function docx_package_xml_text_node_fixture_parts(
    string $documentText,
    string $packetText,
    string $hiddenValueText,
    string $whitespaceText,
    string $mixedLeftText,
    string $mixedRightText,
): array {
    $documentEscaped = htmlspecialchars($documentText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $packetEscaped = htmlspecialchars($packetText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $hiddenValueEscaped = htmlspecialchars($hiddenValueText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $mixedLeftEscaped = htmlspecialchars($mixedLeftText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $mixedRightEscaped = htmlspecialchars($mixedRightText, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $customXml = '<?xml version="1.0" encoding="UTF-8"?><review:packet xmlns:review="urn:docx-text-review">'
        . $packetEscaped
        . '<review:value>'
        . $hiddenValueEscaped
        . '</review:value><review:empty>'
        . $whitespaceText
        . '</review:empty><review:mixed>'
        . $mixedLeftEscaped
        . '<review:child/>'
        . $mixedRightEscaped
        . '</review:mixed></review:packet>';

    return [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/customXml/text-review.xml" ContentType="application/xml; profile=text-review"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rCustomText" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/text-review.xml"/></Relationships>',
        'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>' . $documentEscaped . '</w:t></w:r></w:p></w:body></w:document>',
        'customXml/text-review.xml' => $customXml,
    ];
}
