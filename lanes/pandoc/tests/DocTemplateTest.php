<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocTemplate;

return [
    'renders pandoc doctemplate variables delimiters comments and literals' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Title: $ title $
Author: ${ author.name }
Keywords: ${keywords[, ]}
Metadata present: $meta$
Missing: <$missing$>
Cost: $$5
$-- this comment should be omitted
Body: $body$
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Import Review',
            'author' => ['name' => 'Ada Editor'],
            'keywords' => ['migration', 'wordpress', 'review'],
            'meta' => ['generator' => 'pandoc'],
            'body' => '<p>Review body</p>',
        ]);

        $t->same(implode("\n", [
            'Title: Import Review',
            'Author: Ada Editor',
            'Keywords: migration, wordpress, review',
            'Metadata present: true',
            'Missing: <>',
            'Cost: $5',
            'Body: <p>Review body</p>',
        ]), $output);
    },

    'evaluates pandoc doctemplate conditionals elseif and truthiness' => static function (TestRunner $t): void {
        $template = <<<'TPL'
$if(title)$title:$title$$elseif(fallback)$fallback:$fallback$$else$untitled$endif$
$if(disabled)$disabled$elseif(flag)$flag:$flag$$else$no flag$endif$
$if(values)$values true$else$values false$endif$
$if(empty)$empty true$else$empty false$endif$
$if(meta)$meta true$endif$
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => '',
            'fallback' => 'Imported Batch',
            'disabled' => false,
            'flag' => '0',
            'values' => [false, '', 'kept'],
            'empty' => [false, ''],
            'meta' => ['present' => false],
        ]);

        $t->same(implode("\n", [
            'fallback:Imported Batch',
            'flag:0',
            'values true',
            'empty false',
            'meta true',
        ]), $output);
    },

    'renders pandoc doctemplate for loops arrays maps scalars and separators' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Authors: $for(authors)$$authors.name$ <$it.role$>$sep$; $endfor$
