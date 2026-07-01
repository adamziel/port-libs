<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$math = static fn (string $value, array $attrs = []): AstNode => new AstNode('math', ['text' => $value] + $attrs);

$cases = [
    'inline math escapes dollar body' => [
        'document' => $document([$math('price $5')]),
        'expected' => '$price \\$5$',
    ],
    'display math escapes dollar body before attributes' => [
        'document' => $document([$math('x $ y', ['display' => true, 'id' => 'eq-review'])]),
        'expected' => '$$x \\$ y$${#eq-review}',
    ],
    'inline math escapes adjacent dollar run' => [
        'document' => $document([$math('a $$ b')]),
        'expected' => '$a \\$\\$ b$',
    ],
    'inline math keeps class attributes' => [
        'document' => $document([$math('x + y', ['classes' => ['math']])]),
        'expected' => '$x + y${.math}',
    ],
    'display math keeps id class and data attributes' => [
        'document' => $document([
            $math('x = y', [
                'display' => true,
                'id' => 'eq',
                'classes' => ['math'],
                'attributes' => ['data-source' => 'surge'],
            ]),
        ]),
        'expected' => '$$x = y$${#eq .math data-source="surge"}',
    ],
];

$tests = [
    'records markdown writer math delimiter attribute completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(5, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer math delimiter attribute completion ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter())->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
