<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlReader;

$tests = [];

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

$tests['summarizes html reader microdata value source and type counts'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<!doctype html><html><body><section itemscope itemtype="https://schema.org/Event">'
            . '<h1 itemprop="name">Launch Review</h1>'
            . '<a itemprop="url" href="/events/launch">Event page</a>'
            . '<img itemprop="image" src="/events/cover.png" alt="">'
            . '<meta itemprop="eventStatus" content="EventScheduled">'
            . '<data itemprop="maximumAttendeeCapacity" value="128">128 seats</data>'
            . '<time itemprop="startDate" datetime="2026-07-02">today</time>'
            . '<div itemprop="location" itemscope itemtype="https://schema.org/Place"><span itemprop="name">Town Hall</span></div>'
            . '</section></body></html>'
        );
        $meta = $document->attr('meta');
        $event = $meta['htmlMicrodataItems'][0];
        $place = $meta['htmlMicrodataItems'][1];

        $t->same(2, $meta['htmlMicrodataItemCount']);
        $t->same(8, $meta['htmlMicrodataPropertyCount']);
        $t->same([
            'text' => 2,
            'href' => 1,
            'src' => 1,
            'content' => 1,
            'value' => 1,
            'datetime' => 1,
            'item' => 1,
        ], $meta['htmlMicrodataValueSourceCounts']);
        $t->same([
            'string' => 4,
            'url' => 2,
            'datetime' => 1,
            'item' => 1,
        ], $meta['htmlMicrodataValueTypeCounts']);

        $t->same(7, $event['propertyCount']);
        $t->same([
            'text' => 1,
            'href' => 1,
            'src' => 1,
            'content' => 1,
            'value' => 1,
            'datetime' => 1,
            'item' => 1,
        ], $event['valueSourceCounts']);
        $t->same([
            'string' => 3,
            'url' => 2,
            'datetime' => 1,
            'item' => 1,
        ], $event['valueTypeCounts']);

        $t->same(1, $place['propertyCount']);
        $t->same(['text' => 1], $place['valueSourceCounts']);
        $t->same(['string' => 1], $place['valueTypeCounts']);
    };

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
        $t->same(8, 1 + count($valueSourceCases) + 1 + 1);
    };

return $tests;
