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
      <text:p>XML annotation identity packet.</text:p>
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
    <dc:title>XML Annotation Identity</dc:title>
  </office:meta>
</office:document-meta>
XML;

return [
    'carries ODF package XML annotation metadata into compact and rich identities' => static function (TestRunner $t) use ($contentXml, $stylesXml, $metaXml): void {
        $buildPackage = static function (string $suffix = '') use ($contentXml, $stylesXml, $metaXml): ZipPackage {
            $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/annotation-review.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
            $comment = 'hidden-payload-comment-alpha' . $suffix;
            $cdata = 'hidden-payload-cdata-alpha' . $suffix;
            $packetData = 'state="draft" hidden-payload-packet-alpha' . $suffix;
            $innerData = 'hidden-payload-inner-alpha' . $suffix;
            $reviewXml = <<<XML
<?review-packet {$packetData}?>
<review:state xmlns:review="urn:odf-annotation-identity">
  <review:value><![CDATA[{$cdata}]]><?review-inner {$innerData}?><!--{$comment}-->safe</review:value>
</review:state>
XML;

            return ZipPackage::fromParts([
                ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
                ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
                ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
                ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
                ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
                ['name' => 'META-INF/annotation-review.xml', 'data' => $reviewXml, 'compressionMethod' => 0],
            ], 'odf xml annotation identity');
        };

        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richProvenance = (new OdfReader())->readPackage($buildPackage())['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $changedCompactIdentity = OpenDocumentPackage::fromPackage($buildPackage('-changed'))->summarize()['packageIdentity'];
        $changedRichIdentity = (new OdfReader())->readPackage($buildPackage('-changed'))['importReport']['manifest']['packageProvenance']['packageIdentity'];

        $aggregateKeys = [
            'packagePartXmlCdataSectionPartCount',
            'packagePartXmlCdataSectionCount',
            'packagePartXmlCdataSectionByteLength',
            'packagePartXmlCdataSectionPartNames',
            'packagePartXmlCdataSections',
            'packagePartXmlCdataSectionsTruncated',
            'packagePartXmlCommentPartCount',
            'packagePartXmlCommentCount',
            'packagePartXmlCommentByteLength',
            'packagePartXmlCommentParentDepthCounts',
            'packagePartXmlCommentPartNames',
            'packagePartXmlComments',
            'packagePartXmlCommentsTruncated',
            'packagePartXmlProcessingInstructionPartCount',
            'packagePartXmlProcessingInstructionCount',
            'packagePartXmlProcessingInstructionDataByteLength',
            'packagePartXmlProcessingInstructionTargets',
            'packagePartXmlProcessingInstructionPartNames',
            'packagePartXmlProcessingInstructions',
            'packagePartXmlProcessingInstructionsTruncated',
        ];
        $entryKeys = [
            'xmlCdataSectionCount',
            'xmlCdataSectionByteLength',
            'xmlCdataSections',
            'xmlCdataSectionsTruncated',
            'xmlCommentCount',
            'xmlCommentByteLength',
            'xmlCommentParentDepthCounts',
            'xmlComments',
            'xmlCommentsTruncated',
            'xmlProcessingInstructionCount',
            'xmlProcessingInstructionDataByteLength',
            'xmlProcessingInstructionTargets',
            'xmlProcessingInstructions',
            'xmlProcessingInstructionsTruncated',
        ];
        $entryByKey = static function (array $entries, string $keyName): array {
            $byKey = [];
            foreach ($entries as $entry) {
                if (is_array($entry) && is_string($entry[$keyName] ?? null)) {
                    $byKey[$entry[$keyName]] = $entry;
                }
            }

            return $byKey;
        };
        $expectedPartNames = ['META-INF/annotation-review.xml'];
        $expectedTargets = ['review-inner', 'review-packet'];

        foreach ([
            'compact' => [$compactInventory, $compactIdentity, 'path'],
            'rich' => [$richProvenance, $richIdentity, 'part'],
        ] as $label => [$inventory, $identity, $entryKey]) {
            $inventoryEntries = $entryKey === 'path'
                ? $inventory['parts']
                : $entryByKey($inventory['parts'], 'part');
            $identityEntries = $entryByKey($identity['packageEntries'], $entryKey);
            $inventoryReview = $inventoryEntries['META-INF/annotation-review.xml'];
            $identityReview = $identityEntries['META-INF/annotation-review.xml'];

            foreach ($aggregateKeys as $key) {
                $t->same($inventory[$key], $identity[$key], "{$label} identity {$key}");
            }
            foreach ($entryKeys as $key) {
                $t->same($inventoryReview[$key], $identityReview[$key], "{$label} review entry {$key}");
            }

            $t->same(1, $identity['packagePartXmlCdataSectionPartCount'], "{$label} CDATA part count");
            $t->same(1, $identity['packagePartXmlCdataSectionCount'], "{$label} CDATA count");
            $t->same($expectedPartNames, $identity['packagePartXmlCdataSectionPartNames'], "{$label} CDATA part names");
            $t->same('/review:state/review:value', $identityReview['xmlCdataSections'][0]['parentPath'], "{$label} CDATA parent path");
            $t->same(2, $identityReview['xmlCdataSections'][0]['parentDepth'], "{$label} CDATA parent depth");
            $t->same(1, $identity['packagePartXmlCommentPartCount'], "{$label} comment part count");
            $t->same(1, $identity['packagePartXmlCommentCount'], "{$label} comment count");
            $t->same([2 => 1], $identity['packagePartXmlCommentParentDepthCounts'], "{$label} comment parent depth counts");
            $t->same($expectedPartNames, $identity['packagePartXmlCommentPartNames'], "{$label} comment part names");
            $t->same('/review:state/review:value', $identityReview['xmlComments'][0]['parentPath'], "{$label} comment parent path");
            $t->same(1, $identity['packagePartXmlProcessingInstructionPartCount'], "{$label} PI part count");
            $t->same(2, $identity['packagePartXmlProcessingInstructionCount'], "{$label} PI count");
            $t->same($expectedTargets, $identity['packagePartXmlProcessingInstructionTargets'], "{$label} PI targets");
            $t->same($expectedPartNames, $identity['packagePartXmlProcessingInstructionPartNames'], "{$label} PI part names");
            $t->same('review-packet', $identityReview['xmlProcessingInstructions'][0]['target'], "{$label} packet PI target");
            $t->same('/', $identityReview['xmlProcessingInstructions'][0]['parentPath'], "{$label} packet PI parent path");
            $t->same('review-inner', $identityReview['xmlProcessingInstructions'][1]['target'], "{$label} inner PI target");
            $t->same('/review:state/review:value', $identityReview['xmlProcessingInstructions'][1]['parentPath'], "{$label} inner PI parent path");

            $encodedIdentity = json_encode($identity);
            $t->true(is_string($encodedIdentity), "{$label} identity encodes");
            $t->true(!str_contains((string) $encodedIdentity, 'hidden-payload'), "{$label} identity omits raw annotation text");
        }

        $t->true($compactIdentity['identitySha256'] !== $changedCompactIdentity['identitySha256'], 'compact identity hash tracks changed XML annotations');
        $t->true($richIdentity['identitySha256'] !== $changedRichIdentity['identitySha256'], 'rich identity hash tracks changed XML annotations');
        $t->same('odf-package-identity-metadata-only', $compactIdentity['byteExposurePolicy']);
        $t->same(false, $compactIdentity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
        $t->same(false, $richIdentity['canExposeBytes']);
    },
];
