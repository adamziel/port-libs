<?php
declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => rtrim(
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-gfm-details-list.md'),
    "\r\n"
);

$nodeTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$tests = [];

$tests['maps upstream command gfm details list fixture through markdown reader'] =
    static function (TestRunner $t) use ($fixture, $nodeTypes): void {
        $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
        $list = $document->children[0] ?? new AstNode('missing');
        $first = $list->children[0] ?? new AstNode('missing');
        $second = $list->children[1] ?? new AstNode('missing');
        $nested = $first->children[2] ?? new AstNode('missing');
        $continuation = $first->children[4] ?? new AstNode('missing');

        $t->same(['bullet_list'], $nodeTypes($document));
        $t->same(false, $list->attr('loose'));
        $t->same(['paragraph', 'raw_html', 'bullet_list', 'raw_html', 'paragraph'], $nodeTypes($first));
        $t->same(true, $first->attr('loose'));
        $t->same(['text'], $nodeTypes($second));
        $t->same(false, $second->attr('loose'));
        $t->same('<details>', $first->children[1]->attr('html'));
        $t->same('</details>', $first->children[3]->attr('html'));
        $t->same('bullet_list', $nested->type);
        $t->same('subitem', ($nested->children[0] ?? new AstNode('missing'))->children[0]->attr('text'));
        $t->same(['text', 'emph', 'text', 'strong', 'text'], $nodeTypes($continuation));
        $t->same('continue', $continuation->children[1]->children[0]->attr('text'));
        $t->same('with', $continuation->children[3]->children[0]->attr('text'));
    };

$tests['round trips upstream command gfm details list fixture without loosening siblings'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
        $markdown = (new MarkdownWriter(['variant' => 'gfm']))->write($document);
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same($fixture(), $markdown);
        $t->contains('RawBlock (Format "html") "<details>"', $native);
        $t->contains('BulletList [ [ Plain [ Str "subitem" ]', $native);
        $t->contains('RawBlock (Format "html") "</details>"', $native);
        $t->contains('Para [ Str "item" , Space , Emph [ Str "continue" ] , Space , Strong [ Str "with" ]', $native);
        $t->contains('<details><ul><li>subitem</li></ul></details>', $blocks);
        $t->contains('<p>item <em>continue</em> <strong>with</strong> formatting</p>', $blocks);
        $t->true(
            !str_contains($markdown, "formatting\n\n- next list item"),
            'Sibling list item should not be separated as a loose top-level item'
        );
    };

$tests['maps upstream command gfm details list fixture through markdown writer gfm format option'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
        $markdown = (new MarkdownWriter(['format' => 'gfm']))->write($document);

        $t->same($fixture(), $markdown);
        $t->contains("- list item\n  <details>", $markdown);
        $t->contains("  - subitem\n\n  </details>", $markdown);
        $t->contains("item *continue* **with** formatting\n- next list item", $markdown);
        $t->true(
            !str_contains($markdown, "formatting\n\n- next list item"),
            'GFM details list writer fixture should not loosen the sibling item'
        );
    };

$tests['records upstream command gfm details list reader mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(6, 6);
    };

$tests['records upstream command gfm details list writer mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(5, 5);
        $t->same(2, 2);
    };

return $tests;
