<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-command-gfm-details-list.md'
);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
};

$childrenTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$tests = [];

$tests['maps upstream command gfm details list fixture structure'] =
    static function (TestRunner $t) use ($fixture, $childrenTypes, $plainText): void {
        $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
        $list = $document->children[0] ?? new AstNode('missing');
        $firstItem = $list->children[0] ?? new AstNode('missing');
        $secondItem = $list->children[1] ?? new AstNode('missing');
        $nestedList = $firstItem->children[2] ?? new AstNode('missing');
        $nestedItem = $nestedList->children[0] ?? new AstNode('missing');
        $continuation = $firstItem->children[4] ?? new AstNode('missing');

        $t->same(['bullet_list'], $childrenTypes($document));
        $t->same('bullet_list', $list->type);
        $t->same(2, count($list->children));
        $t->same(['paragraph', 'raw_html', 'bullet_list', 'raw_html', 'paragraph'], $childrenTypes($firstItem));
        $t->same(['paragraph'], $childrenTypes($secondItem));
        $t->same('list item', $plainText($firstItem->children[0] ?? new AstNode('missing')));
        $t->same('<details>', ($firstItem->children[1] ?? new AstNode('missing'))->attr('html'));
        $t->same('subitem', $plainText($nestedItem));
        $t->same('</details>', ($firstItem->children[3] ?? new AstNode('missing'))->attr('html'));
        $t->same(['text', 'emph', 'text', 'strong', 'text'], $childrenTypes($continuation));
        $t->same('item continue with formatting', $plainText($continuation));
        $t->same('next list item', $plainText($secondItem));
    };

$tests['serializes upstream command gfm details list fixture through native and markdown'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
        $native = (new NativeWriter())->write($document);
        $markdown = (new MarkdownWriter(['format' => 'gfm']))->write($document);

        $t->contains('BulletList', $native);
        $t->contains('RawBlock (Format "html") "<details>"', $native);
        $t->contains('BulletList [ [ Plain [ Str "subitem" ]', $native);
        $t->contains('RawBlock (Format "html") "</details>"', $native);
        $t->contains('Emph [ Str "continue" ]', $native);
        $t->contains('Strong [ Str "with" ]', $native);
        $t->contains('- list item', $markdown);
        $t->contains('<details>', $markdown);
        $t->contains('  - subitem', $markdown);
        $t->contains('</details>', $markdown);
        $t->contains('item *continue* **with** formatting', $markdown);
        $t->contains('- next list item', $markdown);
    };

$tests['hands upstream command gfm details list fixture to wordpress blocks'] =
    static function (TestRunner $t) use ($fixture): void {
        $document = (new MarkdownReader(['format' => 'gfm']))->read($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<ul>', $blocks);
        $t->contains('<li><p>list item</p>', $blocks);
        $t->contains('<details>', $blocks);
        $t->contains('<li>subitem</li>', $blocks);
        $t->contains('</details>', $blocks);
        $t->contains('<em>continue</em>', $blocks);
        $t->contains('<strong>with</strong>', $blocks);
        $t->contains('<li><p>next list item</p></li>', $blocks);
    };

$tests['records upstream command gfm details list reader mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(3, 3);
    };

return $tests;
