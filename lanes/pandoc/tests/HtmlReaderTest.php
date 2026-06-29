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

$tests['classifies html reader microdata value policy metadata'] =
    static function (TestRunner $t): void {
        $document = (new HtmlReader())->read(
            '<!doctype html><html><body><section itemscope itemtype="https://schema.org/SoftwareApplication">'
            . '<a itemprop="downloadUrl" href="https://downloads.example.test/app.zip">Download</a>'
            . '<img itemprop="screenshot" src="/media/screen.png" alt="Screen">'
            . '<meta itemprop="alternateName" content="">'
            . '<span itemprop="">Nameless property text</span>'
            . '<a itemprop="supportUrl" href="mailto:help@example.test">Support</a>'
            . '</section></body></html>'
        );
        $meta = $document->attr('meta');
        $item = $meta['htmlMicrodataItems'][0];
        $properties = $item['properties'];

        $t->same(5, $meta['htmlMicrodataPropertyCount']);
        $t->same(3, $meta['htmlMicrodataUrlPropertyCount']);
        $t->same(2, $meta['htmlMicrodataExternalUrlPropertyCount']);
        $t->same(1, $meta['htmlMicrodataEmptyValueCount']);
        $t->same(1, $meta['htmlMicrodataNamelessPropertyCount']);
        $t->same(0, $meta['htmlMicrodataTruncatedValueCount']);
        $t->same([
            'html-microdata-empty-property-value:alternateName',
            'html-microdata-property-without-name',
            'html-microdata-url-non-http:supportUrl',
        ], $meta['htmlMicrodataDiagnostics']);

        $t->same(3, $item['urlPropertyCount']);
        $t->same(2, $item['externalUrlPropertyCount']);
        $t->same(1, $item['emptyValueCount']);
        $t->same(1, $item['namelessPropertyCount']);
        $t->same(0, $item['truncatedValueCount']);

        $t->same('metadata-only-no-fetch', $properties[0]['valueUrlPolicy']);
        $t->same('absolute-http', $properties[0]['valueUrlKind']);
        $t->same('https', $properties[0]['valueUrlScheme']);
        $t->same(true, $properties[0]['valueExternal']);
        $t->same(strlen('https://downloads.example.test/app.zip'), $properties[0]['valueLengthBytes']);
        $t->same(false, $properties[0]['valueTruncated']);
        $t->same(false, $properties[0]['valueEmpty']);

        $t->same('root-relative', $properties[1]['valueUrlKind']);
        $t->same(null, $properties[1]['valueUrlScheme']);
        $t->same(false, $properties[1]['valueExternal']);
        $t->same(true, $properties[2]['valueEmpty']);
        $t->same([], $properties[3]['names']);
        $t->same('absolute-non-http', $properties[4]['valueUrlKind']);
        $t->same('mailto', $properties[4]['valueUrlScheme']);
        $t->same(true, $properties[4]['valueExternal']);
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
        $t->same(0, $meta['htmlMicrodataUrlPropertyCount']);
        $t->same(0, $meta['htmlMicrodataExternalUrlPropertyCount']);
        $t->same(0, $meta['htmlMicrodataEmptyValueCount']);
        $t->same(0, $meta['htmlMicrodataNamelessPropertyCount']);
        $t->same(0, $meta['htmlMicrodataTruncatedValueCount']);
        $t->same(['html-microdata-dom-parse-failed'], $meta['htmlMicrodataDiagnostics']);
    };

$tests['records html reader microdata metadata mapped-case count'] =
    static function (TestRunner $t) use ($valueSourceCases): void {
        $t->same(8, 1 + count($valueSourceCases) + 2);
    };

return $tests;
