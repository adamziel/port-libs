<?php

declare(strict_types=1);

use PortLibs\Readability\ArticleExtractor;

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
    'maps Mozilla normalize-spaces fixture metadata and article text' => static function (TestRunner $t): void {
        $fixture = __DIR__ . '/../fixtures/mozilla/normalize-spaces';
        $source = (string) file_get_contents($fixture . '/source.html');
        $expected = (string) file_get_contents($fixture . '/expected.html');
        $metadata = json_decode((string) file_get_contents($fixture . '/expected-metadata.json'), true, 512, JSON_THROW_ON_ERROR);
        $textFromHtml = static function (string $html): string {
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return trim(preg_replace('/\s+/', ' ', $dom->textContent) ?? '');
        };
        $normalized = static fn (string $text): string => trim(preg_replace('/\s+/', ' ', $text) ?? '');

        $extractor = new ArticleExtractor();
        $article = $extractor->extract($source);

        $t->same($metadata['title'], $article->title);
        $t->same($metadata['byline'], $article->byline);
        $t->same($metadata['siteName'], $article->siteName);
        $t->same($metadata['publishedTime'], $article->publishedTime);
        $t->same($metadata['dir'], $article->dir);
        $t->same($metadata['readerable'], $extractor->isProbablyReaderable($source));
        $t->same($normalized($metadata['excerpt']), $normalized($article->excerpt));
        $t->same($textFromHtml($expected), $textFromHtml($article->contentHtml));
    },
];
