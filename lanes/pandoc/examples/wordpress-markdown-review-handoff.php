<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$markdown = <<<'MARKDOWN'
# Import Review

Migration source note.^[Keep the original archive link with the reviewer handoff.]

Reviewer source says [audit log](https://example.test/wp-admin/post.php?post=42&action=edit) before publish.

Adjacent source echoes: [source](https://example.test/source-a)[source](https://example.test/source-b) [source](https://example.test/source-c) [review brackets].

Citation-adjacent handoff: [citation source](https://example.test/citation)[@migration-audit]

> Block quote note.^[Quoted source needs editorial confirmation.]
>
> Keep the quoted source grouped with its note.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$document = new AstNode('document', $document->attrs, [
    ...$document->children,
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer packet links: ']),
        new AstNode('link', [
            'url' => 'https://example.test/review-packet',
            'classes' => ['uri'],
        ], [
            new AstNode('text', ['text' => 'https://example.test/review-packet']),
        ]),
        new AstNode('text', ['text' => ' and ']),
        new AstNode('link', [
            'url' => 'mailto:editor@example.test',
            'classes' => ['email'],
        ], [
            new AstNode('text', ['text' => 'editor@example.test']),
        ]),
        new AstNode('text', ['text' => ' plus ']),
        new AstNode('link', [
            'url' => 'https://example.test/review-packet',
            'title' => 'Review packet',
            'id' => 'review-packet',
            'classes' => ['source-link'],
            'attributes' => ['data-source' => 'batch-42'],
        ], [
            new AstNode('text', ['text' => 'packet']),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer media preview: ']),
        new AstNode('image', [
            'url' => 'https://example.test/uploads/review-screenshot.jpg',
            'title' => 'Review screenshot',
            'alt' => 'Screenshot alt text',
            'id' => 'review-screenshot',
            'classes' => ['source-image'],
            'attributes' => ['data-source' => 'batch-42'],
        ], [
            new AstNode('text', ['text' => 'Review screenshot']),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer code token: ']),
        new AstNode('code', [
            'text' => 'wp `meta` key',
            'id' => 'enqueue',
            'classes' => ['php', 'wp-import'],
            'attributes' => ['data-source' => 'batch-42'],
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('code_block', [
        'text' => "wp post meta get 42 source_url\n```\nkeep literal reviewer fence",
        'id' => 'review-wp-cli-snippet',
        'classes' => ['bash', 'wp-cli'],
        'attributes' => ['data-source' => 'batch-42'],
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer span marker: ']),
        new AstNode('span', [
            'id' => 'migration-span',
            'classes' => ['review-span'],
            'attributes' => [
                'data-source' => 'batch-42',
                'title' => 'Migration span',
            ],
        ], [
            new AstNode('emph', [], [new AstNode('text', ['text' => 'urgent'])]),
            new AstNode('text', ['text' => ' source flag ']),
            new AstNode('link', [
                'url' => '/wp-admin/post.php?post=42&action=edit',
            ], [
                new AstNode('text', ['text' => 'edit']),
            ]),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer inline marks: ']),
        new AstNode('small_caps', [], [
            new AstNode('text', ['text' => 'source glossary']),
        ]),
        new AstNode('text', ['text' => ', ']),
        new AstNode('strikeout', [], [
            new AstNode('text', ['text' => 'legacy caption']),
        ]),
        new AstNode('text', ['text' => ', revision']),
        new AstNode('superscript', [], [
            new AstNode('text', ['text' => 'draft 2']),
        ]),
        new AstNode('text', ['text' => ', and H']),
        new AstNode('subscript', [], [
            new AstNode('text', ['text' => '2']),
        ]),
        new AstNode('text', ['text' => 'O.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer quoted source: ']),
        new AstNode('quoted', ['kind' => 'double'], [
            new AstNode('text', ['text' => 'source says ']),
            new AstNode('quoted', ['kind' => 'single'], [
                new AstNode('code', ['text' => 'wp_insert_post']),
            ]),
            new AstNode('text', ['text' => ' before ']),
            new AstNode('link', [
                'url' => '/wp-admin/post.php?post=42&action=edit',
            ], [
                new AstNode('text', ['text' => 'edit']),
            ]),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer formula packet: ']),
        new AstNode('math', ['text' => 'E = mc^2', 'display' => false]),
        new AstNode('text', ['text' => ' cites ']),
        new AstNode('raw_tex', ['tex' => '\cite[22-23]{smith.1899}']),
        new AstNode('text', ['text' => ' and keeps ']),
        new AstNode('raw_inline', ['format' => 'markdown', 'text' => '*raw markdown*']),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => '# Literal audit tokens: * _ ` | ^ ~ $ <review> &ouml; \\macro']),
    ]),
    new AstNode('div', [
        'id' => 'migration-review-packet',
        'classes' => ['wp-import', 'needs-review'],
        'attributes' => ['data-source' => 'batch-42'],
    ], [
        new AstNode('paragraph', [], [
            new AstNode('text', ['text' => 'Reviewer packet wrapper: keep this source group together.']),
        ]),
        new AstNode('blockquote', [], [
            new AstNode('paragraph', [], [
                new AstNode('text', ['text' => 'Nested reviewer quote remains inside the fenced div.']),
            ]),
        ]),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer line block stanza:']),
    ]),
    new AstNode('line_block', [], [
        new AstNode('line', ['text' => 'Source address line one'], [
            new AstNode('text', ['text' => 'Source address line one']),
        ]),
        new AstNode('line', ['text' => "\xC2\xA0\xC2\xA0preserve visual indentation"], [
            new AstNode('text', ['text' => "\xC2\xA0\xC2\xA0preserve visual indentation"]),
        ]),
        new AstNode('line', ['text' => '']),
        new AstNode('line', ['text' => 'Final source stanza line'], [
            new AstNode('text', ['text' => 'Final source stanza line']),
        ]),
    ]),
    new AstNode('table', [
        'caption' => 'Migration review queue',
        'captionInlines' => [
            new AstNode('text', ['text' => 'Migration ']),
            new AstNode('strong', [], [new AstNode('text', ['text' => 'review'])]),
            new AstNode('text', ['text' => ' queue']),
        ],
        'alignments' => ['right', 'left', 'center'],
        'widths' => [0.15, 0.25, 0.35],
    ], [
        new AstNode('table_head', [], [
            new AstNode('table_row', ['header' => true], [
                new AstNode('table_cell', ['text' => 'Posts'], [new AstNode('text', ['text' => 'Posts'])]),
                new AstNode('table_cell', ['text' => 'Status'], [new AstNode('text', ['text' => 'Status'])]),
                new AstNode('table_cell', ['text' => 'Reviewer note'], [new AstNode('text', ['text' => 'Reviewer note'])]),
            ]),
        ]),
        new AstNode('table_body', [], [
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => '42'], [new AstNode('text', ['text' => '42'])]),
                new AstNode('table_cell', ['text' => 'ready'], [new AstNode('text', ['text' => 'ready'])]),
                new AstNode('table_cell', [], [new AstNode('text', ['text' => 'source | audit'])]),
            ]),
            new AstNode('table_row', [], [
                new AstNode('table_cell', ['text' => '7'], [new AstNode('text', ['text' => '7'])]),
                new AstNode('table_cell', ['text' => 'needs-review'], [new AstNode('text', ['text' => 'needs-review'])]),
                new AstNode('table_cell', [], [
                    new AstNode('text', ['text' => 'line one']),
                    new AstNode('softbreak'),
                    new AstNode('text', ['text' => 'line two']),
                ]),
            ]),
        ]),
    ]),
    new AstNode('raw_block', [
        'format' => 'markdown',
        'text' => '> Raw reviewer block: keep this migration note with the handoff.',
    ]),
    new AstNode('raw_block', [
        'format' => 'html',
        'text' => '<aside>internal reviewer note omitted from Markdown handoff</aside>',
    ]),
]);

echo (new MarkdownWriter([
    'referenceLinks' => true,
    'referenceLocation' => 'end_of_block',
    'setextHeadings' => true,
]))->write($document) . "\n";