Import: $for(import)$$it.source$: $import.count$$endfor$
Lang: $for(lang)$[$it$/$lang$]$endfor$
TPL;

        $output = (new DocTemplate())->render($template, [
            'authors' => [
                ['name' => 'Ada', 'role' => 'lead'],
                ['name' => 'Grace', 'role' => 'review'],
            ],
            'import' => ['source' => 'wxr', 'count' => 42],
            'lang' => 'en-US',
        ]);

        $t->same(implode("\n", [
            'Authors: Ada <lead>; Grace <review>',
            'Import: wxr: 42',
            'Lang: [en-US/en-US]',
        ]), $output);
    },

    'renders nested pandoc doctemplate loops and conditionals with mixed delimiters' => static function (TestRunner $t): void {
        $template = '${for(sections)}${if(it.visible)}${it.title}:${for(it.items)} ${it}${endfor}${elseif(it.fallback)}${it.fallback}${endif}${sep}|${endfor}';

        $output = (new DocTemplate())->render($template, [
            'sections' => [
                ['title' => 'Intro', 'visible' => true, 'items' => ['one', 'two']],
                ['title' => 'Hidden', 'visible' => false, 'fallback' => 'redacted'],
                ['title' => 'Empty', 'visible' => false, 'items' => ['ignored']],
            ],
        ]);

        $t->same('Intro: one two|redacted|', $output);
    },

    'nests multiline pandoc doctemplate variables with explicit caret alignment' => static function (TestRunner $t): void {
        $template = '$for(items)$<li>$it.number$ $^$$it.description$ <a href="$it.editUrl$">edit</a></li>$sep$' . "\n" . '$endfor$';

        $output = (new DocTemplate())->render($template, [
            'items' => [
                [
                    'number' => '01',
                    'description' => "Imported paragraph\nNeeds media review",
                    'editUrl' => 'https://example.test/wp-admin/post.php?post=42&amp;action=edit',
                ],
                [
                    'number' => '02',
                    'description' => 'Inline source note',
                    'editUrl' => 'https://example.test/wp-admin/post.php?post=43&amp;action=edit',
                ],
            ],
        ]);

        $t->same(implode("\n", [
            '<li>01 Imported paragraph',
            '       Needs media review <a href="https://example.test/wp-admin/post.php?post=42&amp;action=edit">edit</a></li>',
            '<li>02 Inline source note <a href="https://example.test/wp-admin/post.php?post=43&amp;action=edit">edit</a></li>',
        ]), $output);
    },

    'automatically nests multiline pandoc doctemplate variables that stand alone on indented lines' => static function (TestRunner $t): void {
        $template = <<<'TPL'
<section class="wp-import-body">
  $body$
</section>
TPL;

        $output = (new DocTemplate())->render($template, [
            'body' => "<!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p>Needs review.</p><!-- /wp:paragraph -->",
        ]);

        $t->same(implode("\n", [
            '<section class="wp-import-body">',
            '  <!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->',
            '  <!-- wp:paragraph --><p>Needs review.</p><!-- /wp:paragraph -->',
            '</section>',
        ]), $output);
    },

    'omits a single final newline from interpolated pandoc doctemplate variables' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->render(<<<'TPL'
Body:<$body$>
Chomp:<$body/chomp$>
Crlf:<$crlf$>
List:$for(items)$[$it$]$endfor$
TPL, [
            'body' => "Imported paragraph\nNeeds review\n\n",
            'crlf' => "Windows line\r\n",
            'items' => ["first\n", "second\n\n"],
        ]);

        $t->same(implode("\n", [
            'Body:<Imported paragraph',
            'Needs review',
            '>',
            'Chomp:<Imported paragraph',
            'Needs review>',
            'Crlf:<Windows line>',
            'List:[first][second',
            ']',
        ]), $output);

        $output = $renderer->render(<<<'TPL'
<section class="wp-import-body">
  $body$
</section>
TPL, [
            'body' => "<!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->\n<!-- wp:paragraph --><p>Needs review.</p><!-- /wp:paragraph -->\n",
        ]);

        $t->same(implode("\n", [
            '<section class="wp-import-body">',
            '  <!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->',
            '  <!-- wp:paragraph --><p>Needs review.</p><!-- /wp:paragraph -->',
            '</section>',
        ]), $output);
    },

    'renders pandoc doctemplate breakable space markers without leaking markers' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Summary: $~$$warnings/length$ warnings queued for $title$$~$
$if(warnings)$
<ul>
$for(warnings)$<li>$~$$it.source/uppercase$: $it.message$$~$</li>
$endfor$</ul>
$endif$
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Batch 42 Review',
            'warnings' => [
                ['source' => 'media', 'message' => 'Check alt text before publish'],
                ['source' => 'links', 'message' => 'Verify redirects before publish'],
            ],
        ]);

        $t->same(implode("\n", [
            'Summary: 2 warnings queued for Batch 42 Review',
            '',
            '<ul>',
            '<li>MEDIA: Check alt text before publish</li>',
            '<li>LINKS: Verify redirects before publish</li>',
            '</ul>',
            '',
        ]), $output);
    },

    'renders parameter-free pandoc doctemplate pipes for text arrays and maps' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Title: $title/uppercase$ / $title/uppercase/lowercase$
