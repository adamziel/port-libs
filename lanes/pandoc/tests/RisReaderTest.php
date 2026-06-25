<?php

declare(strict_types=1);

use PortLibs\Pandoc\JsonWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\RisReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads ris bibliography records into references metadata and visible blocks' => static function (TestRunner $t): void {
        $source = <<<'RIS'
TY  - JOUR
ID  - smith2026
AU  - Smith, Ada
AU  - Jones, Ben
TI  - Import pipelines for EPUB
JO  - Journal of Porting
PY  - 2026
VL  - 12
IS  - 3
SP  - 10
EP  - 20
DO  - 10.5555/port
KW  - EPUB
KW  - WordPress
ER  -
TY  - BOOK
A1  - WordPress Foundation
T1  - Block Migration Handbook
PB  - WP Press
PP  - Online
Y1  - 2025
UR  - https://example.test/ris-book
ER  -
RIS;

        $document = (new RisReader())->read($source);
        $meta = $document->attr('meta');
        $references = $meta['references']['value'];
        $article = $references[0]['value'];
        $book = $references[1]['value'];
        $blocks = (new WordPressBlockWriter())->write($document);
        $json = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same(2, $meta['risRecordCount']);
        $t->same('MetaList', $meta['references']['type']);
        $t->same('smith2026', $article['id']);
        $t->same('article-journal', $article['type']);
        $t->same('Import pipelines for EPUB', $article['title']);
        $t->same('Journal of Porting', $article['container-title']);
        $t->same('Ada', $article['author']['value'][0]['value']['given']);
        $t->same('Smith', $article['author']['value'][0]['value']['family']);
        $t->same('10-20', $article['page']);
        $t->same('EPUB, WordPress', $article['keyword']);
        $t->same('book', $book['type']);
        $t->same('WordPress', $book['author']['value'][0]['value']['given']);
        $t->same('Foundation', $book['author']['value'][0]['value']['family']);
        $t->same('https://example.test/ris-book', $book['URL']);
        $t->same('div', $document->children[0]->type);
        $t->same(['csl-bib-body'], $document->children[0]->attr('classes'));
        $t->contains('<div id="refs" class="csl-bib-body" data-pandoc-source="ris" data-ris-entry-count="2">', $blocks);
        $t->contains('<div id="ref-smith2026" class="csl-entry" data-ris-id="smith2026" data-ris-type="JOUR">', $blocks);
        $t->contains('Ada Smith and Ben Jones. (2026). Import pipelines for EPUB. <em>Journal of Porting</em> 12(3): 10-20.', $blocks);
        $t->contains('<a href="https://doi.org/10.5555/port">10.5555/port</a>', $blocks);
        $t->contains('<em>Block Migration Handbook</em>. Online: WP Press.', $blocks);
        $t->same('MetaList', $json['meta']['references']['t']);
        $t->same('*', $json['meta']['nocite']['c'][0]['c'][0][0]['citationId']);
    },
    'generates and deduplicates ris ids when ID fields are missing' => static function (TestRunner $t): void {
        $source = <<<'RIS'
TY  - JOUR
AU  - Smith, Ada
TI  - First generated ID
PY  - 2026
ER  -
TY  - JOUR
AU  - Smith, Ada
TI  - Second generated ID
PY  - 2026
ER  -
RIS;

        $document = PandocConverter::read($source, 'ris');
        $references = $document->attr('meta')['references']['value'];
        $blocks = PandocConverter::convert($source, 'ris', 'blocks');

        $t->same('Smith_2026', $references[0]['value']['id']);
        $t->same('Smith_2026a', $references[1]['value']['id']);
        $t->contains('id="ref-Smith_2026"', $blocks);
        $t->contains('id="ref-Smith_2026a"', $blocks);
    },
    'returns a visible empty bibliography notice for files without ris records' => static function (TestRunner $t): void {
        $document = (new RisReader())->read('');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(0, $document->attr('meta')['risRecordCount']);
        $t->same('paragraph', $document->children[0]->type);
        $t->contains('<p>No RIS entries were found.</p>', $blocks);
    },
];
