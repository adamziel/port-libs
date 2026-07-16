<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$fixtures = [
    'style-tags-removal',
    'remove-script-tags',
];

$extractor = new ArticleExtractor();

foreach ($fixtures as $fixture) {
    $source = (string) file_get_contents(__DIR__ . '/../fixtures/mozilla/' . $fixture . '/source.html');
    $article = $extractor->extract($source, 'http://fakehost/test/page.html');
    $blocks = $extractor->toWordPressBlocks($article);

    echo "Fixture: {$fixture}\n";
    echo "Title: {$article->title}\n";
    echo 'Paragraph blocks: ' . substr_count($blocks, '<!-- wp:paragraph -->') . "\n";
    echo 'Heading blocks: ' . substr_count($blocks, '<!-- wp:heading') . "\n";
    echo 'Script/style tags imported: ' . (
        str_contains($blocks, '<script') || str_contains($blocks, '<style')
            ? 'yes'
            : 'no'
    ) . "\n";
}