Title length: $title/length$
Keywords: $keywords/length$ total
First: $keywords/first$
Last: $keywords/last$
Reverse: $for(keywords/reverse)$$it$$sep$ | $endfor$
Rest: $for(keywords/rest)$$it$$sep$, $endfor$
All but last: $for(keywords/allbutlast)$$it$$sep$, $endfor$
Body: <$body/chomp$>
Nowrap: <$title/nowrap$>
Meta: $for(meta/pairs)$$it.key$=$it.value$$sep$; $endfor$
Indexed: $for(keywords/pairs)$$it.key$:$it.value$$sep$; $endfor$
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Import Review',
            'keywords' => ['migration', 'wordpress', 'review'],
            'body' => "Imported paragraph\n\n",
            'meta' => ['format' => 'docx', 'status' => 'draft'],
        ]);

        $t->same(implode("\n", [
            'Title: IMPORT REVIEW / import review',
            'Title length: 13',
            'Keywords: 3 total',
            'First: migration',
            'Last: review',
            'Reverse: review | wordpress | migration',
            'Rest: wordpress, review',
            'All but last: migration, wordpress',
            'Body: <Imported paragraph>',
            'Nowrap: <Import Review>',
            'Meta: format=docx; status=draft',
            'Indexed: 1:migration; 2:wordpress; 3:review',
        ]), $output);
    },

    'applies pandoc doctemplate pipes to loop expressions and conditionals' => static function (TestRunner $t): void {
        $template = <<<'TPL'
$if(warnings/first)$Warnings:
$for(warnings/pairs)$- $it.key$. $it.value.source/uppercase$: $it.value.message$
$endfor$$endif$$if(empty/rest)$unexpected$else$empty rest suppressed$endif$
TPL;

        $output = (new DocTemplate())->render($template, [
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
                ['source' => 'links', 'message' => 'Review redirects'],
            ],
            'empty' => [],
        ]);

        $t->same(implode("\n", [
            'Warnings:',
            '- 1. MEDIA: Confirm alt text',
            '- 2. LINKS: Review redirects',
            'empty rest suppressed',
        ]), $output);
    },

    'renders pandoc doctemplate parameterized enumeration and padding pipes' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Checklist:
$for(items/pairs)$  $it.key/alpha/uppercase$. $it.value.title/left 18 "| " " |"$ $it.value.priority/roman/uppercase/right 4$
$endfor$Codes: $for(codes/roman)$$it$$sep$ $endfor$
Centered: <$title/center 16 "[ " " ]"$>
Escaped: <$title/left 17 "\" " " \\end"$>
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Batch 42',
            'items' => [
                ['title' => 'Media audit', 'priority' => 1],
                ['title' => 'Link redirects', 'priority' => 4],
                ['title' => 'Style review', 'priority' => 9],
            ],
            'codes' => [1, 5, 20],
        ]);

        $t->same(implode("\n", [
            'Checklist:',
            '  A. | Media audit        |    I',
            '  B. | Link redirects     |   IV',
            '  C. | Style review       |   IX',
            'Codes: i v xx',
            'Centered: <[     Batch 42     ]>',
            'Escaped: <" Batch 42          \end>',
        ]), $output);
    },

    'pads pandoc doctemplate block pipes by unicode display width' => static function (TestRunner $t): void {
        $template = <<<'TPL'
CJK: <$cjk/left 6 "|" "|"$>
Emoji: <$emoji/right 4 "|" "|"$>
Accent: <$accent/center 6 "|" "|"$>
Rows:
$for(items)$- $it.label/left 8 "|" "|"$ $it.status$
$endfor$
TPL;

        $output = (new DocTemplate())->render($template, [
            'cjk' => '漢字',
            'emoji' => "🧑🏾‍💻",
            'accent' => "Cafe\u{0301}",
            'items' => [
                ['label' => '魚', 'status' => 'source'],
                ['label' => "A\u{0301}", 'status' => 'accent'],
                ['label' => "☑️", 'status' => 'emoji'],
            ],
        ]);

        $t->same(implode("\n", [
            'CJK: <|漢字  |>',
            'Emoji: <|  🧑🏾‍💻|>',
            "Accent: <| Cafe\u{0301} |>",
            'Rows:',
            '- |魚      | source',
            "- |A\u{0301}       | accent",
            '- |☑️      | emoji',
            '',
        ]), $output);
    },

    'leaves non textual values unchanged for pandoc doctemplate block pipes' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Meta: $meta/left 12 "| " " |"$
