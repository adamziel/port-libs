<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlReader;

$tests = [];

$fixture = static function (string $name): string {
    $bytes = file_get_contents(__DIR__ . '/../fixtures/' . $name);
    if ($bytes === false) {
        throw new RuntimeException("Unable to read fixture {$name}");
    }

    return $bytes;
};

$tests['imports upstream html xml lang metadata from root element'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-xml-lang-metadata.html'));
        $meta = $document->attr('meta');

        $t->same('es', $meta['lang']);
        $t->same('html', $meta['sourceFormat']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('hola', $document->children[0]->attr('text'));
    };

$tests['imports upstream html sup and sub inline nodes'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-sup-sub-inline.html'));
        $paragraph = $document->children[0];

        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(
            ['text', 'subscript', 'text', 'superscript', 'text'],
            array_map(static fn ($node): string => $node->type, $paragraph->children)
        );
        $t->same('Formula H', $paragraph->children[0]->attr('text'));
        $t->same('2', $paragraph->children[1]->children[0]->attr('text'));
        $t->same('O and release note', $paragraph->children[2]->attr('text'));
        $t->same('review', $paragraph->children[3]->children[0]->attr('text'));
        $t->same(' stay inline.', $paragraph->children[4]->attr('text'));
    };

$tests['imports upstream html base absolute image without rewriting absolute url'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-base-absolute-image.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $image = $paragraph->children[0];

        $t->same('HTML Base Absolute Image Import', $meta['title']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('http://example.com/stickman.gif', $image->attr('url'));
        $t->same('Stickman', $image->attr('alt'));
        $t->same('The title', $image->attr('title'));
        $t->same('Stickman', $image->children[0]->attr('text'));
    };

$tests['imports generated current html inline quote cites resolved against base'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-inline-quote-cite-base.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $outerQuote = $paragraph->children[1];
        $outerSpan = $outerQuote->children[0];
        $innerQuote = $outerSpan->children[1];
        $innerSpan = $innerQuote->children[0];

        $t->same('HTML Inline Quote Cite Base Import', $meta['title']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text', 'quoted', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('double', $outerQuote->attr('kind'));
        $t->same('single', $innerQuote->attr('kind'));
        $t->same(
            'https://source.example.test/import/posts/quotes/source.html#frag',
            $outerSpan->attr('attributes')['cite'] ?? null
        );
        $t->same(
            'https://source.example.test/import/shared/context.html',
            $innerSpan->attr('attributes')['cite'] ?? null
        );
        $t->same(['text', 'quoted'], array_map(static fn ($node): string => $node->type, $outerSpan->children));
        $t->same('outer ', $outerSpan->children[0]->attr('text'));
        $t->same('inner source', $innerSpan->children[0]->attr('text'));
        $t->same(' stays linked.', $paragraph->children[2]->attr('text'));
    };

$tests['imports generated current html blockquote fixture as native blockquote'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-blockquote.html'));
        $meta = $document->attr('meta');
        $quote = $document->children[0];
        $quoteParagraph = $quote->children[0];
        $after = $document->children[1];

        $t->same('HTML Blockquote Import', $meta['title']);
        $t->same(['blockquote', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same([], $quote->attrs);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $quote->children));
        $t->same('Quoted source paragraph.', $quoteParagraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $quoteParagraph->children));
        $t->same('Quoted ', $quoteParagraph->children[0]->attr('text'));
        $t->same('source', $quoteParagraph->children[1]->children[0]->attr('text'));
        $t->same(' paragraph.', $quoteParagraph->children[2]->attr('text'));
        $t->same('After quote.', $after->attr('text'));
    };

$tests['imports generated current html definition list fixture as native definition list'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-definition-list.html'));
        $meta = $document->attr('meta');
        $list = $document->children[0];
        $item = $list->children[0];
        $term = $item->children[0];
        $primary = $item->children[1]->children[0];
        $secondary = $item->children[2]->children[0];

        $t->same('HTML Definition List Import', $meta['title']);
        $t->same(['definition_list', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('definition_item', $item->type);
        $t->same('Packet source', $item->attr('term'));
        $t->same('term', $term->type);
        $t->same('Packet source', $term->attr('text'));
        $t->same(['definition', 'definition'], [$item->children[1]->type, $item->children[2]->type]);
        $t->same('Definition primary.', $primary->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $primary->children));
        $t->same('primary', $primary->children[1]->children[0]->attr('text'));
        $t->same('Secondary note.', $secondary->attr('text'));
        $t->same(['text', 'emph', 'text'], array_map(static fn ($node): string => $node->type, $secondary->children));
        $t->same('note', $secondary->children[1]->children[0]->attr('text'));
        $t->same('After glossary.', $document->children[1]->attr('text'));
    };

$tests['extracts html reader microdata item metadata with itemref and nested item scopes'] =
    static function (TestRunner $t): void {
        $html = '<!doctype html><html><head><title>Microdata Dispatch</title></head><body>'
            . '<article id="post" itemscope itemtype="https://schema.org/Article" itemid="/posts/42" itemref="extra missing">'
            . '<h1 itemprop="headline">Launch Notes</h1>'
            . '<a itemprop="url mainEntityOfPage" href="/posts/42">Canonical</a>'
            . '<img itemprop="image" src="/media/cover.jpg" alt="Cover">'
            . '<time itemprop="datePublished" datetime="2026-06-25">today</time>'
            . '<div id="author" itemprop="author" itemscope itemtype="https://schema.org/Person"><span itemprop="name">Ada Lovelace</span></div>'
            . '<section id="comment" itemscope itemtype="https://schema.org/Comment"><span itemprop="text">Nested unrelated</span></section>'
            . '</article>'
            . '<p id="extra"><span itemprop="keywords">migration, html</span></p>'
            . '</body></html>';

        $document = (new HtmlReader())->read($html);
        $meta = $document->attr('meta');
        $items = $meta['htmlMicrodataItems'];
        $article = $items[0];
        $articleProperties = $article['properties'];
        $authorItem = $items[1];
        $commentItem = $items[2];

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('Microdata Dispatch', $meta['title']);
        $t->same('parsed', $meta['htmlMicrodataParseStatus']);
        $t->same('html-microdata-metadata-only', $meta['htmlMicrodataReviewPolicy']);
        $t->same(3, $meta['htmlMicrodataItemCount']);
        $t->same(3, $meta['htmlMicrodataReportedItemCount']);
        $t->same(1, $meta['htmlMicrodataTopLevelItemCount']);
        $t->same([0], $meta['htmlMicrodataTopLevelItemIndexes']);
        $t->same(8, $meta['htmlMicrodataPropertyCount']);
        $t->same(
            ['headline', 'url', 'mainEntityOfPage', 'image', 'datePublished', 'author', 'keywords', 'name', 'text'],
            $meta['htmlMicrodataPropertyNames']
        );
        $t->same(['missing-itemref:missing'], $meta['htmlMicrodataDiagnostics']);

        $t->same('article', $article['elementName']);
        $t->same('post', $article['elementId']);
        $t->same(['https://schema.org/Article'], $article['itemTypes']);
        $t->same('/posts/42', $article['itemId']);
        $t->same(['extra', 'missing'], $article['itemrefIds']);
        $t->same(['missing'], $article['missingItemrefIds']);
        $t->same(6, $article['propertyCount']);
        $t->same(
            ['headline', 'url', 'mainEntityOfPage', 'image', 'datePublished', 'author', 'keywords'],
            $article['propertyNames']
        );
        $t->same([
            'headline' => 1,
            'url' => 1,
            'mainEntityOfPage' => 1,
            'image' => 1,
            'datePublished' => 1,
            'author' => 1,
            'keywords' => 1,
        ], $article['propertyNameCounts']);

        $t->same(['headline'], $articleProperties[0]['names']);
        $t->same('Launch Notes', $articleProperties[0]['value']);
        $t->same('text', $articleProperties[0]['valueSource']);
        $t->same(['url', 'mainEntityOfPage'], $articleProperties[1]['names']);
        $t->same('/posts/42', $articleProperties[1]['value']);
        $t->same('href', $articleProperties[1]['valueSource']);
        $t->same('/media/cover.jpg', $articleProperties[2]['value']);
        $t->same('src', $articleProperties[2]['valueSource']);
        $t->same('2026-06-25', $articleProperties[3]['value']);
        $t->same('datetime', $articleProperties[3]['valueSource']);
        $t->same('item', $articleProperties[4]['valueType']);
        $t->same(['https://schema.org/Person'], $articleProperties[4]['item']['itemTypes']);
        $t->same('author', $articleProperties[4]['item']['elementId']);
        $t->same('Ada Lovelace', $articleProperties[4]['item']['text']);
        $t->same('migration, html', $articleProperties[5]['value']);
        $t->same('text', $articleProperties[5]['valueSource']);

        $t->same('div', $authorItem['elementName']);
        $t->same(['name'], $authorItem['propertyNames']);
        $t->same('Ada Lovelace', $authorItem['properties'][0]['value']);
        $t->same('section', $commentItem['elementName']);
        $t->same(['text'], $commentItem['propertyNames']);
        $t->same('Nested unrelated', $commentItem['properties'][0]['value']);
    };

$tests['consumes upstream html doc-endnotes container after resolving doc-noteref note'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-doc-noteref-footnotes.html'));
        $meta = $document->attr('meta');
        $paragraph = $document->children[0];
        $note = $paragraph->children[1];
        $noteParagraph = $note->children[0];

        $t->same('doc-endnotes-containers-consumed-after-note-resolution', $meta['htmlFootnoteContainerPolicy']);
        $t->same(1, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['text', 'note', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $note->children));
        $t->same('Editor note with source context.', $noteParagraph->attr('text'));
        $t->same(['text', 'strong', 'text'], array_map(static fn ($node): string => $node->type, $noteParagraph->children));
        $t->same('source context', $noteParagraph->children[1]->children[0]->attr('text'));
    };

$tests['resolves upstream html doc-noteref notes in table placements and consumes endnotes'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new HtmlReader())->read($fixture('upstream-html-doc-noteref-table-placement.html'));
        $meta = $document->attr('meta');
        $firstParagraphNote = $document->children[1]->children[1];
        $table = $document->children[2];
        $captionInlines = $table->attr('captionInlines');
        $headCell = $table->children[0]->children[0]->children[0];
        $bodyCell = $table->children[1]->children[0]->children[0];
        $lastParagraphNote = $document->children[4]->children[1];

        $t->same(1, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(
            ['heading', 'paragraph', 'table', 'heading', 'paragraph'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same('doc footnote', $firstParagraphNote->children[0]->attr('text'));
        $t->same(['center'], $table->attr('alignments'));
        $t->same('width:17%', $table->attr('attributes')['style'] ?? null);
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $captionInlines));
        $t->same('caption footnote', $captionInlines[1]->children[0]->attr('text'));
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $headCell->children));
        $t->same('header footnote', $headCell->children[1]->children[0]->attr('text'));
        $t->same(['text', 'note'], array_map(static fn ($node): string => $node->type, $bodyCell->children));
        $t->same('table cell footnote', $bodyCell->children[1]->children[0]->attr('text'));
        $t->same('doc footnote', $lastParagraphNote->children[0]->attr('text'));
    };

$tests['preserves html doc-endnotes container when no noteref was resolved'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<section role="doc-endnotes"><ol><li id="fn1"><p>Loose footnote body.</p></li></ol></section>'
        );
        $meta = $document->attr('meta');
        $container = $document->children[0];

        $t->same(0, $meta['htmlConsumedFootnoteContainerCount']);
        $t->same(['div'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('doc-endnotes', $container->attr('htmlAttributes')['role'] ?? null);
        $t->same('ordered_list', $container->children[0]->type);
        $t->same('Loose footnote body.', $container->children[0]->children[0]->attr('text'));
    };

$valueSourceCases = [
    'data value attribute' => [
        '<data itemprop="ratingValue" value="4.5">four and half</data>',
        'ratingValue',
        '4.5',
        'value',
        'string',
    ],
    'meter value attribute' => [
        '<meter itemprop="confidence" value="0.82">82%</meter>',
        'confidence',
        '0.82',
        'value',
        'string',
    ],
    'object data url' => [
        '<object itemprop="downloadUrl" data="/review.bin"></object>',
        'downloadUrl',
        '/review.bin',
        'data',
        'url',
    ],
    'time text fallback' => [
        '<time itemprop="reviewedAt">June 25</time>',
        'reviewedAt',
        'June 25',
        'text',
        'string',
    ],
    'meta empty content' => [
        '<meta itemprop="empty" content="">',
        'empty',
        '',
        'content',
        'string',
    ],
];

foreach ($valueSourceCases as $name => [$markup, $propertyName, $expectedValue, $expectedSource, $expectedType]) {
    $tests['extracts html reader microdata property value source ' . $name] =
        static function (TestRunner $t) use ($expectedSource, $expectedType, $expectedValue, $markup, $propertyName): void {
            $document = (new HtmlReader())->read(
                '<!doctype html><html><body><section itemscope itemtype="https://schema.org/Review">'
                . $markup
                . '</section></body></html>'
            );
            $property = $document->attr('meta')['htmlMicrodataItems'][0]['properties'][0];

            $t->same([$propertyName], $property['names']);
            $t->same($expectedValue, $property['value']);
            $t->same($expectedSource, $property['valueSource']);
            $t->same($expectedType, $property['valueType']);
        };
}

$tests['keeps html reader imports alive when microdata dom parse is unavailable'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<!doctype html [<!ENTITY review SYSTEM "file:///etc/passwd">]><html><body><p>Unsafe declaration source.</p></body></html>'
        );
        $meta = $document->attr('meta');

        $t->same('html', $document->attr('sourceFormat'));
        $t->same('unavailable', $meta['htmlMicrodataParseStatus']);
        $t->same(0, $meta['htmlMicrodataItemCount']);
        $t->same(0, $meta['htmlMicrodataPropertyCount']);
        $t->same(['html-microdata-dom-parse-failed'], $meta['htmlMicrodataDiagnostics']);
    };

$tests['records html reader microdata metadata mapped-case count'] =
    static function (TestRunner $t) use ($valueSourceCases): void {
        $t->same(10, 1 + 3 + count($valueSourceCases) + 1);
    };

return $tests;
