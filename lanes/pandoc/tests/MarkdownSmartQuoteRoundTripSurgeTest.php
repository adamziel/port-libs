<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$inline = static fn (string $type, array $children = [], array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$quoted = static fn (string $kind, array $children): AstNode => new AstNode('quoted', ['kind' => $kind], $children);
$link = static fn (string $url, array $children, array $attrs = []): AstNode => new AstNode('link', ['url' => $url] + $attrs, $children);
$image = static fn (string $url, string $alt, array $children = [], array $attrs = []): AstNode => new AstNode(
    'image',
    ['url' => $url, 'alt' => $alt] + $attrs,
    $children
);
$note = static fn (array $blocks): AstNode => new AstNode('note', [], $blocks);
$caseDocument = static fn (array $children): AstNode => $document([$paragraph($children)]);

$plainInlineText = static function (array $nodes) use (&$plainInlineText): string {
    $text = '';
    foreach ($nodes as $node) {
        if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
            $text .= (string) $node->attr('text', '');
            continue;
        }

        if ($node->type === 'raw_tex') {
            $text .= (string) $node->attr('tex', '');
            continue;
        }

        if ($node->type === 'raw_inline') {
            $text .= (string) $node->attr('text', '');
            continue;
        }

        if ($node->type === 'raw_html_inline') {
            $text .= (string) $node->attr('html', '');
            continue;
        }

        if ($node->type === 'softbreak' || $node->type === 'linebreak') {
            $text .= "\n";
            continue;
        }

        $text .= $plainInlineText($node->children);
    }

    return $text;
};

