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

    'preserves pandoc doctemplate non-column-one comment line endings' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->render(<<<'TPL'
Before
$-- column-one comment drops its line ending
After column
  $-- indented comment leaves the whitespace line intact
After indent
Inline $-- inline comment preserves the preceding text and line ending
After inline
TPL, []);

        $t->same(implode("\n", [
            'Before',
            'After column',
            '  ',
            'After indent',
            'Inline ',
            'After inline',
        ]), $output);

        $t->same("A\r\n  \r\nB", $renderer->render("A\r\n  $-- CRLF comment\r\nB", []));
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

    'renders pandoc doctemplate false booleans as empty text' => static function (TestRunner $t): void {
        $template = <<<'TPL'
False: <$flag$>
List: <$items[, ]$>
Loop: $for(items)$[$it$]$endfor$
Map: <$meta$>
Block: <$flag/left 8 "| " " |"$>
$if(flag)$unexpected$else$false branch$endif$
TPL;

        $output = (new DocTemplate())->render($template, [
            'flag' => false,
            'items' => [true, false, 'kept'],
            'meta' => ['present' => false],
        ]);

        $t->same(implode("\n", [
            'False: <>',
            'List: <true, , kept>',
            'Loop: [true][][kept]',
            'Map: <true>',
            'Block: <>',
            'false branch',
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

    'keeps pandoc doctemplate loop item fields from shadowing outer variables' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Document: $title$
$for(items)$- outer=$title$ item=$items.title$ it=$it.title$ source=<$source$>$sep$
$endfor$
Map: $for(import)$outer=$title$ map=$import.title$ it=$it.title$ direct=<$source$>$endfor$
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Batch 42 Review',
            'items' => [
                ['title' => 'Imported heading', 'source' => 'docx'],
                ['title' => 'Legacy block', 'source' => 'html'],
            ],
            'import' => ['title' => 'Import manifest', 'source' => 'wxr'],
        ]);

        $t->same(implode("\n", [
            'Document: Batch 42 Review',
            '- outer=Batch 42 Review item=Imported heading it=Imported heading source=<>',
            '- outer=Batch 42 Review item=Legacy block it=Legacy block source=<>',
            'Map: outer=Batch 42 Review map=Import manifest it=Import manifest direct=<>',
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

    'swallows multiline pandoc doctemplate control block newlines' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Before
$if(title)$
Title: $title$
$elseif(fallback)$
Fallback: $fallback$
$else$
Untitled
$endif$
Items:
$for(items)$
- $it.title$
$sep$
---
$endfor$
After
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => '',
            'fallback' => 'Imported Batch',
            'items' => [
                ['title' => 'Media audit'],
                ['title' => 'Link review'],
            ],
        ]);

        $t->same(implode("\n", [
            'Before',
            'Fallback: Imported Batch',
            'Items:',
            '- Media audit',
            '---',
            '- Link review',
            'After',
        ]), $output);
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

    'nests pandoc doctemplate multiline values by unicode display column' => static function (TestRunner $t): void {
        $accent = "A\u{0301}";
        $template = '$for(rows)$<p>$it.label$ $^$$it.note$</p>$sep$' . "\n" . '$endfor$';

        $output = (new DocTemplate())->render($template, [
            'rows' => [
                ['label' => '魚', 'note' => "First\nSecond"],
                ['label' => $accent, 'note' => "First\nSecond"],
                ['label' => "☑️", 'note' => "First\nSecond"],
            ],
        ]);

        $t->same(implode("\n", [
            '<p>魚 First',
            '      Second</p>',
            "<p>{$accent} First",
            '     Second</p>',
            '<p>☑️ First',
            '      Second</p>',
        ]), $output);
    },

    'keeps pandoc doctemplate explicit nesting through literal prefixes' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->render(<<<'TPL'
<p>$^$Note: $note$</p>
<aside>$^$Partial: ${ note-block() }</aside>
TPL, [
            'note' => "First line\nSecond line",
        ], [
            'note-block' => "Imported block\nNeeds review\n",
        ]);

        $t->same(implode("\n", [
            '<p>Note: First line',
            '   Second line</p>',
            '<aside>Partial: Imported block',
            '       Needs review</aside>',
        ]), $output);
    },

    'dedents pandoc doctemplate source-aligned literal lines inside explicit nesting' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
<ul>
<li>01 $^$$title$ ($status$)
       Source: $source$</li>
</ul>
TPL, [
            'title' => 'Imported heading',
            'status' => 'queued',
            'source' => 'DOCX',
        ]);

        $t->same(implode("\n", [
            '<ul>',
            '<li>01 Imported heading (queued)',
            '       Source: DOCX</li>',
            '</ul>',
        ]), $output);
    },

    'ends explicit pandoc doctemplate nesting before dedented source lines' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
