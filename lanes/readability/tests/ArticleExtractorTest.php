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
$svgSymbolSignatures = static function (string $html): array {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8" ?><main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $signatures = [];
    foreach ($dom->getElementsByTagName('svg') as $svg) {
        if (!$svg instanceof DOMElement) {
            continue;
        }

        $ids = [];
        foreach ($svg->getElementsByTagName('symbol') as $symbol) {
            if ($symbol instanceof DOMElement && trim($symbol->getAttribute('id')) !== '') {
                $ids[] = trim($symbol->getAttribute('id'));
            }
        }

        if ($ids === []) {
            continue;
        }

        sort($ids);
        $signatures[] = implode('|', $ids);
    }

    return $signatures;
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
    'serializes retained upstream blockquotes as WordPress quote blocks' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();
        $article = $extractor->extract(
            '<article><h1>Quote Import</h1>'
            . '<p>' . str_repeat('The migration keeps editorial setup copy before a retained source quote. ', 3) . '</p>'
            . '<blockquote><p>Reader-mode quote text should remain distinct from ordinary imported paragraphs.</p></blockquote>'
            . '<p>' . str_repeat('The migration keeps follow-up copy after the retained quote as normal paragraph content. ', 3) . '</p>'
            . '</article>',
        );
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:quote -->'), 'retained blockquotes should become core quote blocks');
        $t->contains('<blockquote class="wp-block-quote"><p>Reader-mode quote text should remain distinct from ordinary imported paragraphs.</p></blockquote>', $blocks);
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<blockquote"), 'retained blockquotes should not be paragraph-wrapped in WordPress output');
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

        $blocks = $extractor->toWordPressBlocks($article);
        $t->same(count($attributeValues($expected, '//ol')), substr_count($blocks, '<!-- wp:list {"ordered":true} -->'));
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<ol>"), 'retained ordered lists should not be paragraph-wrapped in WordPress output');
    },
    'serializes compact ordered editorial list imports as WordPress list blocks' => static function (TestRunner $t): void {
        $html = '<html><head><title>Migration Checklist</title></head><body><article>'
            . '<h1>Migration Checklist</h1>'
            . '<p>' . str_repeat('A migration note can keep article prose before a compact ordered pullout. ', 3) . '</p>'
            . '<ol><li><p>Keep one retained ordered item as a list block.</p></li></ol>'
            . '<p>' . str_repeat('A final paragraph after the lists should remain separate for clean WordPress block output. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:list {"ordered":true} -->'));
        $t->same(2, substr_count($blocks, '<!-- wp:paragraph -->'));
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<ol>"), 'compact ordered editorial lists should not be paragraph-wrapped');
        $t->contains('<li><p>Keep one retained ordered item as a list block.</p></li>', $blocks);
    },
    'serializes explicitly marked unordered editorial list imports as WordPress list blocks' => static function (TestRunner $t): void {
        $html = '<html><head><title>Migration Checklist</title></head><body><article>'
            . '<h1>Migration Checklist</h1>'
            . '<p>' . str_repeat('A migration note can keep article prose before a compact unordered checklist. ', 3) . '</p>'
            . '<ul data-wp-block-list="1">'
            . '<li><p>Keep the source permalink for reviewer traceability.</p></li>'
            . '<li><p>Keep the media sideload note near the imported copy.</p></li>'
            . '</ul>'
            . '<p>' . str_repeat('A final paragraph after the list should remain separate for clean WordPress block output. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:list -->'));
        $t->same(2, substr_count($blocks, '<!-- wp:paragraph -->'));
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<ul"), 'marked unordered editorial lists should not be paragraph-wrapped');
        $t->contains('<li><p>Keep the source permalink for reviewer traceability.</p></li>', $blocks);
        $t->contains('<li><p>Keep the media sideload note near the imported copy.</p></li>', $blocks);
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
    'maps Mozilla remove-extra-paragraphs fixture to nonempty WordPress paragraphs' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/remove-extra-paragraphs';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same(['div', 'div'], $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]'));
        $t->same(5, count($attributeValues($article->contentHtml, '//p')), 'only the five upstream nonempty paragraphs should survive cleanup');
        $t->same([], $attributeValues($article->contentHtml, '//p[not(normalize-space()) and not(.//img or .//embed or .//object or .//iframe)]'));
        $t->same(5, substr_count($blocks, '<!-- wp:paragraph -->'), 'empty source paragraphs should not become WordPress paragraph blocks');
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<p></p>"), 'blank paragraph markup should not be serialized as an import block');
    },
    'maps Mozilla invalid-attributes fixture while sanitizing malformed wrapper markup' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/invalid-attributes';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['div'], $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same(false, str_contains($article->contentHtml, 'data-readability-malformed-attribute-wrapper'), 'internal malformed-attribute marker should not be serialized');
        $t->same(false, str_contains($article->contentHtml, '"=""'), 'invalid source attribute syntax should be sanitized from PHP output');
        $t->same(1, substr_count($blocks, '<!-- wp:paragraph -->'), 'malformed wrapper should flatten to one clean WordPress paragraph block');
        $t->contains('Lorem ipsum dolor sit amet', $blocks);
    },
    'maps Mozilla script style and WordPress social button cleanup fixtures' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $extractor = new ArticleExtractor();

        foreach (['style-tags-removal', 'remove-script-tags', 'social-buttons'] as $name) {
            $fixture = __DIR__ . '/../fixtures/mozilla/' . $name;
            $source = (string) file_get_contents($fixture . '/source.html');
            $expected = (string) file_get_contents($fixture . '/expected.html');
            $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
            $article = $extractor->extract($source, 'http://fakehost/test/page.html');
            $blocks = $extractor->toWordPressBlocks($article);

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
            $t->same(false, str_contains($blocks, '<script'), $name . ' should not emit script tags into WordPress blocks');
            $t->same(false, str_contains($blocks, '<style'), $name . ' should not emit style tags into WordPress blocks');
            $t->same(
                count($attributeValues($expected, '//p')),
                substr_count($blocks, '<!-- wp:paragraph -->'),
                $name . ' paragraph block count should match upstream retained paragraphs',
            );
            $t->same(
                count($attributeValues($expected, '//h2')),
                substr_count($blocks, '<!-- wp:heading {"level":2} -->'),
                $name . ' heading block count should match upstream retained h2 headings',
            );
        }
    },
    'maps Mozilla comment-inside-script parser fixture without leaking script text' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/comment-inside-script-parsing';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, null, true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(5, count($attributeValues($article->contentHtml, '//p')));
        $t->same([], $attributeValues($article->contentHtml, '//script|//style'));
        $t->same(5, substr_count($blocks, '<!-- wp:paragraph -->'));
        foreach (['Silly test', 'foo.js', '<script'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'parser script/comment fragment should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'parser script/comment fragment should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla lifehacker fixture and serializes retained lists as blocks' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/lifehacker-post-comment-load';
        $url = 'http://lifehacker.com/how-to-program-your-mind-to-stop-buying-crap-you-don-t-1690268064';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, $url, true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, $url));

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
        $t->same(37, count($attributeValues($article->contentHtml, '//p')));
        $t->same(8, count($attributeValues($article->contentHtml, '//h3')));
        $t->same(16, count($attributeValues($article->contentHtml, '//li')));
        $t->same(4, substr_count($blocks, '<!-- wp:list -->'), 'retained Kinja editorial lists should become WordPress list blocks');
        $t->same(36, substr_count($blocks, '<!-- wp:paragraph -->'), 'Lifehacker paragraphs and image paragraphs should remain paragraph blocks');
        $t->same(1, substr_count($blocks, '<!-- wp:quote -->'), 'retained Lifehacker quotes should become WordPress quote blocks');
        $t->same(8, substr_count($blocks, '<!-- wp:heading'), 'Lifehacker section headings should remain reviewable');
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<ul>"), 'retained text lists should not be paragraph-wrapped');
        $t->same(true, str_contains($article->contentHtml, 'data-textannotation-id='), 'article HTML keeps upstream Kinja annotations for fixture parity');
        $t->same(false, str_contains($blocks, 'data-textannotation-id='), 'WordPress list blocks should not keep source-only Kinja annotation ids');
        foreach (['Show more comments', 'Related blogs', 'Ads by Google', 'Follow Lifehacker', 'js_post_item'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Kinja comment/navigation/ad chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Kinja comment/navigation/ad chrome should not enter WordPress blocks: ' . $fragment);
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
    'maps Mozilla social-buttons fixture by removing share widget chrome' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/social-buttons';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(5, count($attributeValues($article->contentHtml, '//p')));
        $t->same(5, substr_count($blocks, '<!-- wp:paragraph -->'));
        foreach (['share-buttons', 'Share on Facebook', 'Share on Twitter', 'mailto:', 'social-buttons'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'share widget chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'share widget chrome should not enter WordPress blocks: ' . $fragment);
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
    'preserves configured classes like upstream cleanClasses options' => static function (TestRunner $t): void {
        $source = '<html><head><title>Configured Class Preservation</title></head><body><article>'
            . '<h1>Configured Class Preservation</h1>'
            . '<p>' . str_repeat('Mozilla Readability strips source classes unless the caller explicitly preserves a focused class list. ', 3) . '</p>'
            . '<figure class="source-frame"><img src="/uploads/preserved-caption.jpg" alt="Preserved caption"><figcaption class="caption source-caption">Preserved editorial caption.</figcaption></figure>'
            . '<p class="theme-copy">' . str_repeat('The configured class survives while unrelated source theme classes are still removed. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $defaultArticle = $extractor->extract($source);
        $preservedArticle = $extractor->extract($source, null, false, ['caption']);

        $t->same(false, str_contains($defaultArticle->contentHtml, 'class="caption"'), 'classes should still be stripped by default');
        $t->contains('<figcaption class="caption">Preserved editorial caption.</figcaption>', $preservedArticle->contentHtml);
        foreach (['source-caption', 'source-frame', 'theme-copy'] as $className) {
            $t->same(false, str_contains($preservedArticle->contentHtml, $className), 'unconfigured source class should be stripped: ' . $className);
        }
    },
    'honors upstream keepClasses extraction option' => static function (TestRunner $t): void {
        $source = '<html><head><title>Keep Classes</title></head><body><article class="entry-content source-shell">'
            . '<h1>Keep Classes</h1>'
            . '<p class="lead theme-copy">' . str_repeat('Option-driven migrations sometimes need full source classes for review. ', 4) . '</p>'
            . '</article></body></html>';
        $extractor = new ArticleExtractor();

        $stripped = $extractor->extract($source);
        $kept = $extractor->extractWithOptions($source, ['keepClasses' => true]);

        $t->same(false, str_contains($stripped->contentHtml, 'theme-copy'), 'default cleanup should strip source classes');
        $t->contains('class="lead theme-copy"', $kept->contentHtml);
        $t->same(false, str_contains($kept->contentHtml, 'source-shell'), 'the selected article wrapper is still serialized as inner content');
    },
    'maps Mozilla heise fixture with caption class preservation and article promotion' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/heise';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $defaultArticle = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['caption'], $attributeValues($article->contentHtml, '//p[@class]/@class'));
        $t->same([], $attributeValues($defaultArticle->contentHtml, '//p[@class]/@class'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(false, str_contains($article->text, '7-Tage-News'), 'site navigation should not survive single-article promotion');
        $t->same(false, str_contains($article->text, 'Kommentare lesen'), 'article footer chrome should not survive single-article promotion');
        $t->same(false, str_contains($article->text, '08.04.2015 12:46'), 'leading source timestamp chrome should not survive article cleanup');
    },
    'maps Mozilla ars-1 fixture by removing figure credit-only caption chrome' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/ars-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//figcaption/@class'), $attributeValues($article->contentHtml, '//figcaption/@class'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//figcaption')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//figcaption')),
        );
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(false, str_contains($article->contentHtml, 'caption-credit'), 'credit-only caption wrapper should be removed like upstream conditional cleanup');
        $t->same(false, str_contains($article->contentHtml, 'caption-link'), 'credit-only caption link should be removed');
        $t->same(false, str_contains($article->text, 'Kevin'), 'credit-only caption text should not enter imported article text');
    },
    'maps Mozilla guardian-1 fixture with media captions and articleBody wrapper parity' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/guardian-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            $attributeValues($expected, '//div[@id="readability-page-1"]/*[1]/@data-test-id'),
            $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/@data-test-id'),
            'Guardian articleBody media root should survive inside the upstream readability-page wrapper',
        );
        $t->same(
            $attributeValues($expected, '//div[@id="readability-page-1"]/*[1]/@itemprop'),
            $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/@itemprop'),
        );
        $t->same($attributeValues($expected, '//figure/@id'), $attributeValues($article->contentHtml, '//figure/@id'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//picture/source/@srcset'), $attributeValues($article->contentHtml, '//picture/source/@srcset'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//figure/following-sibling::*[1][self::ul]//li/p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//figure/following-sibling::*[1][self::ul]//li/p')),
        );
        $t->same(14, count($attributeValues($article->contentHtml, '//figure')));
        $t->same(13, substr_count($blocks, '<!-- wp:image -->'), 'Guardian image figures should become WordPress image blocks');
        $t->contains('Hori Parata at his Pātaua farm', $blocks);
        $t->same(false, str_contains($article->text, 'The Guardian - Back to home'), 'Guardian navigation chrome should not enter article text');
        $t->same(false, str_contains($article->text, 'Support The Guardian'), 'Guardian contribution chrome should not enter article text');
        $t->same(false, str_contains($article->text, 'Eleanor Ainge Roy'), 'byline metadata should not be duplicated in article text');
    },
    'maps Mozilla nytimes-1 fixture with rich figure caption and hidden feedback cleanup' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/nytimes-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($attributeValues($expected, '//figure/@id'), $attributeValues($article->contentHtml, '//figure/@id'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//img/@data-mediaviewer-caption'), $attributeValues($article->contentHtml, '//img/@data-mediaviewer-caption'));
        $t->same($attributeValues($expected, '//img/@data-mediaviewer-credit'), $attributeValues($article->contentHtml, '//img/@data-mediaviewer-credit'));
        $t->same($attributeValues($expected, '//figcaption/@class'), $attributeValues($article->contentHtml, '//figcaption/@class'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//figcaption')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//figcaption')),
            'the NYT caption text and credit holder should retain upstream structure',
        );
        $t->same(1, substr_count($blocks, '<!-- wp:image -->'), 'NYT figure should serialize as one WordPress image block');
        $t->contains('United Nations peacekeepers at a refugee camp in Sudan on Monday', $blocks);
        $t->contains('Ashraf Shazly/Agence France-Presse', $blocks);
        $t->same(false, str_contains($article->contentHtml, 'feedback-link'), 'hidden NYT feedback prompt should be removed like upstream visible-content cleanup');
        $t->same(false, str_contains($article->text, 'We’re interested in your feedback'), 'hidden NYT feedback copy should not enter migrated article text');
    },
    'maps Mozilla nytimes-2 fixture with continuation links and hidden story interrupters' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/nytimes-2';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['story'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/article/@id'));
        $t->same($attributeValues($expected, '//figure/@id'), $attributeValues($article->contentHtml, '//figure/@id'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//img/@data-mediaviewer-credit'), $attributeValues($article->contentHtml, '//img/@data-mediaviewer-credit'));
        $t->same($attributeValues($expected, '//figcaption/@class'), $attributeValues($article->contentHtml, '//figcaption/@class'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//a[contains(., "Continue reading the main story")]/@href')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//a[contains(., "Continue reading the main story")]/@href')),
            'NYT continuation anchors should match the upstream fixture boundary',
        );
        $t->same($attributeValues($expected, '//p/@id'), $attributeValues($article->contentHtml, '//p/@id'));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(1, substr_count($blocks, '<!-- wp:image -->'), 'NYT lead figure should serialize as one WordPress image block');
        $t->same(23, substr_count($blocks, '<!-- wp:paragraph -->'), 'NYT body and continuation-link paragraphs should serialize as paragraph blocks');
        $t->same(false, str_contains($article->text, 'Advertisement'), 'hidden NYT ad interrupters should not enter article text');
        $t->same(false, str_contains($article->contentHtml, 'story-ad'), 'hidden NYT story ad containers should be removed');
        $t->same(false, str_contains($article->text, 'Justice Department Toughened Approach'), 'related story rail should not enter imported article text');
    },
    'maps Mozilla nytimes-3 fixture with figure itemid lazy images and related-card cleanup' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/nytimes-3';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(['story'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/article/@id'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//figure/@itemid'), $attributeValues($article->contentHtml, '//figure/@itemid'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//figcaption')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//figcaption')),
            'NYT captions and credit text should survive the figure itemid lazy-image repair',
        );
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(count($attributeValues($expected, '//h2')), count($attributeValues($article->contentHtml, '//h2')));
        $t->same(7, substr_count($blocks, '<!-- wp:image -->'), 'NYT fixture figures should serialize as image blocks after itemid source repair');
        $t->contains('Workers learning how to fix water main breaks', $blocks);
        $t->same(false, str_contains($article->text, 'Advertisement'), 'NYT bottom ad slug should not enter article text');
        $t->same(false, str_contains($article->text, 'Why Are New York City’s Streets Always Under Construction?'), 'related interactive card should not enter article text');
        $t->same(false, str_contains($article->contentHtml, 'nyc101-01-videoLarge'), 'related-card image should not survive as migrated media');
    },
    'maps Mozilla nytimes-4 fixture with debt article graphics and related-link cleanup' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/nytimes-4';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(['story'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/article/@id'));
        $t->same($attributeValues($expected, '//figure/@itemid'), $attributeValues($article->contentHtml, '//figure/@itemid'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//img/@srcset'), $attributeValues($article->contentHtml, '//img/@srcset'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(count($attributeValues($expected, '//h2')), count($attributeValues($article->contentHtml, '//h2')));
        $t->same(1, substr_count($blocks, '<!-- wp:image -->'), 'NYT debt fixture lead figure should serialize as one WordPress image block');
        $t->same(47, substr_count($blocks, '<!-- wp:paragraph -->'), 'NYT debt fixture article/header/print paragraphs should serialize without related cards');
        $t->contains('Interest payments on the federal debt could surpass the Defense Department budget in 2023', $blocks);
        $t->same(false, str_contains($article->text, 'Annual interest payments on the national debt'), 'ai2html debt chart leadins should not enter article text');
        $t->same(false, str_contains($article->text, 'Trump Administration Mulls a Unilateral Tax Cut'), 'NYT RelatedLinks cards should not enter article text');
        $t->same(false, str_contains($article->contentHtml, 'module=RelatedLinks'), 'NYT RelatedLinks anchors should be removed while preserving the section label');
        $t->same(false, str_contains($article->contentHtml, 'data-testid="share-tools"'), 'NYT share toolbars should not survive article cleanup');
        $t->same(false, str_contains($article->text, 'Advertisement'), 'NYT bottom ad slug should not enter article text');
    },
    'maps Mozilla nytimes-5 section front with collection card pruning' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/nytimes-5';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h2')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h2')),
        );
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h3')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h3')),
        );
        $t->same(count($attributeValues($expected, '//figure')), count($attributeValues($article->contentHtml, '//figure')));
        $t->same(count($attributeValues($expected, '//article')), count($attributeValues($article->contentHtml, '//article')));
        $t->same(count($attributeValues($expected, '//section')), count($attributeValues($article->contentHtml, '//section')));
        $t->same(count($attributeValues($expected, '//ol')), count($attributeValues($article->contentHtml, '//ol')));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->contains('El día que renuncié a los Beatles', $article->text);
        $t->contains('El auge y la caída de Elizabeth Holmes', $blocks);
        $t->same(false, str_contains($article->text, 'La muerte cambió mi vida'), 'secondary highlight rail should not enter section-front extraction');
        $t->same(false, str_contains($article->text, 'Elogio de la pereza'), 'latest-stream cards should not enter section-front extraction');
        $t->same(false, str_contains($blocks, 'Lo más reciente'), 'section-front tab navigation should not become WordPress block output');
        $t->same(false, str_contains($article->text, 'Advertisement'), 'NYT collection ad wrappers should not enter article text');
    },
    'maps Mozilla telegraph fixture with text sections and publisher media chrome cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/telegraph';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same(6, count($attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/div')));
        $t->same([], $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'Telegraph image interrupter sections should not become WordPress image blocks');
        $t->same(13, substr_count($blocks, '<!-- wp:paragraph -->'), 'Telegraph text sections should serialize as paragraph blocks');
        $t->same(false, str_contains($article->text, 'HARARE HERALD'), 'lead media credit should not enter article text');
        $t->same(false, str_contains($article->text, 'Related Topics'), 'related-topic chrome should not enter article text');
        $t->same(false, str_contains($article->text, 'Show comments'), 'comment chrome should not enter article text');
    },
    'maps Mozilla liberation-1 fixture by pruning trailing wire author source credit' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $iframeSources, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/liberation-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

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
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(5, substr_count($blocks, '<!-- wp:paragraph -->'), 'Liberation article paragraphs and retained Dailymotion iframe should serialize without the trailing source credit');
        $t->contains('dailymotion.com/embed/video/x2oikl3', $blocks);
        $t->same(false, str_contains($article->text, 'AFP'), 'trailing AFP author/source credit should not enter article text');
        $t->same(false, str_contains($blocks, 'auteur/2005-afp'), 'trailing wire-service author link should not enter WordPress blocks');
    },
    'maps Mozilla la-nacion fixture with UTF-8 BOM and article description lead' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/la-nacion';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->contains('Los pueblos indígenas reclaman', $blocks);
        $t->same(false, str_starts_with($article->text, "\xEF\xBB\xBF"), 'leading UTF-8 BOM should not become article text');
        $t->same(false, str_contains($blocks, 'MENÚ'), 'La Nacion navigation chrome should not become WordPress blocks');
        $t->same(false, str_contains($blocks, 'NO SOPORTADO'), 'hidden compatibility warning should not become WordPress blocks');
    },
    'maps Mozilla bbc-1 fixture with RDFa articleBody and unsupported video placeholders' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/bbc-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(['articleBody'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/div/@property'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h2')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h2')),
        );
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//img/@datasrc'), $attributeValues($article->contentHtml, '//img/@datasrc'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//figcaption')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//figcaption')),
        );
        $t->same(5, substr_count($blocks, '<!-- wp:image -->'), 'BBC retained figures should serialize as image blocks for migration review');
        $t->contains('President Barack Obama has admitted', $blocks);
        $t->same(false, str_contains($article->text, 'News navigation'), 'BBC navigation chrome should not enter article text');
        $t->same(false, str_contains($article->text, 'Media caption Mr Obama told'), 'unsupported BBC video placeholder captions should not enter article text');
        $t->same(false, str_contains($article->contentHtml, 'lead-video-placeholder'), 'BBC video placeholder shell should be removed after unsupported iframe cleanup');
        $t->same(false, str_contains($article->contentHtml, '<iframe'), 'unsupported BBC player iframes should not survive extraction');
    },
    'maps Mozilla cnn fixture with storytext root and widget chrome cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/cnn';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['storytext'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/div/@id'));
        $t->same(1, count($attributeValues($article->contentHtml, '//div[@id="smartassetcontainer"]')));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(1, substr_count($blocks, '<!-- wp:heading {"level":2} -->'), 'CNN story lead should become one heading block');
        $t->same(14, substr_count($blocks, '<!-- wp:paragraph -->'), 'CNN story paragraphs and retained SmartAsset label should serialize as paragraph blocks');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'CNN masthead/video/tracker media should not become image blocks');
        $t->contains('Stanford University', $blocks);
        foreach (['The priest saving LA', 'Your video will play', 'ADVERTISING', 'inRead invented by Teads', 'Disclosures', 'cnn-logo.png'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'CNN widget or masthead chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'CNN widget or masthead chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla citylab-1 fixture by pruning author RSS feed chrome' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/citylab-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://www.citylab.com/design/2019/04/neon-signage-20th-century-history/588400/', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'https://www.citylab.com/design/2019/04/neon-signage-20th-century-history/588400/'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//source/@srcset'), $attributeValues($article->contentHtml, '//source/@srcset'));
        $t->same(20, substr_count($blocks, '<!-- wp:paragraph -->'), 'CityLab article paragraphs, captions, and author bio should serialize without author feed chrome');
        $t->same(4, substr_count($blocks, '<!-- wp:heading'), 'CityLab section and author headings should remain reviewable');
        $t->same(3, substr_count($blocks, '<!-- wp:image -->'), 'CityLab editorial figures should serialize as image blocks');
        $t->contains('The Midcentury Kitchen', $blocks);
        foreach (['/feeds/author/sarah-archer/', '>Feed<'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'CityLab author RSS feed chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'CityLab author RSS feed chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla aclu fixture through Drupal panel sidebar wrappers' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/aclu';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $url = 'https://www.aclu.org/blog/privacy-technology/internet-privacy/facebook-tracking-me-even-though-im-not-facebook';
        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, $url, true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, $url));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h3')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h3')),
        );
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same([], $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(32, substr_count($blocks, '<!-- wp:paragraph -->'), 'ACLU Drupal body paragraphs should serialize without panel/sidebar chrome');
        $t->same(1, substr_count($blocks, '<!-- wp:quote -->'), 'retained ACLU quotes should become WordPress quote blocks');
        $t->same(7, substr_count($blocks, '<!-- wp:heading'), 'ACLU section headings should remain reviewable as heading blocks');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'ACLU channel hero and theme images should not become article image blocks');
        foreach (['ACLU Conference', 'Tags', 'Facebook Twitter Reddit', 'View comments', 'Read the Terms of Use', 'WEB18-Facebook-1160x768.jpg', 'Donate'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'ACLU Drupal panel or comment chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'ACLU Drupal panel or comment chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla wapo-1 fixture with inline gallery and graphic chrome cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/wapo-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(39, substr_count($blocks, '<!-- wp:paragraph -->'), 'Wapo paragraphs, video caption, graphic caption, and map image should serialize as paragraph blocks');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'inline graphics remain paragraph-contained like upstream expected HTML');
        $t->contains('Gunmen in military uniforms stormed Tunisia', $blocks);
        $t->contains('Map: Flow of foreign fighters to Syria', $blocks);
        $t->contains('tunisia600.jpg', $blocks);
        foreach (['View Photos', 'Full Screen', 'Autoplay', 'Buy Photo', 'Wait 1 second', 'foreignFighters-Jan14-GS.jpg'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Wapo gallery or linked-graphic chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Wapo gallery or linked-graphic chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla wapo-2 fixture with lead media and author bio siblings' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/wapo-2';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($elementChildTags($expected, '//div[@id="readability-page-1"]'), $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]'));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//img/@data-hi-res-src'), $attributeValues($article->contentHtml, '//img/@data-hi-res-src'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(28, substr_count($blocks, '<!-- wp:paragraph -->'), 'Wapo lead image paragraph, article paragraphs, and author bio should serialize as paragraph blocks');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'Wapo lead media remains paragraph-contained like upstream expected HTML');
        $t->contains('Israeli Prime Minister Benjamin Netanyahu reacts', $blocks);
        $t->contains('Steven Mufson covers the White House', $blocks);
        foreach (['Follow @StevenMufson', 'March 18 at 12:22 PM', 'Share on Facebook', 'Show Comments', 'Most Read'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Wapo byline/share/comment chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Wapo byline/share/comment chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla yahoo-2 fixture without treating application-name as site metadata' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/yahoo-2';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($metadata['lang'], $article->lang);
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(count($attributeValues($expected, '//h2')), count($attributeValues($article->contentHtml, '//h2')));
        $t->same(16, substr_count($blocks, '<!-- wp:paragraph -->'), 'Yahoo gallery caption and story paragraphs should serialize as paragraph blocks');
        $t->same(1, substr_count($blocks, '<!-- wp:heading'), 'Yahoo lead photo caption heading should remain reviewable as a heading block');
        $t->contains('MOSCOW (AP)', $blocks);
        $t->contains('Progress MS-04 cargo craft', $blocks);
        $t->same(false, str_contains($blocks, 'Yahoo News - Latest News & Headlines'), 'application-name/page title should not be imported as publisher text');
        foreach (['Reblog', 'Share', 'AdChoices'] as $fragment) {
            $t->same(false, str_contains($blocks, $fragment), 'Yahoo share/ad chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla yahoo-3 fixture by pruning GMA provider action chrome' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/yahoo-3';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->contains('Supreme Court', $blocks);
        $t->contains('Pizza Man Making Special Delivery', $blocks);
        foreach (['Share Your Recipe', 'Good Morning America', 'Save', 'More like this', 'Fewer like this', 'Share on Tumblr'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Yahoo GMA provider/action chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Yahoo GMA provider/action chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla yahoo-4 fixture with Japanese article-body selection' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/yahoo-4';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//p')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//p')),
        );
        $t->same([], $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(9, substr_count($blocks, '<!-- wp:paragraph -->'), 'Yahoo Japan body paragraphs should serialize without navigation/ranking chrome');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'Yahoo Japan ranking thumbnails should not become image blocks');
        foreach (['トップ 速報', 'アクセスランキング', 'ツイート', 'シェアする', '最終更新'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Yahoo Japan navigation/share/footer chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Yahoo Japan navigation/share/footer chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla buzzfeed-1 fixture by removing print image and bio chrome' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/buzzfeed-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h2')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h2')),
        );
        $t->same(2, substr_count($blocks, '<!-- wp:heading'), 'BuzzFeed lead and section headings should remain reviewable');
        $t->same(17, substr_count($blocks, '<!-- wp:paragraph -->'), 'BuzzFeed story paragraphs and inline image paragraphs should serialize without publisher bio chrome');
        foreach (['View this image', 'Check out more articles on BuzzFeed.com', 'Mark di Stefano is a breaking news reporter', 'Contact Mark Di Stefano', 'More ▾', 'Promoted by'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'BuzzFeed print/bio/share chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'BuzzFeed print/bio/share chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla lemonde-1 fixture with French articleBody and Dailymotion video' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $iframeSources, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/lemonde-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['articleBody'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/div/@id'));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h2')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h2')),
        );
        $t->same($iframeSources($expected), $iframeSources($article->contentHtml));
        $t->same(28, substr_count($blocks, '<!-- wp:paragraph -->'), 'Le Monde paragraphs should serialize for French article imports');
        $t->same(9, substr_count($blocks, '<!-- wp:heading'), 'Le Monde section headings should remain in block output');
        $t->contains('www.dailymotion.com/embed/video/x2p552m', $blocks);
        foreach (["S'abonner au Monde", 'Édition Abonnés', 'Suivre @lemondefr', 'OUTBRAIN'] as $fragment) {
            $t->same(false, str_contains($blocks, $fragment), 'Le Monde navigation/ad chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla theverge fixture with content wrapper pullquote and newsletter boundaries' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/theverge';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['content'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/@id'));
        $t->same(
            $elementChildTags($expected, '//div[@id="content"]'),
            $elementChildTags($article->contentHtml, '//div[@id="content"]'),
            'The Verge content root should preserve the pullquote wrapper and newsletter card boundary',
        );
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//img/@srcset'), $attributeValues($article->contentHtml, '//img/@srcset'));
        $t->same(count($attributeValues($expected, '//figcaption')), count($attributeValues($article->contentHtml, '//figcaption')));
        $t->same(19, substr_count($blocks, '<!-- wp:paragraph -->'), 'The Verge paragraphs, pullquote, caption, and newsletter copy should serialize as paragraphs');
        $t->same(3, substr_count($blocks, '<!-- wp:heading'), 'The Verge retained newsletter plan headings should serialize as reviewable headings');
        $t->same(1, substr_count($blocks, '<!-- wp:image -->'), 'The Verge editorial Vision Pro image should serialize as one image block');
        $t->contains('It’s easiest to judge the Vision Pro on what', $blocks);
        $t->contains('Command Line', $blocks);
        foreach (['SUBSCRIBE', 'Go to comments', 'From our sponsor', 'Advertiser Content From', 'Google confirms it just laid off around a thousand employees'] as $fragment) {
            $t->same(false, str_contains($blocks, $fragment), 'The Verge action/ad/rail chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla engadget fixture with review gallery and buy chrome cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $iframeSources, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/engadget';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $url = 'https://www.engadget.com/2017/11/03/xbox-one-x-review/';
        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, $url, true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, $url));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same($iframeSources($expected), $iframeSources($article->contentHtml));
        $t->same(32, substr_count($blocks, '<!-- wp:paragraph -->'), 'Engadget review paragraphs, score summary, gallery wrappers, and iframe paragraph should serialize without buy chrome');
        $t->same(6, substr_count($blocks, '<!-- wp:heading'), 'Engadget section and gallery headings should remain reviewable');
        $t->same(5, substr_count($blocks, '<!-- wp:image -->'), 'Engadget figure breakout media should serialize as image blocks while inline gallery images remain reviewable');
        $t->contains('$610.00', $blocks);
        $t->contains('www.youtube.com/embed/c8aFcHFu8QM', $blocks);
        foreach (['+10', '+6', 'Buy Now', 'data-index="grid"', 'thumbnail=130%2C87', '/products/microsoft/xbox/one/x'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'Engadget gallery/product chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Engadget gallery/product chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'preserves requested WordPress caption classes without keeping theme classes' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Caption Class Import"></head><body><article>'
            . '<h1>Caption Class Import</h1>'
            . '<p>' . str_repeat('Some WordPress migration pipelines need caption classes for media review while still dropping source theme classes. ', 3) . '</p>'
            . '<figure class="wp-caption aligncenter theme-frame"><img src="/uploads/captioned.jpg" alt="Captioned import"><figcaption class="wp-caption-text legacy-caption">Imported media caption</figcaption></figure>'
            . '<p>' . str_repeat('The native extractor should preserve the requested WordPress caption contract only. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, null, false, ['wp-caption', 'aligncenter', 'wp-caption-text']);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->contains('<figure class="wp-caption aligncenter">', $article->contentHtml);
        $t->contains('<figcaption class="wp-caption-text">Imported media caption</figcaption>', $article->contentHtml);
        $t->same(1, substr_count($blocks, '<!-- wp:image -->'), 'retained image figures should serialize as image blocks');
        $t->same(false, str_contains($blocks, '<!-- wp:paragraph -->' . "\n" . '<figure'), 'media figures should not be serialized as paragraph blocks');
        $t->contains('class="wp-caption aligncenter"', $blocks);
        $t->contains('<figcaption class="wp-caption-text">Imported media caption</figcaption>', $blocks);
        $t->same(false, str_contains($article->contentHtml, 'theme-frame'), 'source theme figure class should not be preserved');
        $t->same(false, str_contains($article->contentHtml, 'legacy-caption'), 'source theme caption class should not be preserved');
    },
    'removes WordPress image credit-only caption wrappers before block output' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Credit Caption Import"></head><body><article>'
            . '<h1>Credit Caption Import</h1>'
            . '<p>' . str_repeat('A WordPress import should keep editorial media while dropping source-only photo credit links from captions. ', 3) . '</p>'
            . '<figure class="wp-caption source-frame"><img src="/uploads/server-crash.jpg" alt="Imported media"><figcaption class="wp-caption-text"><div class="caption-credit"><a class="caption-link" href="https://source.example/credit">Source Photographer</a></div></figcaption></figure>'
            . '<p>' . str_repeat('The resulting blocks remain focused on portable article copy and reviewable media. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, null, false, ['wp-caption', 'wp-caption-text']);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Credit Caption Import', $article->title);
        $t->contains('<figure class="wp-caption">', $article->contentHtml);
        $t->contains('<figcaption class="wp-caption-text"></figcaption>', $article->contentHtml);
        $t->contains('/uploads/server-crash.jpg', $blocks);
        $t->same(false, str_contains($article->contentHtml, 'caption-credit'), 'source credit wrapper should be removed');
        $t->same(false, str_contains($article->contentHtml, 'caption-link'), 'source credit link class should be removed');
        $t->same(false, str_contains($article->text, 'Source Photographer'), 'source credit text should not become article text');
        $t->same(false, str_contains($blocks, 'Source Photographer'), 'source credit text should not become a WordPress block');
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
    'maps Mozilla clean-links fixture popup links and whitespace-trimmed URIs' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
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
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));

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
    'trims selected-root nonbreaking whitespace from classic WordPress exports' => static function (TestRunner $t): void {
        $nbsp = html_entity_decode('&nbsp;', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $source = '<html><head><title>Classic NBSP Export</title></head><body>&nbsp;&nbsp;'
            . '<table><tbody><tr><td>'
            . '<h1>Classic NBSP Export</h1>'
            . '<p>' . str_repeat('Classic WordPress exports sometimes leave nonbreaking layout padding before the article table. ', 3) . '</p>'
            . '<p>This paragraph keeps&nbsp;internal nonbreaking space while the selected root drops padding-only text nodes.</p>'
            . '</td></tr></tbody></table>&nbsp;</body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Classic NBSP Export', $article->title);
        $t->same(false, str_starts_with($article->text, $nbsp), 'selected-root NBSP padding should not lead article text');
        $t->same(false, str_ends_with($article->text, $nbsp), 'selected-root NBSP padding should not trail article text');
        $t->same(false, str_starts_with($article->contentHtml, $nbsp), 'serialized content should start with article markup');
        $t->contains('keeps' . $nbsp . 'internal nonbreaking space', $article->text);
        $t->contains('<!-- wp:paragraph -->', $blocks);
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
    'removes trailing WordPress wire source credits without losing metadata bylines' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Wire Credit Cleanup"><meta name="author" content="Editorial Desk"></head><body><article>'
            . '<h1>Wire Credit Cleanup</h1>'
            . '<p>' . str_repeat('A WordPress importer should keep the local editorial byline as metadata while dropping a syndicated source credit appended after the story. ', 3) . '</p>'
            . '<p>' . str_repeat('The resulting blocks should contain only portable article copy and reviewable media, not the publisher credit link. ', 3) . '</p>'
            . '<div class="source-credit"><span itemprop="author creator"><a href="/author/afp">AFP</a></span></div>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Wire Credit Cleanup', $article->title);
        $t->same('Editorial Desk', $article->byline);
        $t->contains('syndicated source credit appended after the story', $blocks);
        $t->same(2, substr_count($blocks, '<!-- wp:paragraph -->'), 'source credit should not add a third paragraph block');
        $t->same(false, str_contains($article->text, 'AFP'), 'trailing wire credit text should be removed from article text');
        $t->same(false, str_contains($blocks, '/author/afp'), 'trailing wire credit link should be removed before block output');
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
    'maps Mozilla article-author-tag fixture with Atlas Obscura article body root' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/article-author-tag';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['article-body'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/section/@id'));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(count($attributeValues($expected, '//hr')), count($attributeValues($article->contentHtml, '//hr')));
        $t->same(29, substr_count($blocks, '<!-- wp:paragraph -->'), 'Atlas Obscura article paragraphs should serialize without source header chrome');
        $t->same(1, substr_count($blocks, '<!-- wp:quote -->'), 'retained Atlas Obscura quotes should become WordPress quote blocks');
        $t->same(2, substr_count($blocks, '<!-- wp:separator -->'), 'Atlas Obscura editorial hr separators should become WordPress separator blocks');
        $t->same(6, count($attributeValues($article->contentHtml, '//img/@src')), 'Atlas Obscura image payloads should remain available for media import review');
        foreach (['ArticleHeader__byline', 'July 10, 2015', 'Atlas Obscura Trips'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'Atlas Obscura header/navigation chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Atlas Obscura header/navigation chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla 002 fixture with Mozilla Hacks code blocks and content-main root' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/002';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['content-main'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/div/@id'));
        $t->same(['article'], $attributeValues($article->contentHtml, '//div[@id="content-main"]/article/@role'));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(count($attributeValues($expected, '//pre')), count($attributeValues($article->contentHtml, '//pre')));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(17, substr_count($blocks, '<!-- wp:code -->'), 'Mozilla Hacks syntax examples should become WordPress code blocks');
        $t->contains('fetch<span>(</span><span>"/data.json"</span>', $blocks);
        foreach (['Older Article', '2 comments', 'Read more articles by Nikhil Marathe', 'Except where otherwise noted'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Mozilla Hacks navigation/comment/sidebar chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Mozilla Hacks navigation/comment/sidebar chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla google-sre-book-1 fixture by promoting the chapter main root' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/google-sre-book-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['maia-main'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/section/@id'));
        $t->same(['main'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/section/@role'));
        $t->same(['chapter'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/section/@data-type'));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(count($attributeValues($expected, '//h2')), count($attributeValues($article->contentHtml, '//h2')));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(1, count($attributeValues($article->contentHtml, '//table')));
        $t->same([], $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(53, substr_count($blocks, '<!-- wp:paragraph -->'), 'Google SRE chapter prose should serialize without book navigation paragraphs');
        $t->same(10, substr_count($blocks, '<!-- wp:heading'), 'Google SRE chapter section headings should remain reviewable');
        $t->same(1, substr_count($blocks, '<!-- wp:table -->'), 'Google SRE symptom/cause table should become a WordPress table block');
        $t->contains('The Four Golden Signals', $blocks);
        $t->contains('Private content is world-readable', $blocks);
        foreach (['Table of Contents', 'Part I - Introduction', 'Chapter 6 - Monitoring Distributed Systems', 'lh3.googleusercontent.com'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Google SRE book navigation/header chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Google SRE book navigation/header chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla toc-missing fixture while pruning interactive editor CTA chrome' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/toc-missing';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://hakibenita.com/sql-anomaly-detection', true);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(1, count($attributeValues($article->contentHtml, '//details')));
        $t->same(['Table of Contents'], array_map($normalizedText, $attributeValues($article->contentHtml, '//details/summary')));
        $t->same(26, count($attributeValues($article->contentHtml, '//pre')), 'SQL examples should remain available for code-block migration');
        $t->same(false, str_contains($article->contentHtml, 'To follow along with the article'), 'interactive editor CTA body should not survive upstream fixture extraction');
        $t->same(false, str_contains($article->contentHtml, 'interactive editor on PopSQL'), 'interactive editor CTA link text should not survive article cleanup');
    },
    'keeps technical article TOCs while dropping external editor CTAs before WordPress blocks' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/toc-missing/source.html');

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://hakibenita.com/sql-anomaly-detection');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Simple Anomaly Detection Using Plain SQL', $article->title);
        $t->contains('Table of Contents', $blocks);
        $t->contains('Detecting Anomalies', $blocks);
        $t->same(96, substr_count($blocks, '<!-- wp:paragraph -->'), 'technical article prose and the retained TOC should serialize as reviewable paragraph blocks');
        $t->same(18, substr_count($blocks, '<!-- wp:heading'), 'technical article section headings should remain reviewable');
        $t->same(26, substr_count($blocks, '<!-- wp:code -->'), 'SQL snippets should become WordPress code blocks');
        $t->same(false, str_contains($blocks, 'To follow along with the article'), 'external editor CTA copy should not become WordPress blocks');
        $t->same(false, str_contains($blocks, 'interactive editor on PopSQL'), 'external editor CTA link text should not become WordPress blocks');
    },
    'maps Mozilla wikipedia-4 fixture with list table and category chrome cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/wikipedia-4';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(count($attributeValues($expected, '//h2')), count($attributeValues($article->contentHtml, '//h2')));
        $t->same(count($attributeValues($expected, '//table//tr')), count($attributeValues($article->contentHtml, '//table//tr')));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same([], $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(6, substr_count($blocks, '<!-- wp:paragraph -->'), 'Wikipedia lead copy, see-also list, references list, and table cell paragraphs should remain reviewable');
        $t->same(2, substr_count($blocks, '<!-- wp:heading'), 'Wikipedia See also and References headings should remain reviewable');
        $t->same(1, substr_count($blocks, '<!-- wp:table -->'), 'Wikipedia sortable film list should become one WordPress table block');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'Wikipedia tracking pixels and portal icons should not become image blocks');
        $t->contains('List of films featuring time loops', $article->title);
        $t->contains('The list provides the names and brief synopses of films', $article->text);
        $t->contains('Groundhog Day', $blocks);
        foreach (['dynamic list', 'Special:CentralAutoLogin', 'Categories:', 'Time loop films', 'Film portal'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'Wikipedia maintenance/category chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Wikipedia maintenance/category chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla wikipedia fixture with article hatnote and shell cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/wikipedia';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->same(count($attributeValues($expected, '//h2')), count($attributeValues($article->contentHtml, '//h2')));
        $t->same(count($attributeValues($expected, '//h3')), count($attributeValues($article->contentHtml, '//h3')));
        $t->same(count($attributeValues($expected, '//table')), count($attributeValues($article->contentHtml, '//table')));
        $t->same(count($attributeValues($expected, '//table//tr')), count($attributeValues($article->contentHtml, '//table//tr')));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(73, substr_count($blocks, '<!-- wp:paragraph -->'), 'Wikipedia long-form article copy, lists, and references should remain paragraph-reviewable');
        $t->same(1, substr_count($blocks, '<!-- wp:quote -->'), 'retained Wikipedia quotes should become WordPress quote blocks');
        $t->same(37, substr_count($blocks, '<!-- wp:heading'), 'Wikipedia article and table-of-contents headings should remain reviewable as heading blocks');
        $t->same(2, substr_count($blocks, '<!-- wp:table -->'), 'Wikipedia infobox and release table should become WordPress table blocks');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'Wikipedia table-contained images should stay inside table review output instead of separate image blocks');
        foreach (['From Wikipedia, the free encyclopedia', 'Jump to navigation', 'Main article:', 'Special:CentralAutoLogin', 'Categories:'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'MediaWiki article shell or hatnote chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'MediaWiki article shell or hatnote chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla wikipedia-2 country fixture without status indicator chrome' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/wikipedia-2';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(count($attributeValues($expected, '//h2')), count($attributeValues($article->contentHtml, '//h2')));
        $t->same(count($attributeValues($expected, '//h3')), count($attributeValues($article->contentHtml, '//h3')));
        $t->same(count($attributeValues($expected, '//table')), count($attributeValues($article->contentHtml, '//table')));
        $t->same([], array_values(array_diff($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'))));
        $t->same(148, substr_count($blocks, '<!-- wp:paragraph -->'), 'Wikipedia country prose should remain reviewable as paragraph blocks while definition lists use HTML blocks');
        $t->same(3, substr_count($blocks, '<!-- wp:html -->'), 'Wikipedia country definition lists should become HTML review blocks');
        $t->same(30, substr_count($blocks, '<!-- wp:heading'), 'Wikipedia country sections should remain reviewable as heading blocks');
        $t->same(4, substr_count($blocks, '<!-- wp:table -->'), 'Wikipedia country infobox and data tables should become WordPress table blocks');
        $t->same(0, substr_count($blocks, '<!-- wp:image -->'), 'Wikipedia table-contained country images should stay in table review output');
        $t->contains('Jacinda Ardern', $blocks);
        $t->contains('Mount Cook', $blocks);
        foreach (['This is a good article', 'Page semi-protected', 'From Wikipedia, the free encyclopedia', 'Jump to navigation', 'Special:CentralAutoLogin', 'Categories:'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'MediaWiki country status/shell chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'MediaWiki country status/shell chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla wikipedia-3 fixture with math article shell cleanup' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/wikipedia-3';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

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
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h3')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h3')),
        );
        $t->same(count($attributeValues($expected, '//table')), count($attributeValues($article->contentHtml, '//table')));
        $t->same(count($attributeValues($expected, '//table//tr')), count($attributeValues($article->contentHtml, '//table//tr')));
        $t->same([], array_values(array_diff($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'))));
        $t->same(62, count($attributeValues($expected, '//img/@src')), 'Mozilla wikipedia-3 expected math/editorial images should be fixture-backed');
        $t->same(44, substr_count($blocks, '<!-- wp:paragraph -->'), 'Wikipedia math prose should remain paragraph-reviewable while definition lists use HTML blocks');
        $t->same(18, substr_count($blocks, '<!-- wp:html -->'), 'Wikipedia math definition lists should become HTML review blocks');
        $t->same(12, substr_count($blocks, '<!-- wp:heading'), 'Wikipedia article sections should remain reviewable as heading blocks');
        $t->same(1, substr_count($blocks, '<!-- wp:table -->'), 'Wikipedia maintenance expansion table should stay available for review');
        foreach (['From Wikipedia, the free encyclopedia', 'Jump to navigation', 'Jump to search', 'Special:CentralAutoLogin', 'Categories:'] as $fragment) {
            $t->same(false, str_contains($article->contentHtml, $fragment), 'MediaWiki shell chrome should not enter article HTML: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'MediaWiki shell chrome should not enter WordPress blocks: ' . $fragment);
        }
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
    'maps Mozilla v8-blog fixture without generic time datetime published metadata' => static function (TestRunner $t) use ($normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/v8-blog';
        $source = (string) file_get_contents($fixture . '/source.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');

        $t->contains('datetime="2019-11-21"', $source);
        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime, 'Mozilla does not use visible time datetime nodes for publishedTime metadata');
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
    },
    'does not turn visible WordPress article times into published metadata without upstream fields' => static function (TestRunner $t): void {
        $html = '<html><head><meta property="og:title" content="Visible Date Boundary"></head><body><article>'
            . '<header><h1>Visible Date Boundary</h1><p><time datetime="2024-04-09T12:00:00+00:00" itemprop="datePublished">April 9, 2024</time></p></header>'
            . '<div itemprop="articleBody">'
            . '<p>' . str_repeat('A WordPress importer should not promote visible template dates to post metadata unless upstream Readability metadata fields support it. ', 3) . '</p>'
            . '<p>' . str_repeat('The editorial body remains available for block output while the date boundary stays explicit for the import layer. ', 3) . '</p>'
            . '</div>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($html);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Visible Date Boundary', $article->title);
        $t->same(null, $article->publishedTime);
        $t->contains('should not promote visible template dates', $blocks);
        $t->same(false, str_contains($blocks, 'April 9, 2024'), 'visible template date should not become WordPress block content when articleBody wins');
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
    'maps Mozilla mozilla-2 fixture expected HTML and retained developer content' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/mozilla-2';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
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
        $t->same($fixtureText($expected), $contentText);
        $t->contains('Get to know the features that make it the most complete browser for building the Web.', $contentText);
        $t->contains('Features and tools', $contentText);
        $t->true(!str_contains($contentText, 'Interested in having a direct impact'), 'head comment text should not enter content');
    },
    'maps Mozilla tumblr fixture by promoting the single post over theme sidebars' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/tumblr';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->contains('Minecraft 1.8 - The Bountiful Update', $blocks);
        $t->contains('Removed Herobrine', $blocks);
        $t->same(1, substr_count($blocks, '<!-- wp:heading'), 'Tumblr post title should serialize as one heading block');
        $t->same(1, substr_count($blocks, '<!-- wp:paragraph -->'), 'Tumblr release notes should serialize as one paragraph block with br boundaries');
        foreach (['Minecraft News', 'Powered by Tumblr', 'Official links:', 'Community links:'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'Tumblr theme chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'Tumblr theme chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'maps Mozilla mozilla-1 fixture with main content wrapper and sync CTA cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/mozilla-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(['main-content'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/@id'));
        $t->same(['main'], $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/@role'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same(
            array_map($normalizedText, $attributeValues($expected, '//h2|//h3|//li')),
            array_map($normalizedText, $attributeValues($article->contentHtml, '//h2|//h3|//li')),
        );
        $t->contains('Firefox', $blocks);
        $t->same(false, str_contains($article->text, 'Keep your Firefox in Sync'), 'trailing Mozilla Sync CTA should not enter article text');
        $t->same(false, str_contains($article->contentHtml, 'id="sync"'), 'trailing Mozilla Sync CTA wrapper should not survive upstream fixture extraction');
        $t->same(false, str_contains($blocks, 'sync-button'), 'trailing Mozilla Sync CTA should not become WordPress block output');
    },
    'maps Mozilla firefox-nightly-blog fixture with article-header rel author byline' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/firefox-nightly-blog';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true, ['caption']);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same(array_slice($attributeValues($expected, '//img/@src'), 0, 3), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(['Highlights', 'Friends of the Firefox team', 'Project Updates'], array_map($normalizedText, $attributeValues($article->contentHtml, '//h3')));
        $t->contains('Firefox now supports printing non-contiguous page ranges', $article->text);
        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->same(false, str_contains($article->text, '2 comments on'), 'WordPress comment threads should stay out of imported article text');
        $t->same(false, str_contains($article->text, 'Download Firefox Nightly'), 'site download CTA should stay out of imported article text');
        $t->same(false, str_contains($blocks, 'More articles in'), 'related article sidebar should not become WordPress blocks');
    },
    'maps Mozilla medicalnewstoday fixture with byline inside site header wrapper' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/medicalnewstoday';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same(false, str_contains($article->text, 'Ana Sandoiu'), 'article-scoped byline should become metadata, not article text');
        $t->same(false, str_contains($blocks, 'Ana Sandoiu'), 'article-scoped byline should not become a WordPress paragraph block');
        $t->same(false, str_contains($blocks, 'Thank you for supporting Medical News Today'), 'publisher ad/history chrome should not become WordPress blocks');
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

        $blocks = $extractor->toWordPressBlocks($article);
        $t->same(3, substr_count($blocks, '<!-- wp:html -->'), 'standalone upstream video iframes should become raw HTML blocks for review');
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<iframe"), 'standalone retained iframes should not be paragraph-wrapped');
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
    'honors upstream custom allowed video regex extraction option' => static function (TestRunner $t): void {
        $source = '<article>'
            . '<h1>Custom Video Cleanup</h1>'
            . '<p>' . str_repeat('Publisher-specific embeds can be editorial media in WordPress migrations. ', 5) . '</p>'
            . '<iframe src="https://video.example.test/embed/123"></iframe>'
            . '<iframe src="https://widgets.example.test/ad"></iframe>'
            . '</article>';
        $extractor = new ArticleExtractor();

        $default = $extractor->extractWithOptions($source);
        $custom = $extractor->extractWithOptions($source, ['charThreshold' => 20, 'allowedVideoRegex' => '~//video\.example\.test/embed/~']);

        $t->same(false, str_contains($default->contentHtml, '<iframe'), 'default video whitelist should remove unknown iframe hosts');
        $t->contains('https://video.example.test/embed/123', $custom->contentHtml);
        $t->same(false, str_contains($custom->contentHtml, 'widgets.example.test'), 'custom regex should not keep unrelated widgets');

        $blocks = $extractor->toWordPressBlocks($custom);
        $t->same(1, substr_count($blocks, '<!-- wp:html -->'), 'trusted custom video iframes should serialize as reviewable HTML blocks');
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<iframe"), 'trusted custom video iframes should not be paragraph-wrapped');
    },
    'honors upstream maxElemsToParse extraction option' => static function (TestRunner $t): void {
        $extractor = new ArticleExtractor();

        try {
            $extractor->extractWithOptions('<html><div>yo</div></html>', ['maxElemsToParse' => 1]);
        } catch (RuntimeException $exception) {
            $t->contains('Aborting parsing document; 2 elements found', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected maxElemsToParse to abort oversized document parsing');
    },
    'honors upstream charThreshold retry with the longest nonempty attempt' => static function (TestRunner $t): void {
        $source = '<html><head><title>Threshold Retry Import</title></head><body>'
            . '<div class="comment"><p>Legacy imports sometimes wrap short editorial copy in containers whose classes look like comment chrome to the first Readability pass.</p></div>'
            . '</body></html>';
        $extractor = new ArticleExtractor();

        $strictFirstPass = $extractor->extract($source);
        $article = $extractor->extractWithOptions($source, ['charThreshold' => 1000]);

        $t->same(false, str_contains($strictFirstPass->text, 'Legacy imports sometimes wrap'), 'the normal strict pass should strip unlikely comment containers');
        if (!$article instanceof \PortLibs\Readability\Article) {
            throw new RuntimeException('Expected charThreshold retry to return the longest nonempty attempt');
        }
        $t->contains('Legacy imports sometimes wrap short editorial copy', $article->text);
        $t->same(false, str_contains($article->contentHtml, 'class="comment"'), 'classes should still be cleaned after the relaxed retry');
        $t->same('Threshold Retry Import', $article->title);
    },
    'returns null for chrome-only WordPress imports after charThreshold retries' => static function (TestRunner $t): void {
        $source = '<html><head><title>Chrome Only Import</title></head><body>'
            . '<div class="comment"><span></span></div><nav><a href="/">Home</a></nav>'
            . '</body></html>';

        $article = (new ArticleExtractor())->extractWithOptions($source, ['charThreshold' => 50]);

        $t->same(null, $article, 'empty extraction attempts should not produce a blank WordPress post candidate');
    },
    'retries threshold extraction without class weighting before returning a short candidate' => static function (TestRunner $t): void {
        $source = '<html><head><title>Class Weight Rearm Import</title></head><body>'
            . '<div class="storytext"><p>Short teaser copy.</p></div>'
            . '<article class="comment"><h1>Class Weight Rearm Import</h1>'
            . '<p>' . str_repeat('The legacy theme labels the real migrated article as comments even though it contains the complete editorial body. ', 7) . '</p>'
            . '<p>' . str_repeat('A threshold rearm should disable class weighting after stricter attempts and recover the longer article for WordPress review. ', 7) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $strict = $extractor->extract($source);
        $article = $extractor->extractWithOptions($source, ['charThreshold' => 5000]);

        if (!$strict instanceof \PortLibs\Readability\Article || !$article instanceof \PortLibs\Readability\Article) {
            throw new RuntimeException('Expected threshold retry attempts to return article candidates');
        }

        $t->same('Short teaser copy.', $strict->text, 'the first weighted pass demonstrates the short candidate boundary');
        $t->contains('complete editorial body', $article->text);
        $t->contains('recover the longer article', $article->text);
        $t->same(false, str_contains($article->text, 'Short teaser copy'), 'the relaxed class-weight retry should not keep the teaser wrapper');

        $blocks = $extractor->toWordPressBlocks($article);
        $t->contains('<!-- wp:paragraph -->', $blocks);
        $t->same(false, str_contains($blocks, 'Short teaser copy'), 'WordPress block output should use the recovered article body');
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
    'maps Mozilla cnet svg sprite dedupe fixture' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText, $svgSymbolSignatures): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/cnet-svg-classes';
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
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->same($svgSymbolSignatures($expected), $svgSymbolSignatures($article->contentHtml));
        $t->same(count($attributeValues($expected, '//svg')), count($attributeValues($article->contentHtml, '//svg')));
    },
    'deduplicates repeated WordPress inline SVG symbol sprites before block output' => static function (TestRunner $t): void {
        $sprite = '<svg class="theme-icons"><symbol id="wp-play" viewBox="0 0 10 10"><path d="M0 0 L10 5 L0 10 Z"></path></symbol></svg>';
        $source = '<html><head><meta property="og:title" content="SVG Sprite Import"></head><body><article>'
            . '<h1>SVG Sprite Import</h1>'
            . '<p>' . str_repeat('Legacy WordPress themes can embed repeated inline SVG symbol sprites inside exported post content. ', 3) . '</p>'
            . $sprite
            . '<p>' . str_repeat('The migration should keep one reusable sprite while preventing duplicate icon blocks in the imported article. ', 3) . '</p>'
            . '<svg id="editorial-diagram" viewBox="0 0 20 20"><path d="M0 0 L20 20"></path></svg>'
            . $sprite
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('SVG Sprite Import', $article->title);
        $t->same(1, substr_count($article->contentHtml, '<symbol id="wp-play"'), 'duplicate symbol sprites should be removed from article HTML');
        $t->same(1, substr_count($blocks, '<symbol id="wp-play"'), 'duplicate symbol sprites should not become duplicate WordPress blocks');
        $t->contains('id="editorial-diagram"', $article->contentHtml);
        $t->contains('preventing duplicate icon blocks', $blocks);
    },
    'maps Mozilla keep-images fixture full-width editorial media retention' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $imageAttributeRows, $normalizedText): void {
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
        $t->same(
            $elementChildTags($expected, '//div[@id="readability-page-1"]'),
            $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]'),
            'the upstream readability-page wrapper should keep the named Medium section boundary',
        );
        $t->same(
            $attributeValues($expected, '//div[@id="readability-page-1"]/*[1]/@name'),
            $attributeValues($article->contentHtml, '//div[@id="readability-page-1"]/*[1]/@name'),
            'the copied Medium section name should survive oracle serialization',
        );
        $t->same(
            $elementChildTags($expected, '//div[@id="readability-page-1"]/*[1]'),
            $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]/*[1]'),
            'the named Medium section wrapper should retain its child section layout divs',
        );
        $t->same($attributeValues($expected, '//img[@src]/@src'), $attributeValues($article->contentHtml, '//img[@src]/@src'));
        $t->same(16, count($imageAttributeRows($article->contentHtml)), 'all expected image payloads should remain');
        $t->same(count($attributeValues($expected, '//figure')), count($attributeValues($article->contentHtml, '//figure')));
        $t->same(count($attributeValues($expected, '//p')), count($attributeValues($article->contentHtml, '//p')));
        $t->contains('Cristina Gil Lladanosa, at the Barcelona testing lab', $article->text);
        $t->contains('Photo by Joan Bardeletti', $article->text);
        $t->same(false, str_contains($article->text, 'Ready to publish?'), 'Medium editor chrome should not survive the keep-images fixture extraction');
    },
    'preserves named Medium section wrappers for oracle output while flattening WordPress blocks' => static function (TestRunner $t) use ($attributeValues, $elementChildTags): void {
        $source = '<html><head><meta property="og:title" content="Medium Section Import"></head><body>'
            . '<article><div class="postField postField--body"><section name="wpsec" class="section--first section--last"><div class="section-content">'
            . '<div><p>' . str_repeat('Migration reviewers need source section boundaries when comparing upstream oracle output. ', 3) . '</p></div>'
            . '<div><figure class="postField--fillWidthImage"><div><img src="/uploads/section-photo.jpg" alt="Section photo"></div><figcaption>Section photo</figcaption></figure></div>'
            . '<div><p>' . str_repeat('The block serializer should still avoid importing opaque Medium section shells. ', 3) . '</p></div>'
            . '</div></section></div></article>'
            . '<aside>Related source chrome should not be selected for import.</aside>'
            . '</body></html>';

        $extractor = new ArticleExtractor();
        $oracleArticle = $extractor->extract($source, 'https://example.com/imports/post.html', true);
        $wordpressArticle = $extractor->extract($source, 'https://example.com/imports/post.html');
        $blocks = $extractor->toWordPressBlocks($wordpressArticle);

        $t->same(['div'], $elementChildTags($oracleArticle->contentHtml, '//div[@id="readability-page-1"]'));
        $t->same(['wpsec'], $attributeValues($oracleArticle->contentHtml, '//div[@id="readability-page-1"]/*[1]/@name'));
        $t->contains('Section photo', $blocks);
        $t->contains('src="https://example.com/uploads/section-photo.jpg"', $blocks);
        $t->same(false, str_contains($blocks, 'name="wpsec"'), 'WordPress blocks should flatten source section boundaries');
        $t->same(false, str_contains($blocks, 'section-content'), 'source Medium layout classes should remain stripped');
        $t->same(false, str_contains($wordpressArticle->text, 'Related source chrome'), 'surrounding source chrome should not enter the migrated article');
    },
    'maps Mozilla medium-1 empty heading cleanup and boundary spacing' => static function (TestRunner $t) use ($attributeValues, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/medium-1';
        $source = (string) file_get_contents($fixture . '/source.html');
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
        $t->same([], $attributeValues($article->contentHtml, '//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6][not(normalize-space()) and not(.//img or .//embed or .//object or .//iframe)]'));
        $t->same(false, str_contains($article->text, 'JournalismWe'), 'empty Medium heading wrappers should not concatenate heading and paragraph text');
        $t->true(str_starts_with($article->text, 'Better Student Journalism We pushed out the first version'), 'article text should retain a boundary between the lead heading and paragraph');
    },
    'removes empty imported headings before WordPress paragraph serialization' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Empty Heading Import"></head><body><article>'
            . '<h1>Empty Heading Import</h1>'
            . '<h4><br></h4>'
            . '<p>' . str_repeat('A WordPress migration should not keep visual spacer headings from the source editor. ', 3) . '</p>'
            . '<h2>Review Notes</h2>'
            . '<h3> </h3>'
            . '<p>' . str_repeat('The resulting blocks should keep readable boundaries around real headings and paragraphs. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Empty Heading Import', $article->title);
        $t->same(false, str_contains($article->contentHtml, '<h4'), 'empty spacer heading should be removed before block output');
        $t->same(false, str_contains($article->contentHtml, '<h3'), 'whitespace-only spacer heading should be removed before block output');
        $t->same(false, str_contains($article->text, 'ImportA WordPress'), 'title-adjacent spacer heading should not collapse text boundaries');
        $t->contains('<!-- wp:heading {"level":2} -->', $blocks);
        $t->contains('Review Notes', $blocks);
    },
    'maps Mozilla medium-2 trailing syndication footer cleanup' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/medium-2';
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
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same($imageAttributeRows($expected), $imageAttributeRows($article->contentHtml));
        $t->same([], $attributeValues($article->contentHtml, '//*[contains(., "Originally published at")]'));
        $t->same([], $attributeValues($article->contentHtml, '//section'));
    },
    'maps Mozilla medium-3 hr page breaks to readability page sections' => static function (TestRunner $t) use ($attributeValues, $elementChildTags, $fixtureText, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/medium-3';
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
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(
            $elementChildTags($expected, '//div[@id="readability-page-1"]'),
            $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]'),
            'hr-separated Medium article pages should become sibling readability-page sections',
        );
        $t->same(
            $elementChildTags($expected, '//div[@id="readability-page-1"]/*[1]'),
            $elementChildTags($article->contentHtml, '//div[@id="readability-page-1"]/*[1]'),
            'the first Medium page should keep its avatar, lead paragraph, quote, and article paragraphs',
        );
        $t->same($attributeValues($expected, '//a[@href]/@href'), $attributeValues($article->contentHtml, '//a[@href]/@href'));
        $t->same($imageAttributeRows($expected), $imageAttributeRows($article->contentHtml));
        $t->same(count($attributeValues($expected, '//blockquote')), count($attributeValues($article->contentHtml, '//blockquote')));
        $t->same(count($attributeValues($expected, '//ol')), count($attributeValues($article->contentHtml, '//ol')));
        $t->same(count($attributeValues($expected, '//li')), count($attributeValues($article->contentHtml, '//li')));
        $t->same([], $attributeValues($article->contentHtml, '//hr'));
    },
    'removes Medium page break separators before WordPress block output' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Paged Medium Import"></head><body><article>'
            . '<div class="postField postField--body">'
            . '<div><p>' . str_repeat('The first imported page should remain editorial paragraph content without a synthetic separator block. ', 3) . '</p></div>'
            . '<hr>'
            . '<div><p>' . str_repeat('The second imported page should follow as ordinary WordPress paragraph content. ', 3) . '</p></div>'
            . '</div></article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://example.com/imports/paged-medium');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Paged Medium Import', $article->title);
        $t->contains('first imported page', $blocks);
        $t->contains('second imported page', $blocks);
        $t->same(false, str_contains($article->contentHtml, '<hr'), 'Medium source page separators should be removed during extraction');
        $t->same(false, str_contains($blocks, '<hr'), 'source page separators should not become WordPress paragraph blocks');
    },
    'removes trailing WordPress syndication source notes before block output' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Syndicated Review"></head><body><article>'
            . '<div class="entry-content">'
            . '<p>' . str_repeat('The importer should retain the syndicated article body while dropping source-platform footer notes. ', 3) . '</p>'
            . '<p>' . str_repeat('This keeps the migrated post focused on editorial content and avoids adding a stale original-source note as a paragraph block. ', 2) . '</p>'
            . '<section class="medium-source-note"><p><em>Originally published at <a href="https://old.example/post">old.example</a> on November 18, 2011. Help the word out. Recommend this article to your readers.</em></p></section>'
            . '</div></article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://example.com/imported/syndicated-review');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Syndicated Review', $article->title);
        $t->contains('retain the syndicated article body', $blocks);
        $t->same(false, str_contains($article->text, 'Originally published at'), 'source-platform syndication footer should not survive extraction');
        $t->same(false, str_contains($blocks, 'old.example'), 'source-platform syndication links should not become WordPress paragraph blocks');
    },
    'maps Mozilla simplyfound-1 fixture by pruning trailing account approval modal chrome' => static function (TestRunner $t) use ($fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/simplyfound-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['lang'], $article->lang);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(false, str_contains($article->text, 'approved author'), 'account approval modal text should not survive extraction');
        $t->same(false, str_contains($article->contentHtml, 'adsbygoogle'), 'trailing ad container should not survive extraction');
        $t->same(false, str_contains($blocks, 'approval/request'), 'approval request links should not become WordPress blocks');
    },
    'removes trailing WordPress account modals and ad containers before block output' => static function (TestRunner $t): void {
        $source = '<html><head><meta property="og:title" content="Modal Chrome Import"></head><body><article>'
            . '<div class="entry-content">'
            . '<p>' . str_repeat('The migrated article body should remain intact while hidden account dialogs from the source theme are discarded. ', 3) . '</p>'
            . '<p>' . str_repeat('This keeps review blocks focused on editorial content instead of login or author-approval workflow chrome. ', 2) . '</p>'
            . '<div class="modal fade" id="become-an-approved-author"><div class="modal-dialog"><div class="modal-content"><button type="button" class="close" data-dismiss="modal">Close</button><p>You account is not approved yet.</p><p>To become an approved author, you must have minimum of two articles in your account.</p><p><a href="/approval/request">Send Your Request</a></p></div></div></div>'
            . '<center><ins class="adsbygoogle bottom_ad" data-ad-client="ca-pub-1" data-ad-slot="123"></ins></center>'
            . '</div></article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://example.com/imported/modal-chrome');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same('Modal Chrome Import', $article->title);
        $t->contains('migrated article body should remain intact', $blocks);
        $t->same(false, str_contains($article->text, 'approved author'), 'source account modal should not survive extraction');
        $t->same(false, str_contains($article->contentHtml, 'adsbygoogle'), 'source ad slot should not survive extraction');
        $t->same(false, str_contains($blocks, 'approval/request'), 'modal action links should not become WordPress paragraph blocks');
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
    'maps Mozilla dev418 fixture with mixed image list media retention' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/dev418';
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
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($imageAttributeRows($expected), $imageAttributeRows($article->contentHtml));
        $t->same(4, count($attributeValues($article->contentHtml, '//hr')));
        $t->same(4, count($attributeValues($article->contentHtml, '//h2')));
        $t->same(8, count($attributeValues($article->contentHtml, '//img')));
        $t->same(4, count($attributeValues($article->contentHtml, '//figure')));
        $t->same(2, count($attributeValues($article->contentHtml, '//ul')));
        $t->same(6, count($attributeValues($article->contentHtml, '//li')));
    },
    'maps Mozilla iab-1 fixture with leading header chrome cleanup and retained author bio' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/iab-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

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
        $t->same(array_map($normalizedText, $attributeValues($expected, '//h4')), array_map($normalizedText, $attributeValues($article->contentHtml, '//h4')));
        $t->same(21, substr_count($blocks, '<!-- wp:paragraph -->'), 'IAB article paragraphs and author bio should serialize as paragraph blocks');
        $t->same(1, substr_count($blocks, '<!-- wp:image -->'), 'only the retained author image should become a WordPress image block');
        $t->same(1, substr_count($blocks, '<!-- wp:heading'), 'the retained author heading should remain reviewable');
        foreach (['10.15.15', 'getting-lean-with-digital-ad-ux-2-1000x305.jpg'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'IAB header chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'IAB header chrome should not enter WordPress blocks: ' . $fragment);
        }
        $t->contains('Scott Cunningham', $blocks);
        $t->contains('L.E.A.N. Ads program', $blocks);
    },
    'maps Mozilla bug-1255978 fixture by preserving articleBody despite share-like id' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $imageAttributeRows, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/bug-1255978';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($extractor->extract($source, 'http://fakehost/test/page.html'));

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same($imageAttributeRows($expected), $imageAttributeRows($article->contentHtml));
        $t->same(
            $attributeValues($expected, '//div[@itemprop="articleBody"]/@id'),
            $attributeValues($article->contentHtml, '//div[@itemprop="articleBody"]/@id'),
        );
        $t->same(39, count($attributeValues($article->contentHtml, '//p')));
        $t->same(6, count($attributeValues($article->contentHtml, '//img')));
        $t->contains('Reuse content', $blocks);
        $t->same(31, substr_count($blocks, '<!-- wp:paragraph -->'), 'Independent article copy and retained reuse link should serialize without recommendation blocks');
        $t->same(1, substr_count($blocks, '<!-- wp:html -->'), 'retained publisher video wrappers should become HTML review blocks');
        foreach (['Taboola', '1,000,000 are using this app', 'Business news in pictures', 'US election'] as $fragment) {
            $t->same(false, str_contains($article->text, $fragment), 'publisher recommendation/gallery chrome should not enter article text: ' . $fragment);
            $t->same(false, str_contains($blocks, $fragment), 'publisher recommendation/gallery chrome should not enter WordPress blocks: ' . $fragment);
        }
    },
    'serializes retained image lists from media fixtures as WordPress blocks' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/dev418/source.html');

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:image -->'), 'standalone figures should remain image blocks');
        $t->same(2, substr_count($blocks, '<!-- wp:list'), 'retained media lists should become list blocks');
        $t->same(4, substr_count($blocks, '<!-- wp:separator -->'));
        $t->same(4, substr_count($blocks, '<!-- wp:heading'));
        $t->contains('<!-- wp:list -->', $blocks);
        $t->contains('<ul>', $blocks);
        $t->contains('<img src="http://fakehost/test/florian-giorgio-P1U7-ZgKeOM-unsplash.jpg" alt="An image">', $blocks);
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<ul>"), 'media lists should not be wrapped in paragraph blocks');
    },
    'serializes retained native media elements as WordPress HTML blocks' => static function (TestRunner $t): void {
        $source = '<html><head><title>Native Media Import</title></head><body><article>'
            . '<h1>Native Media Import</h1>'
            . '<p>' . str_repeat('Portable WordPress imports can retain native media elements from old hosted-player markup. ', 3) . '</p>'
            . '<video controls poster="/poster.jpg"><source src="/clip.mp4" type="video/mp4"></video>'
            . '<audio controls src="/episode.mp3"></audio>'
            . '<p>' . str_repeat('The retained media should stay reviewable without being wrapped as paragraph HTML. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://example.test/posts/native-media.html');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(2, substr_count($blocks, '<!-- wp:html -->'), 'standalone native media elements should become HTML blocks');
        $t->contains('<video controls poster="https://example.test/poster.jpg"><source src="https://example.test/clip.mp4" type="video/mp4"></source></video>', $blocks);
        $t->contains('<audio controls src="https://example.test/episode.mp3"></audio>', $blocks);
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<video"), 'standalone video should not be paragraph-wrapped');
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<audio"), 'standalone audio should not be paragraph-wrapped');
    },
    'serializes retained media figures as WordPress HTML blocks' => static function (TestRunner $t): void {
        $source = '<html><head><title>Media Figure Import</title></head><body><article>'
            . '<h1>Media Figure Import</h1>'
            . '<p>' . str_repeat('Legacy migrations often wrap retained provider embeds in figure shells with captions. ', 3) . '</p>'
            . '<figure><iframe src="https://www.youtube.com/embed/abc123"></iframe><figcaption>Watch the archived session.</figcaption></figure>'
            . '<p>' . str_repeat('The importer should keep the caption with the reviewed embed instead of paragraph-wrapping source figure markup. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://example.test/posts/media-figure.html');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:html -->'), 'retained embed figures should become HTML blocks');
        $t->contains('<figure><iframe src="https://www.youtube.com/embed/abc123"></iframe><figcaption>Watch the archived session.</figcaption></figure>', $blocks);
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<figure><iframe"), 'retained embed figures should not be paragraph-wrapped');
    },
    'serializes retained captioned embed wrappers as WordPress HTML blocks' => static function (TestRunner $t): void {
        $source = '<html><head><title>Captioned Embed Import</title></head><body><article>'
            . '<h1>Captioned Embed Import</h1>'
            . '<p>' . str_repeat('Legacy migrations often preserve provider embed wrappers that are not valid figure elements. ', 3) . '</p>'
            . '<div class="video-embed caption"><iframe src="https://www.youtube.com/embed/wrapped-session"></iframe><p>Archived session video.</p></div>'
            . '<p>' . str_repeat('The importer should keep the provider wrapper and caption together for review. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://example.test/posts/captioned-embed.html');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:html -->'), 'captioned embed wrappers should become one HTML block');
        $t->contains('<div><iframe src="https://www.youtube.com/embed/wrapped-session"></iframe><p>Archived session video.</p></div>', $blocks);
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<div><iframe"), 'captioned embed wrappers should not be paragraph-wrapped');
        $t->same(2, substr_count($blocks, '<!-- wp:paragraph -->'), 'surrounding prose should remain paragraph blocks');
    },
    'serializes retained nested embed wrappers as WordPress HTML blocks' => static function (TestRunner $t): void {
        $source = '<html><head><title>Nested Embed Import</title></head><body><article>'
            . '<h1>Nested Embed Import</h1>'
            . '<p>' . str_repeat('Legacy exports can wrap an oEmbed in provider divs inside a captioned migration container. ', 3) . '</p>'
            . '<section class="wp-block-embed provider-card"><div class="embed-responsive"><iframe src="https://www.youtube.com/embed/nested-session"></iframe></div><p>Nested provider session.</p></section>'
            . '<p>Inline context <iframe src="https://www.youtube.com/embed/inline-context"></iframe> should stay paragraph content.</p>'
            . '<p>' . str_repeat('Follow-up copy after the embeds should remain ordinary paragraph content for editing. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'https://example.test/posts/nested-embed.html');
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:html -->'), 'one nested media wrapper should become one HTML block');
        $t->contains('<section><p><iframe src="https://www.youtube.com/embed/nested-session"></iframe></p>' . "\n" . '<p>Nested provider session.</p></section>', $blocks);
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<section><p><iframe"), 'nested captioned embed wrappers should not be paragraph-wrapped');
        $t->contains('<p>Inline context <iframe src="https://www.youtube.com/embed/inline-context"></iframe> should stay paragraph content.</p>', $blocks);
        $t->same(3, substr_count($blocks, '<!-- wp:paragraph -->'), 'surrounding prose and inline-context embed should remain paragraph blocks');
    },
    'serializes retained definition lists as WordPress HTML blocks' => static function (TestRunner $t): void {
        $source = '<html><head><title>Definition List Import</title></head><body><article>'
            . '<h1>Definition List Import</h1>'
            . '<p>' . str_repeat('Long encyclopedia and technical imports can retain definition lists as meaningful article structure. ', 3) . '</p>'
            . '<dl><dt>Reader mode</dt><dd>Clean article content extracted from source chrome.</dd><dt>Migration review</dt><dd>Preserved semantic structure before block editing.</dd></dl>'
            . '<p>' . str_repeat('Follow-up prose should remain ordinary paragraph content after the retained list. ', 3) . '</p>'
            . '</article></body></html>';

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same(1, substr_count($blocks, '<!-- wp:html -->'), 'standalone definition lists should become HTML blocks');
        $t->contains('Reader mode', $blocks);
        $t->contains('Preserved semantic structure before block editing.', $blocks);
        $t->same(false, str_contains($blocks, "<!-- wp:paragraph -->\n<dl>"), 'definition lists should not be paragraph-wrapped');
        $t->same(2, substr_count($blocks, '<!-- wp:paragraph -->'), 'surrounding prose should remain paragraph blocks');
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
    'maps Mozilla tmz-1 fixture with legacy post headline envelope' => static function (TestRunner $t) use ($attributeValues, $fixtureText, $normalizedText): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/tmz-1';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source, 'http://fakehost/test/page.html', true);
        $blocks = $extractor->toWordPressBlocks($article);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalizedText($metadata['excerpt']), $normalizedText($article->excerpt));
        $t->same($fixtureText($expected), $fixtureText($article->contentHtml));
        $t->same(array_map($normalizedText, $attributeValues($expected, '//h4')), array_map($normalizedText, $attributeValues($article->contentHtml, '//h4')));
        $t->same(array_map($normalizedText, $attributeValues($expected, '//h5')), array_map($normalizedText, $attributeValues($article->contentHtml, '//h5')));
        $t->same($attributeValues($expected, '//div[@itemprop="articleBody"]/@itemprop'), $attributeValues($article->contentHtml, '//div[@itemprop="articleBody"]/@itemprop'));
        $t->same($attributeValues($expected, '//img/@src'), $attributeValues($article->contentHtml, '//img/@src'));
        $t->contains('2/26/2015 7:11 AM PST BY TMZ STAFF', $blocks);
        $t->contains('12:00 PM PT', $blocks);
        $t->same(false, str_contains($blocks, '<p></p>'), 'invalid source paragraph wrappers around headings should not create empty WordPress paragraph blocks');
        $t->same(false, str_contains($blocks, '<h2>Lupita Nyong'), 'split title prefix should be removed as duplicate title chrome');
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