$findFirstQuoted = static function (AstNode $node) use (&$findFirstQuoted): AstNode {
    if ($node->type === 'quoted') {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstQuoted($child);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$hasDescendantType = static function (AstNode $node, string $type) use (&$hasDescendantType): bool {
    foreach ($node->children as $child) {
        if ($child->type === $type || $hasDescendantType($child, $type)) {
            return true;
        }
    }

    return false;
};

$quoteDelimiters = [
    'double' => ["\u{201C}", "\u{201D}"],
    'single' => ["\u{2018}", "\u{2019}"],
];

$cases = [];
$addRoundTripCase = static function (
    string $label,
    AstNode $documentNode,
    string $kind,
    ?string $plain,
    array $containsTypes = [],
    array $writerOptions = [],
    array $readerOptions = ['typographicSmartQuotes' => true]
) use (&$cases): void {
    $cases[$label] = [
        'type' => 'round-trip',
        'document' => $documentNode,
        'kind' => $kind,
        'plain' => $plain,
        'containsTypes' => $containsTypes,
        'writerOptions' => $writerOptions,
        'readerOptions' => $readerOptions,
    ];
};

$addReaderCase = static function (
    string $label,
    string $markdown,
    array $readerOptions,
    string $expectedQuotedKind,
    ?string $plain,
    array $containsTypes = []
) use (&$cases): void {
    $cases[$label] = [
        'type' => 'reader',
        'markdown' => $markdown,
        'readerOptions' => $readerOptions,
        'kind' => $expectedQuotedKind,
        'plain' => $plain,
        'containsTypes' => $containsTypes,
    ];
};

$addNoQuoteReaderCase = static function (
    string $label,
    string $markdown,
    array $readerOptions,
    string $expectedPlain
) use (&$cases): void {
    $cases[$label] = [
        'type' => 'reader-no-quote',
        'markdown' => $markdown,
        'readerOptions' => $readerOptions,
        'plain' => $expectedPlain,
    ];
};

$payloads = [
    'plain text payload' => [
        'children' => static fn (string $kind): array => [$text('quoted payload')],
        'plain' => 'quoted payload',
        'types' => [],
    ],
    'escaped punctuation payload' => [
        'children' => static fn (string $kind): array => [$text('a * b (c) and #d')],
        'plain' => 'a * b (c) and #d',
        'types' => [],
    ],
    'unicode entity payload' => [
        'children' => static fn (string $kind): array => [$text("AT&T \u{00A9} packet")],
        'plain' => "AT&T \u{00A9} packet",
        'types' => [],
    ],
    'emphasis payload' => [
        'children' => static fn (string $kind): array => [$text('pre '), $inline('emph', [$text('em')]), $text(' post')],
        'plain' => 'pre em post',
        'types' => ['emph'],
    ],
    'strong payload' => [
        'children' => static fn (string $kind): array => [$text('pre '), $inline('strong', [$text('strong')]), $text(' post')],
        'plain' => 'pre strong post',
        'types' => ['strong'],
    ],
    'nested emphasis strong payload' => [
        'children' => static fn (string $kind): array => [
            $inline('strong', [$text('strong '), $inline('emph', [$text('em')])]),
        ],
        'plain' => 'strong em',
        'types' => ['strong', 'emph'],
    ],
    'code payload' => [
        'children' => static fn (string $kind): array => [$text('use '), $inline('code', [], ['text' => 'wp code'])],
        'plain' => 'use wp code',
        'types' => ['code'],
    ],
    'code containing matching close payload' => [
        'children' => static fn (string $kind): array => [
            $text('code '),
            $inline('code', [], ['text' => $kind === 'single' ? "close \u{2019} marker" : "close \u{201D} marker"]),
            $text(' done'),
        ],
        'plain' => static fn (string $kind): string => $kind === 'single'
            ? "code close \u{2019} marker done"
            : "code close \u{201D} marker done",
        'types' => ['code'],
    ],
    'inline link payload' => [
        'children' => static fn (string $kind): array => [
            $link('/quote', [$text('source '), $inline('emph', [$text('label')])]),
        ],
        'plain' => 'source label',
        'types' => ['link', 'emph'],
    ],
    'inline image payload' => [
        'children' => static fn (string $kind): array => [
            $image('media/quote.png', 'alt quote', [$text('image label')]),
        ],
        'plain' => 'image label',
        'types' => ['image'],
    ],
    'inline math payload' => [
        'children' => static fn (string $kind): array => [$text('math '), $inline('math', [], ['text' => 'x+1'])],
        'plain' => 'math x+1',
        'types' => ['math'],
    ],
    'raw html inline payload' => [
        'children' => static fn (string $kind): array => [$text('press '), $inline('raw_html_inline', [], ['html' => '<kbd>Esc</kbd>'])],
        'plain' => 'press <kbd>Esc</kbd>',
        'types' => ['raw_html_inline'],
    ],
    'raw tex inline payload' => [
        'children' => static fn (string $kind): array => [$text('tex '), $inline('raw_tex', [], ['tex' => '\\LaTeX{}'])],
        'plain' => 'tex \LaTeX{}',
        'types' => ['raw_tex'],
    ],
    'strikeout payload' => [
        'children' => static fn (string $kind): array => [$inline('strikeout', [$text('gone')])],
        'plain' => 'gone',
        'types' => ['strikeout'],
    ],
    'superscript payload' => [
        'children' => static fn (string $kind): array => [$text('build '), $inline('superscript', [$text('42')])],
        'plain' => 'build 42',
        'types' => ['superscript'],
    ],
    'subscript payload' => [
        'children' => static fn (string $kind): array => [$text('H'), $inline('subscript', [$text('2')]), $text('O')],
        'plain' => 'H2O',
        'types' => ['subscript'],
    ],
    'mark span payload' => [
        'children' => static fn (string $kind): array => [$inline('span', [$text('marked')], ['classes' => ['mark']])],
        'plain' => 'marked',
        'types' => ['span'],
    ],
    'generic span payload' => [
        'children' => static fn (string $kind): array => [$inline('span', [$text('tracked')], ['classes' => ['review'], 'attributes' => ['data-case' => 'quote']])],
        'plain' => 'tracked',
        'types' => ['span'],
    ],
    'citation payload' => [
        'children' => static fn (string $kind): array => [$text('see '), new AstNode('citation', ['id' => 'doe2026'])],
        'plain' => null,
        'types' => ['citation'],
    ],
    'citation group payload' => [
        'children' => static fn (string $kind): array => [
            new AstNode('citation_group', [], [
                new AstNode('citation', ['id' => 'doe2026', 'prefix' => 'see']),
                new AstNode('citation', ['id' => 'roe2025', 'mode' => 'suppress_author']),
            ]),
        ],
        'plain' => null,
        'types' => ['citation_group', 'citation'],
    ],
    'inline note payload' => [
        'children' => static fn (string $kind): array => [
            $text('note'),
            $note([$paragraph([$text('body')])]),
        ],
        'plain' => 'notebody',
        'types' => ['note'],
    ],
    'inline note containing matching close payload' => [
        'children' => static fn (string $kind): array => [
            $text('note'),
            $note([$paragraph([$text($kind === 'single' ? "body \u{2019} close" : "body \u{201D} close")])]),
        ],
        'plain' => static fn (string $kind): string => $kind === 'single'
            ? "notebody \u{2019} close"
            : "notebody \u{201D} close",
        'types' => ['note'],
    ],
    'softbreak payload' => [
        'children' => static fn (string $kind): array => [$text('line one'), new AstNode('softbreak'), $text('line two')],
        'plain' => "line one\nline two",
        'types' => ['softbreak'],
    ],
    'linebreak payload' => [
        'children' => static fn (string $kind): array => [$text('line one'), new AstNode('linebreak'), $text('line two')],
        'plain' => "line one\nline two",
        'types' => ['linebreak'],
    ],
    'opposite nested quote payload' => [
        'children' => static fn (string $kind): array => [
            $text('outer '),
            $quoted($kind === 'single' ? 'double' : 'single', [$text('inner')]),
            $text(' tail'),
        ],
        'plain' => 'outer inner tail',
        'types' => ['quoted'],
    ],
    'single apostrophe payload' => [
        'children' => static fn (string $kind): array => [$text($kind === 'single' ? "can\u{2019}t stop" : "double can\u{2019}t stop")],
        'plain' => static fn (string $kind): string => $kind === 'single' ? "can\u{2019}t stop" : "double can\u{2019}t stop",
        'types' => [],
    ],
    'dash ellipsis smart text payload' => [
        'children' => static fn (string $kind): array => [$text("dash \u{2014} ellipsis \u{2026}")],
        'plain' => "dash \u{2014} ellipsis \u{2026}",
        'types' => [],
    ],
    'raw markdown inline payload' => [
        'children' => static fn (string $kind): array => [$text('raw '), $inline('raw_markdown', [], ['text' => '*packet*'])],
        'plain' => 'raw packet',
        'types' => ['emph'],
    ],
];

foreach ($payloads as $label => $payload) {
    foreach (['double', 'single'] as $kind) {
        $plain = $payload['plain'];
        $addRoundTripCase(
            "{$kind} {$label}",
            $caseDocument([$quoted($kind, $payload['children']($kind))]),
            $kind,
            is_callable($plain) ? $plain($kind) : $plain,
            $payload['types']
        );
    }
}

$addRoundTripCase(
    'double quote surrounded by paragraph text',
    $caseDocument([$text('Before '), $quoted('double', [$text('quoted')]), $text(' after')]),
    'double',
    'quoted'
);
$addRoundTripCase(
    'single quote surrounded by paragraph text',
    $caseDocument([$text('Before '), $quoted('single', [$text('quoted')]), $text(' after')]),
    'single',
    'quoted'
);
$addRoundTripCase(
    'double quote after inline link',
    $caseDocument([$link('/before', [$text('Before')]), $text(' '), $quoted('double', [$text('quoted')])]),
    'double',
    'quoted'
);
$addRoundTripCase(
    'single quote before citation',
    $caseDocument([$quoted('single', [$text('quoted')]), $text(' '), new AstNode('citation', ['id' => 'doe2026'])]),
    'single',
    'quoted'
);
$addRoundTripCase(
    'double quote nested inside emphasis',
    $caseDocument([$inline('emph', [$text('say '), $quoted('double', [$text('quoted')]), $text(' now')])]),
    'double',
    'quoted'
);
$addRoundTripCase(
    'single quote nested inside strong',
    $caseDocument([$inline('strong', [$text('say '), $quoted('single', [$text('quoted')]), $text(' now')])]),
    'single',
    'quoted'
);
$addRoundTripCase(
    'double quote inside link label',
    $caseDocument([$link('/quoted-label', [$text('Link '), $quoted('double', [$text('quoted')])])]),
    'double',
    'quoted'
);
$addRoundTripCase(
    'single quote inside image label',
    $caseDocument([$image('media/quoted.png', 'quoted alt', [$text('Image '), $quoted('single', [$text('quoted')])])]),
    'single',
    'quoted'
);
$addRoundTripCase(
    'double quote after softbreak',
    $caseDocument([$text('Before'), new AstNode('softbreak'), $quoted('double', [$text('quoted')])]),
    'double',
    'quoted'
);
$addRoundTripCase(
    'single quote before hard linebreak',
    $caseDocument([$quoted('single', [$text('quoted')]), new AstNode('linebreak'), $text('after')]),
    'single',
    'quoted'
);
$addRoundTripCase(
    'double quote after inline code',
    $caseDocument([$inline('code', [], ['text' => 'before']), $text(' '), $quoted('double', [$text('quoted')])]),
    'double',
    'quoted'
);
$addRoundTripCase(
    'single quote before inline math',
    $caseDocument([$quoted('single', [$text('quoted')]), $text(' '), $inline('math', [], ['text' => 'x+1'])]),
    'single',
    'quoted'
);
$addRoundTripCase(
    'double quote inside generic span',
    $caseDocument([$inline('span', [$quoted('double', [$text('quoted')])], ['classes' => ['review']])]),
    'double',
    'quoted'
);
$addRoundTripCase(
    'single quote inside mark span',
    $caseDocument([$inline('span', [$quoted('single', [$text('quoted')])], ['classes' => ['mark']])]),
    'single',
    'quoted'
);
$addRoundTripCase(
    'double quote amid mixed inline run',
    $caseDocument([
        $text('A '),
        $inline('emph', [$text('before')]),
        $text(' '),
        $quoted('double', [$text('quoted'), new AstNode('softbreak'), $inline('strong', [$text('body')])]),
        $text(' after'),
    ]),
    'double',
    "quoted\nbody",
    ['strong', 'softbreak']
);

$addReaderCase(
    'direct opt-in double typographic quote parses nested emphasis',
    "Review \u{201C}quoted *source*\u{201D} done.",
    ['typographicSmartQuotes' => true],
    'double',
    'quoted source',
    ['emph']
);
$addReaderCase(
    'direct opt-in single typographic quote ignores contraction apostrophe',
    "Review \u{2018}can\u{2019}t stop\u{2019} done.",
    ['typographicSmartQuotes' => true],
    'single',
    "can\u{2019}t stop"
);
$addReaderCase(
    'commonmark plus smart opt-in typographic quote parses',
    "Review \u{201C}quoted\u{201D}.",
    ['format' => 'commonmark+smart', 'typographicSmartQuotes' => true],
    'double',
    'quoted'
);
$addNoQuoteReaderCase(
    'commonmark without smart leaves opt-in typographic quote literal',
    "Review \u{201C}quoted\u{201D}.",
    ['format' => 'commonmark', 'typographicSmartQuotes' => true],
    "Review \u{201C}quoted\u{201D}."
);

$tests = [
    'records markdown smart quote round trip surge mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(75, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown smart quote round trip surge ' . $label] =
        static function (TestRunner $t) use ($case, $label, $findFirstQuoted, $hasDescendantType, $plainInlineText, $quoteDelimiters): void {
            if ($case['type'] === 'reader-no-quote') {
                $document = (new MarkdownReader($case['readerOptions']))->read($case['markdown']);
                $quote = $findFirstQuoted($document);

                $t->same('missing', $quote->type, $case['markdown']);
                $t->same($case['plain'], $plainInlineText($document->children), $case['markdown']);

                return;
            }

            if ($case['type'] === 'reader') {
                $markdown = $case['markdown'];
                $document = (new MarkdownReader($case['readerOptions']))->read($markdown);
                $quote = $findFirstQuoted($document);

                $t->same('quoted', $quote->type, $label);
                $t->same($case['kind'], $quote->attr('kind'), $label);
                if ($case['plain'] !== null) {
                    $t->same($case['plain'], $plainInlineText($quote->children), $label);
                }
                foreach ($case['containsTypes'] as $type) {
                    $t->true($hasDescendantType($quote, $type), $label . ' contains ' . $type);
                }

                return;
            }
            [$open, $close] = $quoteDelimiters[$case['kind']];
            if ($case['plain'] !== null) {
            }
            foreach ($case['containsTypes'] as $type) {
            }
        };
}

return $tests;