<section>
<p class="summary">$^$$summary$
</p>
<p class="status">$^$Status: $status$
                 Owner: $owner$
</p>
</section>
TPL, [
            'summary' => 'Ready for import',
            'status' => 'queued',
            'owner' => 'Migration desk',
        ]);

        $t->same(implode("\n", [
            '<section>',
            '<p class="summary">Ready for import',
            '</p>',
            '<p class="status">Status: queued',
            '                 Owner: Migration desk',
            '</p>',
            '</section>',
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

    'recursively chomps pandoc doctemplate lists and maps' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
List: <$items/chomp[, ]$>
Map: $for(metadata/chomp/pairs)$$it.key$=<$it.value$>$sep$; $endfor$
Nested: $for(sections/chomp)$$it.title$: $it.note$$sep$ | $endfor$
Missing: <$missing/chomp$>
TPL, [
            'items' => ["alpha\n", "beta\n\n", 'gamma'],
            'metadata' => [
                'zeta' => "queued-last\n\n",
                'alpha' => "queued-first\n",
            ],
            'sections' => [
                ['title' => 'Media', 'note' => "Check alt text\n\n"],
                ['title' => 'Links', 'note' => "Review redirects\n"],
            ],
        ]);

        $t->same(implode("\n", [
            'List: <alpha, beta, gamma>',
            'Map: alpha=<queued-first>; zeta=<queued-last>',
            'Nested: Media: Check alt text | Links: Review redirects',
            'Missing: <>',
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
            '<ul>',
            '<li>MEDIA: Check alt text before publish</li>',
            '<li>LINKS: Verify redirects before publish</li>',
            '</ul>',
            '',
        ]), $output);
    },

    'reflows literal whitespace inside pandoc doctemplate breakable-space regions' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Summary: $~$$warnings/length$
  warnings	queued
for $title$$~$
Plain: one
  two
Inline: $~$alpha   beta	gamma$~$
Loop: $~$$for(warnings)$
$it.source$: $it.message$$sep$
 /
$endfor$$~$
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Batch 42 Review',
            'warnings' => [
                ['source' => 'media', 'message' => 'Check alt text'],
                ['source' => 'links', 'message' => 'Verify redirects'],
            ],
        ]);

        $t->same(implode("\n", [
            'Summary: 2 warnings queued for Batch 42 Review',
            'Plain: one',
            '  two',
            'Inline: alpha beta gamma',
            'Loop: media: Check alt text / links: Verify redirects',
        ]), $output);
    },

    'wraps pandoc doctemplate breakable spaces at bounded line lengths' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $template = <<<'TPL'
