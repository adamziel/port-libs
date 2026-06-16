<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 1], [$text($value)]);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$abbr = static fn (string $term, array $attrs = []): AstNode => new AstNode('span', $attrs, [$text($term)]);
$abbrWithTitleAttribute = static fn (string $term, string $name, string $title): AstNode => $abbr($term, [
    'classes' => ['abbr'],
    'attributes' => [$name => $title],
]);
$abbrWithScalarTitle = static fn (string $term, string $name, string $title): AstNode => $abbr($term, [
    'classes' => ['abbr'],
    $name => $title,
]);

$cases = [];

$titleAliasCases = [
    'title attribute' => $abbrWithTitleAttribute('API', 'title', 'Application Programming Interface'),
    'abbr title attribute' => $abbrWithTitleAttribute('HTML', 'abbr-title', 'Hypertext Markup Language'),
    'abbreviation title attribute' => $abbrWithTitleAttribute('CSS', 'abbreviation-title', 'Cascading Style Sheets'),
    'acronym title attribute' => $abbrWithTitleAttribute('DOM', 'acronym-title', 'Document Object Model'),
    'expansion attribute' => $abbrWithTitleAttribute('URI', 'expansion', 'Uniform Resource Identifier'),
    'definition attribute' => $abbrWithTitleAttribute('AST', 'definition', 'Abstract Syntax Tree'),
    'title text scalar' => $abbrWithScalarTitle('PDF', 'titleText', 'Portable Document Format'),
    'abbr title scalar' => $abbrWithScalarTitle('XML', 'abbrTitle', 'Extensible Markup Language'),
    'abbreviation title scalar' => $abbrWithScalarTitle('JSON', 'abbreviationTitle', 'JavaScript Object Notation'),
    'acronym title scalar' => $abbrWithScalarTitle('CPU', 'acronymTitle', 'Central Processing Unit'),
    'expansion scalar' => $abbrWithScalarTitle('CLI', 'expansion', 'Command Line Interface'),
    'definition scalar' => $abbrWithScalarTitle('SDK', 'definition', 'Software Development Kit'),
];

foreach ($titleAliasCases as $label => $node) {
    $term = $node->children[0]->attr('text');
    $attrs = $node->attr('attributes', []);
    $title = '';
    foreach (['title', 'abbr-title', 'abbreviation-title', 'acronym-title', 'expansion', 'definition'] as $name) {
        if (isset($attrs[$name])) {
            $title = $attrs[$name];
        }
        if (is_scalar($node->attr($name))) {
            $title = (string) $node->attr($name);
        }
    }
    foreach (['titleText', 'abbrTitle', 'abbreviationTitle', 'acronymTitle'] as $name) {
        if (is_scalar($node->attr($name))) {
            $title = (string) $node->attr($name);
        }
    }

    $cases['title alias ' . $label] = [
        'document' => $document([$paragraph([$text('Term '), $node])]),
        'expected' => "Term {$term}\n\n*[{$term}]: {$title}",
    ];
}

$profileCases = [
    'markdown default writes definition' => [
        'node' => $abbrWithTitleAttribute('HTML', 'title', 'Hypertext Markup Language'),
        'options' => ['format' => 'markdown'],
        'expected' => "HTML\n\n*[HTML]: Hypertext Markup Language",
    ],
    'commonmark disables abbreviation syntax' => [
        'node' => $abbrWithTitleAttribute('HTML', 'title', 'Hypertext Markup Language'),
        'options' => ['format' => 'commonmark'],
        'expected' => '<span class="abbr" title="Hypertext Markup Language">HTML</span>',
    ],
    'commonmark override enables abbreviation syntax' => [
        'node' => $abbrWithTitleAttribute('HTML', 'title', 'Hypertext Markup Language'),
        'options' => ['format' => 'commonmark+abbreviations'],
        'expected' => "HTML\n\n*[HTML]: Hypertext Markup Language",
    ],
    'gfm disables abbreviation syntax' => [
        'node' => $abbrWithTitleAttribute('CSS', 'title', 'Cascading Style Sheets'),
        'options' => ['format' => 'gfm'],
        'expected' => '<span class="abbr" title="Cascading Style Sheets">CSS</span>',
    ],
    'gfm override enables abbreviation syntax' => [
        'node' => $abbrWithTitleAttribute('CSS', 'title', 'Cascading Style Sheets'),
        'options' => ['format' => 'gfm+abbreviations'],
        'expected' => "CSS\n\n*[CSS]: Cascading Style Sheets",
    ],
    'markdown disable keeps bracketed span' => [
        'node' => $abbrWithTitleAttribute('API', 'title', 'Application Programming Interface'),
        'options' => ['format' => 'markdown-abbreviations'],
        'expected' => '[API]{.abbr title="Application Programming Interface"}',
    ],
    'markdown strict preserves abbreviation extension' => [
        'node' => $abbrWithTitleAttribute('CPU', 'title', 'Central Processing Unit'),
        'options' => ['format' => 'markdown_strict'],
        'expected' => "CPU\n\n*[CPU]: Central Processing Unit",
    ],
    'markdown phpextra preserves abbreviation extension' => [
        'node' => $abbrWithTitleAttribute('DOM', 'title', 'Document Object Model'),
        'options' => ['format' => 'markdown_phpextra'],
        'expected' => "DOM\n\n*[DOM]: Document Object Model",
    ],
];

