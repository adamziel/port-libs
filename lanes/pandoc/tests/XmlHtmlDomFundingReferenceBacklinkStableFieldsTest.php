<?php

declare(strict_types=1);

use PortLibs\Pandoc\XmlHtmlDom;

return [
    'summarizes jats bits funding backlink stable target fields' => static function (TestRunner $t): void {
        $bits = XmlHtmlDom::loadXmlDocument(<<<'XML'
<book book-type="edited-book" dtd-version="2.1" xml:lang="en">
  <book-meta>
    <book-title-group><book-title>Funding Backlink Stable Fields</book-title></book-title-group>
    <funding-group id="fg-stable">
      <award-group id="ag-alpha">
        <funding-source id="fs-alpha"><institution>Alpha Foundation</institution></funding-source>
        <award-id id="award-alpha">STABLE-1</award-id>
        <funding-statement>Alpha funding secret payload <xref id="xref-alpha" ref-type="bibr" rid="missing-ref shared-ref">shared funding citation</xref> remains hidden.</funding-statement>
      </award-group>
      <award-group id="ag-beta">
        <funding-source id="fs-beta"><institution>Beta Foundation</institution></funding-source>
        <award-id id="award-beta">STABLE-1</award-id>
        <funding-statement>Beta funding secret payload <xref id="xref-beta" ref-type="bibr" rid="shared-ref">repeat shared citation</xref> remains hidden.</funding-statement>
      </award-group>
      <award-group id="ag-gamma">
        <funding-source id="fs-gamma"><institution>Gamma Foundation</institution></funding-source>
        <award-id id="award-gamma">OTHER-2</award-id>
        <funding-statement>Gamma funding secret payload <xref id="xref-gamma" ref-type="bibr" rid="solo-ref">solo citation</xref> remains hidden.</funding-statement>
      </award-group>
    </funding-group>
  </book-meta>
  <book-body><book-part><body><sec><title>Body</title><p>Review body.</p></sec></body></book-part></book-body>
  <back>
    <ref-list>
      <ref id="shared-ref"><label>S</label><mixed-citation>Shared Citation Secret must stay hidden</mixed-citation></ref>
      <ref id="solo-ref"><label>A</label><mixed-citation>Solo Citation Secret must stay hidden</mixed-citation></ref>
    </ref-list>
  </back>
</book>
XML, 'BITS funding backlink stable field XML', preserveWhiteSpace: false);

        $packet = XmlHtmlDom::summarizeJatsFrontMatter($bits, 'bits');
        $encodedPacket = json_encode($packet, JSON_THROW_ON_ERROR);

        $t->same(false, $packet['directReaderParity']);
        $t->same('jats-bits-funding-reference-backlinks-metadata-only', $packet['fundingReferenceBacklinkReviewPolicy']);
        $t->same(3, $packet['fundingReferenceBacklinkCount']);
        $t->same(2, $packet['resolvedFundingReferenceBacklinkCount']);
        $t->same(1, $packet['missingFundingReferenceBacklinkCount']);
        $t->same(1, $packet['duplicateFundingReferenceBacklinkCount']);
        $t->same(['missing-ref', 'shared-ref', 'solo-ref'], $packet['fundingReferenceBacklinkTargetIds']);
        $t->same(['shared-ref', 'solo-ref'], $packet['resolvedFundingReferenceBacklinkIds']);
        $t->same(['missing-ref'], $packet['missingFundingReferenceBacklinkIds']);
        $t->same(['shared-ref'], $packet['duplicateFundingReferenceBacklinkIds']);
        $t->same(4, $packet['fundingReferenceBacklinkLinkCount']);
        $t->same(2, $packet['duplicateFundingReferenceBacklinkLinkCount']);
        $t->same([
            'duplicate-award-id',
            'conflicting-award-source-pair',
            'missing-funding-reference-backlink',
            'duplicate-funding-reference-backlink',
        ], $packet['fundingDiagnosticCodes']);
        $t->true(!str_contains($encodedPacket, 'Shared Citation Secret'), 'Expected citation payload text to stay blocked from funding backlink stable fields');
        $t->true(!str_contains($encodedPacket, 'Alpha funding secret'), 'Expected funding statement text to stay blocked from funding backlink stable fields');
    },
];
