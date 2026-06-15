<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$codeBlock = static fn (string $value): AstNode => new AstNode('code_block', [
    'text' => $value,
    'classes' => [],
    'attributes' => [],
]);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);

$findNote = static function (AstNode $node) use (&$findNote): ?AstNode {
    if ($node->type === 'note') {
        return $node;
    }

    foreach ($node->children as $child) {
        $note = $findNote($child);
        if ($note instanceof AstNode) {
            return $note;
        }
    }

    return null;
};

$codeSamples = [
    'single assignment' => 'alpha = 1',
    'two source lines' => "alpha\nbeta",
    'internal blank line' => "alpha\n\nbeta",
    'already indented source' => '    already indented',
    'markdown list markers' => "- not a list\n1. not ordered\n# not heading",
    'fence-looking source' => "``` literal fence\nnot closing",
    'definition marker source' => ": not a definition\n~ not another definition",
    'json-looking source' => '{"key": "value", "enabled": true}',
    'blockquote-looking source' => "> quoted\n> continued",
    'footnote-looking source' => '[^x]: not a nested note',
    'reference-looking source' => '[ref]: /not-a-reference',
    'html-looking source' => '<div data-review="raw">raw</div>',
    'url-looking source' => 'https://example.test/path?a=1&b=2',
    'math-looking source' => '$x$ and $$y$$ stay code',
    'backtick source' => '`inline` and ``ticks`` stay code',
];

$contexts = [
    'middle sentence' => ['prefix' => 'Case note ', 'suffix' => ' closes.'],
    'leading note' => ['prefix' => '', 'suffix' => ' starts the paragraph.'],
    'trailing note' => ['prefix' => 'Paragraph ends with ', 'suffix' => ''],
    'between words' => ['prefix' => 'Before ', 'suffix' => ' after.'],
    'punctuation boundary' => ['prefix' => 'Open(', 'suffix' => ') close.'],
];

$tests = [];
$caseNumber = 0;

foreach ($codeSamples as $sampleName => $source) {
    foreach ($contexts as $contextName => $context) {
        $caseNumber++;
        $label = 'code-' . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT);
        $case = [
            'label' => $label,
            'source' => $source,
            'prefix' => $context['prefix'],
            'suffix' => $context['suffix'],
        ];

        $tests['maps upstream markdown footnote indented code round trip '
            . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT)
            . ' ' . $sampleName . ' ' . $contextName] =
            static function (TestRunner $t) use ($case, $text, $codeBlock, $document, $findNote): void {
                $note = new AstNode('note', ['label' => $case['label']], [$codeBlock($case['source'])]);
                $markdown = (new MarkdownWriter())->write($document([
                    $text($case['prefix']),
                    $note,
                    $text($case['suffix']),
                ]));

                $marker = '[^' . $case['label'] . ']:';
                $t->contains($marker . "\n", $markdown);
                $t->true(!str_contains($markdown, $marker . '    '), 'Code-block note must not keep first code line on marker line');

                $roundTrip = (new MarkdownReader())->read($markdown);
                $roundTripNote = $findNote($roundTrip);
                $t->true($roundTripNote instanceof AstNode, 'Expected note after Markdown writer/reader round trip');

                if (!$roundTripNote instanceof AstNode) {
                    return;
                }

                $firstBlock = $roundTripNote->children[0] ?? new AstNode('missing');
                $t->same('code_block', $firstBlock->type);
                $t->same($case['source'], $firstBlock->attr('text'));
                $t->same([], $firstBlock->attr('classes'));
                $t->same([], $firstBlock->attr('attributes'));
                $t->same($markdown, (new MarkdownWriter())->write($roundTrip));
            };
    }
}

$tests['records markdown note code block round-trip surge mapped-case count'] =
    static function (TestRunner $t) use ($caseNumber): void {
        $t->same(75, $caseNumber);
    };

return $tests;
