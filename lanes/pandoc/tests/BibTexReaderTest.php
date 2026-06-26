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
    'resolves bibtex and biblatex inheritance dates and name particles' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@proceedings{conf2026,
  title = {Migration Conf},
  publisher = {Open Press},
  year = {2026}
}

@xdata{sharedplace,
  location = {Online},
  date = {2026-06-26}
}

@inproceedings{paper2026,
  author = {de la Cruz, Jr., Juan},
  title = {Reader Parity},
  crossref = {conf2026},
  xdata = {sharedplace},
  pages = {1--9}
}
BIB;

        $document = (new BibTexReader('biblatex'))->read($source);
        $meta = $document->attr('meta');
        $reference = $meta['references']['value'][1]['value'];
        $author = $reference['author']['value'][0]['value'];
        $dateParts = $reference['issued']['value']['date-parts']['value'][0]['value'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, $meta['bibtexEntryCount']);
        $t->same(1, $meta['bibtexDataEntryCount']);
        $t->same('paper2026', $reference['id']);
        $t->same('Migration Conf', $reference['container-title']);
        $t->same('Open Press', $reference['publisher']);
        $t->same('Online', $reference['publisher-place']);
        $t->same('2026-06-26', $reference['date']);
        $t->same([2026, 6, 26], $dateParts);
        $t->same('Juan', $author['given']);
        $t->same('Cruz', $author['family']);
        $t->same('Jr.', $author['suffix']);
        $t->same('de la', $author['non-dropping-particle']);
        $t->contains('Juan de la Cruz, Jr. (2026). Reader Parity. <em>Migration Conf</em>: 1--9. Online: Open Press.', $blocks);
    },
    'decodes bibtex tex text and biblatex date and field aliases' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@software{godel2026,
  author = {G{\"o}del, Kurt and Garc{\'i}a, Mar{\'i}a},
  title = {A \href{https://example.test/source}{Linked} Tool},
  date = {2026-06},
  urldate = {2026-06-26},
  origdate = {1931},
  eventdate = {2026-07-01},
  organization = {Example Lab},
  series = {Migration Tools},
  edition = {2},
  keywords = {tex, migration},
  langid = {en-US},
  url = {\url{https://example.test/tool}}
}
BIB;

        $document = (new BibTexReader('biblatex'))->read($source);
        $reference = $document->attr('meta')['references']['value'][0]['value'];
        $authors = $reference['author']['value'];
        $accessed = $reference['accessed']['value']['date-parts']['value'][0]['value'];
        $original = $reference['original-date']['value']['date-parts']['value'][0]['value'];
        $event = $reference['event-date']['value']['date-parts']['value'][0]['value'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('software', $reference['type']);
        $t->same('A Linked Tool', $reference['title']);
        $t->same('Gödel', $authors[0]['value']['family']);
        $t->same('García', $authors[1]['value']['family']);
        $t->same('María', $authors[1]['value']['given']);
        $t->same('Example Lab', $reference['publisher']);
        $t->same('Migration Tools', $reference['collection-title']);
        $t->same('2', $reference['edition']);
        $t->same('tex, migration', $reference['keyword']);
        $t->same('en-US', $reference['language']);
        $t->same('https://example.test/tool', $reference['URL']);
        $t->same([2026, 6], $reference['issued']['value']['date-parts']['value'][0]['value']);
        $t->same([2026, 6, 26], $accessed);
        $t->same([1931], $original);
        $t->same([2026, 7, 1], $event);
        $t->contains('Kurt Gödel and María García. (2026). A Linked Tool. Example Lab.', $blocks);
        $t->contains('<a href="https://example.test/tool">https://example.test/tool</a>', $blocks);
    },
    'cleans nested biblatex tex commands date ranges and name particles' => static function (TestRunner $t): void {
        $source = <<<'BIB'
@collection{collected2026,
  editor = {Ludwig van Beethoven and Fran\c{c}ois van der Waals},
  title = {Collected \textit{Reader {Notes}} and \href{https://example.test}{Nested {Link}}},
  date = {2026-06-25/2026-06-26},
  publisher = {Example Press}
}
BIB;

        $document = (new BibTexReader('biblatex'))->read($source);
        $reference = $document->attr('meta')['references']['value'][0]['value'];
        $editors = $reference['editor']['value'];
        $dateParts = $reference['issued']['value']['date-parts']['value'];
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('book', $reference['type']);
        $t->same('Collected Reader Notes and Nested Link', $reference['title']);
        $t->same([2026, 6, 25], $dateParts[0]['value']);
        $t->same([2026, 6, 26], $dateParts[1]['value']);
        $t->same('Ludwig', $editors[0]['value']['given']);
        $t->same('Beethoven', $editors[0]['value']['family']);
        $t->same('van', $editors[0]['value']['non-dropping-particle']);
        $t->same('François', $editors[1]['value']['given']);
        $t->same('Waals', $editors[1]['value']['family']);
        $t->same('van der', $editors[1]['value']['non-dropping-particle']);
        $t->contains('Ludwig van Beethoven and François van der Waals (ed.). (2026). <em>Collected Reader Notes and Nested Link</em>. Example Press.', $blocks);
    },
    'returns a visible empty bibliography notice for files without entries' => static function (TestRunner $t): void {
        $document = (new BibTexReader())->read('@comment{no entries}');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(0, $document->attr('meta')['bibtexEntryCount']);
        $t->same('paragraph', $document->children[0]->type);
        $t->contains('<p>No BibTeX entries were found.</p>', $blocks);
    },
];
