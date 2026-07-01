<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes jats funding reference backlink buckets without payload text' => static function (TestRunner $t): void {
        $dom = XmlHtmlDom::loadXmlDocument(<<<'XML'
<article article-type="research-article">
  <front>
    <article-meta>
      <title-group><article-title>Funding Backlink Summary</article-title></title-group>
      <funding-group id="fg-summary">
        <award-group id="ag-alpha">
          <funding-source id="fs-alpha"><institution>Alpha Agency</institution></funding-source>
          <award-id id="award-alpha">AWD-1</award-id>
          <funding-statement>Alpha secret payload <xref id="xref-alpha" ref-type="bibr" rid="r-dup r-missing">shared backlink</xref>.</funding-statement>
        </award-group>
        <award-group id="ag-beta">
          <funding-source id="fs-beta"><institution>Beta Agency</institution></funding-source>
          <award-id id="award-beta">AWD-1</award-id>
          <funding-statement>Beta secret payload <xref id="xref-beta" ref-type="bibr" rid="r-dup">duplicate backlink</xref>.</funding-statement>
        </award-group>
        <award-group id="ag-gamma">
          <funding-source id="fs-gamma"><institution>Gamma Agency</institution></funding-source>
          <award-id id="award-gamma">AWD-2</award-id>
          <funding-statement>Gamma secret payload <xref id="xref-gamma" ref-type="bibr" rid="r-single">single backlink</xref>.</funding-statement>
        </award-group>
      </funding-group>
    </article-meta>
  </front>
  <body><sec><title>Body</title><p>Body.</p></sec></body>
  <back>
    <ref-list>
      <ref id="r-dup"><label>D</label><mixed-citation>Duplicate Citation Secret Payload</mixed-citation></ref>
      <ref id="r-single"><label>S</label><mixed-citation>Single Citation Secret Payload</mixed-citation></ref>
    </ref-list>
  </back>
</article>
XML, 'JATS funding backlink summary XML', preserveWhiteSpace: false);

        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom);
        $summary = $packet['fundingReferenceBacklinkSummary'];

        $t->same(false, $packet['directReaderParity']);
        $t->same('jats-bits-funding-reference-backlinks-metadata-only', $packet['fundingReferenceBacklinkReviewPolicy']);
        $t->same('jats-bits-funding-reference-backlink-summary-metadata-only', $summary['policy']);
        $t->same(true, $summary['metadataOnly']);
        $t->same(true, $summary['citationTextBlocked']);
        $t->same(true, $summary['linkTextBlocked']);
        $t->same(3, $summary['referenceCount']);
        $t->same(['r-missing', 'r-dup', 'r-single'], $summary['referenceIds']);
        $t->same(['missing' => 1, 'duplicate' => 1, 'linked' => 1], $summary['statusCounts']);
        $t->same([
            'missing' => ['r-missing'],
            'duplicate' => ['r-dup'],
            'linked' => ['r-single'],
        ], $summary['referenceIdsByStatus']);
        $t->same(1, $summary['missingReferenceCount']);
        $t->same(1, $summary['duplicateReferenceCount']);
        $t->same(1, $summary['linkedReferenceCount']);
        $t->same(2, $summary['awardSourceConflictReferenceCount']);
        $t->same(['r-missing', 'r-dup'], $summary['awardSourceConflictReferenceIds']);
        $t->same(0, $summary['multiAwardReferenceCount']);
        $t->same([], $summary['multiAwardReferenceIds']);
        $t->same(2, $summary['maxLinkCount']);
        $t->same(['r-missing' => 1, 'r-dup' => 2, 'r-single' => 1], $summary['linkCountsByReferenceId']);
        $t->same(['AWD-1'], $summary['awardIdsByReferenceId']['r-dup'] ?? null);
        $t->same(['fs-alpha', 'fs-beta'], $summary['fundingSourceIdsByReferenceId']['r-dup'] ?? null);
        $t->same($summary['statusCounts'], $packet['fundingReferenceBacklinkStatusCounts']);
        $t->same($summary['referenceIdsByStatus'], $packet['fundingReferenceBacklinkReferenceIdsByStatus']);
        $t->same(['r-missing', 'r-dup'], $packet['fundingReferenceBacklinkConflictReferenceIds']);
        $t->same([], $packet['fundingReferenceBacklinkMultiAwardReferenceIds']);
        $t->same(2, $packet['maxFundingReferenceBacklinkLinkCount']);
        $t->same([
            'duplicate-award-id',
            'conflicting-award-source-pair',
            'missing-funding-reference-backlink',
            'duplicate-funding-reference-backlink',
        ], array_values(array_unique($packet['fundingDiagnosticCodes'])));

        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedPacket, 'Duplicate Citation Secret Payload'));
        $t->true(!str_contains($encodedPacket, 'Single Citation Secret Payload'));
        $t->true(!str_contains($encodedPacket, 'Alpha secret payload'));
        $t->true(!str_contains($encodedPacket, 'Beta secret payload'));

        $encodedFundingBacklinkReview = json_encode([
            $summary,
            $packet['fundingLinkedReferences'],
            $packet['fundingReferenceBacklinks'],
            $packet['fundingDiagnostics'],
        ], JSON_THROW_ON_ERROR);
        $t->true(!str_contains($encodedFundingBacklinkReview, 'shared backlink'));
        $t->true(!str_contains($encodedFundingBacklinkReview, 'duplicate backlink'));
    },
];
