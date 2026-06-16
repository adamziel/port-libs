<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$link = static fn (array $attrs, string $label): AstNode => new AstNode('link', $attrs, [$text($label)]);
$image = static fn (array $attrs, array $children = []): AstNode => new AstNode('image', $attrs, $children);

$cases = [
    'link target url title scalar aliases' => [
        'document' => $document([$link(['targetUrl' => '/review/target', 'targetTitle' => 'Target title'], 'Target')]),
        'expected' => '[Target](/review/target "Target title")',
    ],
    'link destination url title scalar aliases' => [
        'document' => $document([$link(['destinationUrl' => '/review/destination', 'destinationTitle' => 'Destination title'], 'Destination')]),
        'expected' => '[Destination](/review/destination "Destination title")',
    ],
    'link source url title scalar aliases' => [
        'document' => $document([$link(['sourceUrl' => '/review/source', 'sourceTitle' => 'Source title'], 'Source')]),
        'expected' => '[Source](/review/source "Source title")',
    ],
    'link explicit link url title aliases' => [
        'document' => $document([$link(['linkUrl' => '/review/link', 'linkTitle' => 'Link title'], 'Link')]),
        'expected' => '[Link](/review/link "Link title")',
    ],
    'image url title alt aliases' => [
        'document' => $document([$image(['imageUrl' => 'media/cover.png', 'imageTitle' => 'Cover title', 'altText' => 'Cover alt'])]),
        'expected' => '![Cover alt](media/cover.png "Cover title")',
    ],
    'image source url title description aliases' => [
        'document' => $document([$image(['sourceUrl' => 'media/source.png', 'sourceTitle' => 'Source image', 'description' => 'Source alt'])]),
        'expected' => '![Source alt](media/source.png "Source image")',
    ],
    'reference link target aliases' => [
        'document' => $document([$link(['targetUrl' => '/review/ref', 'targetTitle' => 'Reference title'], 'Reference')]),
        'expected' => "[Reference]\n\n  [Reference]: /review/ref \"Reference title\"",
        'options' => ['referenceLinks' => true],
    ],
    'autolink source url alias' => [
        'document' => $document([$link(['sourceUrl' => 'https://example.test/review', 'classes' => ['uri']], 'https://example.test/review')]),
        'expected' => '<https://example.test/review>',
    ],
    'wikilink target url alias' => [
        'document' => $document([$link(['targetUrl' => 'Review/Page', 'classes' => ['wikilink']], 'Review Page')]),
        'expected' => '[[Review Page|Review/Page]]',
        'options' => ['format' => 'markdown+wikilinks'],
    ],
    'target array alias keys' => [
        'document' => $document([$link(['target' => ['targetUrl' => '/review/array', 'targetTitle' => 'Array target']], 'Array')]),
        'expected' => '[Array](/review/array "Array target")',
    ],
];

$tests = [
    'records markdown writer link target alias completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(10, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer link target alias completion ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
