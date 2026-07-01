<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\BibliographyReader;
use PortLibs\Pandoc\PandocConverter;

$definitionText = static function (AstNode $item): string {
    return (string) ($item->children[1]->children[0]->children[0]->attr('text') ?? '');
};

return [
    'reads bibtex entries into a csl bibliography ast' => static function (TestRunner $t) use ($definitionText): void {
        $bibtex = <<<'BIB'
@book{smith1899,
  author    = {Smith, Ada},
  title     = {Migration Patterns},
  year      = {1899},
  publisher = {Archive Press},
  doi       = {10.1234/source}
}
BIB;

        $document = (new BibliographyReader('bibtex'))->read($bibtex);
        $bibliography = $document->children[0];
        $item = $bibliography->children[0];

        $t->same('bibtex', $document->attr('sourceFormat'));
        $t->same(1, $document->attr('cslItemCount'));
        $t->same(['smith1899'], $document->attr('cslItemIds'));
        $t->same('bibtex', $document->attr('bibliography')['format'] ?? null);
        $t->same(BibliographyReader::class, $document->attr('bibliography')['reader'] ?? null);
        $t->same('definition_list', $bibliography->type);
        $t->same(['pandoc-csl-bibliography'], $bibliography->attr('classes'));
        $t->same('Smith 1899', $item->children[0]->attr('text'));
        $t->contains('Smith, Ada. Migration Patterns. Archive Press, 1899.', $definitionText($item));
        $t->contains('DOI 10.1234/source', $definitionText($item));
    },
    'converts biblatex bibliography entries through the registered reader path' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@online{preprint,
  author     = {Ng, Nia},
  title      = {Obscure Archive Packet},
  subtitle   = {Source Review Appendix},
  date       = {2026-06-09},
  url        = {https://example.test/preprint},
  urldate    = {2026-06-10},
  eprinttype = {arXiv},
  eprint     = {2606.00001}
}
BIB;

        $document = PandocConverter::read($biblatex, 'biblatex');
        $blocks = PandocConverter::convert($biblatex, 'biblatex', 'blocks');

        $t->same('biblatex', $document->attr('sourceFormat'));
        $t->same(['preprint'], $document->attr('cslItemIds'));
        $t->same('preprint', $document->attr('cslItems')[0]['id'] ?? null);
        $t->same('Obscure Archive Packet: Source Review Appendix', $document->attr('cslItems')[0]['title'] ?? null);
        $t->contains('<dt>Ng 2026</dt><dd>', $blocks);
        $t->contains('Obscure Archive Packet: Source Review Appendix', $blocks);
        $t->contains('https://example.test/preprint', $blocks);
    },
    'keeps biblatex available and submitted dates visible through the registered reader path' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@online{date-review,
  author        = {Ng, Nia},
  title         = {Archive Availability Packet},
  date          = {2026},
  availabledate = {2026-05-01},
  submitteddate = {2026-05-03},
  url           = {https://example.test/date-review}
}
BIB;

        $document = PandocConverter::read($biblatex, 'biblatex');
        $blocks = PandocConverter::write($document, 'blocks');
        $item = $document->attr('cslItems')[0] ?? [];

        $t->same('date-review', $item['id'] ?? null);
        $t->same([2026, 5, 1], $item['available-date']['date-parts'][0] ?? null);
        $t->same([2026, 5, 3], $item['submitted']['date-parts'][0] ?? null);
        $t->contains('Available date: 2026-05-01.', $blocks);
        $t->contains('Submitted date: 2026-05-03.', $blocks);
    },
    'converts csl json ris and endnote xml bibliography inputs through the registered reader path' => static function (TestRunner $t): void {
        $cslJson = json_encode([
            [
                'id' => 'json-source',
                'type' => 'book',
                'title' => 'CSL JSON Packet',
                'author' => [['family' => 'Ng', 'given' => 'Nia']],
                'issued' => ['date-parts' => [[2026]]],
            ],
        ], JSON_THROW_ON_ERROR);
        $ris = <<<'RIS'
TY  - BOOK
ID  - ris-source
AU  - Roe, Pat
TI  - RIS Packet
PY  - 2025
ER  -
RIS;
        $endnoteXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xml>
  <records>
    <record>
      <ref-type name="Book">6</ref-type>
      <rec-number>42</rec-number>
      <accession-num>endnote-source</accession-num>
      <contributors>
        <authors>
          <author>Curator, Eli</author>
        </authors>
      </contributors>
      <titles>
        <title>EndNote Packet</title>
      </titles>
      <dates>
        <year>2024</year>
      </dates>
    </record>
  </records>
</xml>
XML;

        $jsonDocument = PandocConverter::read($cslJson, 'csljson');
        $risDocument = PandocConverter::read($ris, 'ris');
        $endnoteDocument = PandocConverter::read($endnoteXml, 'endnotexml');
        $jsonBlocks = PandocConverter::write($jsonDocument, 'blocks');
        $risBlocks = PandocConverter::write($risDocument, 'blocks');
        $endnoteBlocks = PandocConverter::write($endnoteDocument, 'blocks');

        $t->same('csljson', $jsonDocument->attr('sourceFormat'));
        $t->same(['json-source'], $jsonDocument->attr('cslItemIds'));
        $t->contains('<dt>Ng 2026</dt><dd>Ng, Nia. CSL JSON Packet. 2026.</dd>', $jsonBlocks);

        $t->same('ris', $risDocument->attr('sourceFormat'));
        $t->same(['ris-source'], $risDocument->attr('cslItemIds'));
        $t->contains('<dt>Roe 2025</dt><dd>Roe, Pat. RIS Packet. 2025.</dd>', $risBlocks);

        $t->same('endnotexml', $endnoteDocument->attr('sourceFormat'));
        $t->same(['endnote-source'], $endnoteDocument->attr('cslItemIds'));
        $t->contains('<dt>Curator 2024</dt><dd>Curator, Eli. EndNote Packet. 2024.</dd>', $endnoteBlocks);
    },
    'records metadata only csl json reader review provenance' => static function (TestRunner $t): void {
        $cslJson = json_encode([
            [
                'id' => 'packet-one',
                'type' => 'article-journal',
                'title' => 'Secret CSL JSON Packet Title',
                'author' => [
                    ['family' => 'Ng', 'given' => 'Nia'],
                    ['literal' => 'Archive Desk'],
                ],
                'editor' => [['family' => 'Roe', 'given' => 'Pat']],
                'issued' => ['date-parts' => [[2026, 6, 30]]],
                'accessed' => ['date-parts' => [[2026, 7, 1]]],
                'DOI' => '10.5555/private-doi',
                'URL' => 'https://example.test/private-url',
                'keywordList' => ['private-keyword', 'source-audit'],
                'category-list' => 'private-category, csl',
                'references' => 'private reference payload',
            ],
            [
                'id' => 'packet-two',
                'type' => 'book',
                'container-title' => 'Private Container Title',
                'translator' => [['family' => 'Ito', 'given' => 'Ira']],
                'original-date' => ['date-parts' => [[1999]]],
                'ISBN' => '978-private-isbn',
                'citationAliases' => ['packet-two-alt'],
            ],
        ], JSON_THROW_ON_ERROR);

        $document = (new BibliographyReader('csljson'))->read($cslJson);
        $review = $document->attr('cslJsonReview');
        $items = $review['items'];

        $t->same($review, $document->attr('bibliography')['cslJsonReview'] ?? null);
        $t->same($items, $document->attr('cslJsonItemReviews'));
        $t->same('csl-json-bibliography', $review['scope']);
        $t->same('metadata-only', $review['byteExposurePolicy']);
        $t->same(false, $review['externalTooling']);
        $t->same(2, $review['itemCount']);
        $t->same(['packet-one', 'packet-two'], $review['itemIds']);
        $t->same(17, $review['fieldNameCount']);
        $t->same([
            'DOI',
            'ISBN',
            'URL',
            'accessed',
            'author',
            'category-list',
            'citationAliases',
            'container-title',
            'editor',
            'id',
            'issued',
            'keywordList',
            'original-date',
            'references',
            'title',
            'translator',
            'type',
        ], $review['fieldNames']);
        $t->same(['article-journal' => 1, 'book' => 1], $review['typeCounts']);
        $t->same(2, $review['titleBearingItemCount']);
        $t->same(1, $review['linkBearingItemCount']);
        $t->same(['author' => 2, 'editor' => 1, 'translator' => 1], $review['nameVariableCounts']);
        $t->same(['accessed' => 3, 'issued' => 3, 'original-date' => 1], $review['dateVariableCounts']);
        $t->same(['DOI' => 1, 'ISBN' => 1], $review['identifierFieldCounts']);

        $first = $items[0];
        $t->same(0, $first['index']);
        $t->same('packet-one', $first['id']);
        $t->same('article-journal', $first['type']);
        $t->same(['title'], $first['titleFields']);
        $t->same(['author' => 2, 'editor' => 1], $first['nameVariableCounts']);
        $t->same(3, $first['nameCount']);
        $t->same(['accessed' => 3, 'issued' => 3], $first['datePartCounts']);
        $t->same(['DOI'], $first['identifierFields']);
        $t->same(['DOI', 'URL'], $first['linkFields']);
        $t->same(['references'], $first['relationFields']);
        $t->same('source-values-omitted', $first['payloadExposurePolicy']);

        $second = $items[1];
        $t->same(['container-title'], $second['titleFields']);
        $t->same(['translator' => 1], $second['nameVariableCounts']);
        $t->same(['original-date' => 1], $second['datePartCounts']);
        $t->same(['ISBN'], $second['identifierFields']);
        $t->same([], $second['linkFields']);

        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($reviewJson, 'Secret CSL JSON Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Private Container Title'));
        $t->same(false, str_contains($reviewJson, '10.5555/private-doi'));
        $t->same(false, str_contains($reviewJson, 'https://example.test/private-url'));
        $t->same(false, str_contains($reviewJson, 'private-keyword'));
        $t->same(false, str_contains($reviewJson, 'private-category'));
        $t->same(false, str_contains($reviewJson, 'private reference payload'));
        $t->same(false, str_contains($reviewJson, '978-private-isbn'));
    },
    'records metadata only ris reader review provenance' => static function (TestRunner $t): void {
        $ris = <<<'RIS'
TY  - JOUR
ID  - ris-private-one
AU  - Ng, Nia
AU  - Roe, Pat
TI  - Secret RIS Packet Title
T1  - Private Alternate Packet Title
RI  - Private Reviewed Manual
RI  - Conflicting Reviewed Manual
PY  - 2026/07/01/
DO  - 10.5555/private-ris
UR  - https://example.test/private-ris
L1  - attachments/private.pdf
L2  - https://example.test/private.pdf
ER  -

TY  - RPRT
ID  - ris-private-two
AU  - Migration Review Desk
TI  - Private RIS Report
PY  - 2025
KW  - private-keyword
KW  - source-review
U1  - review channel
C1  - verbatim private note
ER  -
RIS;

        $document = (new BibliographyReader('ris'))->read($ris);
        $review = $document->attr('risReview');
        $items = $review['items'];

        $t->same($review, $document->attr('bibliography')['risReview'] ?? null);
        $t->same($items, $document->attr('risItemReviews'));
        $t->same('ris-bibliography', $review['scope']);
        $t->same('metadata-only', $review['byteExposurePolicy']);
        $t->same(false, $review['externalTooling']);
        $t->same(2, $review['itemCount']);
        $t->same(['ris-private-one', 'ris-private-two'], $review['itemIds']);
        $t->same(['JOUR' => 1, 'RPRT' => 1], $review['recordTypeCounts']);
        $t->same(['article-journal' => 1, 'report' => 1], $review['cslTypeCounts']);
        $t->same(14, $review['fieldTagCount']);
        $t->same(3, $review['fieldValueCounts']['AU'] ?? null);
        $t->same(2, $review['fieldValueCounts']['KW'] ?? null);
        $t->same(2, $review['fieldValueCounts']['RI'] ?? null);
        $t->same(['L1' => 1, 'L2' => 1], $review['attachmentTagCounts']);
        $t->same(2, $review['sourceFileCandidateCount']);
        $t->same(['reviewed-title' => 1, 'title' => 1, 'usera' => 1, 'verba' => 1], $review['mappedFieldCounts']);
        $t->same(2, $review['duplicateMappedFieldCount']);
        $t->same(2, $review['conflictingMappedFieldCount']);

        $first = $items[0];
        $t->same(0, $first['index']);
        $t->same('ris-private-one', $first['id']);
        $t->same('JOUR', $first['recordType']);
        $t->same('article-journal', $first['cslType']);
        $t->same(11, $first['fieldTagCount']);
        $t->same(13, $first['fieldValueCount']);
        $t->same(['L1' => 1, 'L2' => 1], $first['attachmentTagCounts']);
        $t->same(['reviewed-title', 'title'], $first['mappedFields']);
        $t->same(['reviewed-title', 'title'], $first['duplicateMappedFields']);
        $t->same(['reviewed-title', 'title'], $first['conflictingMappedFields']);
        $t->same('source-values-omitted', $first['payloadExposurePolicy']);

        $second = $items[1];
        $t->same('RPRT', $second['recordType']);
        $t->same('report', $second['cslType']);
        $t->same(8, $second['fieldTagCount']);
        $t->same(9, $second['fieldValueCount']);
        $t->same([], $second['attachmentTagCounts']);
        $t->same(['usera', 'verba'], $second['mappedFields']);
        $t->same([], $second['duplicateMappedFields']);
        $t->same([], $second['conflictingMappedFields']);

        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($reviewJson, 'Secret RIS Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Private Alternate Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Private Reviewed Manual'));
        $t->same(false, str_contains($reviewJson, 'Conflicting Reviewed Manual'));
        $t->same(false, str_contains($reviewJson, '10.5555/private-ris'));
        $t->same(false, str_contains($reviewJson, 'https://example.test/private-ris'));
        $t->same(false, str_contains($reviewJson, 'attachments/private.pdf'));
        $t->same(false, str_contains($reviewJson, 'private-keyword'));
        $t->same(false, str_contains($reviewJson, 'verbatim private note'));
    },
    'rejects malformed bibliography inputs through converter dispatch' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('@book{missing,title={Bad}', 'bibtex');
        });
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('{"id":"single-object"}', 'csljson');
        });
    },
];
