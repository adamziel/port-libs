<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$softbreak = static fn (): AstNode => new AstNode('softbreak');
$linebreak = static fn (): AstNode => new AstNode('linebreak');
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$writeDocument = static fn (array $blocks, array $options = []): string => (new MarkdownWriter($options))->write($document($blocks));
$writeParagraph = static fn (array $children, array $options = []): string => $writeDocument([$paragraph($children)], $options);
$link = static fn (string $url, string $label, array $attrs = []): AstNode => new AstNode(
    'link',
    ['url' => $url] + $attrs,
    $label === '' ? [] : [$text($label)]
);
$image = static fn (string $url, string $label, array $attrs = []): AstNode => new AstNode(
    'image',
    ['url' => $url, 'alt' => $label] + $attrs,
    $label === '' ? [] : [$text($label)]
);

$tests = [];

$lineStartCases = [
    'softbreak definition colon marker' => [
        'children' => [$text('Term'), $softbreak(), $text(': definition-looking continuation')],
        'expected' => "Term\n\\: definition-looking continuation",
    ],
    'hardbreak definition colon marker' => [
        'children' => [$text('Term'), $linebreak(), $text(': definition-looking continuation')],
        'expected' => "Term\\\n\\: definition-looking continuation",
    ],
    'literal newline definition colon marker' => [
        'children' => [$text("Term\n: definition-looking continuation")],
        'expected' => "Term\n\\: definition-looking continuation",
    ],
    'softbreak alternate definition marker' => [
        'children' => [$text('Term'), $softbreak(), $text('~ alternate definition-looking continuation')],
        'expected' => "Term\n\\~ alternate definition-looking continuation",
    ],
    'softbreak atx heading marker' => [
        'children' => [$text('Term'), $softbreak(), $text('# not a heading')],
        'expected' => "Term\n\\# not a heading",
    ],
    'softbreak decimal list marker' => [
        'children' => [$text('Term'), $softbreak(), $text('1. not a list')],
        'expected' => "Term\n1\\. not a list",
    ],
    'softbreak parenthesized ordered marker' => [
        'children' => [$text('Term'), $softbreak(), $text('2) not a list')],
        'expected' => "Term\n2\\) not a list",
    ],
    'softbreak dash bullet marker' => [
        'children' => [$text('Term'), $softbreak(), $text('- not a list')],
        'expected' => "Term\n\\- not a list",
    ],
    'softbreak plus bullet marker' => [
        'children' => [$text('Term'), $softbreak(), $text('+ not a list')],
        'expected' => "Term\n\\+ not a list",
    ],
    'softbreak asterisk bullet marker' => [
        'children' => [$text('Term'), $softbreak(), $text('* not a list')],
        'expected' => "Term\n\\* not a list",
    ],
    'emphasis nested definition marker' => [
        'children' => [new AstNode('emph', [], [$text('Term'), $softbreak(), $text(': detail')])],
        'expected' => "*Term\n\\: detail*",
    ],
    'strong nested hardbreak definition marker' => [
        'children' => [new AstNode('strong', [], [$text('Term'), $linebreak(), $text(': detail')])],
        'expected' => "**Term\\\n\\: detail**",
    ],
    'span nested definition marker' => [
        'children' => [new AstNode('span', ['classes' => ['review']], [$text('Term'), $softbreak(), $text(': detail')])],
        'expected' => "[Term\n\\: detail]{.review}",
    ],
    'link label nested definition marker' => [
        'children' => [new AstNode('link', ['url' => '/review'], [$text('Term'), $softbreak(), $text(': detail')])],
        'expected' => "[Term\n\\: detail](/review)",
    ],
    'image label nested definition marker' => [
        'children' => [new AstNode('image', ['url' => '/image.png', 'alt' => 'Term detail'], [$text('Term'), $softbreak(), $text(': detail')])],
        'expected' => "![Term\n\\: detail](/image.png){alt=\"Term detail\"}",
    ],
];

foreach ($lineStartCases as $label => $case) {
    $tests["maps upstream markdown writer inline escape {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $t->same($case['expected'], $writeParagraph($case['children']));
        };
}

