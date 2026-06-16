<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (string $value): AstNode => new AstNode('plain', [], [new AstNode('text', ['text' => $value])]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$findFirst = static function (AstNode $node, string $type) use (&$findFirst): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirst($child, $type);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$plainText = static function (AstNode $node) use (&$plainText): string {
    if (in_array($node->type, ['text', 'code'], true)) {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return trim((string) preg_replace('/\s+/', ' ', $text));
};

$read = static fn (string $format, string $markdown): AstNode =>
    (new MarkdownReader(['format' => $format]))->read($markdown);

$assertAttributeTuple = static function (TestRunner $t, AstNode $node, string $id, string $class, string $key, string $value, string $label): void {
    $t->same($id, $node->attr('id', ''), $label . ' id');
    $t->true(in_array($class, $node->attr('classes', []), true), $label . ' class');
    $t->same($value, $node->attr('attributes', [])[$key] ?? null, $label . ' attribute');
};

$assertNoAttributeTuple = static function (TestRunner $t, AstNode $node, string $id, string $class, string $key, string $label): void {
    $t->same('', $node->attr('id', ''), $label . ' id disabled');
    $t->same(false, in_array($class, $node->attr('classes', []), true), $label . ' class disabled');
    $t->same(false, array_key_exists($key, $node->attr('attributes', [])), $label . ' attribute disabled');
};

$readerCodeFormats = [
    'markdown default' => ['format' => 'markdown', 'enabled' => true],
    'pandoc alias default' => ['format' => 'pandoc', 'enabled' => true],
    'commonmark_x default' => ['format' => 'commonmark_x', 'enabled' => true],
    'commonmark inline_code_attributes' => ['format' => 'commonmark+inline_code_attributes', 'enabled' => true],
    'gfm inline_code_attributes' => ['format' => 'gfm+inline_code_attributes', 'enabled' => true],
    'strict inline_code_attributes' => ['format' => 'markdown_strict+inline_code_attributes', 'enabled' => true],
    'commonmark attributes umbrella' => ['format' => 'commonmark+attributes', 'enabled' => true],
    'gfm attributes umbrella' => ['format' => 'gfm+attributes', 'enabled' => true],
    'commonmark legacy inline_attributes' => ['format' => 'commonmark+inline_attributes', 'enabled' => true],
    'commonmark default disabled' => ['format' => 'commonmark', 'enabled' => false],
    'gfm default disabled' => ['format' => 'gfm', 'enabled' => false],
    'strict default disabled' => ['format' => 'markdown_strict', 'enabled' => false],
    'phpextra default disabled' => ['format' => 'markdown_phpextra', 'enabled' => false],
    'mmd default disabled' => ['format' => 'markdown_mmd', 'enabled' => false],
    'markdown inline_code_attributes disabled' => ['format' => 'markdown-inline_code_attributes', 'enabled' => false],
];

$readerLinkFormats = [
    'markdown default' => ['format' => 'markdown', 'enabled' => true],
    'pandoc alias default' => ['format' => 'pandoc', 'enabled' => true],
    'commonmark_x default' => ['format' => 'commonmark_x', 'enabled' => true],
    'commonmark link_attributes' => ['format' => 'commonmark+link_attributes', 'enabled' => true],
    'gfm link_attributes' => ['format' => 'gfm+link_attributes', 'enabled' => true],
    'strict link_attributes' => ['format' => 'markdown_strict+link_attributes', 'enabled' => true],
    'commonmark attributes umbrella' => ['format' => 'commonmark+attributes', 'enabled' => true],
    'gfm attributes umbrella' => ['format' => 'gfm+attributes', 'enabled' => true],
    'commonmark legacy inline_attributes' => ['format' => 'commonmark+inline_attributes', 'enabled' => true],
    'phpextra default' => ['format' => 'markdown_phpextra', 'enabled' => true],
    'commonmark default disabled' => ['format' => 'commonmark', 'enabled' => false],
    'gfm default disabled' => ['format' => 'gfm', 'enabled' => false],
    'strict default disabled' => ['format' => 'markdown_strict', 'enabled' => false],
    'mmd default disabled' => ['format' => 'markdown_mmd', 'enabled' => false],
    'markdown link_attributes disabled' => ['format' => 'markdown-link_attributes', 'enabled' => false],
];

$readerLinkFixtures = [
    'inline link' => [
        'markdown' => '[review](https://example.test){#link-id .linked data-kind=profile}',
        'type' => 'link',
        'id' => 'link-id',
        'class' => 'linked',
    ],
    'inline image' => [
        'markdown' => '![diagram](media/diagram.png){#image-id .illustrated data-kind=profile}',
        'type' => 'image',
        'id' => 'image-id',
        'class' => 'illustrated',
    ],
    'angle autolink' => [
        'markdown' => '<https://example.test/profile>{#auto-id .autolinked data-kind=profile}',
        'type' => 'link',
        'id' => 'auto-id',
        'class' => 'autolinked',
    ],
];

$readerExampleFormats = [
    'markdown default' => ['format' => 'markdown', 'enabled' => true],
    'pandoc alias default' => ['format' => 'pandoc', 'enabled' => true],
    'commonmark_x default' => ['format' => 'commonmark_x', 'enabled' => true],
    'commonmark example_lists' => ['format' => 'commonmark+example_lists', 'enabled' => true],
    'commonmark numbered_examples' => ['format' => 'commonmark+numbered_examples', 'enabled' => true],
    'gfm numbered_example_list alias' => ['format' => 'gfm+numbered_example_list', 'enabled' => true],
    'strict numbered_example_lists alias' => ['format' => 'markdown_strict+numbered_example_lists', 'enabled' => true],
    'commonmark default disabled' => ['format' => 'commonmark', 'enabled' => false],
    'gfm default disabled' => ['format' => 'gfm', 'enabled' => false],
    'markdown numbered examples disabled' => ['format' => 'markdown-numbered_examples', 'enabled' => false],
];

$writerCodeFormats = [
    'markdown default' => ['format' => 'markdown', 'enabled' => true],
    'pandoc alias default' => ['format' => 'pandoc', 'enabled' => true],
    'commonmark_x default' => ['format' => 'commonmark_x', 'enabled' => true],
    'commonmark inline_code_attributes' => ['format' => 'commonmark+inline_code_attributes', 'enabled' => true],
    'gfm inline_code_attributes' => ['format' => 'gfm+inline_code_attributes', 'enabled' => true],
    'commonmark attributes umbrella' => ['format' => 'commonmark+attributes', 'enabled' => true],
    'strict attributes umbrella' => ['format' => 'markdown_strict+attributes', 'enabled' => true],
    'commonmark default disabled' => ['format' => 'commonmark', 'enabled' => false],
    'gfm default disabled' => ['format' => 'gfm', 'enabled' => false],
    'strict default disabled' => ['format' => 'markdown_strict', 'enabled' => false],
    'phpextra default disabled' => ['format' => 'markdown_phpextra', 'enabled' => false],
    'mmd default disabled' => ['format' => 'markdown_mmd', 'enabled' => false],
    'markdown inline_code_attributes disabled' => ['format' => 'markdown-inline_code_attributes', 'enabled' => false],
];

$writerLinkFormats = [
    'markdown default' => ['format' => 'markdown', 'enabled' => true],
    'pandoc alias default' => ['format' => 'pandoc', 'enabled' => true],
    'commonmark_x default' => ['format' => 'commonmark_x', 'enabled' => true],
    'commonmark link_attributes' => ['format' => 'commonmark+link_attributes', 'enabled' => true],
    'gfm link_attributes' => ['format' => 'gfm+link_attributes', 'enabled' => true],
    'commonmark attributes umbrella' => ['format' => 'commonmark+attributes', 'enabled' => true],
    'strict attributes umbrella' => ['format' => 'markdown_strict+attributes', 'enabled' => true],
    'phpextra default' => ['format' => 'markdown_phpextra', 'enabled' => true],
    'commonmark default disabled' => ['format' => 'commonmark', 'enabled' => false],
    'gfm default disabled' => ['format' => 'gfm', 'enabled' => false],
    'strict default disabled' => ['format' => 'markdown_strict', 'enabled' => false],
    'mmd default disabled' => ['format' => 'markdown_mmd', 'enabled' => false],
    'markdown link_attributes disabled' => ['format' => 'markdown-link_attributes', 'enabled' => false],
];

$writerExampleFormats = [
    'commonmark example_lists' => ['format' => 'commonmark+example_lists', 'enabled' => true],
    'commonmark numbered_examples' => ['format' => 'commonmark+numbered_examples', 'enabled' => true],
    'gfm numbered_examples' => ['format' => 'gfm+numbered_examples', 'enabled' => true],
    'strict numbered_example alias' => ['format' => 'markdown_strict+numbered_example', 'enabled' => true],
    'strict numbered_example_list alias' => ['format' => 'markdown_strict+numbered_example_list', 'enabled' => true],
    'phpextra numbered_example_lists alias' => ['format' => 'markdown_phpextra+numbered_example_lists', 'enabled' => true],
    'commonmark default disabled' => ['format' => 'commonmark', 'enabled' => false],
    'gfm default disabled' => ['format' => 'gfm', 'enabled' => false],
    'markdown numbered examples disabled' => ['format' => 'markdown-numbered_examples', 'enabled' => false],
];

$tests = [
    'records markdown profile attribute extension harvest mapped-case count' =>
        static function (TestRunner $t) use (
            $readerCodeFormats,
            $readerLinkFormats,
            $readerLinkFixtures,
            $readerExampleFormats,
            $writerCodeFormats,
            $writerLinkFormats,
            $writerExampleFormats
        ): void {
            $t->same(
                105,
                count($readerCodeFormats)
                    + count($readerLinkFormats) * count($readerLinkFixtures)
                    + count($readerExampleFormats)
                    + count($writerCodeFormats)
                    + count($writerLinkFormats)
                    + count($writerExampleFormats)
            );
        },
];

foreach ($readerCodeFormats as $label => $case) {
    $tests['maps upstream markdown reader inline code attribute extension ' . $label] =
        static function (TestRunner $t) use ($assertAttributeTuple, $assertNoAttributeTuple, $case, $findFirst, $plainText, $read): void {
            $markdown = 'Use `packet`{#code-id .source data-kind=profile} now.';
            $document = $read($case['format'], $markdown);
            $code = $findFirst($document, 'code');

            $t->same('code', $code->type, $case['format']);
            if ($case['enabled']) {
                $assertAttributeTuple($t, $code, 'code-id', 'source', 'data-kind', 'profile', $case['format']);
            } else {
                $assertNoAttributeTuple($t, $code, 'code-id', 'source', 'data-kind', $case['format']);
                $t->contains('{#code-id .source data-kind=profile}', $plainText($document), $case['format']);
            }
        };
}

foreach ($readerLinkFormats as $formatLabel => $case) {
    foreach ($readerLinkFixtures as $fixtureLabel => $fixture) {
        $tests['maps upstream markdown reader link attribute extension ' . $formatLabel . ' ' . $fixtureLabel] =
            static function (TestRunner $t) use ($assertAttributeTuple, $assertNoAttributeTuple, $case, $findFirst, $fixture, $plainText, $read): void {
                $document = $read($case['format'], $fixture['markdown']);
                $node = $findFirst($document, $fixture['type']);
                $attributeNode = $fixture['type'] === 'image'
                    ? new AstNode('figure_attributes', $node->attr('figureAttributes', []))
                    : $node;

                $t->same($fixture['type'], $node->type, $case['format']);
                if ($case['enabled']) {
                    $assertAttributeTuple($t, $attributeNode, $fixture['id'], $fixture['class'], 'data-kind', 'profile', $case['format']);
                } else {
                    $assertNoAttributeTuple($t, $attributeNode, $fixture['id'], $fixture['class'], 'data-kind', $case['format']);
                    $t->contains('data-kind=profile', $plainText($document), $case['format']);
                }
            };
    }
}

foreach ($readerExampleFormats as $label => $case) {
    $tests['maps upstream markdown reader numbered example extension alias ' . $label] =
        static function (TestRunner $t) use ($case, $findFirst, $plainText, $read): void {
            $document = $read($case['format'], '(@profile-a) Example packet');
            $list = $findFirst($document, 'ordered_list');

            if ($case['enabled']) {
                $t->same('ordered_list', $list->type, $case['format']);
                $t->same('example', $list->attr('style'), $case['format']);
                $t->same('profile-a', ($list->children[0] ?? new AstNode('missing'))->attr('exampleLabel'), $case['format']);
            } else {
                $t->same('missing', $list->type, $case['format']);
                $t->contains('(@profile-a) Example packet', $plainText($document), $case['format']);
            }
        };
}

foreach ($writerCodeFormats as $label => $case) {
    $tests['maps upstream markdown writer inline code attribute extension ' . $label] =
        static function (TestRunner $t) use ($case, $document, $paragraph, $text): void {
            $markdown = (new MarkdownWriter(['format' => $case['format']]))->write($document([
                $paragraph([
                    $text('Use '),
                    new AstNode('code', [
                        'text' => 'packet',
                        'id' => 'code-id',
                        'classes' => ['source'],
                        'attributes' => ['data-kind' => 'profile'],
                    ]),
                    $text(' now.'),
                ]),
            ]));

            $tupleEnabled = str_contains($markdown, '`packet`{#code-id');

            $t->same($case['enabled'], $tupleEnabled, $case['format'] . ' markdown tuple');
            if ($case['enabled']) {
                $t->contains('.source', $markdown, $case['format'] . ' class');
                $t->contains('data-kind', $markdown, $case['format'] . ' attribute');
            }
        };
}

foreach ($writerLinkFormats as $label => $case) {
    $tests['maps upstream markdown writer link attribute extension ' . $label] =
        static function (TestRunner $t) use ($case, $document, $paragraph, $text): void {
            $markdown = (new MarkdownWriter(['format' => $case['format']]))->write($document([
                $paragraph([
                    $text('Visit '),
                    new AstNode('link', [
                        'url' => 'https://example.test/profile',
                        'id' => 'link-id',
                        'classes' => ['linked'],
                        'attributes' => ['data-kind' => 'profile'],
                    ], [$text('profile')]),
                    $text('.'),
                ]),
            ]));

            $tupleEnabled = str_contains($markdown, '](https://example.test/profile){#link-id');

            $t->same($case['enabled'], $tupleEnabled, $case['format'] . ' markdown tuple');
            if ($case['enabled']) {
                $t->contains('.linked', $markdown, $case['format'] . ' class');
                $t->contains('data-kind', $markdown, $case['format'] . ' attribute');
            }
        };
}

foreach ($writerExampleFormats as $label => $case) {
    $tests['maps upstream markdown writer numbered example extension alias ' . $label] =
        static function (TestRunner $t) use ($case, $document, $plain): void {
            $markdown = (new MarkdownWriter(['format' => $case['format']]))->write($document([
                new AstNode('ordered_list', ['style' => 'example'], [
                    new AstNode('list_item', ['exampleLabel' => 'profile-a'], [$plain('Example packet')]),
                ]),
            ]));

            if ($case['enabled']) {
                $t->same('(@profile-a) Example packet', $markdown, $case['format']);
            } else {
                $t->same('1.  Example packet', $markdown, $case['format']);
            }
        };
}

return $tests;
