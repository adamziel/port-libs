<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = static function (): string {
    $bytes = file_get_contents(
        dirname(__DIR__) . '/fixtures/upstream-command-7080-mmd-reference-image-attributes.md'
    );
    if ($bytes === false) {
        throw new RuntimeException('Unable to read upstream-command-7080-mmd-reference-image-attributes.md');
    }

    return $bytes;
};

return [
    'maps upstream command 7080 multimarkdown reference image attributes' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown_mmd']))->read($fixture());

            $t->same(1, count($document->children));
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $t->same('paragraph', $paragraph->type);
            $t->same(1, count($paragraph->children));

            $image = $paragraph->children[0] ?? new AstNode('missing');
            $t->same('image', $image->type);
            $t->same('image.png', $image->attr('url'));
            $t->same('', $image->attr('title', ''));
            $t->same('', $image->attr('alt'));
            $t->same([], $image->children);
            $t->same(['width' => '100px', 'height' => '150px'], $image->attr('attributes'));
        },

    'keeps empty-alt standalone reference images as paragraphs' =>
        static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'markdown_mmd']))->read(
                "![][image]\n\n[image]: image.png\n"
            );

            $t->same(1, count($document->children));
            $t->same('paragraph', $document->children[0]->type);
            $t->same('image', $document->children[0]->children[0]->type);
            $t->same([], $document->children[0]->children[0]->children);
        },

    'keeps nonempty standalone image labels as implicit figures' =>
        static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'markdown_mmd']))->read("![alt](image.png)\n");

            $t->same(1, count($document->children));
            $t->same('figure', $document->children[0]->type);
            $t->same('alt', $document->children[0]->attr('caption'));
        },

    'keeps multimarkdown reference image attributes scoped to multimarkdown' =>
        static function (TestRunner $t) use ($fixture): void {
            $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture());

            $paragraph = $document->children[0] ?? new AstNode('missing');
            $image = $paragraph->children[0] ?? new AstNode('missing');
            $t->same('paragraph', $paragraph->type);
            $t->same('image.png%20width=100px%20height=150px', $image->attr('url'));
            $t->same([], $image->attr('attributes', []));
        },
];