$autolinkGuardCases = [
    'uri with space falls back to inline link' => [
        'inline' => new AstNode('link', ['url' => 'https://example.test/a b', 'classes' => ['uri']], [$text('https://example.test/a b')]),
        'expected' => '[https://example.test/a b](<https://example.test/a b>){.uri}',
    ],
    'uri with less than falls back to inline link' => [
        'inline' => new AstNode('link', ['url' => 'https://example.test/a<b', 'classes' => ['uri']], [$text('https://example.test/a<b')]),
        'expected' => '[https://example.test/a\\<b](<https://example.test/a\\<b>){.uri}',
    ],
    'uri with greater than falls back to inline link' => [
        'inline' => new AstNode('link', ['url' => 'https://example.test/a>b', 'classes' => ['uri']], [$text('https://example.test/a>b')]),
        'expected' => '[https://example.test/a\\>b](<https://example.test/a\\>b>){.uri}',
    ],
    'uri with newline falls back to encoded destination' => [
        'inline' => new AstNode('link', ['url' => "https://example.test/a\nb", 'classes' => ['uri']], [$text("https://example.test/a\nb")]),
        'expected' => "[https://example.test/a\nb](https://example.test/a%0Ab){.uri}",
    ],
    'uri with tab falls back to encoded destination' => [
        'inline' => new AstNode('link', ['url' => "https://example.test/a\tb", 'classes' => ['uri']], [$text("https://example.test/a\tb")]),
        'expected' => "[https://example.test/a\tb](https://example.test/a%09b){.uri}",
    ],
    'email with display-name space falls back to inline link' => [
        'inline' => new AstNode('link', ['url' => 'mailto:editor name@example.test', 'classes' => ['email']], [$text('editor name@example.test')]),
        'expected' => '[editor name@example.test](<mailto:editor name@example.test>){.email}',
    ],
    'email with newline falls back to encoded destination' => [
        'inline' => new AstNode('link', ['url' => "mailto:editor\nname@example.test", 'classes' => ['email']], [$text("editor\nname@example.test")]),
        'expected' => "[editor\nname@example.test](mailto:editor%0Aname@example.test){.email}",
    ],
    'empty uri class target falls back to empty destination' => [
        'inline' => new AstNode('link', ['url' => '', 'classes' => ['uri']], [$text('empty')]),
        'expected' => '[empty](<>){.uri}',
    ],
    'uri with title keeps inline link metadata' => [
        'inline' => new AstNode('link', ['url' => 'https://example.test/source', 'title' => 'Source title', 'classes' => ['uri']], [$text('https://example.test/source')]),
        'expected' => '[https://example.test/source](https://example.test/source "Source title"){.uri}',
    ],
    'valid uri remains autolink' => [
        'inline' => new AstNode('link', ['url' => 'https://example.test/source?post=42', 'classes' => ['uri']], [$text('https://example.test/source?post=42')]),
        'expected' => '<https://example.test/source?post=42>',
    ],
];

foreach ($autolinkGuardCases as $label => $case) {
    $tests["maps upstream markdown writer autolink guard {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $markdown = $writeParagraph([$case['inline']]);

            $t->same($case['expected'], $markdown);
        };
}

