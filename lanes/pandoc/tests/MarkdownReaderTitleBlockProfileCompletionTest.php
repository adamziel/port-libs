<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$source = implode("\n", [
    '% Profile *Title*',
    '% Ada Lovelace; Grace Hopper',
    '% 2026-06-30',
    '',
    'Body after title block.',
]);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$enabledProfiles = [
    'default markdown reader' => [],
    'markdown profile' => ['format' => 'markdown'],
    'pandoc alias profile' => ['format' => 'pandoc'],
    'explicit title block option' => ['titleBlock' => true],
    'commonmark opt in suffix' => ['format' => 'commonmark+pandoc_title_block'],
    'commonmark x opt in suffix' => ['format' => 'commonmark_x+pandoc_title_block'],
    'gfm opt in suffix' => ['format' => 'gfm+pandoc_title_block'],
    'strict opt in suffix' => ['format' => 'markdown_strict+pandoc_title_block'],
    'php extra opt in extension option' => ['format' => 'markdown_phpextra', 'extensions' => ['+pandoc_title_block']],
    'mmd opt in extension map' => ['format' => 'markdown_mmd', 'extensions' => ['pandoc_title_block' => true]],
];

$disabledProfiles = [
    'commonmark default' => ['format' => 'commonmark'],
    'commonmark x disabled suffix' => ['format' => 'commonmark_x-pandoc_title_block'],
    'gfm default' => ['format' => 'gfm'],
    'github alias default' => ['format' => 'markdown_github'],
    'strict default' => ['format' => 'markdown_strict'],
    'php extra default' => ['format' => 'markdown_phpextra'],
    'mmd default' => ['format' => 'markdown_mmd'],
    'markdown disabled suffix' => ['format' => 'markdown-pandoc_title_block'],
    'markdown disabled extension option' => ['format' => 'markdown', 'extensions' => ['pandoc_title_block' => false]],
    'explicit false option' => ['titleBlock' => false],
];

return [
    'maps upstream markdown title-block enabled profile extraction' =>
        static function (TestRunner $t) use ($enabledProfiles, $plainText, $source): void {
            foreach ($enabledProfiles as $label => $options) {
                $document = (new MarkdownReader($options))->read($source);
                $meta = $document->attr('meta', []);
                $body = $document->children[0] ?? new AstNode('missing');

                $t->same('Profile Title', $meta['title'] ?? null, $label);
                $t->same(['Ada Lovelace', 'Grace Hopper'], $meta['authors'] ?? null, $label);
                $t->same('2026-06-30', $meta['date'] ?? null, $label);
                $t->same(['text', 'emph'], array_map(static fn (AstNode $node): string => $node->type, $meta['titleInlines'] ?? []), $label);
                $t->same('Profile Title', $plainText(new AstNode('meta_title', [], $meta['titleInlines'] ?? [])), $label);
                $t->same('paragraph', $body->type, $label);
                $t->same('Body after title block.', $body->attr('text'), $label);
            }
        },

    'keeps upstream markdown title-block disabled profiles as body text' =>
        static function (TestRunner $t) use ($disabledProfiles, $plainText, $source): void {
            foreach ($disabledProfiles as $label => $options) {
                $document = (new MarkdownReader($options))->read($source);
                $meta = $document->attr('meta', []);
                $paragraph = $document->children[0] ?? new AstNode('missing');

                $t->same([], $meta, $label);
                $t->same('paragraph', $paragraph->type, $label);
                $t->same(['text', 'emph', 'softbreak', 'text', 'softbreak', 'text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children), $label);
                $t->same("% Profile Title\n% Ada Lovelace; Grace Hopper\n% 2026-06-30", $plainText($paragraph), $label);
                $t->same('Body after title block.', ($document->children[1] ?? new AstNode('missing'))->attr('text'), $label);
            }
        },

    'records upstream markdown title-block profile completion mapped-case count' =>
        static function (TestRunner $t) use ($enabledProfiles, $disabledProfiles): void {
            $t->same(20, count($enabledProfiles) + count($disabledProfiles));
        },
];
