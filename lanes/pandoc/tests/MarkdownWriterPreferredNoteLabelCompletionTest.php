<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$note = static fn (array $attrs, array $children = []): AstNode => new AstNode('note', $attrs, $children);
$noteBody = static fn (string $value): array => [$paragraph([$text($value)])];

$cases = [
    'preferred label is reused for marker and definition' => [
        'document' => $document([
            $paragraph([
                $text('note'),
                $note(['label' => 'review'], $noteBody('body')),
            ]),
        ]),
        'expected' => "note[^review]\n\n[^review]: body",
    ],
    'duplicate preferred labels get stable suffixes' => [
        'document' => $document([
            $paragraph([
                $note(['label' => 'review'], $noteBody('one')),
                $text(' and '),
                $note(['label' => 'review'], $noteBody('two')),
            ]),
        ]),
        'expected' => "[^review] and [^review-2]\n\n[^review]: one\n\n[^review-2]: two",
    ],
    'case-folded preferred labels collide' => [
        'document' => $document([
            $paragraph([
                $note(['label' => 'Review'], $noteBody('one')),
                $text(' and '),
                $note(['label' => 'review'], $noteBody('two')),
            ]),
        ]),
        'expected' => "[^Review] and [^review-2]\n\n[^Review]: one\n\n[^review-2]: two",
    ],
    'unsafe preferred labels fall back to generated numbers' => [
        'document' => $document([
            $paragraph([
                $note(['label' => 'bad[label'], $noteBody('left')),
                $text(' '),
                $note(['label' => 'bad label'], $noteBody('space')),
            ]),
        ]),
        'expected' => "[^1] [^2]\n\n[^1]: left\n\n[^2]: space",
    ],
    'numeric preferred labels reserve generated labels' => [
        'document' => $document([
            $paragraph([
                $note(['label' => '1'], $noteBody('preferred one')),
                $text(' '),
                $note([], $noteBody('generated two')),
            ]),
        ]),
        'expected' => "[^1] [^2]\n\n[^1]: preferred one\n\n[^2]: generated two",
    ],
    'note label aliases feed the same allocator' => [
        'document' => $document([
            $paragraph([
                $note(['noteLabel' => 'alias'], $noteBody('note label')),
                $text(' '),
                $note(['identifier' => 'alias'], $noteBody('identifier')),
            ]),
        ]),
        'expected' => "[^alias] [^alias-2]\n\n[^alias]: note label\n\n[^alias-2]: identifier",
    ],
];

$tests = [
    'records markdown writer preferred note label completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(6, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer preferred note label completion ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter())->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