Flags: $for(flags/center 8)$$it$$sep$, $endfor$
TPL;

        $output = (new DocTemplate())->render($template, [
            'meta' => ['status' => 'draft'],
            'flags' => [true, false],
        ]);

        $t->same(implode("\n", [
            'Meta: true',
            'Flags: true, false',
        ]), $output);
    },

    'renders pandoc doctemplate partials nested partials and strips final newlines' => static function (TestRunner $t): void {
        $template = <<<'TPL'
${ review-header() }
<section class="wp-import-body">
  ${ review-body.html() }
</section>
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Batch 42 Review',
            'intro' => '<p>Imported paragraph needs review.</p>',
            'warnings' => ['Check media alt text', 'Verify redirected links'],
        ], [
            'review-header' => '<header><h1>$title$</h1></header>' . "\n",
            'review-body.html' => '$intro$' . "\n" . '${ warning-list() }' . "\n",
            'warning-list' => '$if(warnings)$<ul>$for(warnings)$<li>$it$</li>$endfor$</ul>$endif$' . "\n",
        ]);

        $t->same(implode("\n", [
            '<header><h1>Batch 42 Review</h1></header>',
            '<section class="wp-import-body">',
            '  <p>Imported paragraph needs review.</p>',
            '  <ul><li>Check media alt text</li><li>Verify redirected links</li></ul>',
            '</section>',
        ]), $output);
    },

    'applies pandoc doctemplate partials to variables arrays and pipes' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Cards:
${ articles:review-card()[
---
] }
Reviewer: ${ reviewer:badge()/uppercase }
TPL;

        $output = (new DocTemplate())->render($template, [
            'articles' => [
                ['source' => 'docx', 'title' => 'Imported heading', 'warning' => 'Check hierarchy'],
                ['source' => 'html', 'title' => 'Legacy block', 'warning' => 'Review shortcode'],
            ],
            'reviewer' => ['name' => 'Ada Editor', 'role' => 'migration'],
        ], [
            'review-card' => '<article data-source="$it.source$"><h2>$it.title$</h2><p>$it.warning$</p><span>$articles.title$</span></article>' . "\n",
            'badge' => '$it.name$ <$it.role$>',
        ]);

        $t->same(implode("\n", [
            'Cards:',
            '<article data-source="docx"><h2>Imported heading</h2><p>Check hierarchy</p><span></span></article>',
            '---',
            '<article data-source="html"><h2>Legacy block</h2><p>Review shortcode</p><span></span></article>',
            'Reviewer: ADA EDITOR <MIGRATION>',
        ]), $output);
    },

    'renders pandoc doctemplate partials inside explicit loops with current item context' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Warnings:
