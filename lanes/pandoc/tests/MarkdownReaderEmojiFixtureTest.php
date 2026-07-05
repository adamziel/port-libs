<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'maps selected upstream markdown emoji symbols fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-emoji-symbols.md');
            $document = (new MarkdownReader(['format' => 'markdown_github']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $smile = $paragraph->children[0] ?? new AstNode('missing');
            $separator = $paragraph->children[1] ?? new AstNode('missing');
            $thumbsUp = $paragraph->children[2] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same("\u{1F604} and \u{1F44D}", $paragraph->attr('text'));
            $t->same(['span', 'text', 'span'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same(['emoji'], $smile->attr('classes'));
            $t->same(['data-emoji' => 'smile'], $smile->attr('attributes'));
            $t->same("\u{1F604}", $smile->children[0]->attr('text'));
            $t->same(' and ', $separator->attr('text'));
            $t->same(['emoji'], $thumbsUp->attr('classes'));
            $t->same(['data-emoji' => '+1'], $thumbsUp->attr('attributes'));
            $t->same("\u{1F44D}", $thumbsUp->children[0]->attr('text'));
            $t->contains('<span class="emoji" data-emoji="smile">' . "\u{1F604}" . '</span>', $blocks);
            $t->contains('<span class="emoji" data-emoji="+1">' . "\u{1F44D}" . '</span>', $blocks);
        },

    'records upstream markdown emoji symbols fixture mapped-case count' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-emoji-symbols.md');
            $cases = array_values(array_filter(
                preg_split('/\R\R/', trim($source)) ?: [],
                static fn (string $case): bool => $case !== ''
            ));

            $t->same(1, count($cases));
            $t->same(':smile: and :+1:', $cases[0]);
        },

    'maps upstream command gfm adjacent emoji aliases fixture' =>
        static function (TestRunner $t): void {
            $source = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-gfm-adjacent-emoji.md');
            $document = (new MarkdownReader(['format' => 'gfm']))->read($source);
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(1, count($document->children));
            $t->same('paragraph', $paragraph->type);
            $t->same("My\u{1F44D}emoji\u{2764}\u{FE0F}", $paragraph->attr('text'));
            $t->same(['text', 'span', 'text', 'span'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));
            $t->same('My', $paragraph->children[0]->attr('text'));
            $t->same(['emoji'], $paragraph->children[1]->attr('classes'));
            $t->same(['data-emoji' => 'thumbsup'], $paragraph->children[1]->attr('attributes'));
            $t->same("\u{1F44D}", $paragraph->children[1]->children[0]->attr('text'));
            $t->same('emoji', $paragraph->children[2]->attr('text'));
            $t->same(['emoji'], $paragraph->children[3]->attr('classes'));
            $t->same(['data-emoji' => 'heart'], $paragraph->children[3]->attr('attributes'));
            $t->same("\u{2764}\u{FE0F}", $paragraph->children[3]->children[0]->attr('text'));
            $t->contains('<span class="emoji" data-emoji="thumbsup">' . "\u{1F44D}" . '</span>', $blocks);
            $t->contains('<span class="emoji" data-emoji="heart">' . "\u{2764}\u{FE0F}" . '</span>', $blocks);
        },
];
