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
        $review = $document->attr('bibtexReview');

        $t->same('bibtex', $document->attr('sourceFormat'));
        $t->same(1, $document->attr('cslItemCount'));
        $t->same(['smith1899'], $document->attr('cslItemIds'));
        $t->same('bibtex', $document->attr('bibliography')['format'] ?? null);
        $t->same(BibliographyReader::class, $document->attr('bibliography')['reader'] ?? null);
        $t->same($review, $document->attr('bibliography')['bibtexReview'] ?? null);
        $t->same('bibtex-bibliography', $review['scope']);
        $t->same('metadata-only', $review['byteExposurePolicy']);
        $t->same(false, $review['externalTooling']);
        $t->same(['smith1899'], $review['itemIds']);
        $t->same(['book' => 1], $review['entryTypeCounts']);
        $t->same(['book' => 1], $review['cslTypeCounts']);
        $t->same(['DOI' => 1], $review['identifierFieldCounts']);
        $t->same('definition_list', $bibliography->type);
        $t->same(['pandoc-csl-bibliography'], $bibliography->attr('classes'));
        $t->same('Smith 1899', $item->children[0]->attr('text'));
        $t->contains('Smith, Ada. Migration Patterns. Archive Press, 1899.', $definitionText($item));
        $t->contains('DOI 10.1234/source', $definitionText($item));
        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($reviewJson, 'Migration Patterns'));
        $t->same(false, str_contains($reviewJson, '10.1234/source'));
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
    'records metadata only endnote xml reader review provenance' => static function (TestRunner $t): void {
        $endnoteXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xml>
  <records>
    <record>
      <ref-type name="Journal Article">17</ref-type>
      <accession-num>endnote-one</accession-num>
      <contributors>
        <authors>
          <author>Ng, Nia</author>
          <corporate-author>Private Archive Desk</corporate-author>
        </authors>
        <secondary-authors>
          <author>
            <first-name>Eli</first-name>
            <last-name>Editor</last-name>
          </author>
        </secondary-authors>
      </contributors>
      <titles>
        <title>Secret EndNote Packet Title</title>
        <secondary-title>Private Journal Title</secondary-title>
        <alternate-title>Private Short Title</alternate-title>
      </titles>
      <dates>
        <year>2026-07-01</year>
        <pub-date>not-a-date</pub-date>
      </dates>
      <work-type>peer reviewed article</work-type>
      <publication-type>online ahead</publication-type>
      <electronic-resource-num>10.5555/private-endnote</electronic-resource-num>
      <urls>
        <related-urls>
          <url>https://example.test/private-endnote</url>
        </related-urls>
        <pdf-urls>
          <url>attachments/private.pdf</url>
        </pdf-urls>
      </urls>
      <custom3>Private custom note</custom3>
      <research-notes>Private research note</research-notes>
    </record>
    <record>
      <ref-type name="Book">6</ref-type>
      <accession-num>endnote-two</accession-num>
      <contributors>
        <authors>
          <author>
            <first-name>GivenOnly</first-name>
          </author>
          <author>
            <suffix>III</suffix>
          </author>
        </authors>
      </contributors>
      <titles>
        <title>Second Secret EndNote Title</title>
        <tertiary-title>Private Series</tertiary-title>
        <short-title>Private Short</short-title>
      </titles>
      <dates>
        <date></date>
        <issue-date>2025</issue-date>
      </dates>
      <urls>
        <image-urls>
          <url>attachments/private-image.png</url>
        </image-urls>
      </urls>
      <custom1>Private custom field</custom1>
    </record>
  </records>
</xml>
XML;

        $document = (new BibliographyReader('endnotexml'))->read($endnoteXml);
        $review = $document->attr('endnoteXmlReview');
        $items = $review['items'];

        $t->same($review, $document->attr('bibliography')['endnoteXmlReview'] ?? null);
        $t->same($items, $document->attr('endnoteXmlItemReviews'));
        $t->same('endnote-xml-bibliography', $review['scope']);
        $t->same('metadata-only', $review['byteExposurePolicy']);
        $t->same(false, $review['externalTooling']);
        $t->same(2, $review['itemCount']);
        $t->same(['endnote-one', 'endnote-two'], $review['itemIds']);
        $t->same(['article-journal' => 1, 'book' => 1], $review['cslTypeCounts']);
        $t->same(2, $review['titleBearingItemCount']);
        $t->same(2, $review['linkBearingItemCount']);
        $t->same(['author' => 3, 'editor' => 1], $review['nameVariableCounts']);
        $t->same(['issued' => 4], $review['dateVariableCounts']);
        $t->same(['doi' => 1], $review['identifierFieldCounts']);
        $t->same(2, $review['sourceFileCandidateCount']);
        $t->same(['endnote-attachment-not-imported' => 2], $review['sourceFileDiagnosticReasonCounts']);
        $t->same(2, $review['endnoteRefTypeItemCount']);
        $t->same(0, $review['endnoteDatabaseItemCount']);
        $t->same([
            'alternate-title' => 1,
            'secondary-title' => 1,
            'short-title' => 1,
            'tertiary-title' => 1,
            'title' => 2,
        ], $review['endnoteTitleFieldCounts']);
        $t->same(['publication-type' => 1, 'ref-type' => 2, 'work-type' => 1], $review['endnotePublicationTypeHintFieldCounts']);
        $t->same(['endnote-publication-hint-preserved' => 2, 'endnote-ref-type-mapped' => 2], $review['endnotePublicationTypeHintReasonCounts']);
        $t->same(['date' => 1, 'issue-date' => 1, 'pub-date' => 1, 'year' => 1], $review['endnoteDateFieldCounts']);
        $t->same(['endnote-date-empty-field' => 1, 'endnote-date-malformed-field' => 1], $review['endnoteDateDiagnosticReasonCounts']);
        $t->same(['authors' => 4, 'secondary-authors' => 1], $review['endnoteNameGroupCounts']);
        $t->same(['author' => 4, 'editor' => 1], $review['endnoteNameRoleCounts']);
        $t->same(['corporate' => 1, 'personal-comma' => 1, 'personal-parts' => 2, 'skipped' => 1], $review['endnoteNameParsedAsCounts']);
        $t->same(['endnote-name-empty-structured-parts' => 1, 'endnote-name-missing-family' => 1], $review['endnoteNameDiagnosticReasonCounts']);
        $t->same(['custom1' => 1, 'custom3' => 1, 'research-notes' => 1], $review['endnoteUnsupportedFieldCounts']);
        $t->same(['endnote-field-preserved-raw-only' => 3], $review['endnoteUnsupportedFieldReasonCounts']);

        $first = $items[0];
        $t->same(0, $first['index']);
        $t->same('endnote-one', $first['id']);
        $t->same('article-journal', $first['cslType']);
        $t->same(['container-title', 'short-title', 'title'], $first['titleFields']);
        $t->same(['author' => 2, 'editor' => 1], $first['nameVariableCounts']);
        $t->same(3, $first['nameCount']);
        $t->same(['issued' => 3], $first['datePartCounts']);
        $t->same(['doi'], $first['identifierFields']);
        $t->same(['doi', 'sourceFileDiagnostics', 'url'], $first['linkFields']);
        $t->same(['alternate-title' => 1, 'secondary-title' => 1, 'title' => 1], $first['endnoteTitleFieldCounts']);
        $t->same(['publication-type' => 1, 'ref-type' => 1, 'work-type' => 1], $first['endnotePublicationTypeHintFieldCounts']);
        $t->same(['pub-date' => 1, 'year' => 1], $first['endnoteDateFieldCounts']);
        $t->same(['endnote-date-malformed-field' => 1], $first['endnoteDateDiagnosticReasonCounts']);
        $t->same(['authors' => 2, 'secondary-authors' => 1], $first['endnoteNameGroupCounts']);
        $t->same(['author' => 2, 'editor' => 1], $first['endnoteNameRoleCounts']);
        $t->same(['corporate' => 1, 'personal-comma' => 1, 'personal-parts' => 1], $first['endnoteNameParsedAsCounts']);
        $t->same(['custom3' => 1, 'research-notes' => 1], $first['endnoteUnsupportedFieldCounts']);
        $t->same('source-values-omitted', $first['payloadExposurePolicy']);

        $second = $items[1];
        $t->same('endnote-two', $second['id']);
        $t->same('book', $second['cslType']);
        $t->same(['collection-title', 'short-title', 'title'], $second['titleFields']);
        $t->same(['author' => 1], $second['nameVariableCounts']);
        $t->same(['issued' => 1], $second['datePartCounts']);
        $t->same([], $second['identifierFields']);
        $t->same(['sourceFileDiagnostics'], $second['linkFields']);
        $t->same(['short-title' => 1, 'tertiary-title' => 1, 'title' => 1], $second['endnoteTitleFieldCounts']);
        $t->same(['date' => 1, 'issue-date' => 1], $second['endnoteDateFieldCounts']);
        $t->same(['endnote-date-empty-field' => 1], $second['endnoteDateDiagnosticReasonCounts']);
        $t->same(['authors' => 2], $second['endnoteNameGroupCounts']);
        $t->same(['personal-parts' => 1, 'skipped' => 1], $second['endnoteNameParsedAsCounts']);
        $t->same(['endnote-name-empty-structured-parts' => 1, 'endnote-name-missing-family' => 1], $second['endnoteNameDiagnosticReasonCounts']);
        $t->same(['custom1' => 1], $second['endnoteUnsupportedFieldCounts']);

        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($reviewJson, 'Secret EndNote Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Second Secret EndNote Title'));
        $t->same(false, str_contains($reviewJson, 'Private Archive Desk'));
        $t->same(false, str_contains($reviewJson, 'Private Journal Title'));
        $t->same(false, str_contains($reviewJson, '10.5555/private-endnote'));
        $t->same(false, str_contains($reviewJson, 'https://example.test/private-endnote'));
        $t->same(false, str_contains($reviewJson, 'attachments/private.pdf'));
        $t->same(false, str_contains($reviewJson, 'attachments/private-image.png'));
        $t->same(false, str_contains($reviewJson, 'Private custom note'));
        $t->same(false, str_contains($reviewJson, 'Private research note'));
        $t->same(false, str_contains($reviewJson, 'Private custom field'));
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
    'records metadata only biblatex reader review provenance' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@article{biblatex-private-one,
  author = {Ng, Nia and Roe, Pat},
  title = {Secret BibLaTeX Packet Title},
  journaltitle = {Private Journal Title},
  date = {2026-07-01},
  doi = {10.5555/private-biblatex},
  url = {https://example.test/private-biblatex},
  file = {Review PDF:attachments/private.pdf:application/pdf; Remote:https://example.test/private.pdf:application/pdf},
  xdata = {private-xdata, missing-xdata},
  related = {private-related, missing-related},
  relatedtype = {reprintof},
  keywords = {private-keyword, source-review},
  usera = {review channel},
  lista = {source lane and migration priority},
  namea = {Curator, Case},
  title+an:field = {private annotation}
}
@xdata{private-xdata,
  publisher = {Private Publisher}
}
@book{private-related,
  options = {dataonly},
  title = {Private Related Title},
  year = {1999}
}
BIB;

        $document = (new BibliographyReader('biblatex'))->read($biblatex);
        $review = $document->attr('bibtexReview');
        $items = $review['items'];

        $t->same($review, $document->attr('bibliography')['bibtexReview'] ?? null);
        $t->same($items, $document->attr('bibtexItemReviews'));
        $t->same('biblatex-bibliography', $review['scope']);
        $t->same('metadata-only', $review['byteExposurePolicy']);
        $t->same(false, $review['externalTooling']);
        $t->same(1, $review['itemCount']);
        $t->same(['biblatex-private-one'], $review['itemIds']);
        $t->same(['article' => 1], $review['entryTypeCounts']);
        $t->same(['article-journal' => 1], $review['cslTypeCounts']);
        $t->same(16, $review['fieldNameCount']);
        $t->same(1, $review['fieldValueCounts']['title'] ?? null);
        $t->same(1, $review['fieldValueCounts']['journaltitle'] ?? null);
        $t->same(1, $review['fieldValueCounts']['publisher'] ?? null);
        $t->same(1, $review['fieldValueCounts']['file'] ?? null);
        $t->same(1, $review['fieldValueCounts']['xdata'] ?? null);
        $t->same(1, $review['fieldValueCounts']['related'] ?? null);
        $t->same(1, $review['fieldValueCounts']['title+an:field'] ?? null);
        $t->same(1, $review['titleBearingItemCount']);
        $t->same(1, $review['linkBearingItemCount']);
        $t->same(['author' => 2], $review['nameVariableCounts']);
        $t->same(['issued' => 3], $review['dateVariableCounts']);
        $t->same(['DOI' => 1], $review['identifierFieldCounts']);
        $t->same(2, $review['sourceFileCandidateCount']);
        $t->same(['remote-uri' => 1], $review['sourceFileDiagnosticReasonCounts']);
        $t->same(['related' => 2, 'xdata' => 2], $review['relationReferenceCounts']);
        $t->same(['related' => 1, 'xdata' => 1], $review['missingRelationReferenceCounts']);
        $t->same(['usera' => 1], $review['biblatexCustomFieldCounts']);
        $t->same(['lista' => 1], $review['biblatexCustomListCounts']);
        $t->same(['namea' => 1], $review['biblatexCustomNameCounts']);
        $t->same(['title' => 1], $review['biblatexFieldAnnotationCounts']);

        $first = $items[0];
        $t->same(0, $first['index']);
        $t->same('biblatex-private-one', $first['id']);
        $t->same('article', $first['entryType']);
        $t->same('article-journal', $first['cslType']);
        $t->same(16, $first['fieldNameCount']);
        $t->same(16, $first['fieldValueCount']);
        $t->same(['container-title', 'title'], $first['titleFields']);
        $t->same(['author' => 2], $first['nameVariableCounts']);
        $t->same(2, $first['nameCount']);
        $t->same(['issued' => 3], $first['datePartCounts']);
        $t->same(['DOI'], $first['identifierFields']);
        $t->same(['DOI', 'URL', 'sourceFiles'], $first['linkFields']);
        $t->same(2, $first['sourceFileCandidateCount']);
        $t->same(['remote-uri' => 1], $first['sourceFileDiagnosticReasonCounts']);
        $t->same(['related' => 2, 'xdata' => 2], $first['relationReferenceCounts']);
        $t->same(['related' => 1, 'xdata' => 1], $first['missingRelationReferenceCounts']);
        $t->same(['usera'], $first['biblatexCustomFields']);
        $t->same(['lista'], $first['biblatexCustomLists']);
        $t->same(['namea'], $first['biblatexCustomNames']);
        $t->same(['title' => 1], $first['biblatexFieldAnnotationCounts']);
        $t->same('source-values-omitted', $first['payloadExposurePolicy']);

        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($reviewJson, 'Secret BibLaTeX Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Private Journal Title'));
        $t->same(false, str_contains($reviewJson, '10.5555/private-biblatex'));
        $t->same(false, str_contains($reviewJson, 'https://example.test/private-biblatex'));
        $t->same(false, str_contains($reviewJson, 'attachments/private.pdf'));
        $t->same(false, str_contains($reviewJson, 'private-keyword'));
        $t->same(false, str_contains($reviewJson, 'review channel'));
        $t->same(false, str_contains($reviewJson, 'private annotation'));
        $t->same(false, str_contains($reviewJson, 'Private Publisher'));
        $t->same(false, str_contains($reviewJson, 'Private Related Title'));
    },
    'honors biblatex skip bibliography options in the reader bibliography path' => static function (TestRunner $t): void {
        $biblatex = <<<'BIB'
@book{visible-entry,
  author = {Ng, Nia},
  title = {Visible Packet Title},
  date = {2026}
}

@book{skip-entry,
  author = {Roe, Pat},
  title = {Skipped Private Packet Title},
  date = {2025},
  options = {skipbib, useprefix=true}
}

@book{include-entry,
  author = {Ito, Ira},
  title = {Explicit Include Packet Title},
  date = {2024},
  options = {skipbib=false, terseinits},
  langidopts = {variant=british, hyphenation=ngerman}
}

@xdata{source-defaults,
  publisher = {Private Defaults Publisher}
}

@book{data-only-entry,
  title = {Data Only Private Packet Title},
  options = {dataonly}
}
BIB;

        $document = (new BibliographyReader('biblatex'))->read($biblatex);
        $bibliography = $document->children[0];
        $review = $document->attr('bibtexReview');
        $items = [];
        foreach ($review['items'] as $item) {
            $items[$item['id']] = $item;
        }

        $t->same(['visible-entry', 'skip-entry', 'include-entry'], $document->attr('cslItemIds'));
        $t->same([
            'visible-entry',
            'include-entry',
        ], array_map(
            static fn (AstNode $item): string => (string) $item->attr('cslId'),
            $bibliography->children
        ));
        $t->same(3, $review['itemCount']);
        $t->same(2, $review['biblatexBibliographyVisibleItemCount']);
        $t->same(1, $review['biblatexSkipBibliographyItemCount']);
        $t->same(['include' => 2, 'omit' => 1], $review['biblatexBibliographyVisibilityCounts']);
        $t->same(['skipbib' => 2, 'terseinits' => 1, 'useprefix' => 1], $review['biblatexOptionNameCounts']);
        $t->same(['hyphenation' => 1, 'variant' => 1], $review['biblatexLanguageOptionNameCounts']);

        $t->same([], $items['visible-entry']['biblatexOptionNames']);
        $t->same(false, $items['visible-entry']['biblatexSkipsBibliography']);
        $t->same('include', $items['visible-entry']['biblatexBibliographyVisibility']);
        $t->same(['skipbib', 'useprefix'], $items['skip-entry']['biblatexOptionNames']);
        $t->same(true, $items['skip-entry']['biblatexSkipsBibliography']);
        $t->same('omit', $items['skip-entry']['biblatexBibliographyVisibility']);
        $t->same(['skipbib', 'terseinits'], $items['include-entry']['biblatexOptionNames']);
        $t->same(['hyphenation', 'variant'], $items['include-entry']['biblatexLanguageOptionNames']);
        $t->same(false, $items['include-entry']['biblatexSkipsBibliography']);
        $t->same('include', $items['include-entry']['biblatexBibliographyVisibility']);

        $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($reviewJson, 'Skipped Private Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Explicit Include Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Data Only Private Packet Title'));
        $t->same(false, str_contains($reviewJson, 'Private Defaults Publisher'));
        $t->same(false, str_contains($reviewJson, 'british'));
        $t->same(false, str_contains($reviewJson, 'ngerman'));
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