$for(warnings)$${ warning-row() }$sep$
$endfor$
TPL;

        $output = (new DocTemplate())->render($template, [
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
                ['source' => 'links', 'message' => 'Review redirects'],
            ],
        ], [
            'warning-row' => '- $it.source/uppercase$: $it.message$' . "\n",
        ]);

        $t->same(implode("\n", [
            'Warnings:',
            '- MEDIA: Confirm alt text',
            '- LINKS: Review redirects',
        ]), $output);
    },

    'renders pandoc doctemplate resources with partial extension inference' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('templates/review.html', [
            'templates/review.html' => <<<'HTML'
<article>
${ review_header() }
${ styles.html() }
<section>
${ warnings:warning-row()[
] }
</section>
</article>
HTML,
            'templates/review_header.html' => '<header><h1>$title_text$</h1></header>' . "\n",
            'templates/styles.html' => '<style>.warnings{color:$warning_color$}</style>' . "\n",
            'templates/warning-row.html' => '<p class="warnings" data-source="$it.source$">$it.message$</p>' . "\n",
        ], [
            'title_text' => 'Batch 42 Review',
            'warning_color' => 'crimson',
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
                ['source' => 'links', 'message' => 'Review redirects'],
            ],
        ]);

        $t->same(implode("\n", [
            '<article>',
            '<header><h1>Batch 42 Review</h1></header>',
            '<style>.warnings{color:crimson}</style>',
            '<section>',
            '<p class="warnings" data-source="media">Confirm alt text</p>',
            '<p class="warnings" data-source="links">Review redirects</p>',
            '</section>',
            '</article>',
        ]), $output);
    },

    'uses pandoc user data template fallback only for relative template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->renderResource('reports/review.html', [
            'reports/review.html' => '<article>$title$${ footer() }</article>',
            'wp-data/templates/footer.html' => '<footer>$reviewer$</footer>',
        ], [
            'title' => 'Relative packet',
            'reviewer' => 'Migration desk',
        ], 'wp-data');

        $t->same('<article>Relative packet<footer>Migration desk</footer></article>', $output);
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->renderResource('/reports/review.html', [
            '/reports/review.html' => '<article>$title$${ footer() }</article>',
            'wp-data/templates/footer.html' => '<footer>$reviewer$</footer>',
        ], [
            'title' => 'Absolute packet',
            'reviewer' => 'Migration desk',
        ], 'wp-data'));
    },

    'rejects unsafe pandoc doctemplate resource paths' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->renderResource('missing.html', [
            'review.html' => '$title$',
        ], ['title' => 'Missing']));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderResource('../review.html', [
            '../review.html' => '$title$',
        ], ['title' => 'Traversal']));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderResource('review.html', [
            'review.html' => '$title$',
            "bad\0name.html" => '$title$',
        ], ['title' => 'NUL']));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderResource('review.html', [
            'review.html' => ['not', 'a', 'template'],
        ], ['title' => 'Not string']));
    },

    'renders wordpress review packet templates without output escaping' => static function (TestRunner $t): void {
        $template = <<<'TPL'
<article class="wp-import-review">
<h1>$title$</h1>
<p class="authors">$for(authors)$$it.name$$sep$, $endfor$</p>
$if(warnings)$
<ul class="warnings">
$for(warnings)$<li data-source="$it.source$">$it.message$</li>
$endfor$</ul>
$endif$
$body$
</article>
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Batch 42 Review',
            'authors' => [
                ['name' => 'Migration bot'],
                ['name' => 'Content editor'],
            ],
            'warnings' => [
                ['source' => 'media', 'message' => 'Check &amp; confirm alt text'],
                ['source' => 'links', 'message' => 'Verify edit links before publish'],
            ],
            'body' => '<!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->',
        ]);

        $t->contains('<h1>Batch 42 Review</h1>', $output);
        $t->contains('<p class="authors">Migration bot, Content editor</p>', $output);
        $t->contains('<li data-source="media">Check &amp; confirm alt text</li>', $output);
        $t->contains('<!-- wp:paragraph --><p>Imported body is already escaped.</p><!-- /wp:paragraph -->', $output);
    },

    'throws on unclosed pandoc doctemplate control blocks' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$if(title)$missing endif', ['title' => true]));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$for(items)$missing endfor', ['items' => ['x']]));
    },

    'returns pandoc doctemplate loop literal at partial nesting limit' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same('(loop)', $renderer->render('${ loop() }', [], [
            'loop' => '${ loop() }',
        ]));
        $t->same(str_repeat('x', 50) . '(loop)', $renderer->render('${ loop() }', [], [
            'loop' => 'x${ loop() }',
        ]));
    },

    'throws on missing pandoc doctemplate partials' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('${ missing() }', [], []));
    },

    'throws on unsupported pandoc doctemplate pipes' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$title/left$', ['title' => 'Review']));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$title/left a$', ['title' => 'Review']));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$title/uppercase 20$', ['title' => 'Review']));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$title/no-such-pipe$', ['title' => 'Review']));
    },
];
