<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$collectTypes = null;
$collectTypes = static function (AstNode $node) use (&$collectTypes): array {
    $types = [$node->type];
    foreach ($node->children as $child) {
        array_push($types, ...$collectTypes($child));
    }

    return $types;
};

return [
    'maps selected upstream markdown raw email address fixture through github profile' =>
        static function (TestRunner $t) use ($collectTypes): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-raw-email-address.md');
            $document = (new MarkdownReader(['format' => 'markdown_github']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $strong = $paragraph->children[0] ?? new AstNode('missing');
            $text = $strong->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);
            $types = $collectTypes($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same('@user', $paragraph->attr('text'));
            $t->same(['strong'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same('strong', $strong->type);
            $t->same('text', $text->type);
            $t->same('@user', $text->attr('text'));
            $t->same(false, in_array('link', $types, true));
            $t->same(false, in_array('raw_html_inline', $types, true));
            $t->contains('<p><strong>@user</strong></p>', $blocks);
        },

    'records selected upstream markdown raw email address fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-raw-email-address.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same('**@user**', $cases[0]);
        },
];
