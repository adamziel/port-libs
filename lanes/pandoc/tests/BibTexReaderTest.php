<?php

declare(strict_types=1);

use PortLibs\Pandoc\BibTexReader;
use PortLibs\Pandoc\JsonWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads bibtex entries strings names and bibliography blocks into shared ast' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@comment{Ignored parser note.}
@string{JACM = "Journal of the ACM"}
@preamble{"Generated from " # "source export"}

@article{lovelace1843,
  author = {Lovelace, Ada and Babbage, Charles},
  title = {Sketch of the {Analytical Engine}},
  journaltitle = JACM,
  year = {1843},
  volume = {1},
  number = {2},
  pages = {10--21},
  doi = {10.1145/12345},
  note = {Includes {\LaTeX} braces}
}

@book{wp-handbook,
  author = {{WordPress Foundation}},
  title = {Block Editor Handbook},
  publisher = {WordPress.org},
  location = {Online},
  date = {2026-06-25},
  url = {https://example.test/handbook}
}
BIB;

        $document = (new BibTexReader())->read($source);
        $meta = $document->attr('meta');
        $references = $meta['references']['value'];
        $article = $references[0]['value'];
        $book = $references[1]['value'];
        $blocks = (new WordPressBlockWriter())->write($document);
        $json = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('bibtex', $meta['bibtexVariant']);
        $t->same(2, $meta['bibtexEntryCount']);
        $t->same(14, $meta['bibtexStringCount']);
        $t->same(1, $meta['bibtexPreambleCount']);
        $t->same(1, $meta['bibtexCommentCount']);
        $t->same('MetaList', $meta['references']['type']);
        $t->same('lovelace1843', $article['id']);
        $t->same('article-journal', $article['type']);
        $t->same('Sketch of the Analytical Engine', $article['title']);
        $t->same('Journal of the ACM', $article['container-title']);
        $t->same('Lovelace', $article['author']['value'][0]['value']['family']);
        $t->same('Ada', $article['author']['value'][0]['value']['given']);
        $t->same('WordPress Foundation', $book['author']['value'][0]['value']['literal']);
        $t->same('2026', $book['year']);
        $t->same('div', $document->children[0]->type);
        $t->same(['csl-bib-body'], $document->children[0]->attr('classes'));
        $t->same('ref-lovelace1843', $document->children[0]->children[0]->attr('id'));
        $t->contains('<div id="refs" class="csl-bib-body" data-pandoc-source="bibtex" data-bibtex-entry-count="2">', $blocks);
        $t->contains('<div id="ref-lovelace1843" class="csl-entry" data-bibtex-key="lovelace1843" data-bibtex-type="article">', $blocks);
        $t->contains('Ada Lovelace and Charles Babbage. (1843). Sketch of the Analytical Engine. <em>Journal of the ACM</em> 1(2): 10--21.', $blocks);
        $t->contains('<a href="https://doi.org/10.1145/12345">10.1145/12345</a>', $blocks);
        $t->contains('<em>Block Editor Handbook</em>. Online: WordPress.org.', $blocks);
        $t->same('MetaList', $json['meta']['references']['t']);
        $t->same('Cite', $json['meta']['nocite']['c'][0]['t']);
        $t->same('*', $json['meta']['nocite']['c'][0]['c'][0][0]['citationId']);
    },
    'routes biblatex through the converter with biblatex field aliases' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@online{site2026,
  author = {Doe, Jane},
  title = {Import Notes},
  subtitle = {WordPress Blocks},
  date = {2026-06-25},
  url = {https://example.test/import},
  urldate = {2026-06-26}
}
BIB;

        $document = PandocConverter::read($source, 'biblatex');
        $blocks = PandocConverter::convert($source, 'biblatex', 'blocks');
        $meta = $document->attr('meta');
        $reference = $meta['references']['value'][0]['value'];

        $t->same('biblatex', $meta['bibtexVariant']);
        $t->same('site2026', $reference['id']);
        $t->same('webpage', $reference['type']);
        $t->same('Import Notes: WordPress Blocks', $reference['title']);
        $t->same('2026', $reference['year']);
        $t->contains('Jane Doe. (2026). Import Notes: WordPress Blocks.', $blocks);
        $t->contains('<a href="https://example.test/import">https://example.test/import</a>', $blocks);
    },
    'returns a visible empty bibliography notice for files without entries' => static function (TestRunner $t): void {
        $document = (new BibTexReader())->read('@comment{no entries}');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(0, $document->attr('meta')['bibtexEntryCount']);
        $t->same('paragraph', $document->children[0]->type);
        $t->contains('<p>No BibTeX entries were found.</p>', $blocks);
    },
];
