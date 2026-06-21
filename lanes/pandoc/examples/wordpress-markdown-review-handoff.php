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
        new AstNode('text', ['text' => 'Reviewer line break handoff: keep source line']),
        new AstNode('linebreak'),
        new AstNode('text', ['text' => 'attached to the editor continuation.']),
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
        new AstNode('text', ['text' => 'Reviewer metadata span: ']),
        new AstNode('span', [
            'id' => 'migration-span',
            'classes' => ['review-span'],
            'attributes' => [
                'data-source' => 'batch-42',
                'title' => 'Migration span',
            ],
        ], [
            new AstNode('emph', [], [new AstNode('text', ['text' => 'urgent'])]),
            new AstNode('text', ['text' => ' source flag']),
        ]),
        new AstNode('text', ['text' => ' uses ']),
        new AstNode('code', [
            'text' => 'wp_enqueue_script',
            'id' => 'enqueue-call',
            'classes' => ['php'],
            'attributes' => ['data-source' => 'batch-42'],
        ]),
        new AstNode('text', ['text' => ' and keeps emoji ']),
        new AstNode('span', [
            'classes' => ['emoji'],
            'attributes' => ['data-emoji' => 'smile'],
        ], [
            new AstNode('text', ['text' => "\u{1F604}"]),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer quote/style handoff: ']),
        new AstNode('quoted', ['kind' => 'double'], [
            new AstNode('text', ['text' => 'source excerpt']),
        ]),
        new AstNode('text', ['text' => ' keeps ']),
        new AstNode('underline', [], [
            new AstNode('text', ['text' => 'manual underlines']),
        ]),
        new AstNode('text', ['text' => ' and ']),
        new AstNode('small_caps', [], [
            new AstNode('text', ['text' => 'source glossary']),
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer highlight handoff: ']),
        new AstNode('span', ['classes' => ['mark']], [
            new AstNode('text', ['text' => 'verify source caption']),
        ]),
        new AstNode('text', ['text' => ' while literal ==audit tokens== stay text.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer math/raw handoff: water H']),
        new AstNode('subscript', [], [
            new AstNode('text', ['text' => '2']),
        ]),
        new AstNode('text', ['text' => ' status ']),
        new AstNode('superscript', [], [
            new AstNode('emph', [], [
                new AstNode('text', ['text' => 'draft']),
            ]),
        ]),
        new AstNode('text', ['text' => ' replaces ']),
        new AstNode('strikeout', [], [
            new AstNode('text', ['text' => 'legacy TeX screenshot']),
        ]),
        new AstNode('text', ['text' => ' with ']),
        new AstNode('math', ['text' => 'x \in y', 'display' => false]),
        new AstNode('text', ['text' => '2 review marker and raw ']),
        new AstNode('raw_tex', ['tex' => '\cite[22-23]{smith.1899}']),
        new AstNode('text', ['text' => ' plus packet ']),
        new AstNode('raw_inline', ['format' => 'opml', 'text' => '<outline text="Legacy source"/>']),
        new AstNode('text', ['text' => ' and raw HTML marker ']),
        new AstNode('raw_html_inline', ['html' => '<mark data-source="batch-42">legacy HTML source</mark>']),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('div', [
        'id' => 'review-packet',
        'classes' => ['wp-import-review'],
        'attributes' => ['data-source' => 'batch-42'],
    ], [
        new AstNode('paragraph', [], [
            new AstNode('text', ['text' => 'Block-level reviewer packet stays grouped for Pandoc-compatible handoff.']),
        ]),
        new AstNode('raw_block', [
            'format' => 'opml',
            'text' => '<outline text="Legacy WordPress source" type="review"/>',
        ]),
    ]),
    new AstNode('raw_tex', [
        'tex' => "\\begin{migrationreview}\nConfirm source citations before publish.\n\\end{migrationreview}",
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer citation handoff: ']),
        new AstNode('citation', [
            'citations' => [
                [
                    'id' => 'migration-audit',
                    'mode' => 'author_in_text',
                    'suffix' => [new AstNode('text', ['text' => 'p. 12'])],
                ],
                [
                    'id' => 'source-log',
                    'prefix' => [new AstNode('text', ['text' => 'see'])],
                    'suffix' => [new AstNode('text', ['text' => 'ch. 4'])],
                ],
            ],
        ]),
        new AstNode('text', ['text' => ' and suppressed batch ']),
        new AstNode('citation', [
            'citations' => [
                [
                    'id' => 'legacy key',
                    'mode' => 'suppress_author',
                    'suffix' => [new AstNode('text', ['text' => ', appendix'])],
                ],
            ],
        ]),
        new AstNode('text', ['text' => '.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => 'Reviewer emphasis normalization: ']),
        new AstNode('emph', [], [
            new AstNode('emph', [], [
                new AstNode('text', ['text' => 'source flag']),
            ]),
        ]),
        new AstNode('text', ['text' => ' and empty source marks ']),
        new AstNode('strong', [], []),
        new AstNode('text', ['text' => 'drop before handoff.']),
    ]),
    new AstNode('paragraph', [], [
        new AstNode('text', ['text' => '# Literal audit tokens: * _ ` | ^ ~ $ <review> &ouml; \\macro']),
    ]),
]);

echo (new MarkdownWriter([
    'referenceLinks' => true,
    'referenceLocation' => 'end_of_block',
    'setextHeadings' => true,
]))->write($document) . "\n";
