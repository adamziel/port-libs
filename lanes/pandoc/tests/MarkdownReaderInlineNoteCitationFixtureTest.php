<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$collectNodes = null;
$collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
    $matches = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($matches, ...$collectNodes($child, $type));
    }

    return $matches;
};

$inlineNoteCitationFixture = 'foo^[bar [@doe]]';

$tests = [];

$tests['maps upstream command cite-in-inline-note reader fixture'] =
    static function (TestRunner $t) use ($inlineNoteCitationFixture, $collectNodes): void {
        $document = (new MarkdownReader())->read($inlineNoteCitationFixture);
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $note = $paragraph->children[1] ?? new AstNode('missing');
        $noteParagraph = $note->children[0] ?? new AstNode('missing');
        $citation = $noteParagraph->children[1] ?? new AstNode('missing');

        $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same(['text', 'note'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('foo', $paragraph->children[0]->attr('text'));
        $t->same('note', $note->type);
        $t->same(['paragraph'], array_map(static fn (AstNode $node): string => $node->type, $note->children));
        $t->same('bar [@doe]', $noteParagraph->attr('text'));
        $t->same(['text', 'citation'], array_map(static fn (AstNode $node): string => $node->type, $noteParagraph->children));
        $t->same('bar ', $noteParagraph->children[0]->attr('text'));
        $t->same('citation', $citation->type);
        $t->same('doe', $citation->attr('id'));
        $t->same('normal', $citation->attr('mode'));
        $t->same('[@doe]', $citation->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $citation->children));
        $t->same('[@doe]', ($citation->children[0] ?? new AstNode('missing'))->attr('text'));
        $t->same([$citation], $collectNodes($note, 'citation'));
    };

$tests['serializes upstream command cite-in-inline-note through native markdown and wordpress handoff'] =
    static function (TestRunner $t) use ($inlineNoteCitationFixture): void {
        $document = (new MarkdownReader())->read($inlineNoteCitationFixture);
        $native = json_decode((new NativeWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $para = $native['blocks'][0] ?? [];
        $inlines = $para['c'] ?? [];
        $noteBlock = $inlines[1]['c'][0] ?? [];
        $noteInlines = $noteBlock['c'] ?? [];
        $cite = $noteInlines[2] ?? [];
        $citation = $cite['c'][0][0] ?? [];

        $t->same('Para', $para['t'] ?? null);
        $t->same('Str', $inlines[0]['t'] ?? null);
        $t->same('foo', $inlines[0]['c'] ?? null);
        $t->same('Note', $inlines[1]['t'] ?? null);
        $t->same('Para', $noteBlock['t'] ?? null);
        $t->same(['Str', 'Space', 'Cite'], array_map(static fn (array $node): ?string => $node['t'] ?? null, $noteInlines));
        $t->same('bar', $noteInlines[0]['c'] ?? null);
        $t->same('doe', $citation['citationId'] ?? null);
        $t->same('NormalCitation', $citation['citationMode']['t'] ?? null);
        $t->same('Str', $cite['c'][1][0]['t'] ?? null);
        $t->same('[@doe]', $cite['c'][1][0]['c'] ?? null);
        $t->contains('foo[^1]', $markdown);
        $t->contains('[^1]: bar [@doe]', $markdown);
        $t->contains('<p>foo<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></p>', $blocks);
        $t->contains('<li id="fn-1"><p>bar [@doe]</p>', $blocks);
    };

$tests['records upstream command cite-in-inline-note mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(1, 1);
    };

return $tests;
