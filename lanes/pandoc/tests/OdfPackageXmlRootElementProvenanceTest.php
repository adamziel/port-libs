<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>XML root element provenance packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>XML Root Element Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

return [
    'summarizes ODF package XML root elements without exposing values' => static function (TestRunner $t) use ($contentXml, $stylesXml, $metaXml): void {
        $hiddenReviewState = 'hidden-payload-alpha root attribute';
        $hiddenAuditState = 'hidden-payload-beta audit text';
        $hiddenLooseState = 'hidden-payload-gamma loose text';
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/root-review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/root-audit.xml" manifest:media-type="application/xml; profile=root"/>
  <manifest:file-entry manifest:full-path="META-INF/broken.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $reviewXml = <<<XML
<review:packet xmlns:review="urn:odf-root-review" review:state="{$hiddenReviewState}">
  <review:value>safe</review:value>
</review:packet>
XML;
        $auditXml = <<<XML
<audit:state xmlns:audit="urn:odf-root-audit" audit:version="1">
  <audit:item>{$hiddenAuditState}</audit:item>
</audit:state>
XML;
        $looseXml = <<<XML
<loose:packet xmlns:loose="urn:odf-root-loose">
  <loose:value>{$hiddenLooseState}</loose:value>
</loose:packet>
XML;

        $buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/root-review.xml', 'data' => $reviewXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/root-audit.xml', 'data' => $auditXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/root-loose.xml', 'data' => $looseXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/broken.xml', 'data' => '<broken', 'compressionMethod' => 0],
        ], 'odf xml root element provenance');

        $compact = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['packageInventory'];
        $rich = (new OdfReader())->readPackage($buildPackage())['importReport']['manifest']['packageProvenance'];
        $expectedPartNames = [
            'META-INF/manifest.xml',
            'META-INF/root-audit.xml',
            'META-INF/root-loose.xml',
            'META-INF/root-review.xml',
            'content.xml',
            'meta.xml',
            'styles.xml',
        ];
        $expectedRootNames = [
            'audit:state',
            'loose:packet',
            'manifest:manifest',
            'office:document-content',
            'office:document-meta',
            'office:document-styles',
            'review:packet',
        ];
        $expectedRootLocalNames = [
            'document-content',
            'document-meta',
            'document-styles',
            'manifest',
            'packet',
            'state',
        ];

        foreach (['compact' => $compact, 'rich' => $rich] as $label => $inventory) {
            $parts = $inventory['parts'];
            $reviewPart = $parts['META-INF/root-review.xml'];
            $auditPart = $parts['META-INF/root-audit.xml'];
            $loosePart = $parts['META-INF/root-loose.xml'];
            $brokenPart = $parts['META-INF/broken.xml'];
            $rootsByPart = [];
            foreach ($inventory['packagePartXmlRootElements'] as $root) {
                $rootsByPart[$root['partName']] = $root;
            }

            $t->same(7, $inventory['packagePartXmlRootElementPartCount'], "{$label} root part count");
            $t->same($expectedPartNames, $inventory['packagePartXmlRootElementPartNames'], "{$label} root part names");
            $t->same($expectedRootNames, $inventory['packagePartXmlRootElementNames'], "{$label} root names");
            $t->same($expectedRootLocalNames, $inventory['packagePartXmlRootElementLocalNames'], "{$label} root local names");
            $t->same([
                'document-content' => 1,
                'document-meta' => 1,
                'document-styles' => 1,
                'manifest' => 1,
                'packet' => 2,
                'state' => 1,
            ], $inventory['packagePartXmlRootElementLocalNameCounts'], "{$label} root local name counts");
            $t->same(7, $inventory['packagePartXmlRootElementPrefixedCount'], "{$label} root prefixed count");
            $t->same([
                'audit' => 1,
                'loose' => 1,
                'manifest' => 1,
                'office' => 3,
                'review' => 1,
            ], $inventory['packagePartXmlRootElementPrefixCounts'], "{$label} root prefix counts");
            $t->same(false, $inventory['packagePartXmlRootElementsTruncated'], "{$label} root summary not truncated");
            $t->same(3, $inventory['packagePartXmlRootElementNamespaceUriCounts']['urn:oasis:names:tc:opendocument:xmlns:office:1.0'], "{$label} office root namespace count");
            $t->same(1, $inventory['packagePartXmlRootElementNamespaceUriCounts']['urn:odf-root-review'], "{$label} review root namespace count");

            $t->same(true, $reviewPart['xmlHasRootElement'], "{$label} review has root");
            $t->same('review:packet', $reviewPart['xmlRootElementName'], "{$label} review root name");
            $t->same('packet', $reviewPart['xmlRootElementLocalName'], "{$label} review local name");
            $t->same('review', $reviewPart['xmlRootElementPrefix'], "{$label} review prefix");
            $t->same('urn:odf-root-review', $reviewPart['xmlRootElementNamespaceUri'], "{$label} review namespace");
            $t->same('/review:packet', $reviewPart['xmlRootElementPath'], "{$label} review root path");
            $t->same(1, $reviewPart['xmlRootElementAttributeCount'], "{$label} review attribute count");
            $t->same(['review:state'], $reviewPart['xmlRootElementAttributeNames'], "{$label} review attribute names");
            $t->same(1, $reviewPart['xmlRootElementNamespaceDeclarationCount'], "{$label} review namespace declaration count");
            $t->same(['xmlns:review'], $reviewPart['xmlRootElementNamespaceDeclarationNames'], "{$label} review namespace declaration names");

            $t->same('audit:state', $auditPart['xmlRootElementName'], "{$label} audit root name");
            $t->same(['audit:version'], $auditPart['xmlRootElementAttributeNames'], "{$label} audit attribute names");
            $t->same('loose:packet', $loosePart['xmlRootElementName'], "{$label} undeclared loose root name");
            $t->same(['meta-inf-sidecar', 'undeclared-package-entry'], $loosePart['roles'], "{$label} loose remains undeclared sidecar");
            $t->same(false, $brokenPart['xmlHasRootElement'], "{$label} broken XML has no root provenance");
            $t->same(null, $brokenPart['xmlRootElementName'], "{$label} broken XML root name");
            $t->same(false, $parts['mimetype']['xmlHasRootElement'], "{$label} mimetype is not XML");

            $t->same('review:packet', $rootsByPart['META-INF/root-review.xml']['name'], "{$label} summary review root name");
            $t->same(['review:state'], $rootsByPart['META-INF/root-review.xml']['attributeNames'], "{$label} summary review attributes");
            $t->same('loose:packet', $rootsByPart['META-INF/root-loose.xml']['name'], "{$label} summary loose root name");

            $encodedRoots = json_encode([
                $reviewPart,
                $auditPart,
                $loosePart,
                $inventory['packagePartXmlRootElements'],
            ]);
            $t->true(is_string($encodedRoots), "{$label} root metadata should encode for review");
            $t->true(!str_contains((string) $encodedRoots, 'hidden-payload'), "{$label} root metadata should not expose XML values");
        }
    },
];
