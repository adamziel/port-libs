<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => dirname(__DIR__) . '/fixtures/epub3-package';

return [
    'maps epub container opf manifest spine and metadata handoff' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $meta = $document->attr('meta');
        $epub = $document->attr('epub');
        $manifest = $epub['manifestById'];
        $spine = $epub['spine'];

        $t->same('document', $document->type);
        $t->same('WordPress EPUB Import Packet', $meta['title']);
        $t->same(['Migration Team'], $meta['creators']);
        $t->same('en', $meta['language']);
        $t->same('urn:isbn:9780000000002', $meta['identifier']);
        $t->same('Port Libs Press', $meta['publisher']);
        $t->same('2026-06-09', $meta['date']);
        $t->same('EPUB/package.opf', $epub['containerRootfile']);
        $t->same('3.0', $epub['packageVersion']);
        $t->same('pub-id', $epub['uniqueIdentifierId']);
        $t->same(3, count($epub['metadataProperties']));
        $t->same('dcterms:modified', $epub['metadataProperties'][0]['property']);
        $t->same('2026-06-09T11:50:37Z', $epub['metadataProperties'][0]['value']);
        $t->same('file-as', $epub['metadataProperties'][2]['property']);
        $t->same('#creator', $epub['metadataProperties'][2]['refines']);
        $t->same(5, count($epub['manifest']));
        $t->same('nav.xhtml', $manifest['nav']['href']);
        $t->same('EPUB/nav.xhtml', $manifest['nav']['path']);
        $t->same('application/xhtml+xml', $manifest['nav']['mediaType']);
        $t->same(['nav'], $manifest['nav']['properties']);
        $t->same('EPUB/images/cover.png', $manifest['cover']['path']);
        $t->same(['cover-image'], $manifest['cover']['properties']);
        $t->same(2, count($spine));
        $t->same('chapter1', $spine[0]['idref']);
        $t->same('EPUB/chapter1.xhtml', $spine[0]['path']);
        $t->same(true, $spine[0]['linear']);
        $t->same('chapter2', $spine[1]['idref']);
        $t->same('EPUB/chapter2.xhtml', $spine[1]['path']);
        $t->same(true, $spine[1]['linear']);
    },
    'maps epub nav document and ncx fallback outlines' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $toc = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'toc'
        ));
        $landmarks = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'landmarks'
        ));
        $ncx = $epub['ncx'];

        $t->same(2, count($toc));
        $t->same('Opening Packet', $toc[0]['label']);
        $t->same('chapter1.xhtml', $toc[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['path']);
        $t->same('', $toc[0]['fragment']);
        $t->same(1, count($toc[0]['children']));
        $t->same('Opening Note', $toc[0]['children'][0]['label']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['children'][0]['path']);
        $t->same('opening-note', $toc[0]['children'][0]['fragment']);
        $t->same('Details', $toc[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $toc[1]['path']);
        $t->same('details', $toc[1]['fragment']);
        $t->same(2, count($landmarks));
        $t->same('Start', $landmarks[0]['label']);
        $t->same('EPUB/chapter1.xhtml', $landmarks[0]['path']);
        $t->same('Table of contents', $landmarks[1]['label']);
        $t->same('EPUB/nav.xhtml', $landmarks[1]['path']);
        $t->same(2, count($ncx));
        $t->same('Opening Packet', $ncx[0]['label']);
        $t->same(1, $ncx[0]['playOrder']);
        $t->same('EPUB/chapter1.xhtml', $ncx[0]['path']);
        $t->same(1, count($ncx[0]['children']));
        $t->same('Opening Note', $ncx[0]['children'][0]['label']);
        $t->same(2, $ncx[0]['children'][0]['playOrder']);
        $t->same('opening-note', $ncx[0]['children'][0]['fragment']);
        $t->same('Details', $ncx[1]['label']);
        $t->same(3, $ncx[1]['playOrder']);
        $t->same('details', $ncx[1]['fragment']);
    },
    'maps epub page-list navigation targets for print provenance' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $pageList = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'page-list'
        ));

        $t->same(2, count($pageList));
        $t->same('1', $pageList[0]['label']);
        $t->same('chapter1.xhtml#opening-title', $pageList[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $pageList[0]['path']);
        $t->same('opening-title', $pageList[0]['fragment']);
        $t->same('page-list', $pageList[0]['type']);
        $t->same('2', $pageList[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $pageList[1]['path']);
        $t->same('details', $pageList[1]['fragment']);
    },
    'maps epub spine xhtml assets into shared ast and wordpress blocks' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(6, count($document->children));
        $t->same(['heading', 'paragraph', 'blockquote', 'heading', 'paragraph', 'bullet_list'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children
        ));

        $heading = $document->children[0];
        $intro = $document->children[1];
        $quote = $document->children[2];
        $details = $document->children[4];
        $list = $document->children[5];

        $t->same(1, $heading->attr('level'));
        $t->same('opening-title', $heading->attr('id'));
        $t->same('Opening Packet', $heading->attr('text'));
        $t->same('paragraph', $intro->type);
        $t->same('Intro EPUB packet with details and Cover image.', $intro->attr('text'));
        $t->same(['text', 'strong', 'text', 'link', 'text', 'image', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $intro->children
        ));
        $t->same('EPUB', $intro->children[1]->children[0]->attr('text'));
        $t->same('EPUB/chapter2.xhtml#details', $intro->children[3]->attr('url'));
        $t->same('Details', $intro->children[3]->attr('title'));
        $t->same('details', $intro->children[3]->children[0]->attr('text'));
        $t->same('EPUB/images/cover.png', $intro->children[5]->attr('url'));
        $t->same('Cover image', $intro->children[5]->attr('alt'));
        $t->same('Cover', $intro->children[5]->attr('title'));
        $t->same('blockquote', $quote->type);
        $t->same('Reviewer note with wp_insert_post.', $quote->children[0]->attr('text'));
        $t->same('code', $quote->children[0]->children[1]->type);
        $t->same('wp_insert_post', $quote->children[0]->children[1]->attr('text'));
        $t->same(['text', 'emph', 'linebreak', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $details->children
        ));
        $t->same('reading order', $details->children[1]->children[0]->attr('text'));
        $t->same(2, count($list->children));
        $t->same('First migration check', $list->children[0]->children[0]->attr('text'));
        $t->same('Second check with source', $list->children[1]->children[0]->attr('text'));
        $t->same('https://example.test/source', $list->children[1]->children[0]->children[1]->attr('url'));
        $t->contains('<h1 id="opening-title">Opening Packet</h1>', $blocks);
        $t->contains('<strong>EPUB</strong>', $blocks);
        $t->contains('<a href="EPUB/chapter2.xhtml#details" title="Details">details</a>', $blocks);
        $t->contains('<img src="EPUB/images/cover.png" alt="Cover image" title="Cover"/>', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note with <code>wp_insert_post</code>.</p></blockquote>', $blocks);
        $t->contains('<em>reading order</em><br/>and a hard break.', $blocks);
        $t->contains('<li>First migration check</li><li>Second check with <a href="https://example.test/source">source</a></li>', $blocks);
    },
    'rejects missing epub package directories before parsing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static function (): void {
            (new EpubPackageReader())->readDirectory(dirname(__DIR__) . '/fixtures/missing-epub-package');
        });
    },
];
