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
      <text:p>Processing instruction provenance packet.</text:p>
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
    <dc:title>Processing Instruction Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

return [
    'summarizes ODF package XML processing instructions without exposing data' => static function (TestRunner $t) use ($contentXml, $stylesXml, $metaXml): void {
        $reviewPacketData = 'state="draft" hidden-payload-alpha';
        $reviewInnerData = 'hidden-payload-beta';
        $auditData = 'hidden-payload-gamma';
        $looseData = 'hidden-payload-delta';
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
<?review-packet {$reviewPacketData}?>
<review:state xmlns:review="urn:odf-pi-review">
  <review:value><?review-inner {$reviewInnerData}?>safe</review:value>
</review:state>
XML;
        $auditXml = <<<XML
<audit:state xmlns:audit="urn:odf-pi-audit"><?audit-state {$auditData}?><audit:item>safe</audit:item></audit:state>
XML;
        $looseXml = <<<XML
<loose:packet xmlns:loose="urn:odf-pi-loose">
  <loose:value><?loose-state {$looseData}?>safe</loose:value>
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
        ], 'odf processing instruction provenance');

        $compact = OpenDocumentPackage::fromPackage($buildPackage())->summarize()['packageInventory'];
        $rich = (new OdfReader())->readPackage($buildPackage())['importReport']['manifest']['packageProvenance'];
        $expectedPartNames = ['META-INF/audit-state.xml', 'META-INF/loose-review.xml', 'META-INF/review-state.xml'];
        $expectedTargets = ['audit-state', 'loose-state', 'review-inner', 'review-packet'];
        $expectedDataByteLength = strlen($reviewPacketData) + strlen($reviewInnerData) + strlen($auditData) + strlen($looseData);

        $instructionByTarget = static function (array $instructions): array {
            $byTarget = [];
            foreach ($instructions as $instruction) {
                if (is_array($instruction) && is_string($instruction['target'] ?? null)) {
                    $byTarget[$instruction['target']] = $instruction;
                }
            }

            return $byTarget;
        };

        foreach (['compact' => $compact, 'rich' => $rich] as $label => $inventory) {
            $parts = $inventory['parts'];
            $reviewPart = $parts['META-INF/review-state.xml'];
            $auditPart = $parts['META-INF/audit-state.xml'];
            $loosePart = $parts['META-INF/loose-review.xml'];
            $summaryByTarget = $instructionByTarget($inventory['packagePartXmlProcessingInstructions']);

            $t->same(3, $inventory['packagePartXmlProcessingInstructionPartCount'], "{$label} PI part count");
            $t->same(4, $inventory['packagePartXmlProcessingInstructionCount'], "{$label} PI count");
            $t->same($expectedDataByteLength, $inventory['packagePartXmlProcessingInstructionDataByteLength'], "{$label} PI data byte length");
            $t->same([0 => 1, 1 => 1, 2 => 2], $inventory['packagePartXmlProcessingInstructionParentDepthCounts'], "{$label} PI parent depth counts");
            $t->same($expectedTargets, $inventory['packagePartXmlProcessingInstructionTargets'], "{$label} PI targets");
            $t->same($expectedPartNames, $inventory['packagePartXmlProcessingInstructionPartNames'], "{$label} PI part names");
            $t->same(false, $inventory['packagePartXmlProcessingInstructionsTruncated'], "{$label} PI summary not truncated");

            $t->same(2, $reviewPart['xmlProcessingInstructionCount'], "{$label} review PI count");
            $t->same(strlen($reviewPacketData) + strlen($reviewInnerData), $reviewPart['xmlProcessingInstructionDataByteLength'], "{$label} review PI data byte length");
            $t->same([0 => 1, 2 => 1], $reviewPart['xmlProcessingInstructionParentDepthCounts'], "{$label} review PI parent depth counts");
            $t->same(['review-inner', 'review-packet'], $reviewPart['xmlProcessingInstructionTargets'], "{$label} review PI targets");
            $t->same(false, $reviewPart['xmlProcessingInstructionsTruncated'], "{$label} review PI not truncated");
            $t->same('review-packet', $reviewPart['xmlProcessingInstructions'][0]['target'], "{$label} review packet target");
            $t->same('/', $reviewPart['xmlProcessingInstructions'][0]['parentPath'], "{$label} review packet parent path");
            $t->same(0, $reviewPart['xmlProcessingInstructions'][0]['parentDepth'], "{$label} review packet parent depth");
            $t->same(strlen($reviewPacketData), $reviewPart['xmlProcessingInstructions'][0]['dataByteLength'], "{$label} review packet data length");
            $t->same(sprintf('%08x', crc32($reviewPacketData)), $reviewPart['xmlProcessingInstructions'][0]['dataCrc32'], "{$label} review packet crc32");
            $t->same(hash('sha256', $reviewPacketData), $reviewPart['xmlProcessingInstructions'][0]['dataSha256'], "{$label} review packet sha256");
            $t->same('review-inner', $reviewPart['xmlProcessingInstructions'][1]['target'], "{$label} review inner target");
            $t->same('/review:state/review:value', $reviewPart['xmlProcessingInstructions'][1]['parentPath'], "{$label} review inner parent path");
            $t->same(2, $reviewPart['xmlProcessingInstructions'][1]['parentDepth'], "{$label} review inner parent depth");
            $t->same(hash('sha256', $reviewInnerData), $reviewPart['xmlProcessingInstructions'][1]['dataSha256'], "{$label} review inner sha256");

            $t->same(1, $auditPart['xmlProcessingInstructionCount'], "{$label} audit PI count");
            $t->same([1 => 1], $auditPart['xmlProcessingInstructionParentDepthCounts'], "{$label} audit PI parent depth counts");
            $t->same(['audit-state'], $auditPart['xmlProcessingInstructionTargets'], "{$label} audit PI target");
            $t->same('/audit:state', $auditPart['xmlProcessingInstructions'][0]['parentPath'], "{$label} audit parent path");
            $t->same(1, $auditPart['xmlProcessingInstructions'][0]['parentDepth'], "{$label} audit parent depth");
            $t->same(hash('sha256', $auditData), $auditPart['xmlProcessingInstructions'][0]['dataSha256'], "{$label} audit sha256");

            $t->same(1, $loosePart['xmlProcessingInstructionCount'], "{$label} loose PI count");
            $t->same([2 => 1], $loosePart['xmlProcessingInstructionParentDepthCounts'], "{$label} loose PI parent depth counts");
            $t->same(['loose-state'], $loosePart['xmlProcessingInstructionTargets'], "{$label} loose PI target");
            $t->same('/loose:packet/loose:value', $loosePart['xmlProcessingInstructions'][0]['parentPath'], "{$label} loose parent path");
            $t->same(2, $loosePart['xmlProcessingInstructions'][0]['parentDepth'], "{$label} loose parent depth");
            $t->same(hash('sha256', $looseData), $loosePart['xmlProcessingInstructions'][0]['dataSha256'], "{$label} loose sha256");

            $t->same('META-INF/review-state.xml', $summaryByTarget['review-packet']['partName'], "{$label} summary review packet part");
            $t->same('/', $summaryByTarget['review-packet']['parentPath'], "{$label} summary review packet path");
            $t->same('META-INF/audit-state.xml', $summaryByTarget['audit-state']['partName'], "{$label} summary audit part");
            $t->same('/audit:state', $summaryByTarget['audit-state']['parentPath'], "{$label} summary audit path");
            $t->same('META-INF/loose-review.xml', $summaryByTarget['loose-state']['partName'], "{$label} summary loose part");
            $t->same('/loose:packet/loose:value', $summaryByTarget['loose-state']['parentPath'], "{$label} summary loose path");

            $t->true(!isset($reviewPart['xmlProcessingInstructions'][0]['data']), "{$label} raw PI data should not be exposed");
            $encodedInstructions = json_encode([
                $reviewPart['xmlProcessingInstructions'],
                $auditPart['xmlProcessingInstructions'],
                $loosePart['xmlProcessingInstructions'],
                $inventory['packagePartXmlProcessingInstructions'],
            ]);
            $t->true(is_string($encodedInstructions), "{$label} PI metadata should encode for review");
            $t->true(!str_contains((string) $encodedInstructions, 'hidden-payload'), "{$label} raw PI data should not appear in metadata");
        }
    },
];
