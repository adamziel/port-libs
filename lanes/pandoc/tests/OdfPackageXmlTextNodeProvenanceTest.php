<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.3"><office:body><office:text><text:p xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"/></office:text></office:body></office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.3"><office:styles/></office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.3"><office:meta/></office:document-meta>
XML;

return [
    'summarizes ODF package XML text nodes without exposing text' => static function (TestRunner $t) use ($contentXml, $stylesXml, $metaXml): void {
        $reviewText = "hidden-text-alpha\nline-two";
        $reviewWhitespace = '   ';
        $auditText = 'hidden-text-beta';
        $looseText = 'hidden-text-gamma';
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3"><manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="META-INF/text-review.xml" manifest:media-type="text/xml"/><manifest:file-entry manifest:full-path="META-INF/audit-text.xml" manifest:media-type="application/xml; profile=text"/></manifest:manifest>
XML;
        $reviewXml = <<<XML
<review:state xmlns:review="urn:odf-text-review"><review:value>{$reviewText}</review:value><review:space>{$reviewWhitespace}</review:space></review:state>
XML;
        $auditXml = <<<XML
<audit:state xmlns:audit="urn:odf-text-audit"><audit:item><audit:value>{$auditText}</audit:value></audit:item></audit:state>
XML;
        $looseXml = <<<XML
<loose:packet xmlns:loose="urn:odf-text-loose"><loose:value>{$looseText}</loose:value></loose:packet>
XML;

        $buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/text-review.xml', 'data' => $reviewXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/audit-text.xml', 'data' => $auditXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/loose-text.xml', 'data' => $looseXml, 'compressionMethod' => 0],
        ], 'odf text node provenance');

        $compact = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['packageInventory'];
        $rich = (new OdfReader())->readPackage($buildPackage())['importReport']['manifest']['packageProvenance'];
        $expectedPartNames = ['META-INF/audit-text.xml', 'META-INF/loose-text.xml', 'META-INF/text-review.xml'];
        $expectedByteLength = strlen($reviewText) + strlen($reviewWhitespace) + strlen($auditText) + strlen($looseText);

        foreach (['compact' => $compact, 'rich' => $rich] as $label => $inventory) {
            $parts = $inventory['parts'];
            $reviewPart = $parts['META-INF/text-review.xml'];
            $auditPart = $parts['META-INF/audit-text.xml'];
            $loosePart = $parts['META-INF/loose-text.xml'];
            $nodesByPartAndPath = [];
            foreach ($inventory['packagePartXmlTextNodes'] as $node) {
                $nodesByPartAndPath[$node['partName'] . ' ' . $node['parentPath']] = $node;
            }

            $t->same(3, $inventory['packagePartXmlTextNodePartCount'], "{$label} text-node part count");
            $t->same(4, $inventory['packagePartXmlTextNodeCount'], "{$label} text-node count");
            $t->same($expectedByteLength, $inventory['packagePartXmlTextNodeByteLength'], "{$label} text-node byte length");
            $t->same(1, $inventory['packagePartXmlTextNodeWhitespaceCount'], "{$label} whitespace-only node count");
            $t->same(3, $inventory['packagePartXmlTextNodeNonWhitespaceCount'], "{$label} non-whitespace node count");
            $t->same(1, $inventory['packagePartXmlTextNodeLineBreakCount'], "{$label} line-break count");
            $t->same([2 => 3, 3 => 1], $inventory['packagePartXmlTextNodeParentDepthCounts'], "{$label} parent depth counts");
            $t->same($expectedPartNames, $inventory['packagePartXmlTextNodePartNames'], "{$label} text-node part names");
            $t->same(false, $inventory['packagePartXmlTextNodesTruncated'], "{$label} text-node summary not truncated");

            $t->same(2, $reviewPart['xmlTextNodeCount'], "{$label} review text-node count");
            $t->same(strlen($reviewText) + strlen($reviewWhitespace), $reviewPart['xmlTextNodeByteLength'], "{$label} review text byte length");
            $t->same(1, $reviewPart['xmlTextNodeWhitespaceCount'], "{$label} review whitespace count");
            $t->same(1, $reviewPart['xmlTextNodeNonWhitespaceCount'], "{$label} review non-whitespace count");
            $t->same(1, $reviewPart['xmlTextNodeLineBreakCount'], "{$label} review line-break count");
            $t->same([2 => 2], $reviewPart['xmlTextNodeParentDepthCounts'], "{$label} review parent depth counts");
            $t->same('/review:state/review:value', $reviewPart['xmlTextNodes'][0]['parentPath'], "{$label} review value parent path");
            $t->same(2, $reviewPart['xmlTextNodes'][0]['parentDepth'], "{$label} review value parent depth");
            $t->same(strlen($reviewText), $reviewPart['xmlTextNodes'][0]['byteLength'], "{$label} review value byte length");
            $t->same(false, $reviewPart['xmlTextNodes'][0]['whitespaceOnly'], "{$label} review value is not whitespace");
            $t->same(1, $reviewPart['xmlTextNodes'][0]['lineBreakCount'], "{$label} review value line-break count");
            $t->same(sprintf('%08x', crc32($reviewText)), $reviewPart['xmlTextNodes'][0]['crc32'], "{$label} review value crc32");
            $t->same(hash('sha256', $reviewText), $reviewPart['xmlTextNodes'][0]['sha256'], "{$label} review value sha256");
            $t->same('/review:state/review:space', $reviewPart['xmlTextNodes'][1]['parentPath'], "{$label} review whitespace parent path");
            $t->same(true, $reviewPart['xmlTextNodes'][1]['whitespaceOnly'], "{$label} review whitespace-only flag");
            $t->same(strlen($reviewWhitespace), $reviewPart['xmlTextNodes'][1]['byteLength'], "{$label} review whitespace byte length");

            $t->same(1, $auditPart['xmlTextNodeCount'], "{$label} audit text-node count");
            $t->same('/audit:state/audit:item/audit:value', $auditPart['xmlTextNodes'][0]['parentPath'], "{$label} audit parent path");
            $t->same(3, $auditPart['xmlTextNodes'][0]['parentDepth'], "{$label} audit parent depth");
            $t->same(hash('sha256', $auditText), $auditPart['xmlTextNodes'][0]['sha256'], "{$label} audit sha256");

            $t->same(1, $loosePart['xmlTextNodeCount'], "{$label} loose text-node count");
            $t->same('/loose:packet/loose:value', $loosePart['xmlTextNodes'][0]['parentPath'], "{$label} loose parent path");
            $t->same(2, $loosePart['xmlTextNodes'][0]['parentDepth'], "{$label} loose parent depth");
            $t->same(hash('sha256', $looseText), $loosePart['xmlTextNodes'][0]['sha256'], "{$label} loose sha256");

            $t->same(false, $nodesByPartAndPath['META-INF/text-review.xml /review:state/review:value']['whitespaceOnly'], "{$label} summary review value flag");
            $t->same(true, $nodesByPartAndPath['META-INF/text-review.xml /review:state/review:space']['whitespaceOnly'], "{$label} summary review whitespace flag");
            $t->same('/audit:state/audit:item/audit:value', $nodesByPartAndPath['META-INF/audit-text.xml /audit:state/audit:item/audit:value']['parentPath'], "{$label} summary audit path");
            $t->same('/loose:packet/loose:value', $nodesByPartAndPath['META-INF/loose-text.xml /loose:packet/loose:value']['parentPath'], "{$label} summary loose path");

            $t->true(!isset($reviewPart['xmlTextNodes'][0]['text']), "{$label} raw XML text should not be exposed");
            $t->true(!isset($reviewPart['xmlTextNodes'][0]['data']), "{$label} raw XML text data should not be exposed");
            $encodedNodes = json_encode([
                $reviewPart['xmlTextNodes'],
                $auditPart['xmlTextNodes'],
                $loosePart['xmlTextNodes'],
                $inventory['packagePartXmlTextNodes'],
            ]);
            $t->true(is_string($encodedNodes), "{$label} XML text metadata should encode for review");
            $t->true(!str_contains((string) $encodedNodes, 'hidden-text'), "{$label} raw XML text should not appear in metadata");
        }
    },
];
