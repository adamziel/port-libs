<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown header-attribute fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-header-attributes.md');
            $document = (new MarkdownReader(['format' => 'markdown']))->read($source);
            $heading = $document->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');
            $link = $paragraph->children[0] ?? new AstNode('missing');
            $linkText = $link->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(2, count($document->children));
            $t->same('heading', $heading->type);
            $t->same(1, $heading->attr('level'));
            $t->same('Review Heading', $heading->attr('text'));
            $t->same('review-heading', $heading->attr('id'));
            $t->same(['audit'], $heading->attr('classes'));
            $t->same(['data-kind' => 'heading'], $heading->attr('attributes'));
            $t->same([
                'id' => 'review-heading',
                'class' => 'audit',
                'data-kind' => 'heading',
            ], $heading->attr('htmlAttributes'));
            $t->same('paragraph', $paragraph->type);
            $t->same('link', $link->type);
            $t->same('#review-heading', $link->attr('url'));
            $t->same('Review Heading', $linkText->attr('text'));
            $t->contains('<h1 id="review-heading" class="audit" data-kind="heading">Review Heading</h1>', $blocks);
            $t->contains('<a href="#review-heading">Review Heading</a>', $blocks);
        },

    'keeps selected upstream markdown header-attribute fixture behind extension gate' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-header-attributes.md');
            $strict = (new MarkdownReader(['format' => 'markdown_strict']))->read($source);
            $strictWithHeaderAttributes = (new MarkdownReader(['format' => 'markdown_strict+header_attributes']))->read($source);
            $strictHeading = $strict->children[0] ?? new AstNode('missing');
            $enabledHeading = $strictWithHeaderAttributes->children[0] ?? new AstNode('missing');

            $t->same('heading', $strictHeading->type);
            $t->same('Review Heading {#review-heading .audit data-kind="heading"}', $strictHeading->attr('text'));
            $t->same([], $strictHeading->attr('classes', []));
            $t->same([], $strictHeading->attr('attributes', []));
            $t->same('heading', $enabledHeading->type);
            $t->same('Review Heading', $enabledHeading->attr('text'));
            $t->same('review-heading', $enabledHeading->attr('id'));
            $t->same(['audit'], $enabledHeading->attr('classes'));
            $t->same(['data-kind' => 'heading'], $enabledHeading->attr('attributes'));
        },

    'records selected upstream markdown header-attribute fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-header-attributes.md');
            $rows = array_values(array_filter(
                preg_split('/\R/', trim($source)) ?: [],
                static fn (string $row): bool => $row !== ''
            ));

            $t->same(2, count($rows));
            $t->same('# Review Heading {#review-heading .audit data-kind="heading"}', $rows[0]);
            $t->same('[Review Heading]', $rows[1]);
        },
];
