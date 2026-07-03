<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-mmd-short-scripts.md'
);

$inlineTypes = static function (AstNode $node): array {
    return array_map(static fn (AstNode $child): string => $child->type, $node->children);
};

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text') {
        return (string) $node->attr('text', '');
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

return [
    'maps upstream markdown mmd short script fixture delimiter boundaries' =>
        static function (TestRunner $t) use ($fixture, $inlineTypes, $inlineText): void {
            $document = (new MarkdownReader(['format' => 'markdown_mmd']))->read($fixture);

            $t->same(10, count($document->children));

            $subSpace = $document->children[0] ?? new AstNode('missing');
            $subEof = $document->children[1] ?? new AstNode('missing');
            $subPunctuation = $document->children[2] ?? new AstNode('missing');
            $subEmphasis = $document->children[3] ?? new AstNode('missing');
            $subNoNesting = $document->children[4] ?? new AstNode('missing');
            $supSpace = $document->children[5] ?? new AstNode('missing');
            $supEof = $document->children[6] ?? new AstNode('missing');
            $supPunctuation = $document->children[7] ?? new AstNode('missing');
            $supEmphasis = $document->children[8] ?? new AstNode('missing');
            $supNoNesting = $document->children[9] ?? new AstNode('missing');

            $t->same(['text', 'subscript', 'text'], $inlineTypes($subSpace));
            $t->same('2', $inlineText($subSpace->children[1]));
            $t->same(' is dangerous', $subSpace->children[2]->attr('text'));
            $t->same(['text', 'subscript'], $inlineTypes($subEof));
            $t->same('2', $inlineText($subEof->children[1]));
            $t->same(['text', 'subscript', 'text'], $inlineTypes($subPunctuation));
            $t->same('.', $subPunctuation->children[2]->attr('text'));
            $t->same(['text', 'subscript', 'emph'], $inlineTypes($subEmphasis));
            $t->same('2', $inlineText($subEmphasis->children[1]));
            $t->same('combustible!', $inlineText($subEmphasis->children[2]));
            $t->same(['text', 'emph'], $inlineTypes($subNoNesting));
            $t->same('y~', $subNoNesting->children[0]->attr('text'));
            $t->same('2', $inlineText($subNoNesting->children[1]));

            $t->same(['text', 'superscript', 'text'], $inlineTypes($supSpace));
            $t->same('2', $inlineText($supSpace->children[1]));
            $t->same(' = y', $supSpace->children[2]->attr('text'));
            $t->same(['text', 'superscript'], $inlineTypes($supEof));
            $t->same('2', $inlineText($supEof->children[1]));
            $t->same(['text', 'superscript', 'text'], $inlineTypes($supPunctuation));
            $t->same('.', $supPunctuation->children[2]->attr('text'));
            $t->same(['text', 'superscript', 'emph'], $inlineTypes($supEmphasis));
            $t->same('2', $inlineText($supEmphasis->children[1]));
            $t->same('combustible!', $inlineText($supEmphasis->children[2]));
            $t->same(['text', 'emph'], $inlineTypes($supNoNesting));
            $t->same('y^', $supNoNesting->children[0]->attr('text'));
            $t->same('2', $inlineText($supNoNesting->children[1]));
        },

    'records upstream markdown mmd short script fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R/', trim($fixture)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(10, count($cases));
            $t->same('O~2 is dangerous', $cases[0]);
            $t->same('y~*2*', $cases[4]);
            $t->same('x^2 = y', $cases[5]);
            $t->same('y^*2*', $cases[9]);
        },
];