$destinationTitleCases = [
    'newline destination is percent encoded' => [
        'inline' => $link("https://example.test/a\nb", 'packet'),
        'expected' => '[packet](https://example.test/a%0Ab)',
    ],
    'tab destination is percent encoded' => [
        'inline' => $link("https://example.test/a\tb", 'packet'),
        'expected' => '[packet](https://example.test/a%09b)',
    ],
    'carriage return destination is percent encoded' => [
        'inline' => $link("https://example.test/a\rb", 'packet'),
        'expected' => '[packet](https://example.test/a%0Db)',
    ],
    'nul destination is percent encoded' => [
        'inline' => $link("https://example.test/a\x00b", 'packet'),
        'expected' => '[packet](https://example.test/a%00b)',
    ],
    'del destination is percent encoded' => [
        'inline' => $link("https://example.test/a\x7Fb", 'packet'),
        'expected' => '[packet](https://example.test/a%7Fb)',
    ],
    'newline title is serialized on one line' => [
        'inline' => $link('/source', 'packet', ['title' => "Line\nTwo"]),
        'expected' => '[packet](/source "Line Two")',
    ],
    'tab and control title are serialized on one line' => [
        'inline' => $link('/source', 'packet', ['title' => "A\tB\x01C"]),
        'expected' => '[packet](/source "A B C")',
    ],
    'quote and slash title escaping is preserved' => [
        'inline' => $link('/source', 'packet', ['title' => 'A "quoted" \\ title']),
        'expected' => '[packet](/source "A \\"quoted\\" \\\\ title")',
    ],
    'image newline destination is percent encoded' => [
        'inline' => $image("media/a\nb.png", 'alt'),
        'expected' => '![alt](media/a%0Ab.png)',
    ],
    'image newline title is serialized on one line' => [
        'inline' => $image('media/a.png', 'alt', ['title' => "Line\nTwo"]),
        'expected' => '![alt](media/a.png "Line Two")',
    ],
    'reference newline destination is percent encoded' => [
        'inline' => $link("https://example.test/a\nb", 'packet'),
        'expected' => " [packet]\n\n  [packet]: https://example.test/a%0Ab",
        'referenceLinks' => true,
    ],
    'reference newline title is serialized on one line' => [
        'inline' => $link('/source', 'packet', ['title' => "Line\nTwo"]),
        'expected' => " [packet]\n\n  [packet]: /source \"Line Two\"",
        'referenceLinks' => true,
    ],
    'angle wrapped destination still escapes angle tokens' => [
        'inline' => $link('https://example.test/a <b>', 'packet'),
        'expected' => '[packet](<https://example.test/a \\<b\\>>)',
    ],
    'parenthesized destination remains angle wrapped' => [
        'inline' => $link('https://example.test/archive(2026)/source).html', 'packet'),
        'expected' => '[packet](<https://example.test/archive(2026)/source).html>)',
    ],
    'image empty destination remains angle wrapped' => [
        'inline' => $image('', 'alt'),
        'expected' => '![alt](<>)',
    ],
];

foreach ($destinationTitleCases as $label => $case) {
    $tests["maps upstream markdown writer link destination title {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $children = [$case['inline']];
            if (($case['referenceLinks'] ?? false) === true) {
                array_unshift($children, new AstNode('text', ['text' => ' ']));
            }

            $markdown = $writeParagraph($children, ['referenceLinks' => (bool) ($case['referenceLinks'] ?? false)]);

            $t->same($case['expected'], $markdown);
        };
}

$tests['maps upstream markdown writer reference attribute order target reuse'] =
    static function (TestRunner $t) use ($link, $space, $writeParagraph): void {
        $markdown = $writeParagraph([
            $link('/source', 'source', [
                'title' => 'Title',
                'id' => 'id',
                'classes' => ['class'],
                'attributes' => ['b' => '2', 'a' => '1'],
            ]),
            $space(),
            $link('/source', 'source', [
                'title' => 'Title',
                'id' => 'id',
                'classes' => ['class'],
                'attributes' => ['a' => '1', 'b' => '2'],
            ]),
        ], ['referenceLinks' => true]);

        $t->same(implode("\n", [
            '[source] [source]',
            '',
            '  [source]: /source "Title" {#id .class b="2" a="1"}',
        ]), $markdown);
    };

