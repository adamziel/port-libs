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
$attributeValues = static function (string $html, string $query): array {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $values = [];
    foreach ((new DOMXPath($dom))->query($query) ?: [] as $node) {
        $values[] = $node->nodeValue;
    }

    return $values;
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
    'maps Mozilla 005 metadata entity unescape fixture' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/005-unescape-html-entities';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(false, str_contains($article->excerpt, '&#xFFFFFFFF;'), 'out-of-range numeric references should become replacement characters');
        $t->same(false, str_contains($article->excerpt, '&#x0;'), 'zero numeric references should become replacement characters');
    },
    'normalizes invalid numeric metadata entities before WordPress excerpt import' => static function (TestRunner $t): void {
        $replacement = mb_chr(0xfffd, 'UTF-8');
        $emoji = mb_chr(0x1f62d, 'UTF-8');
        $html = '<html><head><title>Metadata Entity Cleanup</title>'
            . '<meta name="description" content="Migrated excerpt keeps emoji &amp;#128557; and replaces invalid source entities &amp;#x0; before import.">'
            . '</head><body><article><h1>Metadata Entity Cleanup</h1>'
            . '<p>' . str_repeat('Legacy WordPress migrations sometimes carry double-escaped metadata from old templates or feeds. ', 3) . '</p>'
            . '<p>' . str_repeat('The native cleanup should keep excerpts parser-safe before storing them as post metadata. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Migrated excerpt keeps emoji ' . $emoji . ' and replaces invalid source entities ' . $replacement . ' before import.', $article->excerpt);
        $t->same(false, str_contains($article->excerpt, '&#128557;'), 'valid numeric entities should be decoded for imported excerpts');
        $t->same(false, str_contains($article->excerpt, '&#x0;'), 'invalid numeric entities should not survive imported excerpts');
        $t->contains('excerpts parser-safe', $blocks);
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
    'maps Mozilla ordered-list fixture without counting list paragraphs as readerable' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/ol';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//ol/li/p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//ol/li/p')),
        );
        $t->same(count($attributeValues($expected, '//ol')), count($attributeValues($article->contentHtml, '//ol')));
        $t->same(count($attributeValues($expected, '//li')), count($attributeValues($article->contentHtml, '//li')));
    },
    'maps Mozilla remove-aria-hidden fixture during extraction cleanup' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/remove-aria-hidden';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same([], $attributeValues($article->contentHtml, '//*[@aria-hidden="true" and not(contains(concat(" ", normalize-space(@class), " "), " fallback-image "))]'));
        $t->true(!str_contains($article->text, '**WRONG**'), 'aria-hidden source text should be removed during extraction');
    },
    'maps Mozilla hidden-nodes fixture without dropping retained headers' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/hidden-nodes';
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
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h2')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h2')),
        );
        $t->true(!str_contains($article->contentHtml, 'display: none'), 'display:none source content should be removed');
        $t->true(!str_contains($article->contentHtml, 'hidden="hidden"'), 'hidden-attribute source content should be removed');
    },
    'maps Mozilla rtl direction fixtures from article ancestors' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $extractor = new ArticleExtractor();

        foreach (['rtl-1', 'rtl-2', 'rtl-3', 'rtl-4'] as $name) {
            $fixture = __DIR__ . '/../fixtures/mozilla/' . $name;
            $source = (string) file_get_contents($fixture . '/source.html');
            $expected = (string) file_get_contents($fixture . '/expected.html');
            $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
            $article = $extractor->extract($source);

            $t->same($metadata['title'], $article->title);
            $t->same($metadata['dir'], $article->dir, $name . ' should match upstream article direction');
            $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
            $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
            $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        }
    },
    'maps Mozilla visibility-hidden fixture to the visible section only' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/visibility-hidden';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same([], $attributeValues($article->contentHtml, '//h1|//h2'));
        $t->same([], $attributeValues($article->contentHtml, '//object|//embed|//iframe'));
        $t->true(!str_contains($article->text, 'Iframe fallback test'), 'visibility:hidden section content should not be imported');
    },
    'maps Mozilla basic tag and empty paragraph cleanup fixtures' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $normalizedText): void {
        $extractor = new ArticleExtractor();

        foreach (['basic-tags-cleaning', 'remove-extra-paragraphs'] as $name) {
            $fixture = __DIR__ . '/../fixtures/mozilla/' . $name;
            $source = (string) file_get_contents($fixture . '/source.html');
            $expected = (string) file_get_contents($fixture . '/expected.html');
            $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
            $article = $extractor->extract($source, 'http://fakehost/test/page.html');

            $t->same($metadata['title'], $article->title);
            $t->same($metadata['byline'], $article->byline);
            $t->same($metadata['siteName'], $article->siteName);
            $t->same($metadata['publishedTime'], $article->publishedTime);
            $t->same($metadata['dir'], $article->dir);
            $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
            $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
            $t->same(
                array_map($normalizedText, $attributeValues($expected, '//p')),
                array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
            );
            $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
            $t->same($elementChildTags($expected, '//div[@id="readability-page-1"]'), $elementChildTags($article->contentHtml, '//main'));
            $t->same([], $attributeValues($article->contentHtml, '//h1|//h2|//object|//embed|//iframe'));
            $t->same([], $attributeValues($article->contentHtml, '//p[not(normalize-space()) and not(.//img or .//embed or .//object or .//iframe)]'));
        }
    },
    'maps Mozilla script style and WordPress social button cleanup fixtures' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $extractor = new ArticleExtractor();

        foreach (['style-tags-removal', 'remove-script-tags', 'social-buttons'] as $name) {
            $fixture = __DIR__ . '/../fixtures/mozilla/' . $name;
            $source = (string) file_get_contents($fixture . '/source.html');
            $expected = (string) file_get_contents($fixture . '/expected.html');
            $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
            $article = $extractor->extract($source, 'http://fakehost/test/page.html');

            $t->same($metadata['title'], $article->title);
            $t->same($metadata['byline'], $article->byline);
            $t->same($metadata['siteName'], $article->siteName);
            $t->same($metadata['publishedTime'], $article->publishedTime);
            $t->same($metadata['dir'], $article->dir);
            $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
            $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
            $t->same(
                array_map($normalizedText, $attributeValues($expected, '//p')),
                array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
            );
            $t->same(
                array_map($normalizedText, $attributeValues($expected, '//h2')),
                array_map($normalizedText, $attributeValues($article->contentHtml, '//h2')),
            );
            $t->same([], $attributeValues($article->contentHtml, '//script|//style'));
        }
    },
    'removes WordPress Jetpack like widgets and inline executable fragments before block output' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Jetpack Widget Cleanup"></head><body><article>'
            . '<h1>Jetpack Widget Cleanup</h1>'
            . '<p>' . str_repeat('A WordPress importer should retain editorial copy while dropping source runtime widgets. ', 3) . '</p>'
            . '<style>.sharedaddy{display:block}</style><script>alert("wrong")</script>'
            . '<div class="sharedaddy sd-block sd-like" id="like-post-wrapper-10"><h3>Like this:</h3><span>Like</span><span>Loading...</span></div>'
            . '<p>' . str_repeat('The native cleanup keeps the article ready for clean paragraph blocks. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Jetpack Widget Cleanup', $article->title);
        $t->contains('retain editorial copy', $article->text);
        $t->contains('ready for clean paragraph blocks', $blocks);
        foreach (['<script', '<style', 'sharedaddy', 'like-post-wrapper', 'Like this:', 'Loading...', 'alert("wrong")'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'source executable/social fragment should be removed: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'block output should not contain source executable/social fragment: ' . $fragment);
        }
    },
    'removes hidden WordPress export duplicates while preserving fallback images' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Hidden Export Cleanup"></head><body><article>'
            . '<h1>Hidden Export Cleanup</h1>'
            . '<p>' . str_repeat('Visible migration copy should be imported without duplicate hidden source fragments. ', 4) . '</p>'
            . '<p><span aria-hidden="true">Screen reader duplicate headline</span>Visible paragraph text remains available.</p>'
            . '<div hidden>Hidden widget copy should not become a block.</div>'
            . '<div style="display:none">Display none tracking content should not be imported.</div>'
            . '<div style="visibility:hidden">Visibility hidden share counters should not be imported.</div>'
            . '<div role="dialog" aria-modal="true">Cookie consent modal should not be imported.</div>'
            . '<p><img class="fallback-image" aria-hidden="true" src="/uploads/math-fallback.png" alt="Formula fallback"></p>'
            . '<p>' . str_repeat('Fallback images are retained because upstream Readability keeps aria-hidden fallback-image media. ', 3) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html);

        $t->contains('Visible paragraph text remains available.', $article->text);
        $t->contains('/uploads/math-fallback.png', $article->contentHtml);
        $t->contains('aria-hidden="true"', $article->contentHtml);
        $t->true(!str_contains($article->text, 'Screen reader duplicate headline'), 'aria-hidden duplicate source text should be removed');
        $t->true(!str_contains($article->text, 'Hidden widget copy'), 'hidden attribute content should be removed');
        $t->true(!str_contains($article->text, 'Display none tracking'), 'display:none content should be removed');
        $t->true(!str_contains($article->text, 'Visibility hidden share'), 'visibility:hidden content should be removed');
        $t->true(!str_contains($article->text, 'Cookie consent modal'), 'aria-modal dialogs should be removed like upstream');
    },
    'preserves WordPress RTL article direction metadata from migrated wrappers' => static function (TestRunner $t): void {
        $html = '<html dir="ltr"><head><title>RTL Import Direction</title></head><body dir="rtl"><main><article>'
            . '<h1>RTL Import Direction</h1>'
            . '<p>' . str_repeat('A WordPress importer should keep right-to-left direction metadata from source wrappers while dropping duplicate title headings. ', 3) . '</p>'
            . '<p>' . str_repeat('The extracted block content remains portable, and the import layer can apply the direction to the post or block wrapper. ', 3) . '</p>'
            . '</article></main></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('RTL Import Direction', $article->title);
        $t->same('rtl', $article->dir);
        $t->contains('right-to-left direction metadata', $blocks);
        $t->same(false, str_contains($article->text, 'RTL Import Direction'), 'duplicate source title heading should not become block text');
    },
    'removes inline WordPress stylesheet links and fieldset controls before block output' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Legacy Inline Junk"></head><body><article>'
            . '<h1>Legacy Inline Junk</h1>'
            . '<p>' . str_repeat('Imported WordPress articles should keep editorial paragraphs while dropping source template fragments. ', 3) . '</p>'
            . '<link rel="stylesheet" href="/wp-content/themes/source-theme/editor.css">'
            . '<fieldset><legend>Subscribe before import</legend><input name="email" value="reader@example.com"></fieldset>'
            . '<p>' . str_repeat('The resulting block output should not include inline stylesheet tags or form field chrome. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->contains('Legacy Inline Junk', $article->title);
        $t->contains('editorial paragraphs', $article->text);
        $t->same(false, str_contains($article->contentHtml, '<link'));
        $t->same(false, str_contains($article->contentHtml, '<fieldset'));
        $t->same(false, str_contains($article->text, 'Subscribe before import'));
        $t->same(false, str_contains($blocks, '<link'));
        $t->same(false, str_contains($blocks, '<fieldset'));
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
    'maps Mozilla wordpress fixture articleBody images and Jetpack cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/wordpress';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//div[@itemprop="articleBody"]//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//img/@srcset'), $attributeValues($article->contentHtml, '//img/@srcset'));
        $t->contains('Stack Overflow published its analysis', $blocks);
        foreach (['Like this:', 'Related', 'There are 13 comments', 'Click to share'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'WordPress fixture chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'WordPress fixture chrome should not enter block output: ' . $fragment);
        }
    },
    'prefers WordPress articleBody microdata over trailing theme chrome' => static function (TestRunner $t): void {
        $body = str_repeat('Portable WordPress articleBody copy should win over template chrome. ', 5);
        $html = '<html><head><title>Microdata Import</title></head><body><article itemprop="blogPost">'
            . '<h1>Microdata Import</h1>'
            . '<div itemprop="articleBody"><p>' . $body . '</p><p>' . $body . '</p></div>'
            . '<div id="terms"><p>Tagged migration import block editor review queue sidebar note should not be selected with the article body.</p></div>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Microdata Import', $article->title);
        $t->contains('articleBody copy should win', $blocks);
        $t->same(false, str_contains($article->text, 'Tagged migration import'), 'articleBody should beat sibling WordPress tag chrome during content selection');
        $t->same(false, str_contains($article->contentHtml, 'id="terms"'), 'theme terms wrapper should not survive when articleBody is the best candidate');
        $t->same(false, str_contains($blocks, 'sidebar note should not be selected'), 'theme terms text should not become a WordPress paragraph block');
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
    'weights hash-only links lightly when collapsing WordPress footnote wrappers' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Footnote Wrapper Cleanup"></head><body><article>'
            . '<h1>Footnote Wrapper Cleanup</h1>'
            . '<p>' . str_repeat('Imported editorial copy should remain available before the citation wrapper. ', 3) . '</p>'
            . '<div id="footnote-shell"><p>Keep this paragraph with a <a href="#citation-one">long internal citation reference</a> while removing only the source wrapper.</p></div>'
            . '<p id="citation-one">' . str_repeat('The footnote target remains in the article so WordPress block output keeps local jump links usable. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Footnote Wrapper Cleanup', $article->title);
        $t->contains('href="#citation-one"', $article->contentHtml);
        $t->contains('long internal citation reference', $blocks);
        $t->contains('id="citation-one"', $blocks);
        $t->same(false, str_contains($article->contentHtml, 'footnote-shell'), 'hash-link wrapper should collapse under upstream link-density weighting');
        $t->same(false, str_contains($blocks, '<div'), 'collapsed wrapper should not become an extra block container');
    },
    'wraps phrasing media in div paragraphs like upstream preprocessing' => static function (TestRunner $t) use ($attributeValues): void {
        $html = '<html><head><title>Phrasing Media Cleanup</title></head><body><article>'
            . '<h1>Phrasing Media Cleanup</h1>'
            . '<p>' . str_repeat('Legacy WordPress migration content often surrounds inline media with layout divs. ', 4) . '</p>'
            . '<figure><div><img src="/uploads/inline-figure.jpg" alt="Inline figure"></div><figcaption>Inline figure</figcaption></figure>'
            . '<div id="avatar-shell"><a href="/author"><img src="/uploads/avatar.jpg" alt="Avatar"></a></div>'
            . '<p>' . str_repeat('The importer should preserve media while matching Readability paragraph wrappers. ', 4) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html);

        $t->same(['/uploads/inline-figure.jpg'], $attributeValues($article->contentHtml, '//figure/div/p/img/@src'));
        $t->same(['/uploads/avatar.jpg'], $attributeValues($article->contentHtml, '//div[@id="avatar-shell"]/p/a/img/@src'));
        $t->contains('Inline figure', $article->text);
        $t->contains('matching Readability paragraph wrappers', $article->text);
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
    'maps Mozilla base URL fixture relative link and media cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/base-url-base-element-relative';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same($attributeValues($expected, '//img[@src]/@src'), $attributeValues($article->contentHtml, '//img[@src]/@src'));
    },
    'maps Mozilla base URL fixture family including paragraphized div content' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $normalizedText): void {
        $extractor = new ArticleExtractor();

        foreach (['base-url', 'base-url-base-element'] as $name) {
            $fixture = __DIR__ . '/../fixtures/mozilla/' . $name;
            $source = (string) file_get_contents($fixture . '/source.html');
            $expected = (string) file_get_contents($fixture . '/expected.html');
            $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
            $article = $extractor->extract($source, 'http://fakehost/test/page.html');

            $t->same($metadata['title'], $article->title);
            $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
            $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
            $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
            $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
            $t->same($attributeValues($expected, '//img[@src]/@src'), $attributeValues($article->contentHtml, '//img[@src]/@src'));
            $t->same($elementChildTags($expected, '//article'), $elementChildTags($article->contentHtml, '//main'));
        }
    },
    'maps Mozilla javascript link replacement fixture to inert span content' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/js-link-replacement';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($elementChildTags($expected, '//div'), $elementChildTags($article->contentHtml, '//main'));
        $t->same($elementChildTags($expected, '//span'), $elementChildTags($article->contentHtml, '//span'));
        $t->same([], $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->true(!str_contains($article->contentHtml, '<head>'), 'fallback content selection should not include document head markup');
        $t->true(!str_contains($article->contentHtml, 'javascript:'), 'javascript href should be removed while preserving link children');
    },
    'absolutizes WordPress migration links and media against the source URL' => static function (TestRunner $t): void {
        $html = '<html><head><base href="/imports/2024/"><meta property="og:title" content="Migrated Link Map"></head><body><article>'
            . '<h1>Migrated Link Map</h1>'
            . '<p>' . str_repeat('A WordPress migration should keep editorial links and media usable after content leaves the source domain. ', 3) . '</p>'
            . '<p><a href="assets/download.zip">Download package</a> <a href="javascript:"><strong>Open inline note</strong></a></p>'
            . '<figure><img src="images/hero.jpg" srcset="images/hero-320.jpg 320w, /media/hero-800.jpg 800w" alt="Imported hero"></figure>'
            . '<p>' . str_repeat('Absolute URLs let block editors, import previews, and media sideloaders inspect the migrated content. ', 3) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html, 'https://example.com/source/page.html');

        $t->contains('href="https://example.com/imports/2024/assets/download.zip"', $article->contentHtml);
        $t->contains('src="https://example.com/imports/2024/images/hero.jpg"', $article->contentHtml);
        $t->contains('srcset="https://example.com/imports/2024/images/hero-320.jpg 320w, https://example.com/media/hero-800.jpg 800w"', $article->contentHtml);
        $t->contains('Open inline note', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, 'javascript:'), 'javascript links should be replaced by inert content like upstream post-processing');
    },
    'maps Mozilla clean-links fixture popup links and whitespace-trimmed URIs' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/clean-links';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));

        $articleHrefs = $attributeValues($article->contentHtml, '//a[@href]/@href');
        $t->same($attributeValues($expected, '//a[@href]/@href'), $articleHrefs);
        $articleImageSources = $attributeValues($article->contentHtml, '//img[@src]/@src');
        $t->same($attributeValues($expected, '//img[@src]/@src'), $articleImageSources);
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same([], $attributeValues($article->contentHtml, '//a[contains(@href, "bartleby/bartleby.html") or contains(@href, "web-hm.htm")]/@href'));
        $t->same([], $attributeValues($article->contentHtml, '//img[contains(@src, "bar.gif") or contains(@src, "myhome.jpg")]/@src'));

        foreach (array_merge($articleHrefs, $articleImageSources) as $uri) {
            $t->same(false, str_contains($uri, '%20'), 'cleaned URIs should not retain encoded trailing spaces');
            $t->same(false, str_contains($uri, '%0A'), 'cleaned URIs should not retain encoded newlines');
        }

        $t->same([], $attributeValues($article->contentHtml, '//a[starts-with(@href, "javascript:")]/@href'));
        $t->same([], $attributeValues($article->contentHtml, '//@onclick|//@onmouseout'));
    },
    'removes trailing WordPress footer link bars after article content' => static function (TestRunner $t): void {
        $html = '<html><head><title>Legacy Footer Link Cleanup</title></head><body><article>'
            . '<h1>Legacy Footer Link Cleanup</h1>'
            . '<p>' . str_repeat('Migrated longform posts can end with source-theme footer bars after the actual editorial body. ', 3) . '<a href="/editorial/source">Editorial source</a>.</p>'
            . '<p>' . str_repeat('The importer should keep article links while dropping compact navigation strips that follow the content. ', 3) . '</p>'
            . '<center><img src="../theme-bar.gif" width="500" height="12"><p><a href="/archive">Archive</a><br><a href="/links">More Links</a></p><p><a href="/"><img src="../home.gif" width="50" height="21"></a></p></center>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html, 'https://example.com/imports/story.html');
        $blocks = (new ArticleExtractor())->toWordPressBlocks($article);

        $t->contains('href="https://example.com/editorial/source"', $article->contentHtml);
        $t->contains('actual editorial body', $blocks);
        $t->same(false, str_contains($article->contentHtml, 'theme-bar.gif'), 'source footer bar image should be dropped');
        $t->same(false, str_contains($article->contentHtml, 'home.gif'), 'source footer home image should be dropped');
        $t->same(false, str_contains($article->contentHtml, '/archive'), 'source footer archive link should be dropped');
        $t->same(false, str_contains($article->contentHtml, 'More Links'), 'source footer navigation text should be dropped');
    },
    'promotes single article bodies and removes empty paragraphs before block migration' => static function (TestRunner $t): void {
        $html = '<html><head><title>Single Article Import</title></head><body>'
            . '<p> </p><div class="masthead"></div>'
            . '<article><p></p><h1>Single Article Import</h1>'
            . '<p>' . str_repeat('The migration should import the real article body without surrounding document wrappers. ', 3) . '</p>'
            . '<p> </p><figure><img src="/uploads/single-article.jpg" alt="Single article"></figure>'
            . '<p>' . str_repeat('Empty source paragraphs should not become blank WordPress paragraph blocks. ', 3) . '</p></article>'
            . '<footer>Subscribe to unrelated site updates</footer></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->contains('real article body', $article->text);
        $t->contains('/uploads/single-article.jpg', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, '<article'), 'single article wrapper should not be imported as block content');
        $t->true(!str_contains($article->contentHtml, '<p></p>'), 'empty source paragraphs should be removed like upstream _prepArticle');
        $t->true(!str_contains($article->text, 'Subscribe to unrelated site updates'), 'surrounding body chrome should not be imported');
        $t->true(!str_contains($blocks, "<p></p>"), 'empty paragraphs should not become blank WordPress blocks');
    },
    'unwraps single-cell layout tables while retaining real data tables' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Legacy Table Layout"></head><body><article>'
            . '<h1>Legacy Table Layout</h1>'
            . '<p>' . str_repeat('Older WordPress themes often used tables as layout wrappers around article copy. ', 3) . '</p>'
            . '<table class="layout-table"><tbody><tr><td class="layout-cell">A migrated paragraph <a href="/docs/table-cleanup">with a relative link</a> should not stay inside a layout table.</td></tr></tbody></table>'
            . '<table id="specs"><tbody><tr><td>Setting</td><td>Value</td></tr><tr><td>Mode</td><td>Native import</td></tr></tbody></table>'
            . '<p>' . str_repeat('Actual tabular data should remain available for later table-block serialization. ', 3) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html, 'https://example.com/import/post.html');
        $blocks = (new ArticleExtractor())->toWordPressBlocks($article);

        $t->same('Legacy Table Layout', $article->title);
        $t->contains('A migrated paragraph', $article->contentHtml);
        $t->contains('href="https://example.com/docs/table-cleanup"', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, '<td>A migrated paragraph'), 'single-cell layout table should be unwrapped like upstream _prepArticle');
        $t->contains('<table id="specs">', $article->contentHtml);
        $t->contains('<td>Setting</td>', $article->contentHtml);
        $t->contains('<td>Value</td>', $article->contentHtml);
        $t->contains('Actual tabular data should remain', $article->text);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<figure class="wp-block-table"><table id="specs">', $blocks);
    },
    'turns single-cell block layout tables into div wrappers for WordPress imports' => static function (TestRunner $t): void {
        $html = '<html><head><title>Layout Table Media</title></head><body><article>'
            . '<p>' . str_repeat('Some migration sources place a whole article section inside one table cell. ', 3) . '</p>'
            . '<table class="wp-layout"><tbody><tr><td class="legacy-cell"><p>Keep this nested section copy.</p><figure><img src="/uploads/table-media.jpg" alt="Table media"></figure></td></tr></tbody></table>'
            . '<p>' . str_repeat('The native extractor should remove table markup without dropping editorial media. ', 3) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html);

        $t->contains('Keep this nested section copy.', $article->contentHtml);
        $t->contains('/uploads/table-media.jpg', $article->contentHtml);
        $t->true(preg_match('/<\/?(?:table|tbody|tr|td)\b/i', $article->contentHtml) !== 1, 'one-cell block layout table markup should be removed');
        $t->contains('remove table markup without dropping editorial media', $article->text);
    },
    'strips presentational table and font markup for WordPress imports' => static function (TestRunner $t) use ($attributeValues): void {
        $html = '<html><head><meta property="og:title" content="Legacy Styling Cleanup"></head><body><article>'
            . '<h1>Legacy Styling Cleanup</h1>'
            . '<p><font size="+2">Legacy status</font> should keep its emphasis without importing obsolete font tags.</p>'
            . '<p>' . str_repeat('A WordPress migration should keep tabular content while dropping source-era presentational attributes. ', 3) . '</p>'
            . '<table id="legacy-data" class="layout-grid" width="90%" cellpadding="8" cellspacing="0" border="1" bgcolor="#fff" style="color:red">'
            . '<tbody><tr><td width="50%" bgcolor="#eee">Metric</td><td style="text-align:right">Ready</td></tr></tbody></table>'
            . '<p>' . str_repeat('Cleaned table markup can become a core table block without carrying theme-specific styling. ', 3) . '</p>'
            . '</article></body></html>';

        $article = (new ArticleExtractor())->extract($html);
        $blocks = (new ArticleExtractor())->toWordPressBlocks($article);

        $t->contains('<span size="+2">Legacy status</span>', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, '<font'), 'legacy font tags should be normalized to spans like upstream _prepDocument');
        $t->contains('<table id="legacy-data">', $article->contentHtml);
        $t->same([], $attributeValues($article->contentHtml, '//@align|//@bgcolor|//@border|//@cellpadding|//@cellspacing|//@style|//table/@width|//td/@width'));
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('dropping source-era presentational attributes', $article->text);
    },
    'maps Mozilla table style attributes fixture cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/table-style-attributes';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        foreach (['table', 'tr', 'td', 'a', 'img', 'span'] as $tagName) {
            $t->same(
                preg_match_all('/<' . $tagName . '\b/i', $expected),
                preg_match_all('/<' . $tagName . '\b/i', $article->contentHtml),
                'expected element count should match copied upstream fixture for ' . $tagName,
            );
        }
        $t->same([], $attributeValues($article->contentHtml, '//font|//@align|//@bgcolor|//@border|//@cellpadding|//@cellspacing|//@style|//table/@width|//td/@width'));
        $t->true(!str_contains($article->contentHtml, '<!--'), 'commented source tables and links should not be imported');
    },
    'maps Mozilla links-in-tables fixture with retained table links' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/links-in-tables';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($attributeValues($expected, '//table//a[@href]/@href'), $attributeValues($article->contentHtml, '//table//a[@href]/@href'));
        $t->same(count($attributeValues($expected, '//table//tr')), count($attributeValues($article->contentHtml, '//table//tr')));
        $t->same(count($attributeValues($expected, '//table//col')), count($attributeValues($article->contentHtml, '//table//col')));
    },
    'maps Mozilla keep-tabular-data fixture table rows and status images' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/keep-tabular-data';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(count($attributeValues($expected, '//table')), count($attributeValues($article->contentHtml, '//table')));
        $t->same(count($attributeValues($expected, '//table//tr')), count($attributeValues($article->contentHtml, '//table//tr')));
        $t->same($attributeValues($expected, '//table//img[@src]/@src'), $attributeValues($article->contentHtml, '//table//img[@src]/@src'));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->contains('Blueprint library', $article->contentHtml);
        $t->same(false, str_contains($article->text, '0.17.0.Not'), 'adjacent paragraphs in retained table fixtures should keep separator whitespace');
        $t->true(!str_contains($article->contentHtml, 'finished_gui_table'), 'source table classes should be stripped while data rows remain');
        $t->true(!str_contains($article->contentHtml, 'style='), 'source table styles should be stripped while data rows remain');
    },
    'preserves upstream marked data tables while unwrapping presentational one cell tables' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Plugin Compatibility Matrix"></head><body><article>'
            . '<h1>Plugin Compatibility Matrix</h1>'
            . '<p>' . str_repeat('A WordPress migration should preserve real tabular compatibility data even when the source table has only one visible cell. ', 3) . '</p>'
            . '<table id="summary-data" summary="Plugin compatibility matrix"><tbody><tr><td>Single compatibility row with a link to <a href="/plugins/import-helper">Import Helper</a>.</td></tr></tbody></table>'
            . '<table id="layout-shell" role="presentation"><tbody><tr><td>Legacy layout copy with <a href="/layout-note">a recovered note</a>.</td></tr></tbody></table>'
            . '<p>' . str_repeat('Layout tables should still collapse into normal article copy before block serialization. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html, 'https://example.com/import/post.html');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->contains('<table id="summary-data" summary="Plugin compatibility matrix">', $article->contentHtml);
        $t->contains('href="https://example.com/plugins/import-helper"', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, '<table id="layout-shell"'), 'presentational one-cell tables should still unwrap as layout');
        $t->contains('Legacy layout copy', $article->contentHtml);
        $t->contains('href="https://example.com/layout-note"', $article->contentHtml);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<figure class="wp-block-table"><table id="summary-data" summary="Plugin compatibility matrix">', $blocks);
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
    'maps Mozilla metadata-content-missing fixture metadata precedence' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/metadata-content-missing';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(false, str_contains((string) $article->byline, 'FAIL'), 'fallback author meta should not beat dc:creator metadata');
    },
    'maps Mozilla metadata preferred and space separated property fixtures' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $extractor = new ArticleExtractor();

        foreach (['003-metadata-preferred', '004-metadata-space-separated-properties'] as $name) {
            $fixture = __DIR__ . '/../fixtures/mozilla/' . $name;
            $source = (string) file_get_contents($fixture . '/source.html');
            $expected = (string) file_get_contents($fixture . '/expected.html');
            $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
            $article = $extractor->extract($source);

            $t->same($metadata['title'], $article->title);
            $t->same($metadata['byline'], $article->byline);
            $t->same($metadata['siteName'], $article->siteName);
            $t->same($metadata['publishedTime'], $article->publishedTime);
            $t->same($metadata['dir'], $article->dir);
            $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
            $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
            $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
            $t->same(false, str_contains($article->title, 'Title Element'), $name . ' should not fall back to document title');
            $t->same(false, str_contains((string) $article->byline, 'FAIL'), $name . ' should not use lower-priority author metadata');
        }
    },
    'uses Dublin Core metadata for WordPress import titles bylines and excerpts' => static function (TestRunner $t): void {
        $html = '<html><head><title>Theme Fallback Title</title>'
            . '<meta property="x:title dc:title" content="Canonical Import Title">'
            . '<meta property="dc:creator twitter:site_name" content="Migration Desk">'
            . '<meta name="author" content="Wrong Theme Author">'
            . '<meta property="og:description twitter:description">'
            . '<meta property="dc:description" content="Clean import excerpt from migrated metadata.">'
            . '</head><body><article>'
            . '<h1>Visible Article Heading</h1>'
            . '<p>' . str_repeat('A WordPress importer should prefer portable metadata over incomplete theme tags. ', 4) . '</p>'
            . '<p>' . str_repeat('The editorial body remains available for clean block serialization. ', 4) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Canonical Import Title', $article->title);
        $t->same('Migration Desk', $article->byline);
        $t->same('Clean import excerpt from migrated metadata.', $article->excerpt);
        $t->contains('<h2>Visible Article Heading</h2>', $article->contentHtml);
        $t->contains('portable metadata over incomplete theme tags', $blocks);
        $t->same(false, str_contains($article->title, 'Theme Fallback Title'));
        $t->same(false, str_contains((string) $article->byline, 'Wrong Theme Author'));
    },
    'maps Mozilla 001 fixture body itemprop author byline' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/001';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->contains('So finally you\'re testing your frontend JavaScript code?', $fixtureText($article->contentHtml));
        $t->contains('<pre><code>// cow.js', $article->contentHtml);
        $t->same(
            array_slice($attributeValues($expected, '//a[@href]/@href'), 0, 5),
            array_slice($attributeValues($article->contentHtml, '//a[@href]/@href'), 0, 5),
        );
        $t->same(false, str_contains($article->contentHtml, 'article-author'), 'body byline node should be removed after extracting itemprop author text');
    },
    'extracts WordPress itemprop body bylines without importing byline blocks' => static function (TestRunner $t): void {
        $html = '<html><head><title>Byline Itemprop Import</title></head><body><article>'
            . '<h1>Byline Itemprop Import</h1>'
            . '<p>Metadata-free source themes can still mark a byline in the body before editorial copy.</p>'
            . '<p itemprop="author" itemscope itemtype="https://schema.org/Person">By <span itemprop="name">Sarah Gooding</span></p>'
            . '<p>' . str_repeat('The WordPress import should preserve editorial article text while keeping source author metadata separate from migrated blocks. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Byline Itemprop Import', $article->title);
        $t->same('Sarah Gooding', $article->byline);
        $t->contains('editorial article text', $blocks);
        $t->same(false, str_contains($article->contentHtml, 'itemprop="author"'), 'body byline markup should not be imported as content');
        $t->same(false, str_contains($article->text, 'Sarah Gooding'), 'source byline should be metadata, not article text');
        $t->same(false, str_contains($blocks, 'Sarah Gooding'), 'source byline should not become a WordPress paragraph block');
    },
    'maps Mozilla title-en-dash fixture title separator cleanup' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/title-en-dash';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(false, str_contains($article->title, 'My website'), 'site suffix should not be retained in the article title');
    },
    'maps Mozilla title and h1 discrepancy fixture without replacing the document title' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/title-and-h1-discrepancy';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h2')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h2')),
        );
        $t->same([], $attributeValues($article->contentHtml, '//h1'));
    },
    'removes source site suffixes from WordPress import titles' => static function (TestRunner $t): void {
        $html = '<html><head><title>Reusable Pattern Migration Planning Guide – Legacy Agency Site</title></head><body><article>'
            . '<h1>Reusable Pattern Migration Planning Guide</h1>'
            . '<p>' . str_repeat('Imported posts should not carry the source site name in the WordPress post title. ', 3) . '</p>'
            . '<h2>Block Review</h2>'
            . '<p>' . str_repeat('The cleaned article body remains available for core paragraph and heading blocks. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Reusable Pattern Migration Planning Guide', $article->title);
        $t->same(false, str_contains($article->title, 'Legacy Agency Site'));
        $t->same(false, str_contains($article->text, 'Reusable Pattern Migration Planning Guide'), 'duplicate title heading should be removed from block content');
        $t->contains('<h2>Block Review</h2>', $article->contentHtml);
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('Imported posts should not carry the source site name', $blocks);
    },
    'uses JSON-LD name when headline does not match the WordPress import title' => static function (TestRunner $t): void {
        $html = '<html><head><title>Canonical Import Title – Legacy Site</title>'
            . '<script type="application/ld+json">{"@context":"http://schema.org","@type":"NewsArticle","name":"Canonical Import Title","headline":"Injected Theme Teaser Should Not Win","description":"Structured excerpt for the import.","author":{"@type":"Person","name":"Migration Desk"},"publisher":{"@type":"Organization","name":"Legacy Site"},"datePublished":"2024-05-01T10:00:00+00:00"}</script>'
            . '</head><body><article>'
            . '<h1>Canonical Import Title</h1>'
            . '<p>' . str_repeat('WordPress migrations can carry plugin-injected structured data with competing title-like fields. ', 3) . '</p>'
            . '<p>' . str_repeat('The native extractor should keep the title that matches the document and imported post metadata. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Canonical Import Title', $article->title);
        $t->same('Migration Desk', $article->byline);
        $t->same('Legacy Site', $article->siteName);
        $t->same('2024-05-01T10:00:00+00:00', $article->publishedTime);
        $t->same('Structured excerpt for the import.', $article->excerpt);
        $t->same(false, str_contains($article->title, 'Injected Theme Teaser'), 'non-matching JSON-LD headline should not replace the matching name');
        $t->same(false, str_contains($article->text, 'Canonical Import Title'), 'duplicate title heading should still be removed from block content');
        $t->contains('plugin-injected structured data', $blocks);
    },
    'maps Mozilla schema-org context object fixture without leading news chrome' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/schema-org-context-object';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);
        $expectedParagraphs = array_map($normalizedText, $attributeValues($expected, '//p'));
        $articleParagraphs = array_map($normalizedText, $attributeValues($article->contentHtml, '//p'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($expectedParagraphs, $articleParagraphs);
        $t->true(str_starts_with($articleParagraphs[0] ?? '', 'SEOUL, South Korea'), 'article text should start at the first editorial paragraph');
        $t->same('Abigail Williams and Beomsu Jo contributed.', $articleParagraphs[count($articleParagraphs) - 1] ?? null, 'React comment-delimited contributor text should remain one paragraph');
        $t->same(false, str_contains($article->text, 'Dec. 6, 2024, 10:00 PM UTC'), 'timestamp chrome should not enter article text');
        $t->same(false, str_contains($article->text, 'By Stella Kim and Jennifer Jett'), 'inline byline chrome should not enter article text');
        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->contains('SEOUL, South Korea', $blocks);
        $t->contains('Abigail Williams and Beomsu Jo contributed.', $blocks);
        $t->same(false, str_contains($blocks, '<p>Abigail Williams</p>'), 'WordPress blocks should not split comment-delimited contributor text into fragment paragraphs');
        $t->same(false, str_contains($blocks, 'article-body-timestamp'), 'timestamp wrappers should not enter WordPress blocks');
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
    'maps Mozilla data-url-image fixture media retention boundaries' => static function (TestRunner $t) use ($attributeValues, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/data-url-image';
        $source = (string) file_get_contents($fixture . '/source.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $rows = $imageAttributeRows($article->contentHtml);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(5, count($rows), 'upstream fixture retains five image payloads');
        $t->true(str_starts_with($rows[0]['src'] ?? '', 'data:image/gif;base64,'), 'standalone tiny gif data URI should be preserved');
        $t->true(!isset($rows[1]['src']), 'tiny placeholder src should be removed when responsive candidates exist');
        $t->same($rows[1]['data-srcset'] ?? '', $rows[1]['srcset'] ?? '', 'responsive data-srcset should be promoted to srcset');
        $t->true(str_starts_with($rows[1]['srcset'] ?? '', 'https://i.kinja-img.com/gawker-media/image/upload/'), 'promoted responsive candidates should remain external image URLs');
        $t->true(str_starts_with($rows[2]['src'] ?? '', 'data:image/svg+xml;utf8,'), 'inline SVG data URI should remain an image source');
        $t->true(str_contains($rows[2]['src'] ?? '', '<svg xmlns='), 'inline SVG data URI should retain upstream literal spaces after serialization');
        $t->same(false, str_contains($rows[2]['src'] ?? '', '%20'), 'inline SVG data URI should not be space-encoded away from upstream fixture semantics');
        $t->true(str_starts_with($rows[3]['src'] ?? '', 'data:image/svg+xml;base64,'), 'base64 SVG data URI should be preserved');
        $t->true(str_starts_with($rows[4]['src'] ?? '', 'data:image/jpeg;base64,'), 'real JPEG data URI should be preserved');
        $t->same(4, count($attributeValues($article->contentHtml, '//p')), 'expected editorial paragraphs should remain around data URI images');
    },
    'maps Mozilla keep-images fixture full-width editorial media retention' => static function (TestRunner $t) use ($attributeValues, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/keep-images';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($attributeValues($expected, '//img[@src]/@src'), $attributeValues($article->contentHtml, '//img[@src]/@src'));
        $t->same(16, count($imageAttributeRows($article->contentHtml)), 'all expected image payloads should remain');
        $t->same(count($attributeValues($expected, '//figure')), count($attributeValues($article->contentHtml, '//figure')));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->contains('Cristina Gil Lladanosa, at the Barcelona testing lab', $article->text);
        $t->contains('Photo by Joan Bardeletti', $article->text);
        $t->same(false, str_contains($article->text, 'Ready to publish?'), 'Medium editor chrome should not survive the keep-images fixture extraction');
    },
    'maps Mozilla lazy-image-1 metadata lazy images and post-article chrome cleanup' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/lazy-image-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');
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
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same($elementChildTags($expected, '//div[@id="readability-page-1"]/*[1]'), $elementChildTags($article->contentHtml, '//main/*[1]'));
        $t->same(['p'], $elementChildTags($article->contentHtml, '//blockquote'));
        $t->same([], $attributeValues($article->contentHtml, '//section'));
        foreach ($expectedSources as $sourceUrl) {
            $t->true(in_array($sourceUrl, $articleSources, true), 'expected lazy-image-1 image source should be preserved: ' . $sourceUrl);
        }
        $t->true(!str_contains($article->contentHtml, 'class='), 'upstream default post-process class cleanup should run on copied Medium fixture output');
        $t->contains('data-old-src="https://miro.medium.com/max/60/1*5o3M5niyi911waUrKWVZ0Q.png?q=20"', $article->contentHtml);
        $t->contains('Sources &amp; links', $article->contentHtml);
        $t->true(!str_contains($article->contentHtml, '<article'), 'single Medium article wrapper should not remain in extracted content');
        $t->true(!str_contains($article->contentHtml, '<p></p>'), 'empty source paragraphs should be removed like upstream _prepArticle');
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
    'serializes upstream readability page wrapper and collapses emptied Medium author wrappers' => static function (TestRunner $t) use ($attributeValues, $elementChildTags): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/lazy-image-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');

        $article = (new ArticleExtractor())->extract($source, 'http://fakehost/test/page.html', true);

        $t->same(['page'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/@class'));
        $t->same(
            $elementChildTags($expected, '//div[@id="readability-page-1"]'),
            $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]'),
        );
        $t->same(
            $elementChildTags($expected, '//div[@id="readability-page-1"]/*[1]'),
            $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]/*[1]'),
        );
        $t->same(
            $attributeValues($expected, '//div[@id="readability-page-1"]/*[1]/*[1]/p/a/img/@src'),
            $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/*[1]/p/a/img/@src'),
        );
        $t->same(['p'], $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/*[1]'));
        $t->same(false, str_contains($article->contentHtml, '<div><div><div><div><p><a rel="noopener" href="http://fakehost/@vincentvallet'), 'emptied Medium byline/action wrappers should collapse before serialized fixture output');
    },
    'unwraps transparent WordPress section wrappers before block output' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Section Wrapper Cleanup"></head><body><article>'
            . '<h1>Section Wrapper Cleanup</h1>'
            . '<section class="wp-block-group alignwide"><div class="wp-block-group__inner-container">'
            . '<p>' . str_repeat('Legacy page builders often wrap migrated article copy in section shells that only carry layout classes. ', 3) . '</p>'
            . '<p>' . str_repeat('The native extractor should keep the editorial paragraphs while dropping transparent section wrappers. ', 3) . '</p>'
            . '</div></section>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Section Wrapper Cleanup', $article->title);
        $t->contains('migrated article copy', $article->text);
        $t->contains('transparent section wrappers', $blocks);
        $t->same(false, str_contains($article->contentHtml, '<section'), 'transparent source section wrappers should not survive extraction');
        $t->same(false, str_contains($blocks, '<section'), 'transparent source section wrappers should not become WordPress block markup');
        $t->same(false, str_contains($article->contentHtml, 'wp-block-group'), 'layout-only source classes should still be stripped');
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
    'preserves WordPress editorial full-width figures while dropping decorative media wrappers' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Editorial Media Import"></head><body><article>'
            . '<h1>Editorial Media Import</h1>'
            . '<p>' . str_repeat('Migration reviewers need the real editorial copy around imported media before cleanup decisions are made. ', 3) . '</p>'
            . '<div class="legacy-media-shell"><figure class="graf--figure postField--fillWidthImage"><div><img src="/uploads/editorial-full-width.jpg" alt="Editorial lab photo"></div><figcaption>Editorial lab photo by source author</figcaption></figure></div>'
            . '<p>' . str_repeat('The source page can also include layout crops that should not become WordPress media blocks. ', 3) . '</p>'
            . '<div class="theme-wide-crop"><figure><img src="/uploads/decorative-crop.jpg" alt="Decorative crop"><figcaption>Decorative crop</figcaption></figure></div>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Editorial Media Import', $article->title);
        $t->contains('/uploads/editorial-full-width.jpg', $article->contentHtml);
        $t->contains('Editorial lab photo by source author', $article->text);
        $t->contains('/uploads/editorial-full-width.jpg', $blocks);
        $t->same(false, str_contains($article->contentHtml, '/uploads/decorative-crop.jpg'), 'decorative source crop should still be removed');
        $t->same(false, str_contains($article->text, 'Decorative crop'), 'decorative crop caption should not become migrated text');
        $t->same(false, str_contains($article->contentHtml, 'postField--fillWidthImage'), 'source Medium classes should be stripped after the keep decision');
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
    'maps Mozilla replace-brs fixture paragraph breaks' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/replace-brs';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//br')), count($attributeValues($article->contentHtml, '//br')));
        $t->true(!str_contains($article->contentHtml, '<br><br>'), 'br chains should be replaced by paragraph boundaries');
        $t->same(false, str_contains($article->text, 'Temporincididunt'), 'br-chain paragraph splits should retain separator whitespace in article text');
    },
    'maps Mozilla remove-extra-brs fixture cleanup' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/remove-extra-brs';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same(0, count($attributeValues($article->contentHtml, '//br')));
    },
    'splits legacy WordPress br-separated exports before block serialization' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Line Break Migration"></head><body><article>'
            . '<h1>Line Break Migration</h1>'
            . '<p>' . str_repeat('Legacy exports keep copy in line-break paragraphs for import. ', 3) . '<br><br>'
            . 'Second migrated paragraph keeps a soft<br>line break for editorial rhythm and has enough article text for scoring.<br><br>'
            . 'Third migrated paragraph becomes a block instead of staying after raw break chains.</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Line Break Migration', $article->title);
        $t->same(3, substr_count($article->contentHtml, '<p>'));
        $t->same(3, substr_count($blocks, '<!-- wp:paragraph -->'));
        $t->contains('Second migrated paragraph keeps a soft<br>line break', $blocks);
        $t->true(!str_contains($article->contentHtml, '<br><br>'), 'hard break chains should not survive into migration markup');
        $t->true(!str_contains($blocks, '<div><p>'), 'layout div wrappers created during br cleanup should flatten before block output');
    },
    'keeps WordPress import text separated across block and table boundaries' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Import Boundary Spacing"></head><body><article>'
            . '<h1>Import Boundary Spacing</h1>'
            . '<p>Version 1.0.</p>'
            . '<h2>Release Plan</h2>'
            . '<p>' . str_repeat('Not all migration blocks start with explicit whitespace in source HTML. ', 3) . '</p>'
            . '<table><tbody><tr><td>Status complete.</td><td>Next review.</td></tr></tbody></table>'
            . '<p>' . str_repeat('Final paragraph text should remain readable in search excerpts and review logs. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(false, str_contains($article->text, '1.0.Release'), 'paragraph to heading boundaries should not concatenate in article text');
        $t->same(false, str_contains($article->text, 'complete.Next'), 'table cell boundaries should not concatenate in article text');
        $t->contains('Version 1.0. Release Plan Not all migration blocks', $article->text);
        $t->contains('Status complete. Next review.', $article->text);
        $t->contains('<!-- wp:table -->', $blocks);
    },
    'maps Mozilla replace-font-tags fixture to span markup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/replace-font-tags';
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
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//span')), count($attributeValues($article->contentHtml, '//span')));
        $t->same($attributeValues($expected, '//span/@face'), $attributeValues($article->contentHtml, '//span/@face'));
        $t->same($attributeValues($expected, '//span/@size'), $attributeValues($article->contentHtml, '//span/@size'));
        $t->same([], $attributeValues($article->contentHtml, '//font'));
    },
    'normalizes legacy WordPress font tags before block output' => static function (TestRunner $t): void {
        $html = '<html><head><title>Classic Editor Font Cleanup</title></head><body><article>'
            . '<h1>Classic Editor Font Cleanup</h1>'
            . '<p><font face="Georgia" size="4">Classic editor exports can preserve editorial emphasis</font> without keeping obsolete font elements.</p>'
            . '<p>' . str_repeat('The WordPress importer should keep the text and attributes needed for review while avoiding invalid source markup in blocks. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Classic Editor Font Cleanup', $article->title);
        $t->contains('<span face="Georgia" size="4">Classic editor exports can preserve editorial emphasis</span>', $article->contentHtml);
        $t->same(false, str_contains($article->contentHtml, '<font'), 'legacy font elements should be replaced with spans during document preparation');
        $t->same(false, str_contains($blocks, '<font'), 'legacy font elements should not survive into WordPress block output');
        $t->contains('Classic editor exports can preserve editorial emphasis', $blocks);
    },
];
