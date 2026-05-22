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
        foreach (['data-src', 'data-srcset', 'data-old-src', 'data-old-srcset', 'alt', 'src', 'srcset'] as $attribute) {
            $value = trim($image->getAttribute($attribute));
            if ($value !== '') {
                $row[$attribute] = $value;
            }
        }
        $rows[] = $row;
    }

    return $rows;
};
$elementChildTags = static function (string $html, string $query): array {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $node = (new DOMXPath($dom))->query($query)?->item(0);
    if (!$node instanceof DOMElement) {
        return [];
    }

    $tags = [];
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $tags[] = strtolower($child->tagName);
        }
    }

    return $tags;
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
    'decodes entity escaped metadata descriptions' => static function (TestRunner $t): void {
        $article = (new ArticleExtractor())->extract('<html><head><meta name="description" content="That&amp;#039;s clean metadata for a migration excerpt."></head><body><article><p>' . str_repeat('Readable article text for the imported post. ', 8) . '</p></article></body></html>');

        $t->same("That's clean metadata for a migration excerpt.", $article->excerpt);
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
        $t->true(!str_contains($article->text, 'Advertisement'), 'in-article ad placeholders should be removed');
    },
    'turns the WordPress migration fixture into core blocks' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $article = $extractor->extract((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-page-builder.html'));
        $blocks = $extractor->toWordPressBlocks($article);

        $t->true(!str_contains($blocks, '<h1>Reusable Blocks After Migration</h1>'), 'post title heading should not be duplicated in imported block content');
        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->contains('canonical article paragraph', $blocks);
    },
    'removes duplicate post title headings and demotes body h1s for WordPress blocks' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $html = '<html><head><meta property="og:title" content="Migration Playbook"></head><body><article>'
            . '<h1>Migration Playbook</h1>'
            . '<p>' . str_repeat('The post title is stored separately during WordPress imports. ', 4) . '</p>'
            . '<h1>Reviewer Notes</h1>'
            . '<p>' . str_repeat('A section heading inside the body should remain available to block serialization. ', 4) . '</p>'
            . '</article></body></html>';

        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Migration Playbook', $article->title);
        $t->true(!str_contains($article->contentHtml, '<h1'), 'remaining content headings should be demoted from h1');
        $t->true(!str_contains($article->text, 'Migration Playbook'), 'duplicate post title should be removed from article body text');
        $t->contains('<h2>Reviewer Notes</h2>', $article->contentHtml);
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
    },
    'strips source classes and simplifies nested wrappers like upstream post processing' => static function (TestRunner $t): void {
        $html = '<html><head><title>Class Cleanup</title></head><body><article class="legacy-template entry-content">'
            . '<h1>Class Cleanup</h1>'
            . '<div class="alignwide wp-container"><section class="theme-row"><div class="inner-row">'
            . '<p id="lead" class="has-text-color" style="color:red">' . str_repeat('WordPress migration output should keep article text and media while dropping source theme CSS classes. ', 4) . '</p>'
            . '</div></section></div>'
            . '<figure class="wp-block-image size-large"><img class="lazy size-full" data-src="/uploads/import-cleanup.jpg" alt="Imported cleanup"></figure>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html);

        $t->same('Class Cleanup', $article->title);
        $t->true(!str_contains($article->contentHtml, 'class='), 'Readability post-processing removes source class attributes by default');
        $t->true(!str_contains($article->contentHtml, '<section'), 'single nested div/section wrappers should be simplified');
        $t->contains('id="lead"', $article->contentHtml);
        $t->contains('src="/uploads/import-cleanup.jpg"', $article->contentHtml);
    },
    'collapses single paragraph div wrappers like upstream scoring cleanup' => static function (TestRunner $t) use ($elementChildTags): void {
        $html = '<html><head><title>Quote Cleanup</title></head><body><article>'
            . '<p>' . str_repeat('Legacy WordPress imports often wrap editorial pull quotes with layout divs from the source theme. ', 3) . '</p>'
            . '<blockquote><div class="wp-block-group quote-shell"><p id="pull">Keep this editorial quote for the migrated post.</p></div></blockquote>'
            . '<p>' . str_repeat('The native extractor should keep the quote while removing the layout-only wrapper. ', 3) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html);

        $t->same(['p'], $elementChildTags($article->contentHtml, '//blockquote'));
        $t->contains('Keep this editorial quote', $article->text);
        $t->true(!str_contains($article->contentHtml, 'quote-shell'), 'source quote wrapper classes should be removed with the wrapper');
    },
    'removes leading byline and action controls before article content' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Migrated Action Bar"></head><body><article class="entry-content">'
            . '<div class="entry-meta">'
            . '<div><a href="/author"><img src="/uploads/author-avatar.jpg" alt="Author avatar"></a></div>'
            . '<div class="byline"><a href="/author">Legacy Contributor</a><button>Follow</button><span>Oct 18, 2019 &middot; 8 min read</span></div>'
            . '<div><a href="/share/twitter?source=post_actions_header">Share this article</a></div>'
            . '</div>'
            . '<h2>Migration Notes</h2>'
            . '<p>' . str_repeat('A WordPress importer should keep editorial copy while dropping source platform controls before the first content heading. ', 3) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html);

        $t->same('Migrated Action Bar', $article->title);
        $t->contains('src="/uploads/author-avatar.jpg"', $article->contentHtml);
        $t->contains('Migration Notes', $article->text);
        $t->contains('source platform controls before the first content heading', $article->text);
        $t->true(!str_contains($article->text, 'Legacy Contributor'), 'leading byline text should be removed before block migration');
        $t->true(!str_contains($article->text, 'Follow'), 'follow button text should be removed');
        $t->true(!str_contains($article->text, '8 min read'), 'platform read-time metadata should be removed');
        $t->true(!str_contains($article->contentHtml, 'post_actions_header'), 'source platform share links should be removed');
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
    'maps Mozilla lazy-image-1 metadata lazy images and post-article chrome cleanup' => static function (TestRunner $t) use ($elementChildTags, $imageAttributeRows, $normalizedText): void {
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
        $t->same($expectedSources, $articleSources);
        $t->same($imageAttributeRows($expected), $imageAttributeRows($article->contentHtml));
        $t->same(['p'], $elementChildTags($article->contentHtml, '//blockquote'));
        foreach ($expectedSources as $sourceUrl) {
            $t->true(in_array($sourceUrl, $articleSources, true), 'expected lazy-image-1 image source should be preserved: ' . $sourceUrl);
        }
        $t->true(!str_contains($article->contentHtml, 'class='), 'upstream default post-process class cleanup should run on copied Medium fixture output');
        $t->contains('data-old-src="https://miro.medium.com/max/60/1*5o3M5niyi911waUrKWVZ0Q.png?q=20"', $article->contentHtml);
        $t->contains('Sources &amp; links', $article->contentHtml);
        $t->true(!str_contains($article->text, 'More From Medium'), 'post-article recommendation heading should be removed');
        $t->true(!str_contains($article->text, 'Discover Medium'), 'platform signup footer should be removed');
        $t->true(!str_contains($article->text, 'Written by Vincent Vallet'), 'author footer should be removed');
        $t->true(!str_contains($article->text, 'Follow'), 'leading Medium follow button should be removed before article content');
        $t->true(!str_contains($article->text, '8 min read'), 'leading Medium read-time metadata should be removed before article content');
        $t->true(!str_contains($article->contentHtml, 'post_actions_header'), 'leading Medium share action links should be removed before article content');
        $t->true(!str_contains($article->contentHtml, 'fit/c/160/160'), 'recommended-author avatars should be removed with the footer');
        $t->true(!str_contains($article->contentHtml, 'CPU profiling before optimization'), 'out-of-band Medium full-width figure wrapper should be removed');
        $t->true(!str_contains($article->contentHtml, 'Zoom in the CPU profiling'), 'out-of-band Medium zoom figure wrapper should be removed');
    },
    'drops out-of-band full-width figure wrappers during WordPress migration cleanup' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Block Media Cleanup"></head><body><article class="article-body">'
            . '<div class="text-column"><p>' . str_repeat('Editorial migration copy keeps article context around media assets. ', 5) . '</p>'
            . '<figure><img src="/uploads/editorial-chart.jpg" alt="Editorial chart"><figcaption>Editorial chart</figcaption></figure></div>'
            . '<div class="full-bleed-layout"><figure><img src="/uploads/decorative-crop.jpg" alt="Decorative crop"><figcaption>Decorative crop</figcaption></figure></div>'
            . '<div class="text-column"><p>' . str_repeat('The imported WordPress post should retain body copy and canonical media while dropping layout-only crops. ', 4) . '</p></div>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($source);

        $t->same('Block Media Cleanup', $article->title);
        $t->contains('/uploads/editorial-chart.jpg', $article->contentHtml);
        $t->contains('Editorial chart', $article->text);
        $t->true(!str_contains($article->contentHtml, '/uploads/decorative-crop.jpg'), 'layout-only full-width crop should be removed');
        $t->true(!str_contains($article->text, 'Decorative crop'), 'decorative figure caption should be removed with the wrapper');
    },
    'maps Mozilla lazy-image-2 responsive image fixture' => static function (TestRunner $t) use ($fixtureText, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/lazy-image-2';
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
        $t->same($imageAttributeRows($expected), $imageAttributeRows($article->contentHtml));
        $t->same(56, count($imageAttributeRows($article->contentHtml)));
        $t->true(!str_contains($article->text, 'Advertisement'), 'Kinja in-article ad placeholders should be removed');
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
