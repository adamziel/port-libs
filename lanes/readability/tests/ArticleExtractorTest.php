<?php

declare(strict_types=1);

use PortLibs\Readability\ArticleExtractor;

$fixtureText = static function (string $html): string {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return trim(preg_replace('/\s+/', ' ', $dom->textContent) ?? '');
};
$iframeSources = static function (string $html): array {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $sources = [];
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//iframe[@src]/@src') ?: [] as $attribute) {
        $sources[] = $attribute->nodeValue;
    }

    return $sources;
};
$imageAttributeRows = static function (string $html): array {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $rows = [];
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//img') ?: [] as $image) {
        if (!$image instanceof DOMElement) {
            continue;
        }

        $row = [];
        foreach (['data-src', 'alt', 'src', 'srcset'] as $attribute) {
            $value = trim($image->getAttribute($attribute));
            if ($value !== '') {
                $row[$attribute] = $value;
            }
        }
        $rows[] = $row;
    }

    return $rows;
};
$normalizedText = static fn (string $text): string => trim(preg_replace('/\s+/', ' ', $text) ?? '');

return [
    'extracts article text while removing navigation and asides' => static function (TestRunner $t): void {
        $html = '<html><head><title>Fallback</title><meta property="og:title" content="Clean Import"></head><body><nav>Menu</nav><article><h1>Clean Import</h1><p>This is the main migration paragraph, with enough text to score well.</p><p>Second paragraph for WordPress blocks.</p></article><aside>Ad text</aside></body></html>';
        $article = (new ArticleExtractor())->extract($html);
        $t->same('Clean Import', $article->title);
        $t->contains('main migration paragraph', $article->text);
        $t->true(!str_contains($article->text, 'Menu'), 'navigation text should be removed');
        $t->true(!str_contains($article->text, 'Ad text'), 'aside text should be removed');
    },
    'converts extracted content to block comments' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $article = $extractor->extract('<article><h2>Heading</h2><p>Paragraph</p></article>');
        $blocks = $extractor->toWordPressBlocks($article);
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('<!-- wp:paragraph -->', $blocks);
    },
    'matches upstream readerable default scoring thresholds' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $document = static fn (string $body): string => '<html><body>' . $body . '</body></html>';

        $t->same(false, $extractor->isProbablyReaderable($document('<p>hello there</p>')), 'very small document should not be readerable');
        $t->same(false, $extractor->isProbablyReaderable($document('<p>' . str_repeat('hello there ', 11) . '</p>')), 'small document should not be readerable with default score');
        $t->same(false, $extractor->isProbablyReaderable($document('<p>' . str_repeat('hello there ', 12) . '</p>')), 'large document remains below default score');
        $t->same(true, $extractor->isProbablyReaderable($document('<p>' . str_repeat('hello there ', 50) . '</p>')), 'very large document should be readerable');
    },
    'honors upstream readerable length and score options' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $document = static fn (string $body): string => '<html><body>' . $body . '</body></html>';
        $small = $document('<p>' . str_repeat('hello there ', 11) . '</p>');
        $large = $document('<p>' . str_repeat('hello there ', 12) . '</p>');
        $veryLarge = $document('<p>' . str_repeat('hello there ', 50) . '</p>');

        $t->same(true, $extractor->isProbablyReaderable($small, ['minContentLength' => 120, 'minScore' => 0]));
        $t->same(true, $extractor->isProbablyReaderable($large, ['minContentLength' => 120, 'minScore' => 0]));
        $t->same(false, $extractor->isProbablyReaderable($large, ['minContentLength' => 200, 'minScore' => 0]));
        $t->same(true, $extractor->isProbablyReaderable($veryLarge, ['minContentLength' => 200, 'minScore' => 0]));
        $t->same(false, $extractor->isProbablyReaderable($small, ['minContentLength' => 0, 'minScore' => 11.5]));
        $t->same(true, $extractor->isProbablyReaderable($large, ['minContentLength' => 0, 'minScore' => 11.5]));
    },
    'skips invisible list and unlikely readerable nodes' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $longText = str_repeat('migration content ', 80);
        $html = '<html><body>'
            . '<p hidden>' . $longText . '</p>'
            . '<p aria-hidden="true">' . $longText . '</p>'
            . '<p class="comment">' . $longText . '</p>'
            . '<ul><li><p>' . $longText . '</p></li></ul>'
            . '</body></html>';

        $t->same(false, $extractor->isProbablyReaderable($html));
        $t->same(false, $extractor->isProbablyReaderable('<p>' . $longText . '</p>', static fn (): bool => false));
    },
    'removes WordPress page-builder chrome with upstream unlikely candidate rules' => static function (TestRunner $t): void {
        $html = file_get_contents(__DIR__ . '/../fixtures/wordpress-page-builder.html');
        $article = (new ArticleExtractor())->extract((string) $html);

        $t->same('Reusable Blocks After Migration', $article->title);
        $t->contains('canonical article paragraph', $article->text);
        $t->true(!str_contains($article->text, 'Related sponsor links'), 'builder navigation should be removed');
        $t->true(!str_contains($article->text, 'Legacy comment thread'), 'comment widgets should be removed');
    },
    'turns the WordPress migration fixture into core blocks' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $article = $extractor->extract((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-page-builder.html'));
        $blocks = $extractor->toWordPressBlocks($article);

        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->contains('canonical article paragraph', $blocks);
    },
    'maps Mozilla normalize-spaces fixture metadata and article text' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/normalize-spaces';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'] ?? null, $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
    },
    'maps Mozilla parsely metadata fixture metadata and article text' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/parsely-metadata';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'] ?? null, $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
    },
    'maps Mozilla mozilla-2 fixture metadata and retained content markers' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/mozilla-2';
        $source = (string) file_get_contents($fixture . '/source.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $contentText = $fixtureText($article->contentHtml);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'] ?? null, $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->contains('Get to know the features that make it the most complete browser for building the Web.', $contentText);
        $t->contains('Features and tools', $contentText);
        $t->true(!str_contains($contentText, 'Interested in having a direct impact'), 'head comment text should not enter content');
    },
    'maps Mozilla embedded-videos fixture allowed iframe preservation' => static function (TestRunner $t) use ($fixtureText, $iframeSources, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/embedded-videos';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($iframeSources($expected), $iframeSources($article->contentHtml));
        $t->same(5, count($iframeSources($article->contentHtml)));
        $t->contains('At root', $fixtureText($article->contentHtml));
        $t->contains('In a paragraph', $fixtureText($article->contentHtml));
        $t->contains('In a div', $fixtureText($article->contentHtml));
    },
    'maps Mozilla videos-2 JSON-LD metadata and video article body' => static function (TestRunner $t) use ($fixtureText, $iframeSources, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/videos-2';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($iframeSources($expected), $iframeSources($article->contentHtml));
        $t->same(7, count($iframeSources($article->contentHtml)));
    },
    'removes non-video embeds while preserving Mozilla allowed video hosts' => static function (TestRunner $t): void {
        $source = '<article>'
            . '<h1>Migration Video Cleanup</h1>'
            . '<p>' . str_repeat('A WordPress migration keeps editorial video embeds but removes tracking widgets. ', 4) . '</p>'
            . '<iframe src="https://www.youtube.com/embed/LtOGa5M8AuU"></iframe>'
            . '<object data="https://player.vimeo.com/video/32246206"></object>'
            . '<p>' . str_repeat('The cleaned article remains ready for block serialization and archival imports. ', 4) . '</p>'
            . '<iframe src="https://tracker.example.test/ad-frame"></iframe>'
            . '<embed src="https://widgets.example.test/chart.swf"></embed>'
            . '</article>';

        $article = (new ArticleExtractor())->extract($source);

        $t->contains('https://www.youtube.com/embed/LtOGa5M8AuU', $article->contentHtml);
        $t->contains('https://player.vimeo.com/video/32246206', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, 'tracker.example.test'), 'generic iframe should be removed');
        $t->true(!str_contains($article->contentHtml, 'widgets.example.test'), 'generic embed should be removed');
        $t->contains('ready for block serialization', $article->text);
    },
    'maps Mozilla lazy-image noscript replacement semantics' => static function (TestRunner $t): void {
        $source = '<html lang="en"><head>'
            . '<meta property="og:title" content="Node.js and CPU profiling on production (in real-time without downtime)">'
            . '<meta name="author" content="Vincent Vallet">'
            . '<meta property="og:site_name" content="Voodoo Engineering">'
            . '<meta property="article:published_time" content="2019-10-18T17:23:34.816Z">'
            . '<meta name="description" content="How to run a CPU profiling with Node.js on your production in real-time and without interruption of service.">'
            . '</head><body><article>'
            . '<h2>Resources</h2>'
            . '<p>' . str_repeat('CPU monitoring and memory leak analysis need reliable production evidence. ', 5) . '</p>'
            . '<figure><div><p><img src="https://miro.medium.com/max/60/1*5o3M5niyi911waUrKWVZ0Q.png?q=20" width="1894" height="970">'
            . '<noscript><img src="https://miro.medium.com/max/3788/1*5o3M5niyi911waUrKWVZ0Q.png" width="1894" height="970"></noscript></p></div>'
            . '<figcaption>Memory leak in action</figcaption></figure>'
            . '<p>' . str_repeat('The profiler output explains where the application spends CPU time. ', 5) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);

        $t->same('Node.js and CPU profiling on production (in real-time without downtime)', $article->title);
        $t->same('Vincent Vallet', $article->byline);
        $t->same('Voodoo Engineering', $article->siteName);
        $t->same('2019-10-18T17:23:34.816Z', $article->publishedTime);
        $t->same('en', $article->lang);
        $t->contains('Memory leak in action', $article->contentHtml);
        $t->contains('src="https://miro.medium.com/max/3788/1*5o3M5niyi911waUrKWVZ0Q.png"', $article->contentHtml);
        $t->contains('data-old-src="https://miro.medium.com/max/60/1*5o3M5niyi911waUrKWVZ0Q.png?q=20"', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, '<noscript'), 'fallback noscript should be removed after image promotion');
    },
    'maps Mozilla lazy data-srcset promotion semantics' => static function (TestRunner $t): void {
        $source = '<article>'
            . '<p>' . str_repeat('Responsive migration images should retain the usable candidate list. ', 6) . '</p>'
            . '<p><img class="lazy" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" data-srcset="https://cdn.example.test/photo-320.jpg 320w, https://cdn.example.test/photo-800.jpg 800w" alt="Migration screenshot"></p>'
            . '<p>' . str_repeat('The surrounding article text keeps the document readerable. ', 6) . '</p>'
            . '</article>';

        $article = (new ArticleExtractor())->extract($source);

        $t->contains('srcset="https://cdn.example.test/photo-320.jpg 320w, https://cdn.example.test/photo-800.jpg 800w"', $article->contentHtml);
        $t->contains('alt="Migration screenshot"', $article->contentHtml);
    },
    'maps Mozilla lazy-image-1 metadata lazy images and post-article chrome cleanup' => static function (TestRunner $t) use ($imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/lazy-image-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $expectedSources = array_values(array_column($imageAttributeRows($expected), 'src'));
        $articleSources = array_values(array_column($imageAttributeRows($article->contentHtml), 'src'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(9, count($expectedSources));
        foreach ($expectedSources as $sourceUrl) {
            $t->true(in_array($sourceUrl, $articleSources, true), 'expected lazy-image-1 image source should be preserved: ' . $sourceUrl);
        }
        $t->contains('data-old-src="https://miro.medium.com/max/60/1*5o3M5niyi911waUrKWVZ0Q.png?q=20"', $article->contentHtml);
        $t->contains('Sources &amp; links', $article->contentHtml);
        $t->true(!str_contains($article->text, 'More From Medium'), 'post-article recommendation heading should be removed');
        $t->true(!str_contains($article->text, 'Discover Medium'), 'platform signup footer should be removed');
        $t->true(!str_contains($article->text, 'Written by Vincent Vallet'), 'author footer should be removed');
        $t->true(!str_contains($article->contentHtml, 'fit/c/160/160'), 'recommended-author avatars should be removed with the footer');
    },
    'maps Mozilla lazy-image-3 full data-src fixture' => static function (TestRunner $t) use ($fixtureText, $imageAttributeRows): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/lazy-image-3';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($imageAttributeRows($expected), $imageAttributeRows($article->contentHtml));
        $t->same(2, count($imageAttributeRows($article->contentHtml)));
    },
    'promotes responsive image candidates behind short data URI placeholders' => static function (TestRunner $t): void {
        $transparentGif = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $source = '<article>'
            . '<p>' . str_repeat('A migrated WordPress article can carry a transparent tracking placeholder before JavaScript hydrates responsive images. ', 3) . '</p>'
            . '<figure><img src="' . $transparentGif . '" data-srcset="https://cdn.example.test/hero-320.webp 320w, https://cdn.example.test/hero-800.webp 800w" alt="Hero image"></figure>'
            . '<p>' . str_repeat('The native extractor should keep the usable candidates for block image output. ', 3) . '</p>'
            . '</article>';

        $article = (new ArticleExtractor())->extract($source);

        $t->contains('srcset="https://cdn.example.test/hero-320.webp 320w, https://cdn.example.test/hero-800.webp 800w"', $article->contentHtml);
        $t->contains('data-srcset="https://cdn.example.test/hero-320.webp 320w, https://cdn.example.test/hero-800.webp 800w"', $article->contentHtml);
        $t->contains('alt="Hero image"', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, $transparentGif), 'short placeholder src should be removed before lazy source promotion');
        $t->contains('usable candidates for block image output', $article->text);
    },
];
