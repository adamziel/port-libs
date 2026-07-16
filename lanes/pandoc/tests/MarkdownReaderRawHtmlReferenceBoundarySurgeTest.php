<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$lastBlock = static fn (AstNode $document): AstNode => $document->children[array_key_last($document->children)] ?? new AstNode('missing');

$assertTrailingShortcutLiteral = static function (TestRunner $t, AstNode $document, string $label): void {
    $paragraph = $document->children[array_key_last($document->children)] ?? new AstNode('missing');
    $child = $paragraph->children[0] ?? new AstNode('missing');

    $t->same('paragraph', $paragraph->type, $label . ' trailing block');
    $t->same('[foo]', $paragraph->attr('text'), $label . ' trailing text');
    $t->same('text', $child->type, $label . ' trailing child');
    $t->same('[foo]', $child->attr('text'), $label . ' literal shortcut');
};

$assertTrailingHeadingShortcutLiteral = static function (TestRunner $t, AstNode $document, string $label) use ($lastBlock): void {
    $paragraph = $lastBlock($document);
    $child = $paragraph->children[0] ?? new AstNode('missing');

    $t->same('paragraph', $paragraph->type, $label . ' trailing block');
    $t->same('[Hidden]', $paragraph->attr('text'), $label . ' trailing text');
    $t->same('text', $child->type, $label . ' trailing child');
    $t->same('[Hidden]', $child->attr('text'), $label . ' literal heading shortcut');
};

$assertTrailingPlainHtml = static function (TestRunner $t, AstNode $document, string $label) use ($lastBlock): void {
    $paragraph = $lastBlock($document);
    $child = $paragraph->children[0] ?? new AstNode('missing');

    $t->same('paragraph', $paragraph->type, $label . ' trailing block');
    $t->same('HTML', $paragraph->attr('text'), $label . ' trailing text');
    $t->same('text', $child->type, $label . ' trailing child');
    $t->same('HTML', $child->attr('text'), $label . ' plain abbreviation text');
};

$explicitRawCases = [
    'pre raw block' => "<pre>\n[foo]: /inside \"Inside\"\n\n[foo]\n</pre>\n\n[foo]",
    'script raw block' => "<script>\n[foo]: /inside \"Inside\"\n\n[foo]\n</script>\n\n[foo]",
    'html comment block' => "<!--\n[foo]: /inside \"Inside\"\n\n[foo]\n-->\n\n[foo]",
    'section raw block' => "<section data-review=\"1\">\n[foo]: /inside \"Inside\"\n[foo]\n</section>\n\n[foo]",
];

return [
    'keeps explicit reference definitions inside raw html blocks from escaping' =>
        static function (TestRunner $t) use ($explicitRawCases, $findFirstNode, $assertTrailingShortcutLiteral): void {
            foreach ($explicitRawCases as $name => $markdown) {
                $document = (new MarkdownReader())->read($markdown);
                $raw = $findFirstNode($document, 'raw_html');

                $t->same('raw_html', $raw->type, $name . ' raw block');
                $t->contains('[foo]: /inside "Inside"', (string) $raw->attr('html', ''), $name . ' preserves definition source');
                $t->contains('[foo]', (string) $raw->attr('html', ''), $name . ' preserves shortcut source');
                $assertTrailingShortcutLiteral($t, $document, $name);
            }
        },

    'keeps explicit reference definitions scoped to native div contents' =>
        static function (TestRunner $t) use ($findFirstNode, $assertTrailingShortcutLiteral): void {
            $markdown = "<div>\n[foo]: /inside \"Inside\"\n\n[foo]\n</div>\n\n[foo]";
            $document = (new MarkdownReader())->read($markdown);
            $div = $document->children[0] ?? new AstNode('missing');
            $link = $findFirstNode($div, 'link');

            $t->same('div', $div->type);
            $t->same('link', $link->type);
            $t->same('/inside', $link->attr('url'));
            $t->same('Inside', $link->attr('title'));
            $t->same('foo', ($link->children[0] ?? new AstNode('missing'))->attr('text'));
            $assertTrailingShortcutLiteral($t, $document, 'native div');
        },

    'keeps implicit heading references inside opaque blocks from escaping' =>
        static function (TestRunner $t) use ($findFirstNode, $assertTrailingHeadingShortcutLiteral): void {
            $fenced = (new MarkdownReader())->read("```\n# Hidden\n```\n\n[Hidden]");
            $pre = (new MarkdownReader())->read("<pre>\n# Hidden\n</pre>\n\n[Hidden]");
            $div = (new MarkdownReader())->read("<div>\n# Hidden\n\n[Hidden]\n</div>\n\n[Hidden]");

            $assertTrailingHeadingShortcutLiteral($t, $fenced, 'fenced code');
            $assertTrailingHeadingShortcutLiteral($t, $pre, 'pre raw block');

            $divBlock = $div->children[0] ?? new AstNode('missing');
            $heading = $findFirstNode($divBlock, 'heading');
            $innerLink = $findFirstNode($divBlock, 'link');
            $t->same('div', $divBlock->type, 'native div heading container');
            $t->same('heading', $heading->type, 'native div local heading');
            $t->same('hidden', $heading->attr('id'), 'native div local heading id');
            $t->same('link', $innerLink->type, 'native div local heading shortcut');
            $t->same('#hidden', $innerLink->attr('url'), 'native div local heading shortcut target');
            $assertTrailingHeadingShortcutLiteral($t, $div, 'native div heading');
        },

    'keeps abbreviation definitions inside opaque blocks from escaping' =>
        static function (TestRunner $t) use ($findFirstNode, $assertTrailingPlainHtml): void {
            $pre = (new MarkdownReader())->read("<pre>\n*[HTML]: Hyper Text Markup Language\n</pre>\n\nHTML");
            $div = (new MarkdownReader())->read("<div>\n*[HTML]: Hyper Text Markup Language\n\nHTML\n</div>\n\nHTML");

            $raw = $findFirstNode($pre, 'raw_html');
            $t->same('raw_html', $raw->type, 'pre abbreviation raw block');
            $t->contains('*[HTML]: Hyper Text Markup Language', (string) $raw->attr('html', ''), 'pre preserves abbreviation source');
            $assertTrailingPlainHtml($t, $pre, 'pre abbreviation');

            $divBlock = $div->children[0] ?? new AstNode('missing');
            $abbr = $findFirstNode($divBlock, 'span');
            $t->same('div', $divBlock->type, 'native div abbreviation container');
            $t->same('span', $abbr->type, 'native div local abbreviation span');
            $t->same(['abbr'], $abbr->attr('classes'), 'native div local abbreviation class');
            $t->same(['title' => 'Hyper Text Markup Language'], $abbr->attr('attributes'), 'native div local abbreviation title');
            $assertTrailingPlainHtml($t, $div, 'native div abbreviation');
        },

    'records markdown reader raw-html reference boundary mapped-case count' =>
        static function (TestRunner $t) use ($explicitRawCases): void {
            $t->same(10, count($explicitRawCases) + 1 + 3 + 2);
        },
];
