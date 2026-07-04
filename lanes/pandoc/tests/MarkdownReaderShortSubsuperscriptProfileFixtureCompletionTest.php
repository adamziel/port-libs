<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-z-short-subsuperscript-profile.md'
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

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

return [
    'maps pandoc markdown short subsuperscript profile fixture' =>
        static function (TestRunner $t) use ($fixture, $inlineText, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown+short_subsuperscripts']))->read($fixture);
            $shortForms = $document->children[0] ?? new AstNode('missing');
            $pairedForms = $document->children[1] ?? new AstNode('missing');

            $t->same(['paragraph', 'paragraph'], array_map(
                static fn (AstNode $node): string => $node->type,
                $document->children
            ));
            $t->same(['text', 'subscript', 'text', 'superscript'], $inlineTypes($shortForms));
            $t->same('H2O and X2', $inlineText($shortForms));
            $t->same('2O', $inlineText($shortForms->children[1] ?? new AstNode('missing')));
            $t->same('2', $inlineText($shortForms->children[3] ?? new AstNode('missing')));

            $t->same(['text', 'subscript', 'text', 'superscript'], $inlineTypes($pairedForms));
            $t->same('H2O and X2', $inlineText($pairedForms));
            $t->same('2', $inlineText($pairedForms->children[1] ?? new AstNode('missing')));
            $t->same('2', $inlineText($pairedForms->children[3] ?? new AstNode('missing')));
        },

    'keeps pandoc markdown short subsuperscript profile fixture literal by default' =>
        static function (TestRunner $t) use ($fixture, $inlineText, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture);
            $shortForms = $document->children[0] ?? new AstNode('missing');
            $pairedForms = $document->children[1] ?? new AstNode('missing');

            $t->same(['text'], $inlineTypes($shortForms));
            $t->same('H~2O and X^2', $inlineText($shortForms));
            $t->same(['text', 'subscript', 'text', 'superscript'], $inlineTypes($pairedForms));
        },
];
