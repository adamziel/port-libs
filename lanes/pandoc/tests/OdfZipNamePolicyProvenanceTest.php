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
      <text:p>Name policy packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/Review.PNG" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/ leading.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/trailing./scan.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/CON" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'Pictures/review.png', 'data' => 'review-png', 'compressionMethod' => 0],
    ['name' => 'Pictures/Review.PNG', 'data' => 'alternate-review-png', 'compressionMethod' => 0],
    ['name' => 'Pictures/ leading.png', 'data' => 'leading-space-png', 'compressionMethod' => 0],
    ['name' => 'Pictures/trailing./scan.png', 'data' => 'trailing-dot-png', 'compressionMethod' => 0],
    ['name' => 'Pictures/CON', 'data' => 'reserved-name-png', 'compressionMethod' => 0],
];

return [
    'carries ODT ZIP name policy provenance through compact and rich package review' => static function (TestRunner $t) use ($parts): void {
        $package = ZipPackage::fromParts($parts, 'odt name policy package');
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $compactPolicy = $compactInventory['namePolicy'];
        $richPolicy = $richProvenance['namePolicy'];
        $caseCollision = $richPolicy['caseInsensitiveNameCollisionGroups'][0];
        $caseEntries = $richPolicy['caseInsensitiveNameCollisionEntries'];
        $hygieneEntries = $richPolicy['nameHygieneReviewEntries'];

        $t->same($richPolicy, $documentProvenance['namePolicy']);
        $t->same($compactPolicy['issueCodes'], $richPolicy['issueCodes']);
        $t->same(false, $richPolicy['valid']);
        $t->same(false, $compactPolicy['valid']);
        $t->same(2, $richPolicy['issueCount']);
        $t->same(['case-insensitive-name-collisions', 'name-hygiene-review-entries'], $richPolicy['issueCodes']);
        $t->same('odf-zip-name-policy-metadata-only', $richPolicy['byteExposurePolicy']);
        $t->same(false, $richPolicy['canExposeBytes']);
        $t->same(count($parts), $richPolicy['entryCount']);
        $t->same(0, $richPolicy['pathHierarchyCollisionEntryCount']);
        $t->same([], $richPolicy['pathHierarchyCollisionEntries']);

        $t->same(1, $richPolicy['caseInsensitiveNameCollisionGroupCount']);
        $t->same(2, $richPolicy['caseInsensitiveNameCollisionEntryCount']);
        $t->same('pictures/review.png', $caseCollision['caseFoldKey']);
        $t->same(['Pictures/review.png', 'Pictures/Review.PNG'], $caseCollision['entryNames']);
        $t->same('Pictures/review.png', $caseEntries[0]['name']);
        $t->same('Pictures/Review.PNG', $caseEntries[1]['name']);
        $t->same(['case-insensitive-name-collision'], $caseEntries[0]['issues']);
        $t->same(['case-insensitive-name-collision'], $caseEntries[1]['issues']);

        $t->same(0, $richPolicy['rawNameCollisionGroupCount']);
        $t->same(0, $richPolicy['rawNameCollisionEntryCount']);
        $t->same(0, $richPolicy['rawNameProvenanceEntryCount']);
        $t->same([], $richPolicy['rawNameCollisionGroups']);
        $t->same([], $richPolicy['rawNameProvenanceEntries']);

        $t->same(3, $richPolicy['nameHygieneReviewEntryCount']);
        $t->same(1, $richPolicy['nameHygieneLeadingOrTrailingWhitespaceEntryCount']);
        $t->same(1, $richPolicy['nameHygieneTrailingDotSegmentEntryCount']);
        $t->same(1, $richPolicy['nameHygieneWindowsReservedNameEntryCount']);
        $t->same(0, $richPolicy['nameHygieneWindowsAlternateDataStreamEntryCount']);
        $t->same(0, $richPolicy['nameHygieneUnicodeFormatControlEntryCount']);
        $t->same(0, $richPolicy['nameHygieneUnicodeBidiControlEntryCount']);
        $t->same('Pictures/ leading.png', $hygieneEntries[0]['name']);
        $t->same(['segment-leading-or-trailing-whitespace'], $hygieneEntries[0]['issues']);
        $t->same(' leading.png', $hygieneEntries[0]['flaggedSegments'][0]['segment']);
        $t->same('Pictures/trailing./scan.png', $hygieneEntries[1]['name']);
        $t->same(['segment-trailing-dot'], $hygieneEntries[1]['issues']);
        $t->same('trailing.', $hygieneEntries[1]['flaggedSegments'][0]['segment']);
        $t->same('Pictures/CON', $hygieneEntries[2]['name']);
        $t->same(['segment-windows-reserved-name'], $hygieneEntries[2]['issues']);
        $t->same('CON', $hygieneEntries[2]['flaggedSegments'][0]['segment']);

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity] as $handoff) {
            $t->same(false, $handoff['zipNamePolicyValid']);
            $t->same(2, $handoff['zipNamePolicyIssueCount']);
            $t->same(['case-insensitive-name-collisions', 'name-hygiene-review-entries'], $handoff['zipNamePolicyIssueCodes']);
            $t->same(0, $handoff['zipPathHierarchyCollisionEntryCount']);
            $t->same(1, $handoff['zipCaseInsensitiveNameCollisionGroupCount']);
            $t->same(2, $handoff['zipCaseInsensitiveNameCollisionEntryCount']);
            $t->same(0, $handoff['zipRawNameCollisionGroupCount']);
            $t->same(0, $handoff['zipRawNameCollisionEntryCount']);
            $t->same(0, $handoff['zipRawNameProvenanceEntryCount']);
            $t->same(3, $handoff['zipNameHygieneReviewEntryCount']);
            $t->same(1, $handoff['zipNameHygieneLeadingOrTrailingWhitespaceEntryCount']);
            $t->same(1, $handoff['zipNameHygieneTrailingDotSegmentEntryCount']);
            $t->same(1, $handoff['zipNameHygieneWindowsReservedNameEntryCount']);
        }
    },
];
