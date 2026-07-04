<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$fixture = (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-markdown-figure-latex-placement.md'
);

return [
    'maps upstream markdown figure latex-placement fixture' => static function (TestRunner $t) use ($fixture): void {
        $document = (new MarkdownReader(['format' => 'markdown']))->read($fixture);
        $figure = $document->children[0] ?? new AstNode('missing');
        $image = $figure->children[0] ?? new AstNode('missing');
        $label = $image->children[0] ?? new AstNode('missing');
        $figureAttributes = $figure->attr('attributes', []);

        $t->same(['figure'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('figure', $figure->type);
        $t->same('caption', $figure->attr('caption'));
        $t->same(['latex-placement' => 'htbp'], $figureAttributes);
        $t->same(['latex-placement' => 'htbp'], $figure->attr('htmlAttributes'));
        $t->same(false, array_key_exists('alt', $figureAttributes));
        $t->same('image', $image->type);
        $t->same('img.jpg', $image->attr('url'));
        $t->same('alt text', $image->attr('alt'));
        $t->same('caption', $image->attr('caption'));
        $t->same([
            'attributes' => ['latex-placement' => 'htbp'],
            'htmlAttributes' => ['latex-placement' => 'htbp'],
        ], $image->attr('figureAttributes'));
        $t->same('text', $label->type);
        $t->same('alt text', $label->attr('text'));
    },

    'records upstream markdown figure latex-placement fixture mapped-case count' => static function (TestRunner $t) use ($fixture): void {
        $rows = array_values(array_filter(
            preg_split('/\R/', trim($fixture)) ?: [],
            static fn (string $row): bool => $row !== ''
        ));

        $t->same(1, count($rows));
        $t->same('![caption](img.jpg){latex-placement="htbp" alt="alt text"}', $rows[0]);
    },
];
