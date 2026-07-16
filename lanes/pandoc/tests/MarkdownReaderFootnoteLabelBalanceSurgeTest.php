<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$findFirstNote = null;
$findFirstNote = static function (AstNode $node) use (&$findFirstNote): ?AstNode {
    if ($node->type === 'note') {
        return $node;
    }

    foreach ($node->children as $child) {
        $note = $findFirstNote($child);
        if ($note instanceof AstNode) {
            return $note;
        }
    }

    return null;
};

$cases = [];
for ($index = 1; $index <= 40; $index++) {
    $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $cases['escaped close bracket label ' . $suffix] = [
        'sourceLabel' => 'source\\]note-' . $suffix,
        'expectedLabel' => 'source]note-' . $suffix,
        'body' => 'Escaped bracket note body ' . $suffix . '.',
    ];
}

for ($index = 1; $index <= 35; $index++) {
    $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $cases['balanced nested bracket label ' . $suffix] = [
        'sourceLabel' => 'source[note-' . $suffix . ']',
        'expectedLabel' => 'source[note-' . $suffix . ']',
        'body' => 'Nested bracket note body ' . $suffix . '.',
    ];
}

$tests = [];
foreach ($cases as $name => $case) {
    $tests['maps upstream markdown footnote balanced label ' . $name] =
        static function (TestRunner $t) use ($case, $findFirstNote, $name): void {
            $label = $case['sourceLabel'];
            $markdown = 'Import note[^' . $label . '] resolves.' . "\n\n"
                . '[^' . $label . ']: ' . $case['body'];
            $document = (new MarkdownReader())->read($markdown);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $note = $findFirstNote($paragraph);

            $t->same(1, count($document->children), $name . ' removes footnote definition from document body');
            $t->same('paragraph', $paragraph->type, $name . ' leaves one referencing paragraph');
            $t->true($note instanceof AstNode, $name . ' resolves a note node');
            if (!$note instanceof AstNode) {
                return;
            }

            $t->same($case['expectedLabel'], $note->attr('label'), $name . ' canonical source label');
            $t->same(['paragraph'], array_map(static fn (AstNode $child): string => $child->type, $note->children));
            $t->same($case['body'], $note->children[0]->attr('text'), $name . ' note body text');
        };
}

$tests['records markdown reader footnote balanced label surge mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(75, count($cases));
    };

return $tests;
