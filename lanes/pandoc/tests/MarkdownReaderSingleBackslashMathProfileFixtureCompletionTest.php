<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zz-tex-math-single-backslash-profile.md'
);

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

return [
    'maps pandoc markdown single backslash math profile fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineText): void {
            $document = (new MarkdownReader(['format' => 'markdown+tex_math_single_backslash']))->read($fixture);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $inline = $paragraph->children[1] ?? new AstNode('missing');
            $display = $paragraph->children[3] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same(['text', 'math', 'text', 'math', 'text'], array_map(
                static fn (AstNode $node): string => $node->type,
                $paragraph->children
            ));
            $t->same('Inline a+1 and display b+2.', $inlineText($paragraph));
            $t->same('a+1', $inline->attr('text'));
            $t->same(false, $inline->attr('display'));
            $t->same('b+2', $display->attr('text'));
            $t->same(true, $display->attr('display'));
        },

    'keeps pandoc markdown single backslash math profile fixture non-math by default' =>
        static function (TestRunner $t) use ($fixture, $inlineText): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture);
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same(1, count($paragraph->children));
            $t->same('text', ($paragraph->children[0] ?? new AstNode('missing'))->type);
            $t->same('Inline (a+1) and display [b+2].', $inlineText($paragraph));
        },
];