$referenceCases = [
    'same label different titles generate separate definitions' => [
        'children' => [
            $link('/a', 'source', ['title' => 'A']),
            $space(),
            $link('/a', 'source', ['title' => 'B']),
        ],
        'expected' => "[source] [source][1]\n\n  [source]: /a \"A\"\n  [1]: /a \"B\"",
    ],
    'same target different visible labels reuse first definition' => [
        'children' => [
            $link('/same', 'first'),
            $space(),
            $link('/same', 'second'),
        ],
        'expected' => "[first] [second][first]\n\n  [first]: /same",
    ],
    'empty label generates numeric definition label' => [
        'children' => [new AstNode('link', ['url' => '/empty'], [])],
        'expected' => "[][1]\n\n  [1]: /empty",
    ],
    'bracket label generates numeric definition label' => [
        'children' => [$link('/bracket', 'bracket [label]')],
        'expected' => "[bracket \\[label\\]][1]\n\n  [1]: /bracket",
    ],
    'case insensitive duplicate labels generate numeric definition label' => [
        'children' => [
            $link('/a', 'Source'),
            $space(),
            $link('/b', 'source'),
        ],
        'expected' => "[Source] [source][1]\n\n  [Source]: /a\n  [1]: /b",
    ],
    'newline label normalizes definition label' => [
        'children' => [new AstNode('link', ['url' => '/source-packet'], [$text('source'), $softbreak(), $text('packet')])],
        'expected' => "[source\npacket]\n\n  [source packet]: /source-packet",
    ],
    'reference control destination is percent encoded' => [
        'children' => [$link("https://example.test/a\nb", 'packet')],
        'expected' => "[packet]\n\n  [packet]: https://example.test/a%0Ab",
    ],
    'reference control title is serialized on one line' => [
        'children' => [$link('/source', 'packet', ['title' => "A\nB"])],
        'expected' => "[packet]\n\n  [packet]: /source \"A B\"",
    ],
    'reference attributes escape quoted values' => [
        'children' => [$link('/source', 'packet', ['attributes' => ['title' => 'A "quote"']])],
        'expected' => "[packet]\n\n  [packet]: /source {title=\"A \\\"quote\\\"\"}",
    ],
    'image shortcut reference definition' => [
        'children' => [$image('/image.png', 'alt')],
        'expected' => "![alt]\n\n  [alt]: /image.png",
    ],
    'image reference control destination is percent encoded' => [
        'children' => [$image("media/a\nb.png", 'alt')],
        'expected' => "![alt]\n\n  [alt]: media/a%0Ab.png",
    ],
];

foreach ($referenceCases as $label => $case) {
    $tests["maps upstream markdown writer reference link {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $t->same($case['expected'], $writeParagraph($case['children'], ['referenceLinks' => true]));
        };
}

$tests['maps upstream markdown writer reference long labels generate numeric definition label'] =
    static function (TestRunner $t) use ($link, $writeParagraph): void {
        $label = str_repeat('a', 1000);
        $markdown = $writeParagraph([$link('/long', $label)], ['referenceLinks' => true]);

        $t->contains('[1]: /long', $markdown);
        $t->true(!str_contains($markdown, '[' . $label . ']: /long'), 'Overlong reference labels should not become definition labels');
    };

$inlineBranchCases = [
    'valid email autolink remains compact' => [
        'children' => [new AstNode('link', ['url' => 'mailto:editor@example.test', 'classes' => ['email']], [$text('editor@example.test')])],
        'expected' => '<editor@example.test>',
    ],
    'emphasis preserves outer whitespace' => [
        'children' => [new AstNode('emph', [], [$text(' review ')])],
        'expected' => ' *review* ',
    ],
    'strong preserves outer whitespace' => [
        'children' => [new AstNode('strong', [], [$text(' review ')])],
        'expected' => ' **review** ',
    ],
    'superscript spaces are escaped' => [
        'children' => [new AstNode('superscript', [], [$text('draft 2')])],
        'expected' => '^draft\\ 2^',
    ],
    'subscript spaces are escaped' => [
        'children' => [new AstNode('subscript', [], [$text('H 2 O')])],
        'expected' => '~H\\ 2\\ O~',
    ],
    'raw html5 inline emits raw html' => [
        'children' => [new AstNode('raw_inline', ['format' => 'html5', 'text' => '<span data-review="1">raw</span>'])],
        'expected' => '<span data-review="1">raw</span>',
    ],
    'raw markdown plus extension emits raw markdown' => [
        'children' => [new AstNode('raw_inline', ['format' => 'markdown+pipe_tables', 'text' => '[raw]{.review}'])],
        'expected' => '[raw]{.review}',
    ],
    'image autolink-looking alt remains image label' => [
        'children' => [$image('https://example.test/source.png', 'https://example.test/source.png')],
        'expected' => '![](https://example.test/source.png)',
    ],
];

foreach ($inlineBranchCases as $label => $case) {
    $tests["maps upstream markdown writer inline branch {$label}"] =
        static function (TestRunner $t) use ($case, $writeParagraph): void {
            $t->same($case['expected'], $writeParagraph($case['children']));
        };
}

return $tests;
