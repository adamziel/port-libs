<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$str = static fn (string $text): array => ['t' => 'Str', 'c' => $text];
$space = static fn (): array => ['t' => 'Space'];
$emph = static fn (array $children): array => ['t' => 'Emph', 'c' => $children];
$strong = static fn (array $children): array => ['t' => 'Strong', 'c' => $children];
$code = static fn (string $text): array => ['t' => 'Code', 'c' => [['', [], []], $text]];
$document = static fn (AstNode $citation): AstNode => new AstNode('document', [], [
    new AstNode('paragraph', [], [$citation]),
]);

$modeConstructor = static fn (string $mode): string => match ($mode) {
    'author_in_text' => 'AuthorInText',
    'suppress_author' => 'SuppressAuthor',
    default => 'NormalCitation',
};

$record = static fn (string $id, string $mode, array $prefix, array $suffix): array => [
    'citationId' => $id,
    'citationPrefix' => $prefix,
    'citationSuffix' => $suffix,
    'citationMode' => ['t' => $modeConstructor($mode)],
    'citationNoteNum' => 0,
    'citationHash' => 0,
];

$citation = static function (string $placement, string $id, string $mode, array $prefix, array $suffix) use ($modeConstructor, $record): AstNode {
    $native = $record($id, $mode, $prefix, $suffix);

    if ($placement === 'tagged') {
        return new AstNode('citation', [
            'citationNative' => ['t' => 'Citation', 'c' => [$native]],
        ]);
    }

    if ($placement === 'split') {
        return new AstNode('citation', [
            'citationNative' => $record($id, 'normal', [], []),
            'citationModeNative' => ['t' => $modeConstructor($mode)],
            'citationPrefixNative' => $prefix,
            'citationSuffixNative' => $suffix,
        ]);
    }

    if ($placement === 'wrapped') {
        return new AstNode('citation', [
            'citationNative' => $record($id, $mode, [$prefix], [$suffix]),
        ]);
    }

    return new AstNode('citation', [
        'citationNative' => $native,
    ]);
};

$ids = [
    'simple' => ['id' => 'doe2026', 'token' => '@doe2026'],
    'space' => ['id' => 'doe 2026', 'token' => '@{doe 2026}'],
    'brace' => ['id' => 'doe}2026', 'token' => '@{doe\\}2026}'],
    'bracket' => ['id' => 'doe]2026', 'token' => '@{doe\\]2026}'],
    'slash' => ['id' => 'archive/source', 'token' => '@archive/source'],
];

$templates = [
    'untagged normal no affix' => [
        'placement' => 'untagged',
        'mode' => 'normal',
        'prefix' => [],
        'suffix' => [],
        'expected' => static fn (string $token): string => '[' . $token . ']',
    ],
    'split normal prefix' => [
        'placement' => 'split',
        'mode' => 'normal',
        'prefix' => [$str('see')],
        'suffix' => [],
        'expected' => static fn (string $token): string => '[see ' . $token . ']',
    ],
    'split normal locator suffix' => [
        'placement' => 'split',
        'mode' => 'normal',
        'prefix' => [],
        'suffix' => [$str('p.'), $space(), $str('4')],
        'expected' => static fn (string $token): string => '[' . $token . ', p. 4]',
    ],
    'untagged normal formatted affixes' => [
        'placement' => 'untagged',
        'mode' => 'normal',
        'prefix' => [$emph([$str('see')])],
        'suffix' => [$code('A12')],
        'expected' => static fn (string $token): string => '[*see* ' . $token . ', `A12`]',
    ],
    'tagged normal strong prefix note suffix' => [
        'placement' => 'tagged',
        'mode' => 'normal',
        'prefix' => [$strong([$str('cf.')])],
        'suffix' => [$str('chapter *intro*')],
        'expected' => static fn (string $token): string => '[**cf.** ' . $token . ', chapter \\*intro\\*]',
    ],
    'untagged suppress no affix' => [
        'placement' => 'untagged',
        'mode' => 'suppress_author',
        'prefix' => [],
        'suffix' => [],
        'expected' => static fn (string $token): string => '[-' . $token . ']',
    ],
    'split suppress formatted prefix' => [
        'placement' => 'split',
        'mode' => 'suppress_author',
        'prefix' => [$emph([$str('see')])],
        'suffix' => [],
        'expected' => static fn (string $token): string => '[*see* -' . $token . ']',
    ],
    'untagged author bare' => [
        'placement' => 'untagged',
        'mode' => 'author_in_text',
        'prefix' => [],
        'suffix' => [],
        'expected' => static fn (string $token): string => $token,
    ],
    'split author source locator' => [
        'placement' => 'split',
        'mode' => 'author_in_text',
        'prefix' => [],
        'suffix' => [$str('p.'), $space(), $str('9')],
        'expected' => static fn (string $token): string => $token . ', p. 9',
    ],
    'wrapped author note suffix' => [
        'placement' => 'wrapped',
        'mode' => 'author_in_text',
        'prefix' => [],
        'suffix' => [$str('chapter *intro*')],
        'expected' => static fn (string $token): string => $token . ' [chapter \\*intro\\*]',
    ],
];

$cases = [];
foreach ($ids as $idName => $idCase) {
    foreach ($templates as $templateName => $template) {
        $cases[$idName . ' ' . $templateName] = [
            'node' => $citation(
                $template['placement'],
                $idCase['id'],
                $template['mode'],
                $template['prefix'],
                $template['suffix']
            ),
            'expected' => $template['expected']($idCase['token']),
        ];
    }
}

$tests = [
    'records markdown writer citation native sidecar completion mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(50, count($cases));
    },
];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown writer citation native sidecar completion ' . $name] =
        static function (TestRunner $t) use ($case, $document): void {
            $markdown = (new MarkdownWriter())->write($document($case['node']));

            $t->same($case['expected'], $markdown);
            $t->true(!str_contains($markdown, '@{}'), 'Citation sidecar id should not fall back to an empty braced id');
        };
}

return $tests;
