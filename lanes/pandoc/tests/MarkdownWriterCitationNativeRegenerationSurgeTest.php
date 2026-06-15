<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$space = static fn (): AstNode => new AstNode('space');
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$nativeCitation = static function (string $id, array $attrs = [], array $children = []) use ($text): AstNode {
    $mode = (string) ($attrs['mode'] ?? 'normal');
    $native = [
        'reviewQueue' => 'stale-markdown-source',
        'citationId' => $id,
        'citationPrefix' => [],
        'citationSuffix' => [],
        'citationMode' => ['t' => match ($mode) {
            'author_in_text' => 'AuthorInText',
            'suppress_author' => 'SuppressAuthor',
            default => 'NormalCitation',
        }],
        'citationNoteNum' => 0,
        'citationHash' => 0,
    ];

    return new AstNode('citation', array_replace([
        'id' => $id,
        'mode' => $mode,
        'text' => '[STALE ' . $id . ']',
        'citationNative' => $native,
        'citationModeNative' => $native['citationMode'],
    ], $attrs), $children === [] ? [$text('[STALE ' . $id . ']')] : $children);
};

$nativeGroup = static function (array $citations, string $label): AstNode {
    return new AstNode('citation_group', [
        'text' => '[STALE GROUP ' . $label . ']',
        'citationRecordsNative' => [['stale-group-source' => $label]],
        'citationSourceInlines' => [
            new AstNode('text', ['text' => '[STALE GROUP ' . $label . ']']),
        ],
    ], $citations);
};

$ids = [
    'simple' => ['id' => 'doe2026', 'token' => '@doe2026'],
    'space' => ['id' => 'doe 2026', 'token' => '@{doe 2026}'],
    'brace' => ['id' => 'doe}2026', 'token' => '@{doe\\}2026}'],
    'slash' => ['id' => 'archive/source', 'token' => '@archive/source'],
    'colon' => ['id' => 'source:chapter.1', 'token' => '@source:chapter.1'],
];

$cases = [];
foreach ($ids as $name => $idCase) {
    $id = $idCase['id'];
    $token = $idCase['token'];
    $suppressedToken = '-' . $token;

    $cases[$name . ' normal citation'] = [
        'node' => $nativeCitation($id),
        'expected' => '[' . $token . ']',
    ];
    $cases[$name . ' normal citation with prefix'] = [
        'node' => $nativeCitation($id, ['prefix' => 'see']),
        'expected' => '[see ' . $token . ']',
    ];
    $cases[$name . ' normal citation with formatted prefix'] = [
        'node' => $nativeCitation($id, ['prefix' => [$emph('see')]]),
        'expected' => '[*see* ' . $token . ']',
    ];
    $cases[$name . ' normal citation with locator'] = [
        'node' => $nativeCitation($id, ['locator' => 'p. 9']),
        'expected' => '[' . $token . ', p. 9]',
    ];
    $cases[$name . ' normal citation with inline suffix'] = [
        'node' => $nativeCitation($id, ['suffix' => [$text('ch.'), $space(), $text('2')]]),
        'expected' => '[' . $token . ', ch. 2]',
    ];
    $cases[$name . ' suppress-author citation'] = [
        'node' => $nativeCitation($id, ['mode' => 'suppress_author']),
        'expected' => '[' . $suppressedToken . ']',
    ];
    $cases[$name . ' suppress-author citation with prefix and locator'] = [
        'node' => $nativeCitation($id, ['mode' => 'suppress_author', 'prefix' => 'compare', 'locator' => 'sec. 2']),
        'expected' => '[compare ' . $suppressedToken . ', sec. 2]',
    ];
    $cases[$name . ' author-in-text citation'] = [
        'node' => $nativeCitation($id, ['mode' => 'author_in_text']),
        'expected' => $token,
    ];
    $cases[$name . ' author-in-text citation with source locator'] = [
        'node' => $nativeCitation($id, ['mode' => 'author_in_text', 'locator' => 'p. 9']),
        'expected' => $token . ', p. 9',
    ];
    $cases[$name . ' author-in-text citation with note suffix'] = [
        'node' => $nativeCitation($id, ['mode' => 'author_in_text', 'suffix' => 'chapter *intro*']),
        'expected' => $token . ' [chapter \\*intro\\*]',
    ];
}

