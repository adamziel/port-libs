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
      <text:p>CDATA sidecar provenance packet.</text:p>
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
    <dc:title>CDATA Sidecar Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

return [
    'summarizes ODF package XML CDATA sections without exposing text' => static function (TestRunner $t) use ($contentXml, $stylesXml, $metaXml): void {
        $reviewCdata = 'odf-cdata-review:hidden-payload-alpha';
        $auditCdata = 'odf-cdata-audit:hidden-payload-beta';
        $looseCdata = 'odf-cdata-loose:hidden-payload-gamma';
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/review-state.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/audit-state.xml" manifest:media-type="application/xml; profile=audit"/>
</manifest:manifest>
XML;
        $reviewXml = <<<XML
<review:state xmlns:review="urn:odf-cdata-review">
  <review:value><![CDATA[{$reviewCdata}]]></review:value>
</review:state>
XML;
        $auditXml = <<<XML
<audit:state xmlns:audit="urn:odf-cdata-audit">
  <audit:item><audit:value><![CDATA[{$auditCdata}]]></audit:value></audit:item>
</audit:state>
XML;
        $looseXml = <<<XML
<loose:packet xmlns:loose="urn:odf-cdata-loose">
  <loose:value><![CDATA[{$looseCdata}]]></loose:value>
</loose:packet>
XML;

        $buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
            ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
            ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
            ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/review-state.xml', 'data' => $reviewXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/audit-state.xml', 'data' => $auditXml, 'compressionMethod' => 0],
            ['name' => 'META-INF/loose-review.xml', 'data' => $looseXml, 'compressionMethod' => 0],
        ], 'odf cdata provenance');

        $compact = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['packageInventory'];
        $rich = (new OdfReader())->readPackage($buildPackage())['importReport']['manifest']['packageProvenance'];
        $expectedPartNames = ['META-INF/audit-state.xml', 'META-INF/loose-review.xml', 'META-INF/review-state.xml'];
        $expectedByteLength = strlen($reviewCdata) + strlen($auditCdata) + strlen($looseCdata);

        foreach (['compact' => $compact, 'rich' => $rich] as $label => $inventory) {
            $parts = $inventory['parts'];
            $sections = $inventory['packagePartXmlCdataSections'];
            $reviewPart = $parts['META-INF/review-state.xml'];
            $auditPart = $parts['META-INF/audit-state.xml'];
            $loosePart = $parts['META-INF/loose-review.xml'];

            $t->same(3, $inventory['packagePartXmlCdataSectionPartCount'], "{$label} CDATA part count");
            $t->same(3, $inventory['packagePartXmlCdataSectionCount'], "{$label} CDATA section count");
            $t->same($expectedByteLength, $inventory['packagePartXmlCdataSectionByteLength'], "{$label} CDATA byte length");
            $t->same($expectedPartNames, $inventory['packagePartXmlCdataSectionPartNames'], "{$label} CDATA part names");
            $t->same(false, $inventory['packagePartXmlCdataSectionsTruncated'], "{$label} CDATA summary not truncated");

            $t->same(1, $reviewPart['xmlCdataSectionCount'], "{$label} review section count");
            $t->same(strlen($reviewCdata), $reviewPart['xmlCdataSectionByteLength'], "{$label} review byte length");
            $t->same('/review:state/review:value', $reviewPart['xmlCdataSections'][0]['parentPath'], "{$label} review parent path");
            $t->same(2, $reviewPart['xmlCdataSections'][0]['parentDepth'], "{$label} review parent depth");
            $t->same(sprintf('%08x', crc32($reviewCdata)), $reviewPart['xmlCdataSections'][0]['crc32'], "{$label} review crc32");
            $t->same(hash('sha256', $reviewCdata), $reviewPart['xmlCdataSections'][0]['sha256'], "{$label} review sha256");

            $t->same(1, $auditPart['xmlCdataSectionCount'], "{$label} audit section count");
            $t->same('/audit:state/audit:item/audit:value', $auditPart['xmlCdataSections'][0]['parentPath'], "{$label} audit parent path");
            $t->same(3, $auditPart['xmlCdataSections'][0]['parentDepth'], "{$label} audit parent depth");
            $t->same(hash('sha256', $auditCdata), $auditPart['xmlCdataSections'][0]['sha256'], "{$label} audit sha256");

            $t->same(1, $loosePart['xmlCdataSectionCount'], "{$label} loose section count");
            $t->same('/loose:packet/loose:value', $loosePart['xmlCdataSections'][0]['parentPath'], "{$label} loose parent path");
            $t->same(2, $loosePart['xmlCdataSections'][0]['parentDepth'], "{$label} loose parent depth");
            $t->same(hash('sha256', $looseCdata), $loosePart['xmlCdataSections'][0]['sha256'], "{$label} loose sha256");

            $t->same('META-INF/review-state.xml', $sections[0]['partName'], "{$label} summary first part");
            $t->same('/review:state/review:value', $sections[0]['parentPath'], "{$label} summary first path");
            $t->same('META-INF/audit-state.xml', $sections[1]['partName'], "{$label} summary second part");
            $t->same('/audit:state/audit:item/audit:value', $sections[1]['parentPath'], "{$label} summary second path");
            $t->same('META-INF/loose-review.xml', $sections[2]['partName'], "{$label} summary third part");
            $t->same('/loose:packet/loose:value', $sections[2]['parentPath'], "{$label} summary third path");
            $t->true(!isset($reviewPart['xmlCdataSections'][0]['data']), "{$label} raw CDATA text should not be exposed");
            $encodedSections = json_encode([$reviewPart['xmlCdataSections'], $auditPart['xmlCdataSections'], $loosePart['xmlCdataSections'], $sections]);
            $t->true(is_string($encodedSections), "{$label} CDATA metadata should encode for review");
            $t->true(!str_contains((string) $encodedSections, 'hidden-payload'), "{$label} raw CDATA text should not appear in metadata");
        }
    },
];
