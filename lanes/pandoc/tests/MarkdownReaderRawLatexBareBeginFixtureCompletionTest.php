<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-raw-latex-bare-begin.md'
);

$nodeTypes = null;
$nodeTypes = static function (AstNode $node) use (&$nodeTypes): array {
    $types = [$node->type];
    foreach ($node->children as $child) {
        array_push($types, ...$nodeTypes($child));
    }

    return $types;
};

return [
    'maps selected upstream markdown raw LaTeX bare begin fixture' =>
        static function (TestRunner $t) use ($fixture, $nodeTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $text = $paragraph->children[0] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('\begin', $paragraph->attr('text'));
            $t->same('text', $text->type);
            $t->same('\begin', $text->attr('text'));
            $t->same(false, in_array('raw_tex', $nodeTypes($document), true));
        },

    'keeps selected upstream markdown raw LaTeX bare begin literal under raw-tex profiles' =>
        static function (TestRunner $t) use ($fixture, $nodeTypes): void {
            foreach (['markdown', 'markdown+raw_tex', 'markdown_strict+raw_tex', 'commonmark+raw_tex'] as $format) {
                $document = (new MarkdownReader(['format' => $format]))->read($fixture());
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same('paragraph', $paragraph->type, $format);
                $t->same('\begin', $paragraph->attr('text'), $format);
                $t->same(false, in_array('raw_tex', $nodeTypes($document), true), $format);
            }
        },

    'keeps complete raw LaTeX environment gated by raw-tex profile boundary' =>
        static function (TestRunner $t): void {
            $source = '\begin{center}' . "\n" . 'raw profile' . "\n" . '\end{center}';
            $enabled = (new MarkdownReader(['format' => 'markdown+raw_tex']))->read($source);
            $disabled = (new MarkdownReader(['format' => 'markdown-raw_tex']))->read($source);
            $enabledBlock = $enabled->children[0] ?? new AstNode('missing');
            $disabledBlock = $disabled->children[0] ?? new AstNode('missing');

            $t->same('raw_tex', $enabledBlock->type);
            $t->same($source, $enabledBlock->attr('tex'));
            $t->same('paragraph', $disabledBlock->type);
            $t->same('\begin{center} raw profile \end{center}', $disabledBlock->attr('text'));
        },

    'records upstream markdown raw LaTeX bare begin fixture mapped-case count' =>
        static function (TestRunner $t) use ($fixture): void {
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($fixture())) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('\begin', $cases[0]);
        },
];
