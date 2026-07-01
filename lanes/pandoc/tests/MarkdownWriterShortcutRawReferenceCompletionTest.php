<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$link = static fn (): AstNode => new AstNode('link', [
    'url' => '/url',
    'title' => '',
], [$text('link')]);
$raw = static fn (string $markdown): AstNode => new AstNode('raw_inline', [
    'format' => 'markdown',
    'text' => $markdown,
]);
$document = static fn (array $children): AstNode => new AstNode('document', [], [
    new AstNode('paragraph', [], $children),
]);

$cases = [
    'raw inline immediately after reference link' => [
        'children' => [$link(), $raw('[rawText]')],
        'expected' => "[link][][rawText]\n\n  [link]: /url",
    ],
    'space node and raw inline after reference link' => [
        'children' => [$link(), $space(), $raw('[rawText]')],
        'expected' => "[link][] [rawText]\n\n  [link]: /url",
    ],
    'raw inline with leading space after reference link' => [
        'children' => [$link(), $raw(' [rawText]')],
        'expected' => "[link][] [rawText]\n\n  [link]: /url",
    ],
];

$tests = [
    'records markdown writer shortcut raw reference completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(3, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer shortcut raw reference completion ' . $label] =
        static function (TestRunner $t) use ($case, $document): void {
            $markdown = (new MarkdownWriter(['referenceLinks' => true]))->write($document($case['children']));

            $t->same($case['expected'], $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $paragraph = $roundTrip->children[0] ?? new AstNode('missing');
            $link = $paragraph->children[0] ?? new AstNode('missing');

            $t->same('link', $link->type);
            $t->same('/url', $link->attr('url'));
        };
}

return $tests;