foreach ($profileCases as $label => $case) {
    $cases['profile ' . $label] = [
        'document' => $document([$paragraph([$case['node']])]),
        'expected' => $case['expected'],
        'options' => $case['options'],
    ];
}

$queueCases = [
    'end of document batches definitions' => [
        'options' => ['referenceLocation' => 'end_of_document'],
        'document' => $document([
            $paragraph([$abbrWithTitleAttribute('HTML', 'title', 'Hypertext Markup Language')]),
            $paragraph([$abbrWithTitleAttribute('CSS', 'title', 'Cascading Style Sheets')]),
        ]),
        'expected' => "HTML\n\nCSS\n\n*[HTML]: Hypertext Markup Language\n*[CSS]: Cascading Style Sheets",
    ],
    'end of block flushes after each block' => [
        'options' => ['referenceLocation' => 'end_of_block'],
        'document' => $document([
            $paragraph([$abbrWithTitleAttribute('HTML', 'title', 'Hypertext Markup Language')]),
            $paragraph([$abbrWithTitleAttribute('CSS', 'title', 'Cascading Style Sheets')]),
        ]),
        'expected' => "HTML\n\n*[HTML]: Hypertext Markup Language\n\nCSS\n\n*[CSS]: Cascading Style Sheets",
    ],
    'end of section flushes before heading' => [
        'options' => ['referenceLocation' => 'end_of_section'],
        'document' => $document([
            $paragraph([$abbrWithTitleAttribute('HTML', 'title', 'Hypertext Markup Language')]),
            $heading('Next'),
            $paragraph([$abbrWithTitleAttribute('CSS', 'title', 'Cascading Style Sheets')]),
        ]),
        'expected' => "HTML\n\n*[HTML]: Hypertext Markup Language\n\n# Next\n\nCSS\n\n*[CSS]: Cascading Style Sheets",
    ],
    'duplicate term keeps latest title in document batch' => [
        'options' => ['referenceLocation' => 'end_of_document'],
        'document' => $document([
            $paragraph([$abbrWithTitleAttribute('API', 'title', 'Application Programming Interface')]),
            $paragraph([$abbrWithTitleAttribute('API', 'title', 'Application Program Interface')]),
        ]),
        'expected' => "API\n\nAPI\n\n*[API]: Application Program Interface",
    ],
    'empty title falls back to bracketed span' => [
        'document' => $document([$paragraph([$abbrWithTitleAttribute('URI', 'title', '')])]),
        'expected' => '[URI]{.abbr title=""}',
    ],
    'extra attribute falls back to bracketed span' => [
        'document' => $document([$paragraph([$abbr('URI', [
            'classes' => ['abbr'],
            'attributes' => ['title' => 'Uniform Resource Identifier', 'data-source' => 'fixture'],
        ])])]),
        'expected' => '[URI]{.abbr title="Uniform Resource Identifier" data-source="fixture"}',
    ],
    'bracket character term falls back to bracketed span' => [
        'document' => $document([$paragraph([$abbrWithTitleAttribute('A]B', 'title', 'Bracketed Term')])]),
        'expected' => '[A\\]B]{.abbr title="Bracketed Term"}',
    ],
    'formatted term falls back to bracketed span' => [
        'document' => $document([$paragraph([new AstNode('span', [
            'classes' => ['abbr'],
            'attributes' => ['title' => 'Formatted Term'],
        ], [
            new AstNode('emph', [], [$text('FMT')]),
        ])])]),
        'expected' => '[*FMT*]{.abbr title="Formatted Term"}',
    ],
    'newline title falls back to bracketed span' => [
        'document' => $document([$paragraph([$abbrWithTitleAttribute('SQL', 'title', "Structured\nQuery Language")])]),
        'expected' => "[SQL]{.abbr title=\"Structured Query Language\"}",
    ],
    'markdown mmd keeps definition queue' => [
        'options' => ['format' => 'markdown_mmd'],
        'document' => $document([$paragraph([$abbrWithTitleAttribute('MMD', 'title', 'MultiMarkdown')])]),
        'expected' => "MMD\n\n*[MMD]: MultiMarkdown",
    ],
];

foreach ($queueCases as $label => $case) {
    $cases['queue ' . $label] = $case;
}

$tests = [
    'records markdown writer abbreviation definition profile surge mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(30, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer abbreviation definition profile surge ' . $label] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter($case['options'] ?? []))->write($case['document']);

            $t->same($case['expected'], $markdown);
        };
}

return $tests;
