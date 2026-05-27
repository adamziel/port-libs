<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Readability\ArticleExtractor;

$fixture = __DIR__ . '/../fixtures/mozilla/base-url-base-element-relative';
$source = (string) file_get_contents($fixture . '/source.html');
$article = (new ArticleExtractor())->extract($source, 'http://fakehost/test/page.html');
$blocks = (new ArticleExtractor())->toWordPressBlocks($article);

echo 'Title: ' . $article->title . "\n";
echo 'Resolved relative base link: ' . (str_contains($blocks, 'href="http://fakehost/test/base/foo/bar/baz.html"') ? 'yes' : 'no') . "\n";
echo 'Resolved root link: ' . (str_contains($blocks, 'href="http://fakehost/foo/bar/baz.html"') ? 'yes' : 'no') . "\n";
echo 'Resolved fragment link: ' . (str_contains($blocks, 'href="http://fakehost/test/base/baz.html#foo"') ? 'yes' : 'no') . "\n";
echo 'Resolved image references: ' . substr_count($blocks, '<img src="http') . "\n";
