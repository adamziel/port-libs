<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$collectTypes = static function (AstNode $node) use (&$collectTypes): array {
    $types = [$node->type];
    foreach ($node->children as $child) {
        array_push($types, ...$collectTypes($child));
    }

    return $types;
};

$plainText = static function (AstNode $node) use (&$plainText): string {
    if (in_array($node->type, ['text', 'code'], true)) {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

return [
    'maps selected upstream markdown fenced-div fixture' =>
        static function (TestRunner $t) use ($plainText): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-fenced-div.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $outer = $document->children[0] ?? new AstNode('missing');
            $intro = $outer->children[0] ?? new AstNode('missing');
            $inner = $outer->children[1] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('div', $outer->type);
            $t->same('review-block', $outer->attr('id'));
            $t->same(['outer'], $outer->attr('classes'));
            $t->same(['data-kind' => 'fenced-div'], $outer->attr('attributes'));
            $t->same('paragraph', $intro->type);
            $t->same('strong', $intro->children[1]->type ?? null);
            $t->same('Intro strong text.', $plainText($intro));
            $t->same('div', $inner->type);
            $t->same(['note'], $inner->attr('classes'));
            $t->same('Nested body.', $plainText($inner));
        },

    'keeps selected upstream markdown fenced-div fixture behind extension gate' =>
        static function (TestRunner $t) use ($collectTypes, $plainText): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-fenced-div.md');
            $strict = (new MarkdownReader(['format' => 'markdown_strict']))->read($source);
            $strictWithFencedDivs = (new MarkdownReader(['format' => 'markdown_strict+fenced_divs']))->read($source);

            $t->same(false, in_array('div', $collectTypes($strict), true));
            $t->contains('Intro strong text.', $plainText($strict));
            $t->contains('Nested body.', $plainText($strict));
            $t->same('div', ($strictWithFencedDivs->children[0] ?? new AstNode('missing'))->type);
        },

    'records selected upstream markdown fenced-div fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-fenced-div.md');
            $rows = array_values(array_filter(
                preg_split('/\R/', trim($source)) ?: [],
                static fn (string $row): bool => $row !== ''
            ));

            $t->same(6, count($rows));
            $t->same(':::: {#review-block .outer data-kind="fenced-div"}', $rows[0]);
            $t->same('::: note', $rows[2]);
            $t->same('::::', $rows[5]);
        },
];
