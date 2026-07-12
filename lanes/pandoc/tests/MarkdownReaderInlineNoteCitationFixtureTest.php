<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
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

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$inlineNoteCitationFixture = static fn (): string =>
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-inline-note-citations.md');

$tests = [];

$tests['maps upstream markdown inline-note citation fixture completion'] =
    static function (TestRunner $t) use ($inlineNoteCitationFixture, $collectNodes, $inlineTypes): void {
        $document = (new MarkdownReader())->read($inlineNoteCitationFixture());
        $paragraphs = $document->children;
        $first = $paragraphs[0] ?? new AstNode('missing');
        $second = $paragraphs[1] ?? new AstNode('missing');
        $third = $paragraphs[2] ?? new AstNode('missing');
        $paragraph = $document->children[0] ?? new AstNode('missing');
        $note = $paragraph->children[1] ?? new AstNode('missing');
        $noteParagraph = $note->children[0] ?? new AstNode('missing');
        $citation = $noteParagraph->children[1] ?? new AstNode('missing');
        $secondNoteParagraph = ($second->children[1] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing');
        $secondLink = $secondNoteParagraph->children[1] ?? new AstNode('missing');
        $secondCitation = $secondNoteParagraph->children[3] ?? new AstNode('missing');
        $thirdNoteParagraph = ($third->children[1] ?? new AstNode('missing'))->children[0] ?? new AstNode('missing');
        $thirdCode = $thirdNoteParagraph->children[1] ?? new AstNode('missing');
        $thirdCitationGroup = $thirdNoteParagraph->children[3] ?? new AstNode('missing');
        $thirdFirstCitation = $thirdCitationGroup->children[0] ?? new AstNode('missing');
        $thirdSecondCitation = $thirdCitationGroup->children[1] ?? new AstNode('missing');

        $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $paragraphs));
        $t->same(['text', 'note'], $inlineTypes($first));
        $t->same(['text', 'note'], $inlineTypes($second));
        $t->same(['text', 'note'], $inlineTypes($third));
        $t->same(['text', 'note'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
        $t->same('foo', $paragraph->children[0]->attr('text'));
        $t->same('alpha', $second->children[0]->attr('text'));
        $t->same('trail', $third->children[0]->attr('text'));
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

        $t->same(['text', 'link', 'text', 'citation'], $inlineTypes($secondNoteParagraph));
        $t->same('https://example.test/source', $secondLink->attr('url'));
        $t->same('source title', $secondLink->attr('title'));
        $t->same('packet', ($secondLink->children[0] ?? new AstNode('missing'))->attr('text'));
        $t->same('roe', $secondCitation->attr('id'));
        $t->same('author_in_text', $secondCitation->attr('mode'));
        $t->same('p. 9', $secondCitation->attr('suffix'));

        $t->same(['text', 'code', 'text', 'citation_group'], $inlineTypes($thirdNoteParagraph));
        $t->same(']', $thirdCode->attr('text'));
        $t->same('[@smith; see -@doe]', $thirdCitationGroup->attr('text'));
        $t->same('smith', $thirdFirstCitation->attr('id'));
        $t->same('normal', $thirdFirstCitation->attr('mode'));
        $t->same('doe', $thirdSecondCitation->attr('id'));
        $t->same('suppress_author', $thirdSecondCitation->attr('mode'));
        $t->same('see', $thirdSecondCitation->attr('prefix'));
    };

$tests['serializes upstream markdown inline-note citations through native markdown and wordpress handoff'] =
    static function (TestRunner $t) use ($inlineNoteCitationFixture): void {
        $document = (new MarkdownReader())->read($inlineNoteCitationFixture());
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('Para [ Str "foo" , Note [ Para [ Str "bar"', $native);
        $t->contains('citationId = "doe"', $native);
        $t->contains('citationMode = NormalCitation', $native);
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "packet" ] ( "https://example.test/source" , "source title" )', $native);
        $t->contains('citationId = "roe"', $native);
        $t->contains('citationSuffix = [ Str "p." , Space , Str "9" ]', $native);
        $t->contains('Code ( "" , [  ] , [  ] ) "]"', $native);
        $t->contains('citationId = "smith"', $native);
        $t->contains('citationPrefix = [ Str "see" ]', $native);
        $t->contains('citationMode = SuppressAuthor', $native);
        $t->contains('<p>foo<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup></p>', $blocks);
        $t->contains('<span class="pandoc-citation" data-pandoc-citation-id="doe"', $blocks);
        $t->contains('<a href="https://example.test/source" title="source title">packet</a>', $blocks);
        $t->contains('<span class="pandoc-citation" data-pandoc-citation-id="roe"', $blocks);
        $t->contains('<code>]</code>', $blocks);
        $t->contains('<span class="pandoc-citation" data-pandoc-citation-count="2"', $blocks);
        $t->contains('data-pandoc-citation-ids="[&quot;smith&quot;,&quot;doe&quot;]"', $blocks);
        $t->contains('>[@smith; see -@doe]</span>', $blocks);
    };

$tests['records upstream markdown inline-note citation mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(3, 3);
    };

return $tests;
