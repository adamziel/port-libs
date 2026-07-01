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
    'converts csl json ris and endnote xml bibliography inputs through the registered reader path' => static function (TestRunner $t): void {
        $cslJson = json_encode([
            'items' => [
                [
                    'id' => 'json-source',
                    'type' => 'book',
                    'title' => 'CSL JSON Packet',
                    'author' => [['family' => 'Ng', 'given' => 'Nia']],
                    'issued' => ['date-parts' => [[2026]]],
                ],
            ],
            'metadata' => [
                'exporter' => 'Reference Manager',
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
    'rejects malformed bibliography inputs through converter dispatch' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('@book{missing,title={Bad}', 'bibtex');
        });
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('{"id":"single-object"}', 'csljson');
        });
        $t->throws(\InvalidArgumentException::class, static function (): void {
            PandocConverter::read('{"references":"not-a-list"}', 'csljson');
        });
    },
];
