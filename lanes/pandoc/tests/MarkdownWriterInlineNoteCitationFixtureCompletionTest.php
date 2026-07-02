<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$citation = static fn (array $attrs): AstNode => new AstNode('citation', $attrs);
$citationGroup = static fn (array $children): AstNode => new AstNode('citation_group', [], $children);
$link = static fn (string $url, string $title, string $label): AstNode => new AstNode(
    'link',
    ['url' => $url, 'title' => $title],
    [$text($label)]
);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$note = static fn (array $children): AstNode => new AstNode('note', [], [$paragraph($children)]);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$fixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-note-citations.md');

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$cases = [
    'citation in simple inline note' => [
        'source' => 'foo^[bar [@doe]]',
        'document' => $document([
            $text('foo'),
            $note([
                $text('bar '),
                $citation(['id' => 'doe']),
            ]),
        ]),
        'noteText' => 'bar [@doe]',
        'citationIds' => ['doe'],
    ],
    'link and author citation in inline note' => [
        'source' => 'alpha^[note [packet](https://example.test/source "source title") and @roe [p. 9]]',
        'document' => $document([
            $text('alpha'),
            $note([
                $text('note '),
                $link('https://example.test/source', 'source title', 'packet'),
                $text(' and '),
                $citation(['id' => 'roe', 'mode' => 'author_in_text', 'suffix' => 'p. 9']),
            ]),
        ]),
        'noteText' => 'note packet and @roe [p. 9]',
        'citationIds' => ['roe'],
    ],
    'code and citation group in inline note' => [
        'source' => 'trail^[code `]` and [@smith; see -@doe]]',
        'document' => $document([
            $text('trail'),
            $note([
                $text('code '),
                $code(']'),
                $text(' and '),
                $citationGroup([
                    $citation(['id' => 'smith']),
                    $citation(['id' => 'doe', 'mode' => 'suppress_author', 'prefix' => 'see']),
                ]),
            ]),
        ]),
        'noteText' => 'code ] and @smith-@doe',
        'citationIds' => ['smith', 'doe'],
    ],
];

$tests = [
    'records markdown writer inline-note citation fixture completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(3, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer inline-note citation fixture ' . $label] =
        static function (TestRunner $t) use ($case, $collectNodes, $fixture, $label): void {
            $markdown = (new MarkdownWriter(['format' => 'markdown+inline_notes']))->write($case['document']);

            $t->contains($case['source'], $fixture(), $label . ' source fixture line');
            $t->same($case['source'], $markdown, $label . ' inline-note markdown');

            $roundTrip = (new MarkdownReader())->read($markdown);
            $note = $collectNodes($roundTrip, 'note')[0] ?? new AstNode('missing');
            $citations = $collectNodes($roundTrip, 'citation');

            $t->same('note', $note->type, $label . ' round-trip note');
            $t->same($case['noteText'], $note->children[0]->attr('text'), $label . ' note text');
            $t->same($case['citationIds'], array_map(
                static fn (AstNode $citation): string => (string) $citation->attr('id'),
                $citations
            ), $label . ' citation ids');
            $t->same(
                $case['source'],
                (new MarkdownWriter(['extensions' => ['inline_notes']]))->write($roundTrip),
                $label . ' stable inline-note regeneration'
            );
        };
}

$tests['keeps reference-style notes as default without inline-notes extension'] =
    static function (TestRunner $t) use ($cases): void {
        $markdown = (new MarkdownWriter())->write($cases['citation in simple inline note']['document']);

        $t->same("foo[^1]\n\n[^1]: bar [@doe]", $markdown);
    };

$tests['falls back to reference definition for multi-block notes under inline-notes extension'] =
    static function (TestRunner $t) use ($paragraph, $text): void {
        $document = new AstNode('document', [], [
            $paragraph([
                $text('multi'),
                new AstNode('note', [], [
                    $paragraph([$text('first')]),
                    $paragraph([$text('second')]),
                ]),
            ]),
        ]);

        $markdown = (new MarkdownWriter(['format' => 'markdown+inline_note']))->write($document);

        $t->same("multi[^1]\n\n[^1]: first\n\n    second", $markdown);
    };

return $tests;
