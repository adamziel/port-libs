<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-zzzzzzzzzzzzzz-footnote-fenced-div-boundary.md'
);

$collectNotes = null;
$collectNotes = static function (AstNode $node) use (&$collectNotes): array {
    $notes = $node->type === 'note' ? [$node] : [];
    foreach ($node->children as $child) {
        array_push($notes, ...$collectNotes($child));
    }

    return $notes;
};

return [
    'maps upstream markdown footnote fenced-div same-line boundary fixture' =>
        static function (TestRunner $t) use ($fixture, $collectNotes): void {
            $document = (new MarkdownReader(['format' => 'markdown+footnotes+fenced_divs+native_divs']))->read($fixture());
            $notes = $collectNotes($document);
            $sameLineNote = $notes[0] ?? new AstNode('missing');
            $indentedNote = $notes[1] ?? new AstNode('missing');
            $sameLineBody = $sameLineNote->children[0] ?? new AstNode('missing');
            $indentedBody = $indentedNote->children[0] ?? new AstNode('missing');
            $indentedParagraph = $indentedBody->children[0] ?? new AstNode('missing');

            $t->same(2, count($notes));
            $t->same('same', $sameLineNote->attr('label'));
            $t->same('paragraph', $sameLineBody->type);
            $t->same('::: note same line stays literal :::', $sameLineBody->attr('text'));
            $t->same(false, in_array('div', array_map(
                static fn (AstNode $node): string => $node->type,
                $sameLineNote->children
            ), true));

            $t->same('indented', $indentedNote->attr('label'));
            $t->same('div', $indentedBody->type);
            $t->same(['note'], $indentedBody->attr('classes'));
            $t->same('paragraph', $indentedParagraph->type);
            $t->same('indented becomes div', $indentedParagraph->attr('text'));
        },

    'records upstream markdown footnote fenced-div boundary mapped-case count' =>
        static function (TestRunner $t): void {
            $t->same(2, 2);
        },
];
