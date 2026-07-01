<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$rawMarkdown = static fn (string $value): AstNode => new AstNode('raw_inline', [
    'format' => 'markdown',
    'text' => $value,
]);
$citation = static fn (string $id): AstNode => new AstNode('citation', ['id' => $id]);
$link = static fn (string $url, string $title, string $label): AstNode => new AstNode(
    'link',
    ['url' => $url, 'title' => $title],
    [$text($label)]
);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = [];
    if ($node->type === $type) {
        $matches[] = $node;
    }

    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$cases = [
    'simple link shortcutable' => [
        'children' => [$link('/url', 'title', 'foo')],
        'expected' => "[foo]\n\n  [foo]: /url \"title\"",
        'linkUrls' => ['/url'],
    ],
    'followed by another link unshortcutable' => [
        'children' => [
            $link('/url1', 'title1', 'first'),
            $link('/url2', 'title2', 'second'),
        ],
        'expected' => "[first][][second]\n\n  [first]: /url1 \"title1\"\n  [second]: /url2 \"title2\"",
        'linkUrls' => ['/url1', '/url2'],
    ],
    'followed by space and another link unshortcutable' => [
        'children' => [
            $link('/url1', 'title1', 'first'),
            $space(),
            $link('/url2', 'title2', 'second'),
        ],
        'expected' => "[first][] [second]\n\n  [first]: /url1 \"title1\"\n  [second]: /url2 \"title2\"",
        'linkUrls' => ['/url1', '/url2'],
    ],
    'duplicate labels without spaces are unshortcutable' => [
        'children' => [
            $link('/url1', '', 'foo'),
            $link('/url2', '', 'foo'),
            $link('/url3', '', 'foo'),
        ],
        'expected' => "[foo][][foo][1][foo][2]\n\n  [foo]: /url1\n  [1]: /url2\n  [2]: /url3",
        'linkUrls' => ['/url1', '/url2', '/url3'],
    ],
    'duplicate labels with spaces are unshortcutable' => [
        'children' => [
            $link('/url1', '', 'foo'),
            $space(),
            $link('/url2', '', 'foo'),
            $space(),
            $link('/url3', '', 'foo'),
        ],
        'expected' => "[foo][] [foo][1] [foo][2]\n\n  [foo]: /url1\n  [1]: /url2\n  [2]: /url3",
        'linkUrls' => ['/url1', '/url2', '/url3'],
    ],
    'followed by text in brackets' => [
        'children' => [
            $link('/url', '', 'link'),
            $text('[text in brackets]'),
        ],
        'expected' => "[link][]\\[text in brackets\\]\n\n  [link]: /url",
        'linkUrls' => ['/url'],
    ],
    'followed by space and text in brackets' => [
        'children' => [
            $link('/url', '', 'link'),
            $text(' [text in brackets]'),
        ],
        'expected' => "[link][] \\[text in brackets\\]\n\n  [link]: /url",
        'linkUrls' => ['/url'],
    ],
    'followed by raw inline' => [
        'children' => [
            $link('/url', '', 'link'),
            $rawMarkdown('[rawText]'),
        ],
        'expected' => "[link][][rawText]\n\n  [link]: /url",
        'linkUrls' => ['/url'],
    ],
    'followed by space and raw inline' => [
        'children' => [
            $link('/url', '', 'link'),
            $space(),
            $rawMarkdown('[rawText]'),
        ],
        'expected' => "[link][] [rawText]\n\n  [link]: /url",
        'linkUrls' => ['/url'],
    ],
    'followed by raw inline with leading space' => [
        'children' => [
            $link('/url', '', 'link'),
            $rawMarkdown(' [rawText]'),
        ],
        'expected' => "[link][] [rawText]\n\n  [link]: /url",
        'linkUrls' => ['/url'],
    ],
    'followed by citation' => [
        'children' => [
            $link('/url', '', 'link'),
            $citation('author'),
        ],
        'expected' => "[link][][@author]\n\n  [link]: /url",
        'linkUrls' => ['/url'],
        'citationIds' => ['author'],
    ],
    'followed by space and citation' => [
        'children' => [
            $link('/url', '', 'link'),
            $space(),
            $citation('author'),
        ],
        'expected' => "[link][] [@author]\n\n  [link]: /url",
        'linkUrls' => ['/url'],
        'citationIds' => ['author'],
    ],
];

$tests = [
    'records upstream markdown writer shortcut reference fixture mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(12, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer shortcut reference fixture ' . $label] =
        static function (TestRunner $t) use ($case, $collectNodes, $document): void {
            $markdown = (new MarkdownWriter(['referenceLinks' => true]))->write($document($case['children']));

            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $links = $collectNodes($roundTrip, 'link');
            $t->same($case['linkUrls'], array_map(
                static fn (AstNode $node): string => (string) $node->attr('url', ''),
                $links
            ));

            if (isset($case['citationIds'])) {
                $citations = $collectNodes($roundTrip, 'citation');
                $t->same($case['citationIds'], array_map(
                    static fn (AstNode $node): string => (string) $node->attr('id', ''),
                    $citations
                ));
            }
        };
}

return $tests;