$idValues = array_values($ids);
foreach ($idValues as $index => $idCase) {
    $next = $idValues[($index + 1) % count($idValues)];
    $id = $idCase['id'];
    $token = $idCase['token'];
    $nextId = $next['id'];
    $nextToken = $next['token'];
    $label = array_search($idCase, $ids, true);
    $label = is_string($label) ? $label : 'group-' . $index;

    $cases[$label . ' group normal and suppress-author'] = [
        'node' => $nativeGroup([
            $nativeCitation($id),
            $nativeCitation($nextId, ['mode' => 'suppress_author']),
        ], $label . '-normal-suppress'),
        'expected' => '[' . $token . '; -' . $nextToken . ']',
    ];
    $cases[$label . ' group prefixed normal and author text'] = [
        'node' => $nativeGroup([
            $nativeCitation($id, ['prefix' => 'see']),
            $nativeCitation($nextId, ['mode' => 'author_in_text']),
        ], $label . '-prefix-author'),
        'expected' => '[see ' . $token . '; ' . $nextToken . ']',
    ];
    $cases[$label . ' group locator pair'] = [
        'node' => $nativeGroup([
            $nativeCitation($id, ['locator' => 'p. 4']),
            $nativeCitation($nextId, ['mode' => 'suppress_author', 'locator' => 'sec. 2']),
        ], $label . '-locator-pair'),
        'expected' => '[' . $token . ', p. 4; -' . $nextToken . ', sec. 2]',
    ];
    $cases[$label . ' group formatted prefix and code suffix'] = [
        'node' => $nativeGroup([
            $nativeCitation($id, ['prefix' => [$emph('see')], 'suffix' => [new AstNode('code', ['text' => 'A12'])]]),
            $nativeCitation($nextId, ['mode' => 'suppress_author', 'prefix' => 'cf.']),
        ], $label . '-formatted-code'),
        'expected' => '[*see* ' . $token . ', `A12`; cf. -' . $nextToken . ']',
    ];
    $cases[$label . ' group mixed suffix forms'] = [
        'node' => $nativeGroup([
            $nativeCitation($id, ['prefix' => 'review', 'suffix' => [$text('appendix'), $space(), $text('B')]]),
            $nativeCitation($nextId, ['locator' => [$text('fig.'), $space(), $text('3')]]),
        ], $label . '-mixed-suffix'),
        'expected' => '[review ' . $token . ', appendix B; ' . $nextToken . ', fig. 3]',
    ];
}

$tests = [];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer native citation regeneration ' . $label] =
        static function (TestRunner $t) use ($case, $document): void {
            $markdown = (new MarkdownWriter())->write($document([$case['node']]));

            $t->same($case['expected'], $markdown);
            $t->true(!str_contains($markdown, 'STALE'), 'Markdown writer must not reuse stale native citation source text');
        };
}

$tests['records markdown writer native citation regeneration mapped case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(75, count($cases));
    };

$tests['keeps hand-authored citation text as explicit markdown fallback'] =
    static function (TestRunner $t) use ($document): void {
        $citation = new AstNode('citation', [
            'id' => 'manual-source',
            'text' => '[pre-rendered @manual-source, p. 9]',
        ]);

        $t->same('[pre-rendered @manual-source, p. 9]', (new MarkdownWriter())->write($document([$citation])));
    };

$tests['keeps rendered citation override authoritative with native sidecars'] =
    static function (TestRunner $t) use ($document, $nativeCitation): void {
        $citation = $nativeCitation('rendered-source', [
            'rendered' => '(Rendered Source 2026)',
            'prefix' => 'see',
        ]);

        $t->same('(Rendered Source 2026)', (new MarkdownWriter())->write($document([$citation])));
    };

return $tests;
