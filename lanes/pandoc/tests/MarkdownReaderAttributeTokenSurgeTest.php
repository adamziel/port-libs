<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$assertAttributes = static function (TestRunner $t, array $attrs, string $id, string $class, string $value, string $context, bool $html = true): void {
    $t->same($id, $attrs['id'] ?? null, $context . ' id');
    $t->same([$class], $attrs['classes'] ?? [], $context . ' classes');

    $attributes = $attrs['attributes'] ?? [];
    $t->true(is_array($attributes), $context . ' attributes should be an array');
    $t->same($value, $attributes['data-token'] ?? null, $context . ' data-token');

    if (!$html) {
        return;
    }

    $htmlAttributes = $attrs['htmlAttributes'] ?? [];
    $t->true(is_array($htmlAttributes), $context . ' html attributes should be an array');
    $t->same($id, $htmlAttributes['id'] ?? null, $context . ' html id');
    $t->same($class, $htmlAttributes['class'] ?? null, $context . ' html class');
    $t->same($value, $htmlAttributes['data-token'] ?? null, $context . ' html data-token');
};

$attributeSpec = static function (string $source): string {
    return '{#id' . $source . 'token .class' . $source . 'token data-token="value ' . $source . ' token"}';
};

$readSpanWithSpec = static function (string $spec) use ($findFirstNode): AstNode {
    return $findFirstNode((new MarkdownReader())->read('[token]' . $spec), 'span');
};

$escapedPunctuationCases = [
    'bang' => ['\\!', '!'],
    'double quote' => ['\\"', '"'],
    'hash' => ['\\#', '#'],
    'dollar' => ['\\$', '$'],
    'percent' => ['\\%', '%'],
    'ampersand' => ['\\&', '&'],
    'apostrophe' => ["\\'", "'"],
    'open paren' => ['\\(', '('],
    'close paren' => ['\\)', ')'],
    'asterisk' => ['\\*', '*'],
    'plus' => ['\\+', '+'],
    'comma' => ['\\,', ','],
    'minus' => ['\\-', '-'],
    'period' => ['\\.', '.'],
    'slash' => ['\\/', '/'],
    'colon' => ['\\:', ':'],
    'semicolon' => ['\\;', ';'],
    'less than' => ['\\<', '<'],
    'equals' => ['\\=', '='],
    'greater than' => ['\\>', '>'],
    'question' => ['\\?', '?'],
    'at sign' => ['\\@', '@'],
    'caret' => ['\\^', '^'],
    'underscore' => ['\\_', '_'],
    'backtick' => ['\\`', '`'],
    'open brace' => ['\\{', '{'],
    'pipe' => ['\\|', '|'],
    'close brace' => ['\\}', '}'],
    'tilde' => ['\\~', '~'],
];

$entityCases = [
    'amp entity' => ['&amp;', '&'],
    'less-than entity' => ['&lt;', '<'],
    'greater-than entity' => ['&gt;', '>'],
    'quote entity' => ['&quot;', '"'],
    'apostrophe entity' => ['&apos;', "'"],
    'copyright entity' => ['&copy;', "\u{00A9}"],
    'registered entity' => ['&reg;', "\u{00AE}"],
    'euro entity' => ['&euro;', "\u{20AC}"],
    'lambda hex entity' => ['&#x3bb;', "\u{03BB}"],
    'lambda decimal entity' => ['&#955;', "\u{03BB}"],
    'mdash entity' => ['&mdash;', "\u{2014}"],
    'ellipsis entity' => ['&hellip;', "\u{2026}"],
    'trade entity' => ['&trade;', "\u{2122}"],
    'plus-minus entity' => ['&plusmn;', "\u{00B1}"],
    'times entity' => ['&times;', "\u{00D7}"],
    'division entity' => ['&divide;', "\u{00F7}"],
    'section entity' => ['&sect;', "\u{00A7}"],
    'paragraph entity' => ['&para;', "\u{00B6}"],
    'middle-dot entity' => ['&middot;', "\u{00B7}"],
    'bullet entity' => ['&bull;', "\u{2022}"],
    'ndash entity' => ['&ndash;', "\u{2013}"],
];

return [
    'maps pandoc attribute tuple escaped and entity id class token surge' => static function (TestRunner $t) use (
        $assertAttributes,
        $attributeSpec,
        $escapedPunctuationCases,
        $entityCases,
        $readSpanWithSpec
    ): void {
        $cases = array_merge($escapedPunctuationCases, $entityCases);
        $mapped = 0;

        foreach ($cases as $name => [$source, $expected]) {
            $spec = $attributeSpec($source);
            $span = $readSpanWithSpec($spec);
            $expectedId = 'id' . $expected . 'token';
            $expectedClass = 'class' . $expected . 'token';
            $expectedValue = 'value ' . $expected . ' token';

            $t->same('span', $span->type, $name . ' should parse as an attributed span');
            $assertAttributes($t, $span->attrs, $expectedId, $expectedClass, $expectedValue, $name);

            $document = new AstNode('document', [], [
                new AstNode('paragraph', [], [$span]),
            ]);
            $mapped++;
        }

        $t->same(50, $mapped);
    },

    'applies decoded attribute tokens across markdown tuple parse sites' => static function (TestRunner $t) use ($assertAttributes, $attributeSpec, $findFirstNode): void {
        $spec = $attributeSpec('\\#');
        $expectedId = 'id#token';
        $expectedClass = 'class#token';
        $expectedValue = 'value # token';
        $reader = new MarkdownReader();

        $sites = [
            'span' => [
                'markdown' => '[token]' . $spec,
                'type' => 'span',
                'attrs' => static fn (AstNode $node): array => $node->attrs,
                'html' => true,
            ],
            'heading' => [
                'markdown' => '## token ' . $spec,
                'type' => 'heading',
                'attrs' => static fn (AstNode $node): array => $node->attrs,
                'html' => false,
            ],
            'fenced code' => [
                'markdown' => "``` " . $spec . "\ncode\n```",
                'type' => 'code_block',
                'attrs' => static fn (AstNode $node): array => $node->attrs,
                'html' => false,
            ],
            'link' => [
                'markdown' => '[token](/target)' . $spec,
                'type' => 'link',
                'attrs' => static fn (AstNode $node): array => $node->attrs,
                'html' => true,
            ],
            'image' => [
                'markdown' => '![token](image.png)' . $spec,
                'type' => 'image',
                'attrs' => static fn (AstNode $node): array => $node->attr('figureAttributes', []),
                'html' => true,
            ],
            'math' => [
                'markdown' => '$x$' . $spec,
                'type' => 'math',
                'attrs' => static fn (AstNode $node): array => $node->attrs,
                'html' => true,
            ],
        ];

        foreach ($sites as $name => $case) {
            $node = $findFirstNode($reader->read($case['markdown']), $case['type']);
            $t->same($case['type'], $node->type, $name . ' node type');
            $assertAttributes(
                $t,
                $case['attrs']($node),
                $expectedId,
                $expectedClass,
                $expectedValue,
                $name,
                $case['html']
            );
        }
    },
];
