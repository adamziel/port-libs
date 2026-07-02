<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$link = static fn (array $attrs, array $children): AstNode => new AstNode('link', $attrs, $children);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs);
$citationGroup = static fn (array $children): AstNode => new AstNode('citation_group', [], $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$note = static fn (array $children): AstNode => new AstNode('note', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$fixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-note-citations.md');

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$citationIds = null;
$citationIds = static function (AstNode $node) use (&$citationIds): array {
    $ids = $node->type === 'citation' ? [(string) $node->attr('id')] : [];
    foreach ($node->children as $child) {
        array_push($ids, ...$citationIds($child));
    }

    return $ids;
};

$cases = [
    'simple inline-note citation' => [
        'source' => 'foo^[bar [@doe]]',
        'document' => $document([
            $paragraph([
                $text('foo'),
                $note([
                    $paragraph([
                        $text('bar '),
                        $citation(['id' => 'doe']),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "foo[^1]\n\n[^1]: bar [@doe]",
        'citationIds' => ['doe'],
    ],
    'link and author-in-text suffix inside note' => [
        'source' => 'alpha^[note [packet](https://example.test/source "source title") and @roe [p. 9]]',
        'document' => $document([
            $paragraph([
                $text('alpha'),
                $note([
                    $paragraph([
                        $text('note '),
                        $link(
                            ['url' => 'https://example.test/source', 'title' => 'source title'],
                            [$text('packet')]
                        ),
                        $text(' and '),
                        $citation(['id' => 'roe', 'mode' => 'author_in_text', 'suffix' => 'p. 9']),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "alpha[^1]\n\n[^1]: note [packet](https://example.test/source \"source title\") and @roe [p. 9]",
        'citationIds' => ['roe'],
    ],
    'code and mixed citation group inside note' => [
        'source' => 'trail^[code `]` and [@smith; see -@doe]]',
        'document' => $document([
            $paragraph([
                $text('trail'),
                $note([
                    $paragraph([
                        $text('code '),
                        $code(']'),
                        $text(' and '),
                        $citationGroup([
                            $citation(['id' => 'smith']),
                            $citation(['id' => 'doe', 'mode' => 'suppress_author', 'prefix' => 'see']),
                        ]),
                    ]),
                ]),
            ]),
        ]),
        'expected' => "trail[^1]\n\n[^1]: code `]` and [@smith; see -@doe]",
        'citationIds' => ['smith', 'doe'],
    ],
];

$tests = [
    'records markdown writer inline-note citation fixture mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(3, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer inline-note citation fixture ' . $label] =
        static function (TestRunner $t) use ($case, $citationIds, $fixture, $inlineTypes, $label): void {
            $markdown = (new MarkdownWriter())->write($case['document']);

            $t->contains($case['source'], $fixture(), $label . ' source fixture line');
            $t->same($case['expected'], $markdown, $label . ' markdown');

            $roundTrip = (new MarkdownReader())->read($markdown);
            $first = $roundTrip->children[0] ?? new AstNode('missing');
            $roundTripNote = $first->children[1] ?? new AstNode('missing');

            $t->same($case['expected'], (new MarkdownWriter())->write($roundTrip), $label . ' stable regeneration');
            $t->same(['text', 'note'], $inlineTypes($first), $label . ' round-trip inline types');
            $t->same('note', $roundTripNote->type, $label . ' round-trip note');
            $t->same($case['citationIds'], $citationIds($roundTripNote), $label . ' round-trip citation ids');
        };
}

return $tests;