Summary: $~$alpha beta gamma delta$~$
Partial: ${ review-line() }
Nowrap: ${ review-line()/nowrap }
Plain: alpha beta gamma delta
TPL;

        $output = $renderer->renderWrapped($template, [], 19, [
            'review-line' => '$~$media links layout status$~$',
        ]);

        $t->same(implode("\n", [
            'Summary: alpha beta',
            'gamma delta',
            'Partial: media',
            'links layout status',
            'Nowrap: media links layout status',
            'Plain: alpha beta gamma delta',
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

    'renders pandoc doctemplate map pairs in deterministic upstream key order' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Metadata: $for(metadata/pairs)$$it.key$=$it.value$$sep$; $endfor$
List: $for(checks/pairs)$$it.key$=$it.value$$sep$; $endfor$
TPL;

        $output = (new DocTemplate())->render($template, [
            'metadata' => [
                'zeta' => 'queued-last',
                'alpha' => 'queued-first',
                'review-id' => 'PR-42',
            ],
            'checks' => ['media', 'links', 'layout'],
        ]);

        $t->same(implode("\n", [
            'Metadata: alpha=queued-first; review-id=PR-42; zeta=queued-last',
            'List: 1=media; 2=links; 3=layout',
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

    'joins pandoc doctemplate piped variables with trailing separators' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Keywords: $keywords/reverse/uppercase[ / ]$
Sources: ${ sources/rest/uppercase[, ] }
Missing: <$missing/rest[ | ]$>
TPL;

        $output = (new DocTemplate())->render($template, [
            'keywords' => ['migration', 'wordpress', 'review'],
            'sources' => ['media', 'links', 'layout'],
        ]);

        $t->same(implode("\n", [
            'Keywords: REVIEW / WORDPRESS / MIGRATION',
            'Sources: LINKS, LAYOUT',
            'Missing: <>',
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

    'renders braced pandoc doctemplate pipe arguments containing closing braces' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Box: ${ title/center 12 "{" "}" }
Sources: $for(warnings)$${ it.source/uppercase/left 8 "{" "}" }$sep$ $endfor$
Escaped: ${ title/right 8 "\}" "\{" }
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => 'Review',
            'warnings' => [
                ['source' => 'media'],
                ['source' => 'links'],
            ],
        ]);

        $t->same(implode("\n", [
            'Box: {   Review   }',
            'Sources: {MEDIA   } {LINKS   }',
            'Escaped: }  Review{',
        ]), $output);
    },

    'renders pandoc doctemplate alphabetic pipe overflow markers' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same('[y][z][aa][ab][az][ba][zz][aaa]', $renderer->render('$for(numbers)$[$it/alpha$]$endfor$', [
            'numbers' => [25, 26, 27, 28, 52, 53, 702, 703],
        ]));
        $t->same('Y Z AA AB AZ BA ZZ AAA', $renderer->render('$for(numbers)$$it/alpha/uppercase$$sep$ $endfor$', [
            'numbers' => [25, 26, 27, 28, 52, 53, 702, 703],
        ]));
        $t->same('0 draft', $renderer->render('$for(values)$$it/alpha$$sep$ $endfor$', [
            'values' => [0, 'draft'],
        ]));
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
            'Flags: true, ',
        ]), $output);
    },

    'applies pandoc doctemplate pipes after missing and null lookups' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->render(<<<'TPL'
Missing length: <$missing/length$>
Null length: <$nullish/length$>
Missing block: <$missing/left 6 "[" "]"$>
Null block: <$nullish/right 4 "{" "}"$>
Upper missing: <$missing/uppercase$>
$if(missing/length)$missing length truthy$endif$
Loop: <$for(missing/rest)$unexpected$endfor$>
TPL, [
            'nullish' => null,
        ]);

        $t->same(implode("\n", [
            'Missing length: <0>',
            'Null length: <0>',
            'Missing block: <[      ]>',
            'Null block: <{    }>',
            'Upper missing: <>',
            'missing length truthy',
            'Loop: <>',
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

    'removes exactly one upstream final line ending from included pandoc doctemplate partials' => static function (TestRunner $t): void {
        $template = <<<'TPL'
One:<${ one() }>
Double:<${ double() }>
Crlf:<${ crlf() }>
Cr:<${ cr() }>
Applied:<${ items:row()[|] }>
TPL;

        $output = (new DocTemplate())->render($template, [
            'items' => ['media', 'links'],
        ], [
            'one' => "alpha\n",
            'double' => "alpha\n\n",
            'crlf' => "alpha\r\n",
            'cr' => "alpha\r",
            'row' => '$it$' . "\n\n",
        ]);

        $t->same("One:<alpha>\nDouble:<alpha\n>\nCrlf:<alpha>\nCr:<alpha>\nApplied:<media\n|links\n>", $output);
        $t->same('AppliedCrlf:<media|links>', (new DocTemplate())->render('AppliedCrlf:<${ items:crlf-row()[|] }>', [
            'items' => ['media', 'links'],
        ], [
            'crlf-row' => '$it$' . "\r\n",
        ]));
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
            '<article data-source="docx"><h2>Imported heading</h2><p>Check hierarchy</p><span>Imported heading</span></article>',
            '---',
            '<article data-source="html"><h2>Legacy block</h2><p>Review shortcode</p><span>Legacy block</span></article>',
            'Reviewer: ADA EDITOR <MIGRATION>',
        ]), $output);
    },

    'rebinds pandoc doctemplate applied partial variables like explicit loops' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Cards: ${ articles:card()[ | ] }
Next: ${ warnings/rest/first:warning-summary() }
Nested: ${ import.items/last:import-row() }
TPL;

        $output = (new DocTemplate())->render($template, [
            'articles' => [
                ['title' => 'Imported heading', 'source' => 'docx'],
                ['title' => 'Legacy block', 'source' => 'html'],
            ],
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
                ['source' => 'links', 'message' => 'Review redirects'],
            ],
            'import' => [
                'items' => [
                    ['title' => 'First item'],
                    ['title' => 'Final item'],
                ],
            ],
        ], [
            'card' => '$articles.title$/$it.title$',
            'warning-summary' => '$warnings.source$/$it.source$: $warnings.message$',
            'import-row' => '$import.items.title$/$it.title$',
        ]);

        $t->same(implode("\n", [
            'Cards: Imported heading/Imported heading | Legacy block/Legacy block',
            'Next: links/links: Review redirects',
            'Nested: Final item/Final item',
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

    'renders pandoc doctemplate path partials and piped variables applied to partials' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('review-packets/review.html', [
            'review-packets/review.html' => <<<'HTML'
<article>
${ components/review-header() }
<section>
${ warnings/rest:components/warning-row()[
] }
</section>
Next: ${ warnings/rest/first:components/warning-summary()/uppercase }
</article>
HTML,
            'review-packets/components/review-header.html' => '<header><h1>$title$</h1></header>' . "\n",
            'review-packets/components/warning-row.html' => '<p data-source="$it.source$">$it.message$</p>' . "\n",
            'review-packets/components/warning-summary.html' => '$it.source$: $it.message$',
        ], [
            'title' => 'Batch 42 Review',
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
                ['source' => 'links', 'message' => 'Review redirects'],
                ['source' => 'layout', 'message' => 'Check columns'],
            ],
        ]);

        $t->same(implode("\n", [
            '<article>',
            '<header><h1>Batch 42 Review</h1></header>',
            '<section>',
            '<p data-source="links">Review redirects</p>',
            '<p data-source="layout">Check columns</p>',
            '</section>',
            'Next: LINKS: REVIEW REDIRECTS',
            '</article>',
        ]), $output);
    },

    'swallows standalone empty pandoc doctemplate partial lines without changing inline partials' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same(implode("\n", [
            '<ul>',
            '  <li>Kept</li>',
            '</ul>',
        ]), $renderer->render(<<<'TPL'
<ul>
  ${ maybe-warning() }
  <li>Kept</li>
</ul>
TPL, [], [
            'maybe-warning' => '',
        ]));

        $t->same(implode("\n", [
            '<ul>',
            '  <li>Visible</li>',
            '  <li>Kept</li>',
            '</ul>',
        ]), $renderer->render(<<<'TPL'
<ul>
  ${ warning-row() }
  <li>Kept</li>
</ul>
TPL, [], [
            'warning-row' => '<li>Visible</li>' . "\n",
        ]));

        $t->same(implode("\n", [
            'Before',
            '  <span>One</span>',
            '<span>Two</span> tail',
            'After',
        ]), $renderer->render(<<<'TPL'
Before
  ${ inline() } tail
After
TPL, [], [
            'inline' => '<span>One</span>' . "\n" . '<span>Two</span>' . "\n",
        ]));
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

    'renders unicode pandoc doctemplate variables and partial resource names' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('packets/review.html', [
            'packets/review.html' => <<<'HTML'
<article>
${ components/résumé() }
Auteur: $auteur.nom$
文書: $文書.題名$
</article>
HTML,
            'packets/components/résumé.html' => '<p data-état="$révision.état$">$révision.titre$</p>' . "\n",
        ], [
            'auteur' => ['nom' => 'Ada Editor'],
            'révision' => ['état' => 'prêt', 'titre' => 'Résumé de migration'],
            '文書' => ['題名' => '移行レビュー'],
        ]);

        $t->same(implode("\n", [
            '<article>',
            '<p data-état="prêt">Résumé de migration</p>',
            'Auteur: Ada Editor',
            '文書: 移行レビュー',
            '</article>',
        ]), $output);
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
