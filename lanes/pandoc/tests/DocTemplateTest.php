<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocTemplate;

$makeTemplateTree = static function (array $files): string {
    $root = sys_get_temp_dir() . '/pandoc-doctemplate-fixture-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create doctemplate fixture directory');
    }

    foreach ($files as $relativePath => $contents) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $relativePath);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create doctemplate fixture subdirectory');
        }

        file_put_contents($path, (string) $contents);
    }

    return $root;
};

$removeTemplateTree = static function (string $root): void {
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            rmdir($fileInfo->getPathname());
        } else {
            unlink($fileInfo->getPathname());
        }
    }

    rmdir($root);
};

$expectTemplateErrorContains = static function (TestRunner $t, callable $callback, string $needle): void {
    try {
        $callback();
    } catch (UnexpectedValueException $exception) {
        $t->contains($needle, $exception->getMessage());

        return;
    }

    throw new RuntimeException('Expected doctemplate exception containing ' . $needle);
};

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

    'ignores pandoc doctemplate spaces and tabs after closing delimiters' => static function (TestRunner $t): void {
        $template = "Before\n"
            . '$if(title)$' . "   \t\n"
            . 'Title: $ title $' . "   \t\n"
            . '$elseif(fallback)$' . "\tno\n"
            . '$else$' . "\tno\n"
            . '$endif$' . "   \t\n"
            . "Loop:\n"
            . '$for(items)$' . " \t\n"
            . '- $it$' . " \t\n"
            . '$sep$' . " \t\n"
            . "/\n"
            . '$endfor$' . " \t\n"
            . "Braced:\n"
            . '${ author.name }' . " \t\n"
            . "Partial:\n"
            . '${ badge() }' . "\t\n"
            . 'After';

        $output = (new DocTemplate())->render($template, [
            'title' => 'Review',
            'fallback' => 'Fallback',
            'items' => ['media', 'links'],
            'author' => ['name' => 'Ada'],
        ], [
            'badge' => 'Migration desk' . "\n",
        ]);

        $t->same(implode("\n", [
            'Before',
            'Title: Review',
            'Loop:',
            '- media',
            '/',
            '- links',
            'Braced:',
            'Ada',
            'Partial:',
            'Migration desk',
            'After',
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

    'renders pandoc doctemplate false booleans as literal text while branching false' => static function (TestRunner $t): void {
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
            'False: <false>',
            'List: <true, false, kept>',
            'Loop: [true][false][kept]',
            'Map: <true>',
            'Block: <false>',
            'false branch',
        ]), $output);
    },

    'matches upstream pandoc doctemplates boolean fixture rendering' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
$foo$
$bar$
$if(foo)$XXX$else$YYY$endif$
$if(bar)$XXX$else$YYY$endif$
TPL, [
            'foo' => true,
            'bar' => false,
        ]);

        $t->same(implode("\n", [
            'true',
            'false',
            'XXX',
            'YYY',
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

    'keeps pandoc doctemplate explicit nesting across source-aligned variables until dedent' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
<article>
<p>$^$Summary: $summary$
   Status: $status$
   Note: $note$</p>
<footer>$owner$</footer>
</article>
TPL, [
            'summary' => "Imported paragraph\nNeeds review",
            'status' => "queued\nmanual",
            'note' => "Check alt text\nbefore publish",
            'owner' => "Migration desk\nDone",
        ]);

        $t->same(implode("\n", [
            '<article>',
            '<p>Summary: Imported paragraph',
            '   Needs review',
            '   Status: queued',
            '   manual',
            '   Note: Check alt text',
            '   before publish</p>',
            '<footer>Migration desk',
            'Done</footer>',
            '</article>',
        ]), $output);
    },

    'preserves source-aligned blank lines inside explicit pandoc doctemplate nesting' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
<aside>
<p>$^$Intro: $summary$

   Note: $note$
   $details$</p>
</aside>
TPL, [
            'summary' => "First line\nSecond line",
            'note' => "Third line\nFourth line",
            'details' => "Fifth line\nSixth line",
        ]);

        $t->same(implode("\n", [
            '<aside>',
            '<p>Intro: First line',
            '   Second line',
            '   ',
            '   Note: Third line',
            '   Fourth line',
            '   Fifth line',
            '   Sixth line</p>',
            '</aside>',
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

    'matches upstream pandoc doctemplate nested literal continuation after scalar interpolation' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
$bim.zub$ $^$$foo$
          bar $sup$
TPL, [
            'bim' => ['zub' => 'sim'],
            'foo' => 1,
            'sup' => "a multiline\nstring",
        ]);

        $t->same(implode("\n", [
            'sim 1',
            '    bar a multiline',
            '    string',
        ]), $output);
    },

    'ends upstream pandoc doctemplate explicit nesting before dedented loop directives' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
$bim.zub$ $^$$foo$
          bar $sup$
$for(baz)$
1. $^$Hello $if(it)$
$it$
$endif$
$endfor$
$^$hey $sup$
hey $if(foo)$
$foo$
$endif$
hey
TPL, [
            'foo' => 1,
            'baz' => ['a', 'b'],
            'bim' => ['zub' => 'sim'],
            'sup' => "a multiline\nstring",
        ]);

        $t->same(implode("\n", [
            'sim 1',
            '    bar a multiline',
            '    string',
            '1. Hello a',
            '1. Hello b',
            'hey a multiline',
            'string',
            'hey 1',
            'hey',
        ]), $output);
    },

    'matches upstream pandoc doctemplate full nesting fixture' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
$sup$
$sup$
$^$$sup$
$bim.zub$ $^$$sup$
$bim.zub$ $^$$foo$
          bar $sup$
$for(baz)$
1. $^$Hello $if(it)$
$it$
$endif$
$endfor$
$^$hey $sup$
hey $sup$
hey $sup$
hey $if(foo)$
$foo$
$endif$
hey
TPL, [
            'foo' => 1,
            'baz' => ['a', 'b'],
            'bim' => ['zub' => 'sim'],
            'sup' => "a multiline\nstring",
        ]);

        $t->same(implode("\n", [
            'a multiline',
            'string',
            'a multiline',
            'string',
            'a multiline',
            'string',
            'sim a multiline',
            '    string',
            'sim 1',
            '    bar a multiline',
            '    string',
            '1. Hello a',
            '1. Hello b',
            'hey a multiline',
            'string',
            'hey a multiline',
            'string',
            'hey a multiline',
            'string',
            'hey 1',
            'hey',
        ]), $output);
    },

    'matches upstream pandoc doctemplate indented control lines in nested fixture loops' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
$for(baz)$
1. $^$Hello
   $if(it)$
     $it$
   $endif$
$endfor$
TPL, [
            'baz' => ['a', 'b'],
        ]);

        $t->same("1. Hello\n     a\n1. Hello\n     b\n", $output);
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

    'does not indent empty lines from nested pandoc doctemplate values' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $automatic = $renderer->render(<<<'TPL'
<section class="wp-import-body">
  $body$
</section>
TPL, [
            'body' => "<!-- wp:paragraph --><p>First block.</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>Second block.</p><!-- /wp:paragraph -->",
        ]);

        $t->same(implode("\n", [
            '<section class="wp-import-body">',
            '  <!-- wp:paragraph --><p>First block.</p><!-- /wp:paragraph -->',
            '',
            '  <!-- wp:paragraph --><p>Second block.</p><!-- /wp:paragraph -->',
            '</section>',
        ]), $automatic);

        $explicit = $renderer->render('<p>$^$Note: $note$</p>', [
            'note' => "First review line\n\nSecond review line",
        ]);

        $t->same(implode("\n", [
            '<p>Note: First review line',
            '',
            '   Second review line</p>',
        ]), $explicit);
    },

    'automatically nests CR-only pandoc doctemplate variable and partial lines' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $template = "<section class=\"wp-import-body\">\r  " . '$body$' . "\r  " . '${ review-note() }' . "\r</section>";

        $output = $renderer->render($template, [
            'body' => "<!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->\r<!-- wp:paragraph --><p>Needs review.</p><!-- /wp:paragraph -->",
        ], [
            'review-note' => "<aside>Legacy CR template</aside>\r<aside>Partial second line</aside>\r",
        ]);

        $t->same(implode("\r", [
            '<section class="wp-import-body">',
            '  <!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->',
            '  <!-- wp:paragraph --><p>Needs review.</p><!-- /wp:paragraph -->',
            '  <aside>Legacy CR template</aside>',
            '  <aside>Partial second line</aside>',
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

    'wraps explicit nested pandoc doctemplate breakable spaces with continuation indentation' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->renderWrapped(
            '<p>$^$Note: $~$media links layout status$~$</p>',
            [],
            18,
        );

        $t->same(implode("\n", [
            '<p>Note: media',
            '   links layout',
            '   status</p>',
        ]), $output);

        $partialOutput = $renderer->renderWrapped(
            '<aside>$^$${ review-line() }</aside>',
            [],
            18,
            [
                'review-line' => '$~$media links layout status$~$',
            ],
        );

        $t->same(implode("\n", [
            '<aside>media links',
            '       layout',
            '       status</aside>',
        ]), $partialOutput);
    },

    'inherits pandoc doctemplate breakable spaces into partial resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $template = <<<'TPL'
Inline: $~$${ review-line() }$~$
Applied: $~$${ reviewers:reviewer-line()[ / ] }$~$
Nowrap: $~$${ review-line()/nowrap }$~$
TPL;

        $output = $renderer->renderWrapped($template, [
            'reviewers' => [
                ['name' => 'Ada Lovelace'],
                ['name' => 'Grace Hopper'],
            ],
        ], 18, [
            'review-line' => 'media links layout status',
            'reviewer-line' => '$it.name$ queued',
        ]);

        $t->same(implode("\n", [
            'Inline: media',
            'links layout',
            'status',
            'Applied: Ada Lovelace',
            'queued / Grace Hopper',
            'queued',
            'Nowrap: media links layout status',
        ]), $output);
    },

    'throws on unclosed pandoc doctemplate breakable-space regions' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('Summary: $~$alpha beta', []));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('Summary: ${~}alpha beta', []));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->renderWrapped('Summary: $~$alpha beta', [], 20));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('${ broken() }', [], [
            'broken' => '$~$alpha beta',
        ]));
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

    'matches upstream pandoc doctemplates pipes fixture applied partial newlines' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $context = [
            'bim' => ['Zub' => 'Sim'],
            'digits' => [1, 5, 20],
            'foo' => 1,
            'hasblanksmap' => [
                'a' => "hello\n\n",
                'b' => "there\n\n",
            ],
            'items' => [
                "one with\na line break",
                'two',
                "three with\na line break",
            ],
        ];

        $enumerated = $renderer->render('$items/pairs/reverse:enum()$', $context, [
            'enum' => '$it.key/alpha/uppercase$.  $^$$it.value$' . "\n\n",
        ]);
        $t->same(implode("\n", [
            'C.  three with',
            '    a line break',
            'B.  two',
            'A.  one with',
            '    a line break',
            '',
        ]), $enumerated);

        $chompedMap = $renderer->render(<<<'TPL'
$for(hasblanksmap/chomp/pairs/uppercase)$
$it.key$ ($it.value$)
$endfor$
TPL, $context);
        $t->same(implode("\n", [
            'A (HELLO)',
            'B (THERE)',
            '',
        ]), $chompedMap);

        $t->same('SIM', $renderer->render('$for(bim/uppercase)$$it.Zub$$endfor$', $context));
        $t->same('i v xx', $renderer->render('$digits/roman[ ]$', $context));
        $t->same("1\n20\n5\n20\n1\n5\n1", $renderer->render(<<<'TPL'
$digits/first$
$digits/last$
$for(digits/rest)$
$it$
$endfor$$for(digits/allbutlast)$
$it$
$endfor$$foo/first$
TPL, $context));
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

    'requires pandoc doctemplate variable separators after pipe suffixes' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $t->same(
            'Sources: MEDIA / LINKS / LAYOUT',
            $renderer->render('Sources: $sources/uppercase[ / ]$', [
                'sources' => ['media', 'links', 'layout'],
            ])
        );

        $t->same(
            'Rows: DOCX, ODT',
            $renderer->render('Rows: ${ rows:row()[, ]/uppercase }', [
                'rows' => [
                    ['source' => 'docx'],
                    ['source' => 'odt'],
                ],
            ], [
                'row' => '$it.source$',
            ])
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('Broken: $sources[ / ]/uppercase$', [
                'sources' => ['media', 'links'],
            ]),
            'Doctemplate variable separators must follow pipe suffixes in sources[ / ]/uppercase at <template>:1:9'
        );
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

    'renders unbraced pandoc doctemplate dollar separators and pipe borders' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Cost: $title/left 8 "$" " USD"$
Sources: $sources/uppercase[ $ ]$
Bracket: $sources[[ ]$
Rows: $rows:row()[ $ ]$
Partial: $badge()/center 8 "$" "$"$
Escaped: $title/right 6 "\$" "$"$
TPL;

        $output = (new DocTemplate())->render($template, [
            'title' => '42',
            'sources' => ['media', 'links', 'layout'],
            'rows' => [
                ['source' => 'docx', 'message' => 'Imported heading'],
                ['source' => 'odt', 'message' => 'Styled paragraph'],
            ],
        ], [
            'row' => '$it.source$=$it.message$',
            'badge' => 'OK',
        ]);

        $t->same(implode("\n", [
            'Cost: $42       USD',
            'Sources: MEDIA $ LINKS $ LAYOUT',
            'Bracket: media[ links[ layout',
            'Rows: docx=Imported heading $ odt=Styled paragraph',
            'Partial: $   OK   $',
            'Escaped: $    42$',
        ]), $output);
    },

    'renders braced pandoc doctemplate separators containing closing braces' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Sources: ${ sources/uppercase[} ] }
Rows: ${ rows:row()[}] }
Inline: ${ sources[}] }
TPL;

        $output = (new DocTemplate())->render($template, [
            'sources' => ['media', 'links', 'layout'],
            'rows' => [
                ['source' => 'docx', 'message' => 'Imported heading'],
                ['source' => 'odt', 'message' => 'Styled paragraph'],
            ],
        ], [
            'row' => '$it.source$: $it.message$',
        ]);

        $t->same(implode("\n", [
            'Sources: MEDIA} LINKS} LAYOUT',
            'Rows: docx: Imported heading}odt: Styled paragraph',
            'Inline: media}links}layout',
        ]), $output);
    },

    'renders braced pandoc doctemplate separators containing opening brackets' => static function (TestRunner $t): void {
        $template = <<<'TPL'
Sources: ${ sources[[ ] }
Piped: ${ sources/uppercase[[ ] }
Rows: ${ rows:row()[[ ] }
Inline: ${ sources[[ ] }
TPL;

        $output = (new DocTemplate())->render($template, [
            'sources' => ['media', 'links', 'layout'],
            'rows' => [
                ['source' => 'docx', 'message' => 'Imported heading'],
                ['source' => 'odt', 'message' => 'Styled paragraph'],
            ],
        ], [
            'row' => '$it.source$: $it.message$',
        ]);

        $t->same(implode("\n", [
            'Sources: media[ links[ layout',
            'Piped: MEDIA[ LINKS[ LAYOUT',
            'Rows: docx: Imported heading[ odt: Styled paragraph',
            'Inline: media[ links[ layout',
        ]), $output);
    },

    'renders pandoc doctemplate alphabetic pipe modulo markers' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same('[y][z][a][b][z][a][z][a]', $renderer->render('$for(numbers)$[$it/alpha$]$endfor$', [
            'numbers' => [25, 26, 27, 28, 52, 53, 702, 703],
        ]));
        $t->same('Y Z A B Z A Z A', $renderer->render('$for(numbers)$$it/alpha/uppercase$$sep$ $endfor$', [
            'numbers' => [25, 26, 27, 28, 52, 53, 702, 703],
        ]));
        $t->same('0 draft', $renderer->render('$for(values)$$it/alpha$$sep$ $endfor$', [
            'values' => [0, 'draft'],
        ]));
    },

    'renders pandoc doctemplate roman pipe zero as an empty marker' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same('<> <i> <mmmcmxcix> <4000> <draft>', $renderer->render('<$zero/roman$> <$one/roman$> <$max/roman$> <$overflow/roman$> <$text/roman$>', [
            'zero' => 0,
            'one' => 1,
            'max' => 3999,
            'overflow' => 4000,
            'text' => 'draft',
        ]));
        $t->same('|I|IV|4000|DRAFT', $renderer->render('$for(priorities/roman/uppercase)$$it$$sep$|$endfor$', [
            'priorities' => [0, 1, 4, 4000, 'draft'],
        ]));
        $t->same('<    > <   I>', $renderer->render('<$zero/roman/uppercase/right 4$> <$one/roman/uppercase/right 4$>', [
            'zero' => 0,
            'one' => 1,
        ]));
    },

    'renders pandoc doctemplate alpha pipe as single modulo glyph in review lists' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render('$for(items/pairs)$$it.key/alpha/uppercase$: $it.value$$sep$ | $endfor$', [
            'items' => array_map(static fn (int $index): string => 'review-' . $index, range(1, 28)),
        ]);

        $t->same(
            'A: review-1 | B: review-2 | C: review-3 | D: review-4 | E: review-5 | F: review-6 | G: review-7 | H: review-8 | I: review-9 | J: review-10 | K: review-11 | L: review-12 | M: review-13 | N: review-14 | O: review-15 | P: review-16 | Q: review-17 | R: review-18 | S: review-19 | T: review-20 | U: review-21 | V: review-22 | W: review-23 | X: review-24 | Y: review-25 | Z: review-26 | A: review-27 | B: review-28',
            $output,
        );
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

    'reboxes pandoc doctemplate block pipes by requested display width' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same("[Revi]\n[ew! ]", $renderer->render('$title/left 4 "[" "]"$', [
            'title' => 'Review!',
        ]));
        $t->same("[Revi]\n[ ew!]", $renderer->render('$title/right 4 "[" "]"$', [
            'title' => 'Review!',
        ]));
        $t->same("[Revi]\n[ew! ]", $renderer->render('$title/center 4 "[" "]"$', [
            'title' => 'Review!',
        ]));
        $t->same("[A]\n[B]", $renderer->render('$code/center 0 "[" "]"$', [
            'code' => 'AB',
        ]));
        $t->same("|漢|\n|字|\n|A |", $renderer->render('$cjk/left 2 "|" "|"$', [
            'cjk' => '漢字A',
        ]));
    },

    'composes adjacent pandoc doctemplate block pipes horizontally' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same(implode("\n", [
            '    a multiline  a multiline  a multiline',
            '         string    string     string',
        ]), $renderer->render('$sup/right 15$$sup/center 15$$sup/left 15$', [
            'sup' => "a multiline\nstring",
        ]));

        $tableTemplate = '+------+-----------+' . "\n"
            . '$for(rows/pairs)$'
            . '$it.key/right 4 "| " " | "$$it.value/left 10 "" "|"$' . "\n"
            . '+------+-----------+' . "\n"
            . '$endfor$';

        $t->same(implode("\n", [
            '+------+-----------+',
            '|    1 | a         |',
            '|      | b         |',
            '+------+-----------+',
            '|    2 | b         |',
            '|      | c         |',
            '|      | d         |',
            '+------+-----------+',
            '',
        ]), $renderer->render($tableTemplate, [
            'rows' => ["a\nb", "b\nc\nd"],
        ]));
    },

    'matches upstream pandoc doctemplate pad fixture final blank-line handling' => static function (TestRunner $t): void {
        $template = <<<'TPL'
$sup/right 15$$sup/center 15$$sup/left 15$

$for(baz/pairs)$
$it.key/alpha/right 4$. $^$$it.value$
$endfor$

+------+-----------+
$for(baz/pairs)$
$it.key/right 4 "| " " | "$$it.value/left 10 "" "|"$
+------+-----------+
$endfor$

|------------|------------|
$for(employee)$
$it.name.first/uppercase/left 10 "| "$$it.salary/right 10 " | " " |"$
$endfor$|------------|------------|

TPL;

        $expected = <<<'EXPECTED'
    a multiline  a multiline  a multiline
         string    string     string

   a. a
      b
   b. b
      c
      d

+------+-----------+
|    1 | a         |
|      | b         |
+------+-----------+
|    2 | b         |
|      | c         |
|      | d         |
+------+-----------+

|------------|------------|
| JOHN       |            |
| OMAR       |      30000 |
| SARA       |      60000 |
|------------|------------|
EXPECTED;
        $expected .= "\n";

        $output = (new DocTemplate())->render($template, [
            'sup' => "a multiline\nstring",
            'baz' => ["a\nb", "b\nc\nd"],
            'employee' => [
                ['name' => ['first' => 'John', 'last' => 'Doe']],
                ['name' => ['first' => 'Omar', 'last' => 'Smith'], 'salary' => '30000'],
                ['name' => ['first' => 'Sara', 'last' => 'Chen'], 'salary' => '60000'],
            ],
        ]);

        $t->same($expected, $output);
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

    'keeps piped missing doctemplate loop values from inventing variable paths' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->render(<<<'TPL'
Missing loop: $for(missing/length)$missing=<$missing$>; it=<$it$>$endfor$
Existing null loop: $for(nullish/length)$nullish=<$nullish$>; it=<$it$>$endfor$
Nested missing loop: $for(meta.absent/length)$meta.absent=<$meta.absent$>; it=<$it$>; meta=<$meta$>$endfor$
Applied missing: ${ missing/length:missing-row() }
Applied existing: ${ nullish/length:nullish-row() }
TPL, [
            'nullish' => null,
            'meta' => ['present' => 'yes'],
        ], [
            'missing-row' => 'missing=<$missing$>; it=<$it$>',
            'nullish-row' => 'nullish=<$nullish$>; it=<$it$>',
        ]);

        $t->same(implode("\n", [
            'Missing loop: missing=<>; it=<0>',
            'Existing null loop: nullish=<0>; it=<0>',
            'Nested missing loop: meta.absent=<>; it=<0>; meta=<true>',
            'Applied missing: missing=<>; it=<0>',
            'Applied existing: nullish=<0>; it=<0>',
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

    'suppresses standalone pandoc doctemplate partial source line endings after newline-terminated partials' => static function (TestRunner $t): void {
        $template = <<<'TPL'
---
  $boilerplate()$
---
$for(employee)$
$it:name()$
$endfor$
END
TPL;

        $output = (new DocTemplate())->render($template, [
            'employee' => [
                ['name' => ['first' => 'John', 'last' => 'Doe']],
                ['name' => ['first' => 'Omar', 'last' => 'Smith']],
                ['name' => ['first' => 'Sara', 'last' => 'Chen']],
            ],
        ], [
            'boilerplate' => "BOILERPLATE\nHERE\n\n",
            'name' => '($it.name.first$) $it.name.last$' . "\n",
        ]);

        $t->same(implode("\n", [
            '---',
            '  BOILERPLATE',
            '  HERE',
            '---',
            '(John) Doe',
            '(Omar) Smith',
            '(Sara) Chen',
            'END',
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

    'joins pandoc doctemplate applied partials after partial pipes with trailing separators' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $template = <<<'TPL'
Rows: ${ warnings:warning-row()/uppercase[ | ] }
Sections: ${ sections/rest:section-row()/chomp[ / ] }
Legacy separator order: ${ warnings:warning-row()[ + ]/lowercase }
TPL;

        $output = $renderer->render($template, [
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
                ['source' => 'links', 'message' => 'Review redirects'],
            ],
            'sections' => [
                ['title' => 'Ignored first'],
                ['title' => 'Metadata'],
                ['title' => 'Body'],
            ],
        ], [
            'warning-row' => '<span data-source="$it.source$">$it.source$: $it.message$</span>',
            'section-row' => '$it.title$' . "\n",
        ]);

        $t->same(implode("\n", [
            'Rows: <SPAN DATA-SOURCE="MEDIA">MEDIA: CONFIRM ALT TEXT</SPAN> | <SPAN DATA-SOURCE="LINKS">LINKS: REVIEW REDIRECTS</SPAN>',
            'Sections: Metadata / Body',
            'Legacy separator order: <span data-source="media">media: confirm alt text</span> + <span data-source="links">links: review redirects</span>',
        ]), $output);

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render(
            '${ warnings:warning-row()[, ]/uppercase[ | ] }',
            ['warnings' => ['one']],
            ['warning-row' => '$it$'],
        ));
    },

    'rejects pandoc doctemplate variable separators before applied partial colons' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('${ warnings[, ]:warning-row() }', [
                'warnings' => [
                    ['source' => 'media'],
                    ['source' => 'links'],
                ],
            ], [
                'warning-row' => '$it.source$',
            ]),
            'Doctemplate applied partial separators must follow the partial call in warnings[, ]:warning-row() at <template>:1:4',
        );
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

    'resolves extensionless pandoc custom template resources by output format' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->renderResource('templates/review', [
            'templates/review.html' => <<<'HTML'
<article>
${ header() }
<section>$title$</section>
</article>
HTML,
            'templates/header.html' => '<header>$format$ review</header>' . "\n",
        ], [
            'title' => 'Batch 42 Review',
            'format' => 'HTML',
        ], null, 'html');

        $t->same(implode("\n", [
            '<article>',
            '<header>HTML review</header>',
            '<section>Batch 42 Review</section>',
            '</article>',
        ]), $output);

        $t->same('plain wins', $renderer->renderResource('templates/review', [
            'templates/review' => 'plain wins',
            'templates/review.html' => 'html fallback',
        ], [], null, 'html'));

        $t->same("Summary: media\nlinks", $renderer->renderResourceWrapped('templates/summary', [
            'templates/summary.html' => 'Summary: $~$media links$~$',
        ], [], 14, null, 'html'));

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->renderResource('templates/missing', [
            'templates/review.html' => '$title$',
        ], ['title' => 'Missing'], null, 'html'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderResource('templates/review', [
            'templates/review.html' => '$title$',
        ], ['title' => 'Bad format'], null, '../html'));
    },

    'resolves pandoc extension-qualified output formats to base templates' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $custom = $renderer->renderResource('templates/review', [
            'templates/review.html' => '<article class="format-extension">$body$</article>',
        ], [
            'body' => 'Custom HTML extension packet',
        ], null, 'html+smart-native_divs');
        $t->same('<article class="format-extension">Custom HTML extension packet</article>', $custom);

        $exactExtensionResource = $renderer->renderResource('templates/review', [
            'templates/review.html+smart' => '<article class="exact-extension">$body$</article>',
            'templates/review.html' => '<article class="base-extension">$body$</article>',
        ], [
            'body' => 'Exact resource packet',
        ], null, 'html+smart');
        $t->same('<article class="exact-extension">Exact resource packet</article>', $exactExtensionResource);

        $htmlDefault = $renderer->renderResource('templates/default', [], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Extension Default Packet',
            'body' => '<p>HTML extension default body.</p>',
        ], null, 'html+smart-raw_html');
        $t->contains('<!DOCTYPE html>', $htmlDefault);
        $t->contains('<title>Extension Default Packet</title>', $htmlDefault);
        $t->contains('<p>HTML extension default body.</p>', $htmlDefault);

        $markdownDefault = $renderer->renderResource('templates/default', [], [
            'body' => 'Markdown extension default body',
        ], null, 'markdown_strict+emoji-hard_line_breaks');
        $t->same("Markdown extension default body\n", $markdownDefault);

        $gfmDefault = $renderer->renderResource('templates/default', [], [
            'body' => 'GFM extension default body',
        ], null, 'gfm+emoji');
        $t->same("GFM extension default body\n", $gfmDefault);

        $docxDefault = $renderer->renderResource('templates/default', [], [
            'body' => '<w:p>DOCX extension default body.</w:p>',
            'sectpr' => '<w:sectPr/>',
        ], null, 'docx+styles');
        $t->contains('<w:p>DOCX extension default body.</w:p>', $docxDefault);
        $t->contains('<w:sectPr/>', $docxDefault);

        $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderResource('templates/review', [
            'templates/review.html' => '$body$',
        ], [
            'body' => 'Bad extension',
        ], null, 'html+../raw_html'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderResource('templates/review', [
            'templates/review.html' => '$body$',
        ], [
            'body' => 'Bad extension',
        ], null, 'html+'));
    },

    'resolves extension-qualified pandoc template partials through exact and base extensions' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $template = <<<'HTML'
<article>
${ components/header() }
<section>
${ warnings:components/warning-row()[
] }
</section>
</article>
HTML;

        $exactOutput = $renderer->renderResource('templates/review', [
            'templates/review.html+smart' => $template,
            'templates/components/header.html' => '<header class="base">$title$</header>' . "\n",
            'templates/components/header.html+smart' => '<header class="exact">$title$</header>' . "\n",
            'templates/components/warning-row.html' => '<p data-source="$it.source$">$it.message$</p>' . "\n",
        ], [
            'title' => 'Extension Packet',
            'warnings' => [
                ['source' => 'media', 'message' => 'Confirm alt text'],
                ['source' => 'links', 'message' => 'Review redirects'],
            ],
        ], null, 'html+smart');

        $t->same(implode("\n", [
            '<article>',
            '<header class="exact">Extension Packet</header>',
            '<section>',
            '<p data-source="media">Confirm alt text</p>',
            '<p data-source="links">Review redirects</p>',
            '</section>',
            '</article>',
        ]), $exactOutput);

        $baseOutput = $renderer->renderResource('templates/review', [
            'templates/review.html+smart' => $template,
            'templates/components/header.html' => '<header class="base">$title$</header>' . "\n",
            'templates/components/warning-row.html' => '<p data-source="$it.source$">$it.message$</p>' . "\n",
        ], [
            'title' => 'Base Partial Packet',
            'warnings' => [
                ['source' => 'docx', 'message' => 'Imported heading'],
            ],
        ], null, 'html+smart');

        $t->same(implode("\n", [
            '<article>',
            '<header class="base">Base Partial Packet</header>',
            '<section>',
            '<p data-source="docx">Imported heading</p>',
            '</section>',
            '</article>',
        ]), $baseOutput);
    },

    'resolves explicit base-extension partial calls to exact extension resources when base is absent' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $exactOnly = $renderer->renderResource('templates/review', [
            'templates/review.html+smart' => <<<'HTML'
<article>
${ components/header.html() }
${ components/warning-row.html() }
</article>
HTML,
            'templates/components/header.html+smart' => '<header class="exact-only">$title$</header>' . "\n",
            'templates/components/warning-row.html+smart' => '<p class="exact-only">$warning$</p>' . "\n",
        ], [
            'title' => 'Exact Extension Packet',
            'warning' => 'Review HTML extension resource',
        ], null, 'html+smart');

        $t->same(implode("\n", [
            '<article>',
            '<header class="exact-only">Exact Extension Packet</header>',
            '<p class="exact-only">Review HTML extension resource</p>',
            '</article>',
        ]), $exactOnly);

        $basePreferred = $renderer->renderResource('templates/review', [
            'templates/review.html+smart' => '${ components/header.html() }',
            'templates/components/header.html+smart' => '<header class="exact">$title$</header>',
            'templates/components/header.html' => '<header class="base">$title$</header>',
        ], [
            'title' => 'Base Preferred Packet',
        ], null, 'html+smart');

        $t->same('<header class="base">Base Preferred Packet</header>', $basePreferred);
    },

    'renders bounded pandoc default html4 template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $html4 = $renderer->renderResource('templates/default', [], [
            'pandoc-version' => '3.7.0',
            'lang' => 'en',
            'dir' => 'ltr',
            'title-prefix' => 'WordPress Import',
            'pagetitle' => 'HTML4 Default Packet',
            'title' => 'HTML4 Review',
            'subtitle' => 'Legacy XHTML review',
            'author' => ['Migration bot', 'Content editor'],
            'author-meta' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'date-meta' => '2026-06-08',
            'keywords' => ['migration', 'wordpress', 'html4'],
            'description-meta' => 'HTML4 default template packet',
            'css' => ['legacy-review.css'],
            'header-includes' => ['<meta name="robots" content="noindex" />'],
            'math' => '<script type="math/tex">queued</script>',
            'include-before' => ['<div class="before">Queued before</div>'],
            'idprefix' => 'wp-',
            'toc' => true,
            'toc-title' => 'Contents',
            'table-of-contents' => '<ul><li>Imported body</li></ul>',
            'abstract-title' => 'Abstract',
            'abstract' => '<p>Legacy HTML import summary.</p>',
            'body' => '<p>HTML4 default body.</p>',
            'include-after' => ['<div class="after">Queued after</div>'],
        ], null, 'html4');

        $t->contains('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"', $html4);
        $t->contains('<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" dir="ltr">', $html4);
        $t->contains('<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />', $html4);
        $t->contains('<meta http-equiv="Content-Style-Type" content="text/css" />', $html4);
        $t->contains('<meta name="generator" content="pandoc 3.7.0" />', $html4);
        $t->contains('<meta name="author" content="Migration bot" />', $html4);
        $t->contains('<meta name="date" content="2026-06-08" />', $html4);
        $t->contains('<meta name="keywords" content="migration, wordpress, html4" />', $html4);
        $t->contains('<meta name="description" content="HTML4 default template packet" />', $html4);
        $t->contains('<title>WordPress Import – HTML4 Default Packet</title>', $html4);
        $t->contains('<style type="text/css">', $html4);
        $t->contains('span.smallcaps{font-variant: small-caps;}', $html4);
        $t->contains('<link rel="stylesheet" href="legacy-review.css" type="text/css" />', $html4);
        $t->contains('<meta name="robots" content="noindex" />', $html4);
        $t->contains('<script type="math/tex">queued</script>', $html4);
        $t->contains('<div class="before">Queued before</div>', $html4);
        $t->contains('<div id="wp-header">', $html4);
        $t->contains('<h1 class="title">HTML4 Review</h1>', $html4);
        $t->contains('<h1 class="subtitle">Legacy XHTML review</h1>', $html4);
        $t->contains('<h2 class="author">Content editor</h2>', $html4);
        $t->contains('<h3 class="date">2026-06-08</h3>', $html4);
        $t->contains('<div class="abstract-title">Abstract</div>', $html4);
        $t->contains('<div id="wp-TOC">', $html4);
        $t->contains('<h2 id="wp-toc-title">Contents</h2>', $html4);
        $t->contains('<p>HTML4 default body.</p>', $html4);
        $t->contains('<div class="after">Queued after</div>', $html4);

        $direct = $renderer->renderResource('templates/default.html4', [], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Direct HTML4 Packet',
            'body' => '<p>Direct HTML4 body.</p>',
        ]);
        $t->contains('<title>Direct HTML4 Packet</title>', $direct);
        $t->contains('<p>Direct HTML4 body.</p>', $direct);

        $extensionQualified = $renderer->renderResource('templates/default', [], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'HTML4 Extension Packet',
            'body' => '<p>HTML4 extension body.</p>',
        ], null, 'html4+smart');
        $t->contains('XHTML 1.0 Transitional', $extensionQualified);
        $t->contains('<title>HTML4 Extension Packet</title>', $extensionQualified);
        $t->contains('<p>HTML4 extension body.</p>', $extensionQualified);

        $t->same('custom html4 Exact HTML4 override', $renderer->renderResource('templates/default', [
            'templates/default.html4' => 'custom html4 $body$',
            'templates/default.html5' => 'custom html5 $body$',
        ], [
            'body' => 'Exact HTML4 override',
        ], null, 'html4'));

        $t->same('custom html5 HTML alias override', $renderer->renderResource('templates/default', [
            'templates/default.html5' => 'custom html5 $body$',
        ], [
            'body' => 'HTML alias override',
        ], null, 'html'));
    },

    'renders bounded pandoc default rtf template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $rtf = $renderer->renderResource('templates/default', [], [
            'header-includes' => ['{\\*\\generator PortLibs Review;}'],
            'title' => 'RTF Default Review',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'spacer' => true,
            'toc' => true,
            'table-of-contents' => '{\\pard \\ql Contents\\par}',
            'include-before' => ['{\\pard \\ql Before import\\par}'],
            'body' => '{\\pard \\ql WordPress RTF body\\par}',
            'include-after' => ['{\\pard \\ql After import\\par}'],
        ], null, 'rtf+smart');

        foreach ([
            '{\\rtf1\\ansi\\deff0',
            '{\\fonttbl{\\f0 \\fswiss Helvetica;}{\\f1 \\fmodern Courier;}}',
            '{\\colortbl;\\red255\\green0\\blue0;\\red0\\green0\\blue255;}',
            '\\widowctrl\\hyphauto',
            '{\\*\\generator PortLibs Review;}',
            '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 \\b \\fs36 RTF Default Review\\par}',
            '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 Migration bot\\par}',
            '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 Content editor\\par}',
            '{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 2026-06-08\\par}',
            '{\\pard \\ql \\f0 \\sa180 \\li0 \\fi0 \\par}',
            '{\\pard \\ql Contents\\par}',
            '{\\pard \\ql Before import\\par}',
            '{\\pard \\ql WordPress RTF body\\par}',
            '{\\pard \\ql After import\\par}',
        ] as $needle) {
            $t->contains($needle, $rtf);
        }

        $t->same('custom RTF body', $renderer->renderResource('templates/default', [
            'templates/default.rtf' => 'custom $body$',
        ], [
            'body' => 'RTF body',
        ], null, 'rtf'));

        $wrapped = $renderer->renderResource('templates/wrapper.rtf', [
            'templates/wrapper.rtf' => 'Wrapper: ${ default.rtf() }',
        ], [
            'title' => 'Nested RTF Default Review',
            'body' => '{\\pard \\ql Nested RTF body\\par}',
        ]);
        $t->contains('Wrapper: {\\rtf1\\ansi\\deff0', $wrapped);
        $t->contains('{\\pard \\qc \\f0 \\sa180 \\li0 \\fi0 \\b \\fs36 Nested RTF Default Review\\par}', $wrapped);
        $t->contains('{\\pard \\ql Nested RTF body\\par}', $wrapped);
    },

    'renders bounded pandoc default chunkedhtml template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $chunked = $renderer->renderResource('templates/default', [], [
            'lang' => 'en',
            'dir' => 'ltr',
            'title-prefix' => 'WordPress Import',
            'pagetitle' => 'Chunked Review Packet',
            'title' => 'Chunked Review',
            'subtitle' => 'Native split-page metadata',
            'author' => ['Migration bot', 'Content editor'],
            'author-meta' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'date-meta' => '2026-06-08',
            'keywords' => ['migration', 'wordpress', 'chunked'],
            'description-meta' => 'Chunked HTML review packet',
            'css' => ['chunked-review.css'],
            'header-includes' => ['<meta name="robots" content="noindex">'],
            'math' => '<script type="math/tex">queued</script>',
            'include-before' => ['<main class="chunked-before">Queued</main>'],
            'up' => ['url' => '../index.html', 'title' => 'Manual Root'],
            'next' => ['url' => 'next.html', 'title' => 'Next Chunk'],
            'previous' => ['url' => 'previous.html', 'title' => 'Previous Chunk'],
            'abstract-title' => 'Abstract',
            'abstract' => '<p>Chunked abstract survives.</p>',
            'toc' => true,
            'idprefix' => 'wp-chunked-',
            'toc-title' => 'Chunk Contents',
            'table-of-contents' => '<ul><li>Chunk body</li></ul>',
            'body' => '<!-- wp:paragraph --><p>Chunked body.</p><!-- /wp:paragraph -->',
            'include-after' => ['<footer>Chunk done</footer>'],
            'document-css' => true,
            'mainfont' => 'Atkinson Hyperlegible',
            'csl-css' => true,
            'csl-entry-spacing' => '0.25em',
        ], null, 'chunkedhtml');

        foreach ([
            '<!DOCTYPE html>',
            '<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" dir="ltr">',
            '<meta name="generator" content="pandoc" />',
            '<meta name="author" content="Migration bot" />',
            '<meta name="dcterms.date" content="2026-06-08" />',
            '<meta name="keywords" content="migration, wordpress, chunked" />',
            '<meta name="description" content="Chunked HTML review packet" />',
            '<title>WordPress Import – Chunked Review Packet</title>',
            'div.sitenav { display: flex; flex-direction: row; flex-wrap: wrap; }',
            'font-family: Atkinson Hyperlegible;',
            '/* CSS for citations */',
            'margin-bottom: 0.25em;',
            '<link rel="stylesheet" href="chunked-review.css" />',
            '<meta name="robots" content="noindex">',
            '<script type="math/tex">queued</script>',
            '<main class="chunked-before">Queued</main>',
            '<span class="navlink-label">Up:</span> <a href="../index.html" accesskey="u" rel="up">Manual Root</a>',
            '<span class="navlink-label">Next:</span> <a href="next.html" accesskey="n" rel="next">Next Chunk</a>',
            '<span class="navlink-label">Previous:</span> <a href="previous.html" accesskey="p" rel="previous">Previous Chunk</a>',
            '<h1 class="title">Chunked Review</h1>',
            '<p class="subtitle">Native split-page metadata</p>',
            '<p class="author">Migration bot</p>',
            '<div class="abstract-title">Abstract</div>',
            '<nav id="wp-chunked-TOC" role="doc-toc">',
            '<h2 id="wp-chunked-toc-title">Chunk Contents</h2>',
            '<ul><li>Chunk body</li></ul>',
            '<!-- wp:paragraph --><p>Chunked body.</p><!-- /wp:paragraph -->',
            '<footer>Chunk done</footer>',
        ] as $needle) {
            $t->contains($needle, $chunked);
        }

        $topPage = $renderer->renderResource('templates/default.chunkedhtml', [], [
            'lang' => 'en',
            'pagetitle' => 'Chunked Top Packet',
            'top' => ['url' => 'index.html', 'title' => 'Top Chunk'],
            'title' => 'Suppressed Top Title',
            'body' => '<p>Top body.</p>',
        ], null, 'chunkedhtml+smart');
        $t->contains('<span class="navlink-label">Top:</span> <a href="index.html" accesskey="t" rel="top">Top Chunk</a>', $topPage);
        $t->same(false, str_contains($topPage, '<h1 class="title">Suppressed Top Title</h1>'));

        $extensionQualified = $renderer->renderResource('templates/default', [], [
            'lang' => 'en',
            'pagetitle' => 'Chunked Extension Packet',
            'body' => '<p>Chunked extension body.</p>',
        ], null, 'chunkedhtml+smart');
        $t->contains('<title>Chunked Extension Packet</title>', $extensionQualified);
        $t->contains('<p>Chunked extension body.</p>', $extensionQualified);

        $wrappedDefault = $renderer->renderResource('templates/review.html', [
            'templates/review.html' => '<article class="wrapped-chunked">${ default.chunkedhtml() }</article>',
        ], [
            'lang' => 'en',
            'pagetitle' => 'Wrapped Chunked Packet',
            'body' => '<p>Wrapped chunked body.</p>',
        ], null, 'html');
        $t->contains('<article class="wrapped-chunked"><!DOCTYPE html>', $wrappedDefault);
        $t->contains('<title>Wrapped Chunked Packet</title>', $wrappedDefault);
        $t->contains('<p>Wrapped chunked body.</p>', $wrappedDefault);

        $t->same('custom chunkedhtml Exact chunked override', $renderer->renderResource('templates/default', [
            'templates/default.chunkedhtml' => 'custom chunkedhtml $body$',
        ], [
            'body' => 'Exact chunked override',
        ], null, 'chunkedhtml'));
    },

    'renders bounded pandoc default html5 template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->renderResource('templates/default', [], [
            'lang' => 'en',
            'dir' => 'ltr',
            'pandoc-version' => '3.7.0',
            'title-prefix' => 'WordPress Import',
            'pagetitle' => 'Batch 42 Review Packet',
            'title' => 'Batch 42 Review',
            'subtitle' => 'DOCX metadata review',
            'author' => ['Migration bot', 'Content editor'],
            'author-meta' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-05',
            'date-meta' => '2026-06-05',
            'keywords' => ['migration', 'wordpress', 'review'],
            'description-meta' => 'Native doctemplate handoff',
            'css' => ['review.css'],
            'header-includes' => ['<meta name="robots" content="noindex">'],
            'math' => '<script type="math/tex">queued</script>',
            'include-before' => ['<main class="before">Queued</main>'],
            'abstract-title' => 'Abstract',
            'abstract' => '<p>Review summary.</p>',
            'toc' => true,
            'idprefix' => 'wp-review-',
            'toc-title' => 'Contents',
            'table-of-contents' => '<ul><li>Imported body</li></ul>',
            'body' => '<!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->',
            'include-after' => ['<footer>Done</footer>'],
        ], null, 'html');

        $t->contains('<!DOCTYPE html>', $output);
        $t->contains('<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" dir="ltr">', $output);
        $t->contains('<meta charset="utf-8" />', $output);
        $t->contains('<meta name="generator" content="pandoc 3.7.0" />', $output);
        $t->contains('<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />', $output);
        $t->contains('<meta name="author" content="Migration bot" />', $output);
        $t->contains('<meta name="author" content="Content editor" />', $output);
        $t->contains('<meta name="dcterms.date" content="2026-06-05" />', $output);
        $t->contains('<meta name="keywords" content="migration, wordpress, review" />', $output);
        $t->contains('<meta name="description" content="Native doctemplate handoff" />', $output);
        $t->contains('<title>WordPress Import &ndash; Batch 42 Review Packet</title>', $output);
        $t->contains('<link rel="stylesheet" href="review.css" />', $output);
        $t->contains('<meta name="robots" content="noindex">', $output);
        $t->contains('<script type="math/tex">queued</script>', $output);
        $t->contains('<h1 class="title">Batch 42 Review</h1>', $output);
        $t->contains('<p class="subtitle">DOCX metadata review</p>', $output);
        $t->contains('<p class="author">Migration bot</p>', $output);
        $t->contains('<p class="author">Content editor</p>', $output);
        $t->contains('<p class="date">2026-06-05</p>', $output);
        $t->contains('<div class="abstract-title">Abstract</div>', $output);
        $t->contains('<p>Review summary.</p>', $output);
        $t->contains('<nav id="wp-review-TOC" role="doc-toc">', $output);
        $t->contains('<h2 id="wp-review-toc-title">Contents</h2>', $output);
        $t->contains('<ul><li>Imported body</li></ul>', $output);
        $t->contains('<!-- wp:paragraph --><p>Imported body.</p><!-- /wp:paragraph -->', $output);
        $t->contains('<footer>Done</footer>', $output);
        $t->same('custom Batch 42 Review', $renderer->renderResource('templates/default', [
            'templates/default.html5' => 'custom $title$',
        ], [
            'title' => 'Batch 42 Review',
        ], null, 'html5'));
    },

    'renders bounded pandoc default html5 style partial resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $output = $renderer->renderResource('templates/default', [], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Styled Review Packet',
            'body' => '<p>Styled body.</p>',
            'document-css' => true,
            'mainfont' => 'Atkinson Hyperlegible',
            'fontsize' => '18px',
            'linestretch' => '1.6',
            'fontcolor' => '#202124',
            'backgroundcolor' => '#ffffff',
            'linkcolor' => '#135e96',
            'maxwidth' => '42em',
            'margin-left' => '2rem',
            'margin-right' => '2rem',
            'margin-top' => '3rem',
            'margin-bottom' => '3rem',
            'monofont' => 'JetBrains Mono',
            'monobackgroundcolor' => '#f6f8fa',
            'table-caption-below' => true,
            'quotes' => true,
            'displaymath-css' => true,
            'highlighting-css' => '.sourceCode .kw { color: #005cc5; }',
            'csl-css' => true,
            'csl-entry-spacing' => '0.5em',
        ], null, 'html');

        $t->contains('<style>', $output);
        $t->contains('/* Default styles provided by pandoc.', $output);
        $t->contains('font-family: Atkinson Hyperlegible;', $output);
        $t->contains('font-size: 18px;', $output);
        $t->contains('line-height: 1.6;', $output);
        $t->contains('color: #202124;', $output);
        $t->contains('background-color: #ffffff;', $output);
        $t->contains('max-width: 42em;', $output);
        $t->contains('padding-left: 2rem;', $output);
        $t->contains('padding-right: 2rem;', $output);
        $t->contains('padding-top: 3rem;', $output);
        $t->contains('padding-bottom: 3rem;', $output);
        $t->contains('color: #135e96;', $output);
        $t->contains('font-family: JetBrains Mono;', $output);
        $t->contains('background-color: #f6f8fa;', $output);
        $t->contains('caption-side: bottom;', $output);
        $t->contains('q { quotes: "\\201C" "\\201D" "\\2018" "\\2019"; }', $output);
        $t->contains('.display.math{display: block; text-align: center; margin: 0.5rem auto;}', $output);
        $t->contains('/* CSS for syntax highlighting */', $output);
        $t->contains('.sourceCode .kw { color: #005cc5; }', $output);
        $t->contains('/* CSS for citations */', $output);
        $t->contains('margin-bottom: 0.5em;', $output);

        $custom = $renderer->renderResource('templates/default', [
            'templates/styles.html' => '/* Custom WordPress review styles */' . "\n" . '.wp-review { color: rebeccapurple; }',
        ], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Custom Style Packet',
            'body' => '<p>Styled body.</p>',
        ], null, 'html');

        $t->contains('/* Custom WordPress review styles */', $custom);
        $t->contains('.wp-review { color: rebeccapurple; }', $custom);
        $t->same(false, str_contains($custom, '/* Default styles provided by pandoc.'));
    },

    'renders pandoc default html5 void metadata tags like upstream' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('templates/default', [], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Void Tag Review',
            'author-meta' => ['Migration bot'],
            'date-meta' => '2026-06-05',
            'keywords' => ['migration', 'wordpress'],
            'description-meta' => 'Native default-template tag review',
            'css' => ['review.css'],
            'header-includes' => ['<meta name="robots" content="noindex">'],
            'body' => '<p>Review body.</p>',
        ], null, 'html');

        $t->contains('<meta charset="utf-8" />', $output);
        $t->contains('<meta name="generator" content="pandoc 3.7.0" />', $output);
        $t->contains('<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />', $output);
        $t->contains('<meta name="author" content="Migration bot" />', $output);
        $t->contains('<meta name="dcterms.date" content="2026-06-05" />', $output);
        $t->contains('<meta name="keywords" content="migration, wordpress" />', $output);
        $t->contains('<meta name="description" content="Native default-template tag review" />', $output);
        $t->contains('<link rel="stylesheet" href="review.css" />', $output);
        $t->contains('<meta name="robots" content="noindex">', $output);
        $t->same(false, str_contains($output, '<meta name="robots" content="noindex" />'));
    },

    'renders bounded pandoc default markdown and commonmark template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $markdown = $renderer->renderResource('templates/default', [], [
            'titleblock' => "# Batch 42 Review\n\nMigration handoff",
            'header-includes' => ['<!-- wp:html --><aside>Header note</aside><!-- /wp:html -->'],
            'include-before' => ['<!-- wp:paragraph --><p>Before import.</p><!-- /wp:paragraph -->'],
            'toc' => true,
            'table-of-contents' => '- [Body](#body)',
            'body' => "## Body\n\nImported content.",
            'include-after' => ['<!-- wp:paragraph --><p>After import.</p><!-- /wp:paragraph -->'],
        ], null, 'markdown_strict');

        $t->contains("# Batch 42 Review\n\nMigration handoff", $markdown);
        $t->contains("<!-- wp:html --><aside>Header note</aside><!-- /wp:html -->\n\n<!-- wp:paragraph --><p>Before import.</p><!-- /wp:paragraph -->", $markdown);
        $t->contains("- [Body](#body)\n\n## Body\n\nImported content.", $markdown);
        $t->contains("Imported content.\n\n<!-- wp:paragraph --><p>After import.</p><!-- /wp:paragraph -->", $markdown);

        $t->same("GFM body\n", $renderer->renderResource('templates/default', [], [
            'body' => 'GFM body',
        ], null, 'gfm'));
        $t->same("CommonMark body\n", $renderer->renderResource('templates/default.commonmark', [], [
            'body' => 'CommonMark body',
        ]));
        $t->same('custom markdown', $renderer->renderResource('templates/default', [
            'templates/default.markdown' => 'custom $body$',
        ], [
            'body' => 'markdown',
        ], null, 'markdown'));
        $t->same('custom commonmark', $renderer->renderResource('templates/default', [
            'templates/default.commonmark' => 'custom $body$',
        ], [
            'body' => 'commonmark',
        ], null, 'commonmark_x'));
    },

    'renders bounded pandoc default asciidoc template resource and aliases' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $asciidoc = $renderer->renderResource('templates/default', [], [
            'titleblock' => true,
            'title' => 'AsciiDoc Review Packet',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-07',
            'keywords' => ['migration', 'wordpress', 'review'],
            'lang' => 'en-US',
            'toc' => true,
            'math' => true,
            'abstract' => 'Native AsciiDoc template review.',
            'header-includes' => [':wp-review: enabled'],
            'include-before' => ['[NOTE]' . "\n" . '====' . "\n" . 'Review imported blocks before publishing.' . "\n" . '===='],
            'body' => "== Imported Body\n\nConverted content.",
            'include-after' => ['[appendix]' . "\n" . '== Handoff'],
        ], null, 'asciidoc');

        foreach ([
            '= AsciiDoc Review Packet',
            'Migration bot; Content editor',
            '2026-06-07',
            ':keywords: migration, wordpress, review',
            ':lang: en-US',
            ':toc:',
            ':stem: latexmath',
            '[abstract]',
            '== Abstract',
            'Native AsciiDoc template review.',
            ':wp-review: enabled',
            "[NOTE]\n====\nReview imported blocks before publishing.\n====",
            "== Imported Body\n\nConverted content.",
            "[appendix]\n== Handoff",
        ] as $needle) {
            $t->contains($needle, $asciidoc);
        }

        $dateOnly = $renderer->renderResource('templates/default.asciidoc', [], [
            'titleblock' => true,
            'date' => '2026-06-07',
            'body' => 'Date-only body',
        ]);
        $t->contains(':revdate: 2026-06-07', $dateOnly);
        $t->contains('Date-only body', $dateOnly);

        $t->same("Alias body\n", $renderer->renderResource('templates/default', [], [
            'body' => 'Alias body',
        ], null, 'asciidoctor'));
        $t->same("Legacy body\n", $renderer->renderResource('templates/default', [], [
            'body' => 'Legacy body',
        ], null, 'asciidoc_legacy'));
        $t->same('custom asciidoc', $renderer->renderResource('templates/default', [
            'templates/default.asciidoc' => 'custom $body$',
        ], [
            'body' => 'asciidoc',
        ], null, 'asciidoctor'));
    },

    'renders bounded pandoc default plain template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same("Plain review body\n", $renderer->renderResource('templates/default', [], [
            'body' => 'Plain review body',
        ], null, 'plain'));

        $t->same("Direct plain body\n\n", $renderer->renderResource('templates/default.plain', [], [
            'body' => "Direct plain body\n\n",
        ]));

        $t->same('custom plain', $renderer->renderResource('templates/default', [
            'templates/default.plain' => 'custom $body$',
        ], [
            'body' => 'plain',
        ], null, 'plain'));
    },

    'renders pandoc default plain title include and toc hooks' => static function (TestRunner $t): void {
        $plain = (new DocTemplate())->renderResource('templates/default', [], [
            'titleblock' => "Plain Review Packet\n===================",
            'header-includes' => ['Plain header metadata'],
            'include-before' => ['Before plain import'],
            'toc' => true,
            'table-of-contents' => 'Plain contents',
            'body' => 'Plain body handoff',
            'include-after' => ['After plain import'],
        ], null, 'plain');

        foreach ([
            "Plain Review Packet\n===================",
            'Plain header metadata',
            'Before plain import',
            'Plain contents',
            'Plain body handoff',
            'After plain import',
        ] as $needle) {
            $t->contains($needle, $plain);
        }
    },

    'renders bounded pandoc default ansi and bibliography template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $ansi = $renderer->renderResource('templates/default', [], [
            'titleblock' => "ANSI Review Packet\n==================",
            'header-includes' => ['ANSI header metadata'],
            'include-before' => ['Before ANSI import'],
            'toc' => true,
            'table-of-contents' => 'ANSI contents',
            'body' => 'ANSI body handoff',
            'include-after' => ['After ANSI import'],
        ], null, 'ansi+smart');

        foreach ([
            "ANSI Review Packet\n==================",
            'ANSI header metadata',
            'Before ANSI import',
            'ANSI contents',
            'ANSI body handoff',
            'After ANSI import',
        ] as $needle) {
            $t->contains($needle, $ansi);
        }

        $t->contains('Direct ANSI body', $renderer->renderResource('templates/default.ansi', [], [
            'body' => "Direct ANSI body\n\n",
        ]));

        $t->same('custom ansi', $renderer->renderResource('templates/default', [
            'templates/default.ansi' => 'custom $body$',
        ], [
            'body' => 'ansi',
        ], null, 'ansi'));

        $bibtex = $renderer->renderResource('templates/default', [], [
            'header-includes' => ['% BibTeX header audit'],
            'include-before' => ['@comment{before-import}'],
            'toc' => true,
            'table-of-contents' => '@comment{contents}',
            'body' => '@book{review2026, title = {Review Packet}}',
            'include-after' => ['@comment{after-import}'],
        ], null, 'bibtex');

        foreach ([
            '% BibTeX header audit',
            '@comment{before-import}',
            '@comment{contents}',
            '@book{review2026, title = {Review Packet}}',
            '@comment{after-import}',
        ] as $needle) {
            $t->contains($needle, $bibtex);
        }

        $biblatex = $renderer->renderResource('templates/default.biblatex', [], [
            'body' => '@online{migration2026, title = {Migration Review}}',
        ]);
        $t->contains('@online{migration2026, title = {Migration Review}}', $biblatex);

        $t->same('custom biblatex', $renderer->renderResource('templates/default', [
            'templates/default.biblatex' => 'custom $body$',
        ], [
            'body' => 'biblatex',
        ], null, 'biblatex+smart'));
    },

    'renders bounded pandoc default muse template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $muse = $renderer->renderResource('templates/default', [], [
            'author' => ['Migration bot', 'Content editor'],
            'title' => 'Muse Review Packet',
            'lang' => 'en-US',
            'LISTtitle' => 'Review Queue',
            'subtitle' => 'Native template handoff',
            'SORTauthors' => 'Migration bot',
            'SORTtopics' => 'migration wordpress',
            'date' => '2026-06-08',
            'notes' => 'Reviewer packet metadata survives.',
            'source' => 'https://example.test/wp-admin/import',
            'header-includes' => ['#custom reviewer-metadata'],
            'include-before' => ['** Before import'],
            'body' => "Imported Muse body\nSecond review line.",
            'include-after' => ['** After import'],
        ], null, 'muse');

        foreach ([
            '#author Migration bot; Content editor',
            '#title Muse Review Packet',
            '#lang en-US',
            '#LISTtitle Review Queue',
            '#subtitle Native template handoff',
            '#SORTauthors Migration bot',
            '#SORTtopics migration wordpress',
            '#date 2026-06-08',
            '#notes Reviewer packet metadata survives.',
            '#source https://example.test/wp-admin/import',
            '#custom reviewer-metadata',
            '** Before import',
            "Imported Muse body\nSecond review line.",
            '** After import',
        ] as $needle) {
            $t->contains($needle, $muse);
        }

        $direct = $renderer->renderResource('templates/default.muse', [], [
            'body' => 'Direct Muse body',
        ]);
        $t->contains('Direct Muse body', $direct);

        $extensionQualified = $renderer->renderResource('templates/default', [], [
            'title' => 'Muse Extension Packet',
            'body' => 'Muse extension body',
        ], null, 'muse+smart');
        $t->contains('#title Muse Extension Packet', $extensionQualified);
        $t->contains('Muse extension body', $extensionQualified);

        $t->same('custom muse', $renderer->renderResource('templates/default', [
            'templates/default.muse' => 'custom $body$',
        ], [
            'body' => 'muse',
        ], null, 'muse'));
    },

    'renders bounded pandoc default org template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $org = $renderer->renderResource('templates/default', [], [
            'title' => 'Org Review Packet',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'options' => [
                'toc' => 'nil',
                'num' => 't',
            ],
            'header-includes' => ['#+setupfile: reviewer.org'],
            'abstract' => "Native Org default handoff.\nSecond abstract line.",
            'include-before' => ['* Before import'],
            'body' => "* Imported Body\nConverted content.",
            'include-after' => ['* Handoff'],
        ], null, 'org');

        foreach ([
            '#+title: Org Review Packet',
            '#+author: Migration bot; Content editor',
            '#+date: 2026-06-08',
            '#+options: num:t',
            '#+options: toc:nil',
            '#+setupfile: reviewer.org',
            '#+begin_abstract',
            "Native Org default handoff.\nSecond abstract line.",
            '#+end_abstract',
            '* Before import',
            "* Imported Body\nConverted content.",
            '* Handoff',
        ] as $needle) {
            $t->contains($needle, $org);
        }

        $direct = $renderer->renderResource('templates/default.org', [], [
            'body' => "Direct Org body\n\n",
        ]);
        $t->contains('Direct Org body', $direct);

        $extensionQualified = $renderer->renderResource('templates/default', [], [
            'title' => 'Org Extension Packet',
            'body' => 'Org extension body',
        ], null, 'org+smart');
        $t->contains('#+title: Org Extension Packet', $extensionQualified);
        $t->contains('Org extension body', $extensionQualified);

        $t->same('custom org', $renderer->renderResource('templates/default', [
            'templates/default.org' => 'custom $body$',
        ], [
            'body' => 'org',
        ], null, 'org'));
    },

    'renders bounded pandoc default texinfo template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $texinfo = $renderer->renderResource('templates/default', [], [
            'filename' => 'review.info',
            'title' => 'Texinfo Review Packet',
            'version' => '1.2',
            'header-includes' => ['@syncodeindex fn cp'],
            'strikeout' => true,
            'titlepage' => true,
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'include-before' => ['@node Before Import' . "\n" . '@chapter Before Import'],
            'toc' => true,
            'body' => "@node Imported Body\n@chapter Imported Body\nConverted content.",
            'include-after' => ['@node Handoff' . "\n" . '@chapter Handoff'],
        ], null, 'texinfo');

        foreach ([
            '\\input texinfo  @c -*-texinfo-*-',
            '@setfilename review.info',
            '@settitle Texinfo Review Packet 1.2',
            '@documentencoding UTF-8',
            '@syncodeindex fn cp',
            '@macro textstrikeout{text}',
            '~~\\text\\~~',
            '@ifnottex',
            '@paragraphindent 0',
            '@titlepage',
            '@title Texinfo Review Packet',
            '@subtitle 1.2',
            '@author Migration bot',
            '@author Content editor',
            '2026-06-08',
            '@node Before Import',
            '@contents',
            "@node Imported Body\n@chapter Imported Body\nConverted content.",
            '@node Handoff',
            '@bye',
        ] as $needle) {
            $t->contains($needle, $texinfo);
        }

        $direct = $renderer->renderResource('templates/default.texinfo', [], [
            'body' => "@chapter Direct Body\n",
        ]);
        $t->contains("@chapter Direct Body\n", $direct);

        $extensionQualified = $renderer->renderResource('templates/default', [], [
            'title' => 'Texinfo Extension Packet',
            'body' => '@chapter Extension Body',
        ], null, 'texinfo+smart');
        $t->contains('@settitle Texinfo Extension Packet', $extensionQualified);
        $t->contains('@chapter Extension Body', $extensionQualified);

        $t->same('custom texinfo', $renderer->renderResource('templates/default', [
            'templates/default.texinfo' => 'custom $body$',
        ], [
            'body' => 'texinfo',
        ], null, 'texinfo'));
    },

    'renders bounded pandoc default rst template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $rst = $renderer->renderResource('templates/default', [], [
            'titleblock' => "Batch 42 Review\n===============",
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'address' => 'https://example.test/wp-admin/edit.php',
            'contact' => 'migration@example.test',
            'copyright' => 'Internal review only',
            'dedication' => 'Reviewer desk',
            'organization' => 'Port Libs',
            'revision' => 'r42',
            'status' => 'draft',
            'version' => '3.7',
            'abstract' => "Native RST default template review.\nSecond abstract line.",
            'rawtex' => true,
            'include-before' => ['.. note:: Review imported sections before publishing.'],
            'toc' => true,
            'toc-depth' => 2,
            'number-sections' => true,
            'header-includes' => ['.. |wp| replace:: WordPress'],
            'body' => "Imported Body\n-------------\n\nConverted content.",
            'include-after' => ['.. footer:: Migration desk'],
        ], null, 'rst');

        foreach ([
            "Batch 42 Review\n===============",
            ':Author: Migration bot',
            ':Author: Content editor',
            ':Date: 2026-06-08',
            ':Address: https://example.test/wp-admin/edit.php',
            ':Contact: migration@example.test',
            ':Copyright: Internal review only',
            ':Dedication: Reviewer desk',
            ':Organization: Port Libs',
            ':Revision: r42',
            ':Status: draft',
            ':Version: 3.7',
            ":Abstract:\n   Native RST default template review.\n   Second abstract line.",
            '.. role:: raw-latex(raw)',
            '   :format: latex',
            '.. note:: Review imported sections before publishing.',
            ".. contents::\n   :depth: 2",
            '.. section-numbering::',
            '.. |wp| replace:: WordPress',
            "Imported Body\n-------------\n\nConverted content.",
            '.. footer:: Migration desk',
        ] as $needle) {
            $t->contains($needle, $rst);
        }

        $direct = $renderer->renderResource('templates/default.rst', [], [
            'body' => "Direct RST body\n\n",
        ]);
        $t->contains('Direct RST body', $direct);

        $t->same('custom rst', $renderer->renderResource('templates/default', [
            'templates/default.rst' => 'custom $body$',
        ], [
            'body' => 'rst',
        ], null, 'rst'));
    },

    'renders bounded pandoc default bbcode template resource and aliases' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $body = "[b]Batch 42 Review[/b]\n\n[url=https://example.test/wp-admin/edit.php]Review import queue[/url]\n";

        foreach ([
            'bbcode',
            'bbcode_phpbb',
            'bbcode_fluxbb',
            'bbcode_steam',
            'bbcode_hubzilla',
            'bbcode_xenforo',
        ] as $format) {
            $t->same(substr($body, 0, -1), $renderer->renderResource('templates/default', [], [
                'body' => $body,
            ], null, $format));
        }

        $t->same('[i]Direct BBCode body[/i]', $renderer->renderResource('templates/default.bbcode', [], [
            'body' => "[i]Direct BBCode body[/i]\n",
        ]));

        $t->same('[quote]custom bbcode[/quote]', $renderer->renderResource('templates/default', [
            'templates/default.bbcode' => '[quote]custom $body$[/quote]',
        ], [
            'body' => 'bbcode',
        ], null, 'bbcode_steam'));
    },

    'renders bounded pandoc default wiki and vimdoc template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $jira = $renderer->renderResource('templates/default', [], [
            'include-before' => ['h1. Before Jira import'],
            'body' => 'h2. Imported Jira body',
            'include-after' => ['h1. Jira handoff'],
        ], null, 'jira+smart');

        foreach ([
            'h1. Before Jira import',
            'h2. Imported Jira body',
            'h1. Jira handoff',
        ] as $needle) {
            $t->contains($needle, $jira);
        }
        $t->same(false, str_contains($jira, '__TOC__'));

        foreach ([
            'dokuwiki' => '====== DokuWiki body ======',
            'mediawiki' => '== MediaWiki body ==',
        ] as $format => $body) {
            $output = $renderer->renderResource('templates/default', [], [
                'include-before' => ['//Before ' . $format . ' import//'],
                'toc' => true,
                'body' => $body,
                'include-after' => ['//After ' . $format . ' import//'],
            ], null, $format . '+smart');

            foreach ([
                '//Before ' . $format . ' import//',
                '__TOC__',
                $body,
                '//After ' . $format . ' import//',
            ] as $needle) {
                $t->contains($needle, $output);
            }
        }

        $directMediaWiki = $renderer->renderResource('templates/default.mediawiki', [], [
            'toc' => true,
            'body' => 'Direct MediaWiki body',
        ]);
        $t->contains('__TOC__', $directMediaWiki);
        $t->contains('Direct MediaWiki body', $directMediaWiki);

        $vimdoc = $renderer->renderResource('templates/default', [], [
            'filename' => 'wp-import-review.txt',
            'abstract' => 'WordPress import review packet.',
            'combined-title' => 'WP Import Review',
            'toc-reminder' => 'Type gO for table of contents.',
            'toc' => '|wp-import-toc|',
            'body' => '*wp-import-body* Converted Vimdoc body.',
            'modeline' => 'vim:tw=78:ft=help:norl:',
        ], null, 'vimdoc+smart');

        foreach ([
            '*wp-import-review.txt*',
            'WordPress import review packet.',
            'WP Import Review',
            'Type gO for table of contents.',
            '|wp-import-toc|',
            '*wp-import-body* Converted Vimdoc body.',
            'vim:tw=78:ft=help:norl:',
        ] as $needle) {
            $t->contains($needle, $vimdoc);
        }

        $directVimdoc = $renderer->renderResource('templates/default.vimdoc', [], [
            'body' => 'Direct Vimdoc body',
        ]);
        $t->contains('Direct Vimdoc body', $directVimdoc);

        $t->same('custom jira', $renderer->renderResource('templates/default', [
            'templates/default.jira' => 'custom $body$',
        ], [
            'body' => 'jira',
        ], null, 'jira'));
        $t->same('custom dokuwiki', $renderer->renderResource('templates/default', [
            'templates/default.dokuwiki' => 'custom $body$',
        ], [
            'body' => 'dokuwiki',
        ], null, 'dokuwiki'));
        $t->same('custom vimdoc', $renderer->renderResource('templates/default', [
            'templates/default.vimdoc' => 'custom $body$',
        ], [
            'body' => 'vimdoc',
        ], null, 'vimdoc'));
    },

    'renders bounded pandoc default opml lightweight markup and wiki template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $opml = $renderer->renderResource('templates/default', [], [
            'title' => 'OPML Review Packet',
            'date' => '2026-06-08',
            'author' => ['Migration bot', 'Content editor'],
            'body' => '<outline text="Imported body"/>',
        ], null, 'opml+smart');
        foreach ([
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<opml version="2.0">',
            '<title>OPML Review Packet</title>',
            '<dateModified>2026-06-08</dateModified>',
            '<ownerName>Migration bot; Content editor</ownerName>',
            '<outline text="Imported body"/>',
            '</opml>',
        ] as $needle) {
            $t->contains($needle, $opml);
        }

        $djot = $renderer->renderResource('templates/default', [], [
            'title' => 'Djot Review Packet',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'header-includes' => [':::{.review-meta}'],
            'include-before' => [':::note' . "\n" . 'Before Djot import' . "\n" . ':::'],
            'body' => '## Imported Djot body',
            'include-after' => [':::handoff' . "\n" . 'After Djot import' . "\n" . ':::'],
        ], null, 'djot+smart');
        foreach ([
            '# Djot Review Packet',
            "Migration bot\nContent editor",
            '2026-06-08',
            ':::{.review-meta}',
            ":::note\nBefore Djot import\n:::",
            '## Imported Djot body',
            ":::handoff\nAfter Djot import\n:::",
        ] as $needle) {
            $t->contains($needle, $djot);
        }

        $textile = $renderer->renderResource('templates/default', [], [
            'include-before' => ['h1. Before Textile import'],
            'body' => 'h2. Imported Textile body',
            'include-after' => ['h1. Textile handoff'],
        ], null, 'textile+smart');
        foreach ([
            'h1. Before Textile import',
            'h2. Imported Textile body',
            'h1. Textile handoff',
        ] as $needle) {
            $t->contains($needle, $textile);
        }

        $markua = $renderer->renderResource('templates/default', [], [
            'titleblock' => '# Markua Review Packet',
            'header-includes' => ['{frontmatter: review}'],
            'include-before' => ['# Before Markua import'],
            'toc' => true,
            'table-of-contents' => '{toc}',
            'body' => '# Imported Markua body',
            'include-after' => ['# Markua handoff'],
        ], null, 'markua+smart');
        foreach ([
            '# Markua Review Packet',
            '{frontmatter: review}',
            '# Before Markua import',
            '{toc}',
            '# Imported Markua body',
            '# Markua handoff',
        ] as $needle) {
            $t->contains($needle, $markua);
        }

        $tei = $renderer->renderResource('templates/default', [], [
            'lang' => 'en',
            'title' => 'TEI Review Packet',
            'author' => ['Migration bot', 'Content editor'],
            'publicationStmt' => 'Internal migration review',
            'license' => 'Internal use only',
            'publisher' => 'Port Libs',
            'pubPlace' => 'Review desk',
            'address' => 'https://example.test/wp-admin/import',
            'date' => '2026-06-08',
            'sourceDesc' => '<p>Converted from a source document.</p>',
            'include-before' => ['<front><p>Before TEI import</p></front>'],
            'body' => '<div><p>Imported TEI body.</p></div>',
            'include-after' => ['<back><p>TEI handoff.</p></back>'],
        ], null, 'tei+smart');
        foreach ([
            '<?xml version="1.0" encoding="utf-8"?>',
            '<TEI xmlns="http://www.tei-c.org/ns/1.0" xml:lang="en">',
            '<title>TEI Review Packet</title>',
            '<author>Migration bot</author>',
            '<author>Content editor</author>',
            '<p>Internal migration review</p>',
            '<availability><licence>Internal use only</licence></availability>',
            '<publisher>Port Libs</publisher>',
            '<pubPlace>Review desk</pubPlace>',
            '<address>https://example.test/wp-admin/import</address>',
            '<date>2026-06-08</date>',
            '<p>Converted from a source document.</p>',
            '<front><p>Before TEI import</p></front>',
            '<body>',
            '<div><p>Imported TEI body.</p></div>',
            '<back><p>TEI handoff.</p></back>',
            '</TEI>',
        ] as $needle) {
            $t->contains($needle, $tei);
        }
        $t->same(false, str_contains($tei, 'Produced by pandoc.'));

        $teiDefaultSourceDesc = $renderer->renderResource('templates/default.tei', [], [
            'title' => 'Default sourceDesc',
            'body' => '<p>Body</p>',
        ]);
        $t->contains('<p>Produced by pandoc.</p>', $teiDefaultSourceDesc);

        foreach ([
            'xwiki' => ['{{toc /}}', '= XWiki Body ='],
            'zimwiki' => ['__TOC__', '====== ZimWiki Body ======'],
        ] as $format => [$tocMarker, $body]) {
            $output = $renderer->renderResource('templates/default', [], [
                'include-before' => ['Before ' . $format . ' import'],
                'toc' => true,
                'body' => $body,
                'include-after' => ['After ' . $format . ' import'],
            ], null, $format . '+smart');

            if ($format === 'zimwiki') {
                $t->contains('Content-Type: text/x-zim-wiki', $output);
                $t->contains('Wiki-Format: zim 0.4', $output);
            }

            foreach ([
                'Before ' . $format . ' import',
                $tocMarker,
                $body,
                'After ' . $format . ' import',
            ] as $needle) {
                $t->contains($needle, $output);
            }
        }

        $t->same('Direct Haddock body', $renderer->renderResource('templates/default.haddock', [], [
            'body' => 'Direct Haddock body',
        ]));
        $t->same('custom xwiki', $renderer->renderResource('templates/default', [
            'templates/default.xwiki' => 'custom $body$',
        ], [
            'body' => 'xwiki',
        ], null, 'xwiki+smart'));
    },

    'renders bounded pandoc default latex template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $latex = $renderer->renderResource('templates/default', [], [
            'documentclass' => 'report',
            'classoption' => ['oneside', 'openany'],
            'geometry' => ['margin=1in', 'includeheadfoot'],
            'title' => 'Batch 42 Review',
            'thanks' => 'Internal migration packet',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-06',
            'abstract' => 'Native LaTeX template review.',
            'header-includes' => ['\\usepackage{microtype}'],
            'include-before' => ['\\chapter*{Reviewer Queue}'],
            'toc' => true,
            'toc-title' => 'Review Contents',
            'toc-depth' => 2,
            'lof' => true,
            'lot' => true,
            'linestretch' => '1.15',
            'has-frontmatter' => true,
            'body' => '\\section{Imported Body}',
            'nocite-ids' => ['doe2024', 'roe2025'],
            'natbib' => true,
            'bibliography' => ['review', 'migration'],
            'biblio-title' => 'Review Bibliography',
            'include-after' => ['\\appendix'],
        ], null, 'latex');

        foreach ([
            '\\documentclass[oneside, openany]{report}',
            '\\usepackage[margin=1in,includeheadfoot]{geometry}',
            '\\usepackage{amsmath,amssymb}',
            '\\usepackage{setspace}',
            '\\usepackage{microtype}',
            '\\title{Batch 42 Review\\thanks{Internal migration packet}}',
            '\\author{Migration bot \\and Content editor}',
            '\\date{2026-06-06}',
            '\\begin{document}',
            '\\frontmatter',
            '\\maketitle',
            '\\begin{abstract}',
            'Native LaTeX template review.',
            '\\chapter*{Reviewer Queue}',
            '\\renewcommand*\\contentsname{Review Contents}',
            '\\setcounter{tocdepth}{2}',
            '\\tableofcontents',
            '\\listoffigures',
            '\\listoftables',
            '\\setstretch{1.15}',
            '\\mainmatter',
            '\\section{Imported Body}',
            '\\backmatter',
            '\\nocite{doe2024, roe2025}',
            '\\renewcommand\\refname{Review Bibliography}',
            '\\bibliography{review,migration}',
            '\\appendix',
            '\\end{document}',
        ] as $needle) {
            $t->contains($needle, $latex);
        }

        $biblatex = $renderer->renderResource('templates/default.latex', [], [
            'documentclass' => 'article',
            'body' => 'Body',
            'biblatex' => true,
            'biblio-title' => 'Imported Sources',
        ]);

        $t->contains('\\printbibliography[title=Imported Sources]', $biblatex);
        $t->same('custom latex', $renderer->renderResource('templates/default', [
            'templates/default.latex' => 'custom $body$',
        ], [
            'body' => 'latex',
        ], null, 'latex'));

        $pdf = $renderer->renderResource('templates/default', [], [
            'documentclass' => 'article',
            'title' => 'PDF Template Review',
            'author' => ['Migration bot'],
            'date' => '2026-06-08',
            'include-before' => ['\\section*{WordPress Review}'],
            'body' => '\\section{PDF Body}',
            'include-after' => ['\\appendix'],
        ], null, 'pdf');

        foreach ([
            '\\documentclass{article}',
            '\\title{PDF Template Review}',
            '\\author{Migration bot}',
            '\\date{2026-06-08}',
            '\\section*{WordPress Review}',
            '\\section{PDF Body}',
            '\\appendix',
            '\\end{document}',
        ] as $needle) {
            $t->contains($needle, $pdf);
        }

        $pdfExtension = $renderer->renderResource('templates/default', [], [
            'documentclass' => 'article',
            'title' => 'PDF Extension Review',
            'body' => '\\section{PDF extension body}',
        ], null, 'pdf+smart');

        $t->contains('\\title{PDF Extension Review}', $pdfExtension);
        $t->contains('\\section{PDF extension body}', $pdfExtension);
        $t->same('custom pdf latex', $renderer->renderResource('templates/default', [
            'templates/default.latex' => 'custom pdf $body$',
        ], [
            'body' => 'latex',
        ], null, 'pdf'));
    },

    'renders bundled pandoc latex partial fallback resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $template = <<<'LATEX'
${ document-metadata.latex() }
${ passoptions.latex() }
${ fonts.latex() }
${ font-settings.latex() }
${ common.latex() }
$for(header-includes)$
$header-includes$
$endfor$
${ after-header-includes.latex() }
${ hypersetup.latex() }
LATEX;

        $latex = $renderer->renderResource('templates/review.latex', [
            'templates/review.latex' => $template,
        ], [
            'pdfstandard' => [
                'version' => '2.0',
                'standards' => ['a-2b', 'ua-2'],
                'tagging' => true,
            ],
            'lang' => 'en-US',
            'hyperrefoptions' => ['pdfencoding=auto'],
            'colorlinks' => true,
            'CJKmainfont' => 'Noto Serif CJK',
            'fontenc' => 'LY1',
            'mainfont' => 'Alegreya',
            'mainfontoptions' => ['Numbers=OldStyle'],
            'mainfontfallback' => ['Noto Serif CJK', 'Noto Emoji'],
            'sansfont' => 'Atkinson Hyperlegible',
            'sansfontoptions' => ['Scale=MatchLowercase'],
            'monofont' => 'JetBrains Mono',
            'monofontoptions' => ['Scale=0.9'],
            'fontfamilies' => [
                [
                    'name' => '\\reviewfont',
                    'options' => ['Scale=0.95'],
                    'font' => 'Review Serif',
                ],
            ],
            'mathfont' => 'TeX Gyre Pagella Math',
            'mathfontoptions' => ['Scale=MatchUppercase'],
            'CJKoptions' => ['AutoFakeBold=true'],
            'CJKsansfont' => 'Noto Sans CJK',
            'CJKmonofont' => 'Noto Sans Mono CJK',
            'luatexjapresetoptions' => ['noto-otc'],
            'luatexjafontspecoptions' => ['match'],
            'zero-width-non-joiner' => true,
            'microtypeoptions' => ['protrusion=true'],
            'linestretch' => '1.15',
            'block-headings' => true,
            'verbatim-in-note' => true,
            'listings' => true,
            'lhs' => true,
            'highlighting-macros' => '\\newcommand{\\ReviewCode}[1]{#1}',
            'tables' => true,
            'multirow' => true,
            'graphics' => true,
            'svg' => true,
            'strikeout' => true,
            'csl-refs' => true,
            'csl-hanging-indent' => true,
            'babel-lang' => 'english',
            'babeloptions' => ['main=english'],
            'babelfonts' => [
                'serif' => 'Alegreya',
            ],
            'pagestyle' => 'plain',
            'subfigure' => true,
            'dir' => 'rtl',
            'natbib' => true,
            'natbiboptions' => 'numbers',
            'biblio-style' => 'plainnat',
            'biblatex' => true,
            'biblatexoptions' => ['backend=biber'],
            'bibliography' => ['review.bib', 'archive.bib'],
            'csquotes' => true,
            'csquotesoptions' => ['autostyle'],
            'header-includes' => ['\\usepackage{booktabs}'],
            'urlstyle' => 'same',
            'links-as-notes' => true,
            'title-meta' => 'Review Packet',
            'author-meta' => 'Migration bot',
            'subject' => 'Migration review',
            'keywords' => ['migration', 'wordpress'],
            'linkcolor' => 'Maroon',
            'filecolor' => 'ForestGreen',
            'citecolor' => 'Blue',
            'urlcolor' => 'MidnightBlue',
        ]);

        foreach ([
            '\\DocumentMetadata{',
            'pdfversion=2.0,',
            'pdfstandard={a-2b,ua-2},',
            'tagging=on,',
            '\\PassOptionsToPackage{unicode,pdfencoding=auto}{hyperref}',
            '\\PassOptionsToPackage{dvipsnames,svgnames,x11names}{xcolor}',
            '\\PassOptionsToPackage{space}{xeCJK}',
            '\\usepackage[LY1]{fontenc}',
            '\\usepackage{unicode-math} % this also loads fontspec',
            '\\defaultfontfeatures{Scale=MatchLowercase}',
            '\\setmainfont[Numbers=OldStyle,RawFeature={fallback=mainfontfallback}]{Alegreya}',
            '\\directlua{luaotfload.add_fallback("mainfontfallback"',
            '"Noto Serif CJK","Noto Emoji"',
            '\\setsansfont[Scale=MatchLowercase]{Atkinson Hyperlegible}',
            '\\setmonofont[Scale=0.9]{JetBrains Mono}',
            '\\newfontfamily{\\reviewfont}[Scale=0.95]{Review Serif}',
            '\\setmathfont[Scale=MatchUppercase]{TeX Gyre Pagella Math}',
            '\\setCJKmainfont[AutoFakeBold=true]{Noto Serif CJK}',
            '\\setCJKsansfont[AutoFakeBold=true]{Noto Sans CJK}',
            '\\setCJKmonofont[AutoFakeBold=true]{Noto Sans Mono CJK}',
            '\\usepackage[noto-otc]{luatexja-preset}',
            '\\usepackage[match]{luatexja-fontspec}',
            '\\setmainjfont[AutoFakeBold=true]{Noto Serif CJK}',
            '\\def\\zerowidthnonjoiner',
            '\\UseMicrotypeSet[protrusion]{basicmath}',
            '\\usepackage{setspace}',
            '\\paragraph',
            '\\subparagraph',
            '\\VerbatimFootnotes',
            '\\usepackage{listings}',
            '\\newcommand{\\ReviewCode}[1]{#1}',
            '\\usepackage{longtable,booktabs,array}',
            '\\usepackage{multirow}',
            '\\usepackage{graphicx}',
            '\\usepackage{svg}',
            '\\usepackage{soul}',
            '\\newlength{\\cslhangindent}',
            '\\usepackage[bidi=basic,shorthands=off,main=english]{babel}',
            '\\babelfont[serif]{rm}{Alegreya}',
            '\\pagestyle{plain}',
            '\\usepackage{subcaption}',
            '\\TeXXeTstate=1',
            '\\usepackage[numbers]{natbib}',
            '\\bibliographystyle{plainnat}',
            '\\usepackage[style=plainnat,backend=biber]{biblatex}',
            '\\addbibresource{review.bib}',
            '\\addbibresource{archive.bib}',
            '\\usepackage[autostyle]{csquotes}',
            '\\usepackage{booktabs}',
            '\\usepackage{bookmark}',
            '\\IfFileExists{xurl.sty}{\\usepackage{xurl}}{}',
            '\\urlstyle{same}',
            '\\DeclareRobustCommand{\\href}[2]{#2\\footnote{\\url{#1}}}',
            'pdftitle={Review Packet},',
            'pdfauthor={Migration bot},',
            'pdflang={en-US},',
            'pdfsubject={Migration review},',
            'pdfkeywords={\\xmpquote{migration}, \\xmpquote{wordpress}},',
            'linkcolor={Maroon},',
            'filecolor={ForestGreen},',
            'citecolor={Blue},',
            'urlcolor={MidnightBlue},',
            'pdfcreator={LaTeX via pandoc}}',
        ] as $needle) {
            $t->contains($needle, $latex);
        }

        $t->same('% custom fonts', $renderer->renderResource('templates/review.latex', [
            'templates/review.latex' => '${ fonts.latex() }',
            'templates/fonts.latex' => '% custom fonts',
        ], []));
    },

    'renders bounded pandoc default context template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $context = $renderer->renderResource('templates/default', [], [
            'tagging' => true,
            'context-lang' => 'en',
            'context-dir' => 'r2l',
            'title' => 'ConTeXt Review',
            'subtitle' => 'Native template packet',
            'author' => ['Migration bot', 'Content editor'],
            'keywords' => ['migration', 'wordpress', 'review'],
            'linkstyle' => 'bold',
            'linkcolor' => 'blue',
            'linkcontrastcolor' => 'purple',
            'urlstyle' => 'tt',
            'papersize' => ['A4', 'landscape'],
            'layout' => ['margin=1in', 'header=1cm'],
            'pagenumbering' => ['location={footer,right}'],
            'pdfa' => '2b',
            'pdfaiccprofile' => ['sRGB.icc'],
            'pdfaintent' => 'sRGB IEC61966-2.1',
            'mainfontfallback' => ['Noto Serif CJK'],
            'mainfont' => 'Alegreya',
            'mathfont' => 'TeX Gyre Pagella Math',
            'sansfont' => 'Atkinson Hyperlegible',
            'monofont' => 'JetBrains Mono',
            'fontsize' => '11pt',
            'whitespace' => 'big',
            'indenting' => ['yes', 'medium'],
            'interlinespace' => ['line=14pt'],
            'headertext' => ['Batch 42', 'Review'],
            'footertext' => ['Port Libs'],
            'emphasis-commands' => '\\setupitaliccorrection[global, always]',
            'highlighting-commands' => '\\definehighlight[ReviewCode][style=mono]',
            'csl-refs' => true,
            'csl-hanging-indent' => true,
            'includesource' => true,
            'curdir' => 'review-input',
            'sourcefile' => ['review.md', 'media.csv'],
            'header-includes' => ['\\setupbodyfontenvironment[default][em=italic]'],
            'date' => '2026-06-07',
            'abstract' => 'Native ConTeXt abstract.',
            'include-before' => ['\\startsection[title={Reviewer Queue}]'],
            'toc' => true,
            'lof' => true,
            'lot' => true,
            'body' => '\\section{Imported Body}',
            'include-after' => ['\\stopsection'],
        ], null, 'context');

        foreach ([
            '\\setupbackend[format=pdf/ua-2]',
            '\\mainlanguage[en]',
            '\\setupalign[r2l]',
            '\\setupdirections[bidi=on,method=two]',
            '\\setupinteraction',
            'title={ConTeXt Review}',
            'subtitle={Native template packet}',
            'author={Migration bot; Content editor}',
            'keyword={migration; wordpress; review}',
            'style=bold',
            'color=blue',
            'contrastcolor=purple',
            '\\setupurl[style=tt]',
            '\\placebookmarks[chapter, section, subsection, subsubsection][chapter, section]',
            '\\setupinteractionscreen[option={bookmark,title}]',
            '\\setuppapersize[A4,landscape]',
            '\\setuplayout[margin=1in,header=1cm]',
            '\\setuppagenumbering[location={footer,right}]',
            'format=PDF/A-2b',
            'profile={sRGB.icc}',
            'intent=sRGB IEC61966-2.1',
            '\\setupstructure[state=start,method=auto]',
            '\\definefallbackfamily[mainface][rm][Noto Serif CJK]',
            '\\definefontfamily[mainface][rm][Alegreya]',
            '\\definefontfamily[mainface][mm][TeX Gyre Pagella Math]',
            '\\definefontfamily[mainface][ss][Atkinson Hyperlegible]',
            '\\definefontfamily[mainface][tt][JetBrains Mono][features=none]',
            '\\setupbodyfont[mainface,11pt]',
            '\\setupwhitespace[big]',
            '\\setupindenting[yes,medium]',
            '\\setupinterlinespace[line=14pt]',
            '\\setupheadertexts[Batch 42][Review]',
            '\\setupfootertexts[Port Libs]',
            '\\setuphead[chapter, section, subsection, subsubsection][number=no]',
            '\\setupitaliccorrection[global, always]',
            '\\definehighlight[ReviewCode][style=mono]',
            '\\definemeasure[cslhangindent][1.5em]',
            '\\definenarrower[hangingreferences][left=\\measure{cslhangindent}]',
            'before={\\starthangingreferences[left]}',
            '\\attachment[file=review-input/review.md,method=hidden]',
            '\\attachment[file=review-input/media.csv,method=hidden]',
            '\\setupbodyfontenvironment[default][em=italic]',
            '\\starttext',
            '{\\tfd\\setupinterlinespace ConTeXt Review}',
            '{\\tfa\\setupinterlinespace Native template packet}',
            '{\\tfa\\setupinterlinespace Migration bot\\crlf Content editor}',
            '{\\tfa\\setupinterlinespace 2026-06-07}',
            '\\midaligned{\\it Abstract}',
            'Native ConTeXt abstract.',
            '\\startsection[title={Reviewer Queue}]',
            '\\completecontent',
            '\\completelistoffigures',
            '\\completelistoftables',
            '\\section{Imported Body}',
            '\\stopsection',
            '\\stoptext',
        ] as $needle) {
            $t->contains($needle, $context);
        }

        $direct = $renderer->renderResource('templates/default.context', [], [
            'body' => 'Direct ConTeXt body',
        ]);

        $t->contains('\\starttext', $direct);
        $t->contains('Direct ConTeXt body', $direct);
        $t->same('custom context', $renderer->renderResource('templates/default', [
            'templates/default.context' => 'custom $body$',
        ], [
            'body' => 'context',
        ], null, 'context'));
    },

    'renders bounded pandoc default man template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $man = $renderer->renderResource('templates/default', [], [
            'has-tables' => true,
            'pandoc-version' => '3.7.0',
            'adjusting' => 'l',
            'title' => 'wp-import-review',
            'section' => '7',
            'date' => '2026-06-07',
            'footer' => 'Port Libs',
            'header' => 'WordPress import',
            'header-includes' => ['.mso review custom macro'],
            'include-before' => ['.SH REVIEW QUEUE'],
            'body' => ".PP\nNative man packet.\n",
            'include-after' => ['.SH HANDOFF'],
            'author' => ['Migration bot', 'Content editor'],
        ], null, 'man');

        foreach ([
            "'\\\" t",
            '.\" Automatically generated by Pandoc 3.7.0',
            '.ad l',
            '.TH "wp-import-review" "7" "2026-06-07" "Port Libs" "WordPress import"',
            '.mso review custom macro',
            '.SH REVIEW QUEUE',
            ".PP\nNative man packet.",
            '.SH HANDOFF',
            '.SH AUTHORS',
            'Migration bot; Content editor.',
        ] as $needle) {
            $t->contains($needle, $man);
        }

        $direct = $renderer->renderResource('templates/default.man', [], [
            'title' => 'direct-review',
            'section' => '1',
            'date' => '2026-06-07',
            'footer' => 'Port Libs',
            'body' => ".PP\nDirect body.",
        ]);

        $t->contains('.TH "direct-review" "1" "2026-06-07" "Port Libs"', $direct);
        $t->contains(".PP\nDirect body.", $direct);
        $t->same('custom man', $renderer->renderResource('templates/default', [
            'templates/default.man' => 'custom $body$',
        ], [
            'body' => 'man',
        ], null, 'man'));
    },

    'renders bounded pandoc default ms template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $ms = $renderer->renderResource('templates/default', [], [
            'pandoc-version' => '3.7.0',
            'highlighting-macros' => '.de REVIEWCODE' . "\n" . '..',
            'pointsize' => '11p',
            'lineheight' => '13p',
            'fontfamily' => 'H',
            'indent' => '2m',
            'papersize' => 'a4',
            'adjusting' => 'b',
            'hyphenate' => true,
            'has-inline-math' => true,
            'pdf-engine' => true,
            'title-meta' => 'MS Default Review',
            'author-meta' => 'Migration bot',
            'header-includes' => ['.mso review custom ms macro'],
            'title' => 'MS Default Review',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-07',
            'abstract' => 'Native ms abstract.',
            'include-before' => ['.SH REVIEW QUEUE'],
            'body' => ".PP\nNative ms packet.\n",
            'toc' => true,
            'include-after' => ['.SH HANDOFF'],
        ], null, 'ms');

        foreach ([
            '.\" Automatically generated by Pandoc 3.7.0',
            '.de REVIEWCODE',
            '.nr LL 5.5i',
            '.nr PS 11p',
            '.nr VS 13p',
            '.fam H',
            '.nr PI 2m',
            '.ds paper a4',
            '.ad b',
            '.hy',
            ".EQ\ndelim @@\n.EN",
            '.pdfinfo /Title "MS Default Review"',
            '.pdfinfo /Author "Migration bot"',
            '.mso review custom ms macro',
            ".TL\nMS Default Review",
            ".AU\nMigration bot",
            ".AU\nContent editor",
            ".AU\n.sp 0.5\n.ft R\n2026-06-07",
            ".AB\nNative ms abstract.\n.AE",
            '.1C',
            '.SH REVIEW QUEUE',
            ".PP\nNative ms packet.",
            '.TC',
            '.SH HANDOFF',
            '.pdfsync',
        ] as $needle) {
            $t->contains($needle, $ms);
        }

        $direct = $renderer->renderResource('templates/default.ms', [], [
            'title' => 'direct-ms-review',
            'body' => ".PP\nDirect ms body.",
        ]);

        $t->contains(".TL\ndirect-ms-review", $direct);
        $t->contains(".PP\nDirect ms body.", $direct);
        $t->same('custom ms', $renderer->renderResource('templates/default', [
            'templates/default.ms' => 'custom $body$',
        ], [
            'body' => 'ms',
        ], null, 'ms'));
    },

    'renders bounded pandoc default beamer template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $beamer = $renderer->renderResource('templates/default', [], [
            'documentclass' => 'beamer',
            'fontsize' => '10pt',
            'handout' => true,
            'aspectratio' => '169',
            'classoption' => ['professionalfonts'],
            'geometry' => ['paperwidth=16cm', 'paperheight=9cm'],
            'theme' => 'Madrid',
            'themeoptions' => ['progressbar=frametitle'],
            'colortheme' => 'dolphin',
            'fonttheme' => 'professionalfonts',
            'innertheme' => 'rounded',
            'outertheme' => 'infolines',
            'navigation' => 'vertical',
            'title' => 'Batch 42 Slides',
            'shorttitle' => 'Batch 42',
            'subtitle' => 'Reviewer packet',
            'shortsubtitle' => 'Review',
            'thanks' => 'Internal migration packet',
            'author' => ['Migration bot', 'Content editor'],
            'shortauthor' => 'Migration desk',
            'date' => '2026-06-06',
            'shortdate' => '2026',
            'institute' => ['WordPress Migration', 'Content Review'],
            'shortinstitute' => 'WP',
            'titlegraphic' => ['review-cover.png', 'review-logo.png'],
            'titlegraphicoptions' => ['width=2cm'],
            'logo' => 'wp-logo.png',
            'logooptions' => ['height=1cm'],
            'section-titles' => true,
            'beameroption' => ['show notes'],
            'header-includes' => ['\\usepackage{booktabs}'],
            'include-before' => ['\\begin{frame}{Queue}\\end{frame}'],
            'toc' => true,
            'toc-title' => 'Deck Contents',
            'toc-depth' => 2,
            'lof' => true,
            'lot' => true,
            'linestretch' => '1.1',
            'body' => '\\begin{frame}{Imported Body}Body\\end{frame}',
            'nocite-ids' => ['doe2024'],
            'natbib' => true,
            'bibliography' => ['review'],
            'biblio-title' => 'Sources',
            'include-after' => ['\\appendix'],
        ], null, 'beamer');

        foreach ([
            '\\documentclass[10pt, ignorenonframetext, handout, aspectratio=169, professionalfonts]{beamer}',
            '\\geometry{paperwidth=16cm,paperheight=9cm}',
            '\\usepackage{pgfpages}',
            '\\setbeamertemplate{caption}[numbered]',
            '\\beamertemplatenavigationsymbolsvertical',
            '\\setbeameroption{show notes}',
            '\\AtBeginSection{',
            '\\usetheme[progressbar=frametitle]{Madrid}',
            '\\usecolortheme{dolphin}',
            '\\usefonttheme{professionalfonts}',
            '\\useinnertheme{rounded}',
            '\\useoutertheme{infolines}',
            '\\usepackage{booktabs}',
            '\\title[Batch 42]{Batch 42 Slides\\thanks{Internal migration packet}}',
            '\\subtitle[Review]{Reviewer packet}',
            '\\author[Migration desk]{Migration bot \\and Content editor}',
            '\\date[2026]{2026-06-06}',
            '\\institute[WP]{WordPress Migration \\and Content Review}',
            '\\includegraphics[width=2cm]{review-cover.png}\\enspace',
            '\\includegraphics[width=2cm]{review-logo.png}',
            '\\logo{\\includegraphics[height=1cm]{wp-logo.png}}',
            '\\begin{document}',
            '\\frame{\\titlepage}',
            '\\begin{frame}{Queue}\\end{frame}',
            '\\renewcommand*\\contentsname{Deck Contents}',
            '\\begin{frame}[allowframebreaks]',
            '\\frametitle{Deck Contents}',
            '\\setcounter{tocdepth}{2}',
            '\\tableofcontents',
            '\\listoffigures',
            '\\listoftables',
            '\\setstretch{1.1}',
            '\\begin{frame}{Imported Body}Body\\end{frame}',
            '\\begin{frame}[allowframebreaks]{Sources}',
            '\\nocite{doe2024}',
            '\\bibliographytrue',
            '\\bibliography{review}',
            '\\appendix',
            '\\end{document}',
        ] as $needle) {
            $t->contains($needle, $beamer);
        }

        $biblatex = $renderer->renderResource('templates/default.beamer', [], [
            'documentclass' => 'beamer',
            'body' => '\\begin{frame}{Body}\\end{frame}',
            'biblatex' => true,
            'biblio-title' => 'Imported Sources',
            'nocite-ids' => ['doe2024', 'roe2025'],
        ]);

        $t->contains('\\begin{frame}[allowframebreaks]{Imported Sources}', $biblatex);
        $t->contains('\\nocite{doe2024, roe2025}', $biblatex);
        $t->contains('\\printbibliography[heading=none]', $biblatex);
        $t->same('custom beamer', $renderer->renderResource('templates/default', [
            'templates/default.beamer' => 'custom $body$',
        ], [
            'body' => 'beamer',
        ], null, 'beamer'));
    },

    'renders bounded pandoc default revealjs template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $reveal = $renderer->renderResource('templates/default', [], [
            'lang' => 'en',
            'dir' => 'ltr',
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Reveal Packet',
            'title' => 'Reveal Default Review',
            'subtitle' => 'Native slide handoff',
            'author' => ['Migration bot', 'Content editor'],
            'author-meta' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-07',
            'date-meta' => '2026-06-07',
            'keywords' => ['migration', 'slides', 'wordpress'],
            'description-meta' => 'Reveal.js review packet',
            'revealjs-url' => 'vendor/reveal.js',
            'theme' => 'league',
            'css' => ['review-slides.css'],
            'header-includes' => ['<meta name="robots" content="noindex">'],
            'include-before' => ['<section><h2>Reviewer Queue</h2></section>'],
            'toc' => true,
            'toc-title' => 'Deck Contents',
            'table-of-contents' => '<ul><li>Imported slides</li></ul>',
            'body' => '<section><h2>Imported slides</h2><p>Review body.</p></section>',
            'include-after' => ['<section><h2>Handoff</h2></section>'],
            'transition' => 'fade',
            'background-transition' => 'slide',
            'controls' => true,
            'progress' => false,
            'slideNumber' => 'c/t',
            'history' => true,
            'keyboard' => true,
            'overview' => true,
            'center' => false,
            'touch' => true,
            'loop' => true,
            'rtl' => false,
            'navigationMode' => 'linear',
            'fragments' => false,
            'embedded' => false,
            'width' => 1280,
            'height' => 720,
            'margin' => '0.05',
            'minScale' => '0.2',
            'maxScale' => '1.5',
            'revealjs-plugins' => [
                'RevealNotes' => 'vendor/reveal.js/plugin/notes/notes.js',
                'RevealSearch' => 'vendor/reveal.js/plugin/search/search.js',
            ],
        ], null, 'revealjs');

        foreach ([
            '<!doctype html>',
            '<html lang="en" dir="ltr">',
            '<meta charset="utf-8">',
            '<meta name="generator" content="pandoc 3.7.0">',
            '<meta name="author" content="Migration bot">',
            '<meta name="author" content="Content editor">',
            '<meta name="dcterms.date" content="2026-06-07">',
            '<meta name="keywords" content="migration, slides, wordpress">',
            '<meta name="description" content="Reveal.js review packet">',
            '<title>Reveal Packet</title>',
            '<link rel="stylesheet" href="vendor/reveal.js/dist/reveal.css">',
            '<link rel="stylesheet" href="vendor/reveal.js/dist/theme/league.css" id="theme">',
            '<link rel="stylesheet" href="review-slides.css">',
            '<meta name="robots" content="noindex">',
            '<div class="reveal">',
            '<div class="slides">',
            '<section id="title-slide">',
            '<h1 class="title">Reveal Default Review</h1>',
            '<p class="subtitle">Native slide handoff</p>',
            '<p class="author">Migration bot</p>',
            '<p class="author">Content editor</p>',
            '<p class="date">2026-06-07</p>',
            '<section><h2>Reviewer Queue</h2></section>',
            '<nav id="TOC" role="doc-toc">',
            '<h2 id="toc-title">Deck Contents</h2>',
            '<ul><li>Imported slides</li></ul>',
            '<section><h2>Imported slides</h2><p>Review body.</p></section>',
            '<section><h2>Handoff</h2></section>',
            '<script src="vendor/reveal.js/dist/reveal.js"></script>',
            '<script src="vendor/reveal.js/plugin/notes/notes.js"></script>',
            '<script src="vendor/reveal.js/plugin/search/search.js"></script>',
            'Reveal.initialize({',
            'hash: true,',
            'controls: true,',
            'progress: false,',
            'slideNumber: "c/t",',
            'transition: "fade",',
            'backgroundTransition: "slide",',
            'navigationMode: "linear",',
            'fragments: false,',
            'embedded: false,',
            'width: 1280,',
            'height: 720,',
            'margin: 0.05,',
            'minScale: 0.2,',
            'maxScale: 1.5,',
            'plugins: [ RevealNotes, RevealSearch ]',
        ] as $needle) {
            $t->contains($needle, $reveal);
        }

        $direct = $renderer->renderResource('templates/default.revealjs', [], [
            'title' => 'Direct Reveal',
            'body' => '<section>Direct body.</section>',
        ]);
        $t->contains('<h1 class="title">Direct Reveal</h1>', $direct);
        $t->contains('<section>Direct body.</section>', $direct);

        $t->same('custom revealjs', $renderer->renderResource('templates/default', [
            'templates/default.revealjs' => 'custom $body$',
        ], [
            'body' => 'revealjs',
        ], null, 'revealjs'));
    },

    'renders bounded pandoc default legacy html slide template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $context = [
            'lang' => 'en',
            'dir' => 'ltr',
            'pagetitle' => 'Legacy Slide Packet',
            'title-prefix' => 'WordPress Import',
            'title' => 'Legacy Slides',
            'subtitle' => 'Native doctemplate packet',
            'author' => ['Migration bot', 'Content editor'],
            'author-meta' => ['Migration bot', 'Content editor'],
            'institute' => ['Review desk'],
            'date' => '2026-06-08',
            'date-meta' => '2026-06-08',
            'keywords' => ['migration', 'slides', 'wordpress'],
            'css' => ['review-slides.css'],
            'header-includes' => ['<meta name="robots" content="noindex">'],
            'include-before' => ['<section><h2>Reviewer Queue</h2></section>'],
            'toc' => true,
            'idprefix' => 'legacy-',
            'table-of-contents' => '<ul><li>Imported slides</li></ul>',
            'body' => '<section><h2>Imported slides</h2><p>Review body.</p></section>',
            'include-after' => ['<section><h2>Handoff</h2></section>'],
            's5-url' => 'vendor/s5/default',
            'slidy-url' => 'vendor/slidy',
            'slideous-url' => 'vendor/slideous',
            'duration' => '12',
            'dzslides-core' => '<script>window.__dzslidesReview = true;</script>',
            'document-css' => true,
        ];

        $s5 = $renderer->renderResource('templates/default', [], $context, null, 's5');
        foreach ([
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"',
            '<meta name="version" content="S5 1.1" />',
            '<title>WordPress Import – Legacy Slide Packet</title>',
            '<link rel="stylesheet" href="vendor/s5/default/slides.css" type="text/css" media="projection" id="slideProj" />',
            '<script src="vendor/s5/default/slides.js" type="text/javascript"></script>',
            '<div class="title-slide slide">',
            '<h1 class="title">Legacy Slides</h1>',
            '<h2 class="subtitle">Native doctemplate packet</h2>',
            '<h3 class="author">Migration bot<br/>Content editor</h3>',
            '<h3 class="institute">Review desk</h3>',
            '<div class="slide" id="legacy-TOC">',
            '<section><h2>Imported slides</h2><p>Review body.</p></section>',
        ] as $needle) {
            $t->contains($needle, $s5);
        }

        $wrappedS5 = $renderer->renderResource('templates/wrapper', [
            'templates/wrapper.s5' => '<div class="wrapped-slide">${ default() }</div>',
        ], $context, null, 's5');
        $t->contains('<div class="wrapped-slide"><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"', $wrappedS5);
        $t->contains('<h1 class="title">Legacy Slides</h1>', $wrappedS5);

        $slidy = $renderer->renderResource('templates/default', [], $context, null, 'slidy');
        foreach ([
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"',
            '<link rel="stylesheet" type="text/css" media="screen, projection, print"',
            'href="vendor/slidy/styles/slidy.css"',
            '<script src="vendor/slidy/scripts/slidy.js"',
            '<meta name="duration" content="12" />',
            '<div class="slide titlepage">',
            '<p class="subtitle">Native doctemplate packet</p>',
            '<p class="author">',
            'Migration bot<br/>Content editor',
        ] as $needle) {
            $t->contains($needle, $slidy);
        }

        $slideous = $renderer->renderResource('templates/default', [], $context, null, 'slideous');
        foreach ([
            'href="vendor/slideous/slideous.css"',
            '<script src="vendor/slideous/slideous.js"',
            '<div id="statusbar">',
            '<button id="prevslidebutton" title="previous slide">&laquo;</button>',
            '<span id="eos">&frac12;</span>',
            '<h1 class="subtitle">Native doctemplate packet</h1>',
            '<section><h2>Handoff</h2></section>',
        ] as $needle) {
            $t->contains($needle, $slideous);
        }

        $dzslides = $renderer->renderResource('templates/default', [], $context, null, 'dzslides');
        foreach ([
            '<!DOCTYPE html>',
            '<head lang="en" dir="ltr">',
            '<meta name="dcterms.date" content="2026-06-08">',
            '<link rel="stylesheet" href="review-slides.css">',
            '<section class="title">',
            '<h1 class="title">Legacy Slides</h1>',
            '<span class="author">Migration bot, Content editor</span> · <span class="institute">Review desk</span> · <span class="date">2026-06-08</span>',
            '<section id="legacy-TOC">',
            '<script>window.__dzslidesReview = true;</script>',
        ] as $needle) {
            $t->contains($needle, $dzslides);
        }

        $t->same('custom s5', $renderer->renderResource('templates/default', [
            'templates/default.s5' => 'custom $body$',
        ], [
            'body' => 's5',
        ], null, 's5'));
    },

    'renders pandoc default dzslides no-css fallback styles from upstream template' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $dzslides = $renderer->renderResource('templates/default', [], [
            'lang' => 'en',
            'dir' => 'ltr',
            'pagetitle' => 'DZSlides No CSS Packet',
            'title' => 'DZSlides Native Defaults',
            'subtitle' => 'WordPress review deck',
            'author' => ['Migration bot'],
            'institute' => ['Review desk'],
            'date' => '2026-06-09',
            'body' => '<section><h2>Imported body</h2><figure><img src="review.png"><figcaption>Review image</figcaption></figure></section>',
            'dzslides-core' => '<script>window.__dzslidesNoCss = true;</script>',
        ], null, 'dzslides+smart');

        foreach ([
            "<link href='https://fonts.googleapis.com/css?family=Oswald' rel='stylesheet'>",
            '/* A section is a slide. It\'s size is 800x600, and this will never change */',
            'counter-increment: slideidx;',
            'content: counter(slideidx, decimal-leading-zero);',
            '.view head > title {',
            'h1, h2 {',
            'blockquote:before {',
            'content: open-quote;',
            'figure > img, figure > video {',
            'width: 100%; height: 100%;',
            'footer {',
            '-moz-transition: left 400ms linear 0s;',
            '.view section[aria-selected] {',
            '.incremental > *[aria-selected] ~ * { opacity: 0; }',
            '#progress-bar {',
            '<h1 class="title">DZSlides Native Defaults</h1>',
            '<span class="author">Migration bot</span> · <span class="institute">Review desk</span> · <span class="date">2026-06-09</span>',
            '<script>window.__dzslidesNoCss = true;</script>',
        ] as $needle) {
            $t->contains($needle, $dzslides);
        }

        $t->same(false, str_contains($dzslides, '<link rel="stylesheet" href="review-slides.css">'));
    },

    'renders bounded pandoc default office and epub template resources' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $openxml = $renderer->renderResource('templates/default', [], [
            'title' => 'Batch 42 Review',
            'subtitle' => 'DOCX metadata packet',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-06',
            'abstract-title' => 'Abstract',
            'abstract' => '<w:p>Native office template review.</w:p>',
            'include-before' => ['<w:p>Before body</w:p>'],
            'toc' => '<w:sdt><w:tag w:val="toc"/></w:sdt>',
            'lof' => '<w:p>Figures</w:p>',
            'lot' => '<w:p>Tables</w:p>',
            'body' => '<w:p>Imported body.</w:p>',
            'include-after' => ['<w:p>After body</w:p>'],
            'sectpr' => '<w:sectPr/>',
        ], null, 'docx');

        foreach ([
            'Batch 42 Review',
            'DOCX metadata packet',
            'Migration bot',
            'Content editor',
            '2026-06-06',
            'Abstract',
            '<w:p>Native office template review.</w:p>',
            '<w:p>Before body</w:p>',
            '<w:sdt><w:tag w:val="toc"/></w:sdt>',
            '<w:p>Figures</w:p>',
            '<w:p>Tables</w:p>',
            '<w:p>Imported body.</w:p>',
            '<w:p>After body</w:p>',
            '<w:sectPr/>',
        ] as $needle) {
            $t->contains($needle, $openxml);
        }

        $opendocument = $renderer->renderResource('templates/default', [], [
            'automatic-styles' => '<office:automatic-styles/>',
            'header-includes' => ['<text:p>Header include</text:p>'],
            'title' => '<text:h>ODT Review</text:h>',
            'subtitle' => '<text:p>OpenDocument packet</text:p>',
            'author' => ['<text:p>Migration bot</text:p>'],
            'date' => '<text:p>2026-06-06</text:p>',
            'abstract' => '<text:p>Native ODT template review.</text:p>',
            'include-before' => ['<text:p>Before ODT body</text:p>'],
            'toc' => true,
            'toc-title' => '<text:p>Contents</text:p>',
            'body' => '<text:p>Imported ODT body.</text:p>',
            'include-after' => ['<text:p>After ODT body</text:p>'],
        ], null, 'odt');

        foreach ([
            '<office:automatic-styles/>',
            '<text:p>Header include</text:p>',
            '<text:h>ODT Review</text:h>',
            '<text:p>OpenDocument packet</text:p>',
            '<text:p>Migration bot</text:p>',
            '<text:p>2026-06-06</text:p>',
            '<text:p>Native ODT template review.</text:p>',
            '<text:p>Before ODT body</text:p>',
            '<text:p>Contents</text:p>',
            '<text:p>Imported ODT body.</text:p>',
            '<text:p>After ODT body</text:p>',
        ] as $needle) {
            $t->contains($needle, $opendocument);
        }

        $epub = $renderer->renderResource('templates/default', [], [
            'titlepage' => true,
            'title' => [
                ['type' => 'main', 'text' => 'EPUB Review'],
                'Fallback EPUB Title',
            ],
            'subtitle' => 'Navigation packet',
            'author' => ['Migration bot'],
            'creator' => [
                ['text' => 'Content editor'],
            ],
            'publisher' => 'WordPress Migration',
            'date' => '2026-06-06',
            'rights' => 'Internal review only',
            'abstract-title' => 'Abstract',
            'abstract' => 'Native EPUB titlepage template review.',
        ], null, 'epub');

        foreach ([
            '# EPUB Review',
            '# Fallback EPUB Title',
            'Navigation packet',
            'Migration bot',
            'Content editor',
            'WordPress Migration',
            '2026-06-06',
            'Internal review only',
            'Abstract',
            'Native EPUB titlepage template review.',
        ] as $needle) {
            $t->contains($needle, $epub);
        }

        $epubBody = $renderer->renderResource('templates/default.epub3', [], [
            'include-before' => ['<section>Before EPUB body</section>'],
            'body' => '<section>Imported EPUB body.</section>',
            'include-after' => ['<section>After EPUB body</section>'],
        ]);

        $t->contains('<section>Before EPUB body</section>', $epubBody);
        $t->contains('<section>Imported EPUB body.</section>', $epubBody);
        $t->contains('<section>After EPUB body</section>', $epubBody);

        $epub2TitlePage = $renderer->renderResource('templates/default', [], [
            'titlepage' => true,
            'pagetitle' => 'Legacy EPUB Review',
            'lang' => 'en',
            'dir' => 'ltr',
            'csl-css' => true,
            'highlighting-css' => 'code{color:crimson;}',
            'css' => ['epub.css'],
            'header-includes' => ['<meta name="review" content="legacy" />'],
            'title' => [
                ['text' => 'Title Text Without Type'],
                'Fallback Title',
            ],
            'subtitle' => 'Legacy navigation packet',
            'author' => ['Migration bot'],
            'creator' => [
                ['role' => 'editor', 'text' => 'Content editor'],
            ],
            'publisher' => 'WordPress Migration',
            'date' => '2026-06-08',
            'rights' => 'Internal review only',
            'abstract-title' => 'Abstract',
            'abstract' => '<p>Legacy EPUB titlepage template review.</p>',
        ], null, 'epub2+smart');

        foreach ([
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
            '<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" dir="ltr">',
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
            '<meta http-equiv="Content-Style-Type" content="text/css" />',
            '<title>Legacy EPUB Review</title>',
            '<style type="text/css">',
            'div.csl-entry',
            'code{color:crimson;}',
            '<link rel="stylesheet" type="text/css" href="epub.css" />',
            '<meta name="review" content="legacy" />',
            '<body>',
            '<h1 class="">Title Text Without Type</h1>',
            '<h1 class="title">Fallback Title</h1>',
            '<p class="subtitle">Legacy navigation packet</p>',
            '<p class="author">Migration bot</p>',
            '<p class="editor">Content editor</p>',
            '<p class="publisher">WordPress Migration</p>',
            '<p class="date">2026-06-08</p>',
            '<div class="rights">Internal review only</div>',
            '<div class="abstract-title">Abstract</div>',
            '<p>Legacy EPUB titlepage template review.</p>',
        ] as $needle) {
            $t->contains($needle, $epub2TitlePage);
        }
        $t->same(false, str_contains($epub2TitlePage, 'epub:type='));

        $epub2Cover = $renderer->renderResource('templates/default.epub2', [], [
            'coverpage' => true,
            'cover-image-width' => 640,
            'cover-image-height' => 960,
            'cover-image' => 'cover.jpg',
        ]);
        foreach ([
            '<body id="cover">',
            '<div id="cover-image">',
            'viewBox="0 0 640 960"',
            'xlink:href="../media/cover.jpg"',
        ] as $needle) {
            $t->contains($needle, $epub2Cover);
        }

        $epub2Body = $renderer->renderResource('templates/default.epub2', [], [
            'include-before' => ['<section>Before legacy EPUB body</section>'],
            'body' => '<section>Imported legacy EPUB body.</section>',
            'include-after' => ['<section>After legacy EPUB body</section>'],
        ]);
        $t->contains('<section>Before legacy EPUB body</section>', $epub2Body);
        $t->contains('<section>Imported legacy EPUB body.</section>', $epub2Body);
        $t->contains('<section>After legacy EPUB body</section>', $epub2Body);

        $t->same('custom openxml', $renderer->renderResource('templates/default', [
            'templates/default.openxml' => 'custom $body$',
        ], [
            'body' => 'openxml',
        ], null, 'docx'));
        $t->same('custom epub2', $renderer->renderResource('templates/default', [
            'templates/default.epub2' => 'custom $body$',
        ], [
            'body' => 'epub2',
        ], null, 'epub2'));
    },

    'renders bounded pandoc default icml template resource and alias' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $icml = $renderer->renderResource('templates/default', [], [
            'title-prefix' => 'WordPress Import',
            'pagetitle' => 'ICML Review Packet',
            'charStyles' => '<CharacterStyle Self="CharacterStyle/ReviewCode" Name="ReviewCode" />',
            'parStyles' => '<ParagraphStyle Self="ParagraphStyle/ReviewPara" Name="ReviewPara" />',
            'objectStyles' => '<ObjectStyle Self="ObjectStyle/ReviewFrame" Name="ReviewFrame" />',
            'body' => '<ParagraphStyleRange AppliedParagraphStyle="ParagraphStyle/ReviewPara"><CharacterStyleRange AppliedCharacterStyle="CharacterStyle/ReviewCode"><Content>Native ICML body.</Content></CharacterStyleRange></ParagraphStyleRange>',
            'hyperlinks' => '<Hyperlink Self="Hyperlink/review" Name="Review link" />',
        ], null, 'icml');

        foreach ([
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<?aid SnippetType="InCopyInterchange"?>',
            '<Document DOMVersion="8.0" Self="pandoc_doc">',
            '<RootCharacterStyleGroup Self="pandoc_character_styles">',
            '<CharacterStyle Self="$ID/NormalCharacterStyle" Name="Default" />',
            '<CharacterStyle Self="CharacterStyle/ReviewCode" Name="ReviewCode" />',
            '<RootParagraphStyleGroup Self="pandoc_paragraph_styles">',
            '<ParagraphStyle Self="$ID/NormalParagraphStyle" Name="$ID/NormalParagraphStyle"',
            '<ParagraphStyle Self="ParagraphStyle/ReviewPara" Name="ReviewPara" />',
            '<RootObjectStyleGroup Self="pandoc_object_styles">',
            '<ObjectStyle Self="ObjectStyle/ReviewFrame" Name="ReviewFrame" />',
            'StoryTitle="WordPress Import – ICML Review Packet"',
            '<StoryPreference OpticalMarginAlignment="true" OpticalMarginSize="12" />',
            '<!-- body needs to be non-indented, otherwise code blocks are indented too far -->',
            '<ParagraphStyleRange AppliedParagraphStyle="ParagraphStyle/ReviewPara">',
            '<Content>Native ICML body.</Content>',
            '<Hyperlink Self="Hyperlink/review" Name="Review link" />',
            '</Document>',
        ] as $needle) {
            $t->contains($needle, $icml);
        }

        $direct = $renderer->renderResource('templates/default.icml', [], [
            'pagetitle' => 'Direct ICML Review',
            'body' => '<Content>Direct ICML body.</Content>',
        ]);
        $t->contains('StoryTitle="Direct ICML Review"', $direct);
        $t->contains('<Content>Direct ICML body.</Content>', $direct);
        $t->same(false, str_contains($direct, '<RootObjectStyleGroup Self="pandoc_object_styles">'));

        $t->same('custom icml', $renderer->renderResource('templates/default', [
            'templates/default.icml' => 'custom $body$',
        ], [
            'body' => 'icml',
        ], null, 'icml'));
    },

    'renders bounded pandoc default docbook5 template resource and alias' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $article = $renderer->renderResource('templates/default', [], [
            'article' => true,
            'title' => 'Batch 42 Review',
            'subtitle' => 'DocBook metadata packet',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-07',
            'abstract' => '<para>Native DocBook default handoff.</para>',
            'include-before' => ['<section><title>Before import</title></section>'],
            'body' => '<section><title>Imported body</title></section>',
            'include-after' => ['<section><title>After import</title></section>'],
        ], null, 'docbook');

        foreach ([
            '<article xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.0" xml:lang="en">',
            '<title>Batch 42 Review</title>',
            '<subtitle>DocBook metadata packet</subtitle>',
            '<author>',
            'Migration bot',
            'Content editor',
            '<date>2026-06-07</date>',
            '<abstract>',
            '<para>Native DocBook default handoff.</para>',
            '<section><title>Before import</title></section>',
            '<section><title>Imported body</title></section>',
            '<section><title>After import</title></section>',
            '</article>',
        ] as $needle) {
            $t->contains($needle, $article);
        }

        $chapter = $renderer->renderResource('templates/default.docbook5', [], [
            'title' => 'Chapter Review',
            'body' => '<section><title>Chapter body</title></section>',
        ]);
        $t->contains('<chapter xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" version="5.0" xml:lang="en">', $chapter);
        $t->contains('<section><title>Chapter body</title></section>', $chapter);
        $t->contains('</chapter>', $chapter);
        $t->same(false, str_contains($chapter, '<article '));

        $t->same('custom docbook', $renderer->renderResource('templates/default', [
            'templates/default.docbook5' => 'custom $body$',
        ], [
            'body' => 'docbook',
        ], null, 'docbook'));
    },

    'renders bounded pandoc default docbook4 template resource and mathml branch' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $docbook4 = $renderer->renderResource('templates/default', [], [
            'title' => 'DocBook 4 Review',
            'author' => ['Migration bot', 'Content editor'],
            'date' => '2026-06-08',
            'include-before' => ['<section><title>Before DocBook 4 body</title></section>'],
            'body' => '<para>DocBook 4 body.</para>',
            'include-after' => ['<section><title>After DocBook 4 body</title></section>'],
        ], null, 'docbook4');

        foreach ([
            '<?xml version="1.0" encoding="utf-8" ?>',
            '<!DOCTYPE article PUBLIC "-//OASIS//DTD DocBook XML V4.5//EN"',
            '"http://www.oasis-open.org/docbook/xml/4.5/docbookx.dtd">',
            '<article>',
            '<articleinfo>',
            '<title>DocBook 4 Review</title>',
            '<authorgroup>',
            '<author>',
            'Migration bot',
            'Content editor',
            '<date>2026-06-08</date>',
            '<section><title>Before DocBook 4 body</title></section>',
            '<para>DocBook 4 body.</para>',
            '<section><title>After DocBook 4 body</title></section>',
            '</article>',
        ] as $needle) {
            $t->contains($needle, $docbook4);
        }
        $t->same(false, str_contains($docbook4, 'xmlns="http://docbook.org/ns/docbook"'));
        $t->same(false, str_contains($docbook4, '<info>'));

        $mathml = $renderer->renderResource('templates/default.docbook4', [], [
            'mathml' => true,
            'title' => 'MathML DocBook 4 Review',
            'body' => '<para><inlineequation><math/></inlineequation></para>',
        ]);
        $t->contains('<!DOCTYPE article PUBLIC "-//OASIS//DTD DocBook EBNF Module V1.1CR1//EN"', $mathml);
        $t->contains('"http://www.oasis-open.org/docbook/xml/mathml/1.1CR1/dbmathml.dtd">', $mathml);
        $t->contains('<title>MathML DocBook 4 Review</title>', $mathml);
        $t->contains('<para><inlineequation><math/></inlineequation></para>', $mathml);

        $t->same('custom docbook4', $renderer->renderResource('templates/default', [
            'templates/default.docbook4' => 'custom $body$',
        ], [
            'body' => 'docbook4',
        ], null, 'docbook4'));

        $docbook5 = $renderer->renderResource('templates/default', [], [
            'article' => true,
            'title' => 'DocBook 5 Still Separate',
            'body' => '<section><title>DocBook 5 body</title></section>',
        ], null, 'docbook');
        $t->contains('xmlns="http://docbook.org/ns/docbook"', $docbook5);
        $t->contains('<section><title>DocBook 5 body</title></section>', $docbook5);
        $t->same(false, str_contains($docbook5, 'docbookx.dtd'));
    },

    'renders bounded pandoc default jats template resources and aliases' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $jats = $renderer->renderResource('templates/default', [], [
            'xml-stylesheet' => 'review.xsl',
            'journal' => [
                'publisher-id' => 'wp-port',
                'title' => 'WordPress Migration Review',
                'publisher-name' => 'Port Libs',
            ],
            'article' => [
                'type' => 'review-article',
                'doi' => '10.5555/review',
                'heading' => 'Review Queue',
                'categories' => ['migration', 'wordpress'],
                'funding-statement' => 'No external funding.',
            ],
            'title' => 'Batch 42 Review',
            'subtitle' => 'Native JATS handoff',
            'author' => [
                [
                    'orcid' => '0000-0002-1825-0097',
                    'surname' => 'Editor',
                    'given-names' => 'Ada',
                    'affiliation' => ['1'],
                    'email' => 'ada@example.test',
                    'cor-id' => '1',
                ],
            ],
            'affiliation' => [
                [
                    'id' => '1',
                    'department' => 'Migration',
                    'organization' => 'Port Libs',
                    'ror' => 'https://ror.org/03yrm5c26',
                    'city' => 'Remote',
                    'country' => 'United States',
                    'country-code' => 'US',
                ],
            ],
            'date' => [
                'type' => 'reviewed',
                'day' => '07',
                'month' => '06',
                'year' => '2026',
                'iso-8601' => '2026-06-07',
            ],
            'copyright' => [
                'statement' => ['Copyright 2026 Port Libs'],
                'year' => ['2026'],
                'holder' => ['Port Libs'],
            ],
            'license' => [
                [
                    'type' => 'internal-review',
                    'link' => 'https://example.test/license',
                    'text' => 'Internal review license.',
                ],
            ],
            'abstract' => '<p>Native JATS template review.</p>',
            'tags' => ['migration', 'jats'],
            'notes' => '<fn><p>Reviewer note.</p></fn>',
            'body' => '<sec><title>Imported Body</title><p>Converted content.</p></sec>',
            'back' => '<ref-list><title>References</title></ref-list>',
            'floats-group' => '<fig id="fig-review"/>',
        ], null, 'jats');

        foreach ([
            '<?xml version="1.0" encoding="utf-8" ?>',
            '<?xml-stylesheet type="text/xsl" href="review.xsl"?>',
            '<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Archiving and Interchange DTD v1.2 20190208//EN"',
            '<article xmlns:mml="http://www.w3.org/1998/Math/MathML" xmlns:xlink="http://www.w3.org/1999/xlink" dtd-version="1.2" article-type="review-article">',
            '<journal-id journal-id-type="publisher-id">wp-port</journal-id>',
            '<journal-title>WordPress Migration Review</journal-title>',
            '<issn></issn>',
            '<publisher-name>Port Libs</publisher-name>',
            '<article-id pub-id-type="doi">10.5555/review</article-id>',
            '<subject>migration</subject>',
            '<article-title>Batch 42 Review</article-title>',
            '<subtitle>Native JATS handoff</subtitle>',
            '<contrib-id contrib-id-type="orcid">0000-0002-1825-0097</contrib-id>',
            '<surname>Editor</surname>',
            '<given-names>Ada</given-names>',
            '<xref ref-type="aff" rid="aff-1"/>',
            '<email>ada@example.test</email>',
            '<aff id="aff-1">',
            '<institution content-type="dept">Migration</institution>',
            '<institution>Port Libs</institution>',
            '<institution-id institution-id-type="ROR">https://ror.org/03yrm5c26</institution-id>',
            '<country country="US">United States</country>',
            '<pub-date date-type="reviewed" publication-format="electronic" iso-8601-date="2026-06-07">',
            '<license license-type="internal-review">',
            '<ali:license_ref xmlns:ali="http://www.niso.org/schemas/ali/1.0/">https://example.test/license</ali:license_ref>',
            '<license-p>Internal review license.</license-p>',
            '<abstract>',
            '<p>Native JATS template review.</p>',
            '<kwd>migration</kwd>',
            '<funding-statement>No external funding.</funding-statement>',
            '<notes><fn><p>Reviewer note.</p></fn></notes>',
            '<sec><title>Imported Body</title><p>Converted content.</p></sec>',
            '<ref-list><title>References</title></ref-list>',
            '<floats-group>',
            '<fig id="fig-review"/>',
        ] as $needle) {
            $t->contains($needle, $jats);
        }

        $publishing = $renderer->renderResource('templates/default.jats_publishing', [], [
            'journal' => ['publisher-name' => 'Port Libs'],
            'title' => 'Publishing Review',
            'body' => '<p>Publishing body.</p>',
        ]);
        $t->contains('<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Publishing DTD v1.2 20190208//EN"', $publishing);
        $t->contains('<article-title>Publishing Review</article-title>', $publishing);
        $t->contains('<p>Publishing body.</p>', $publishing);

        $articleAuthoring = $renderer->renderResource('templates/default.jats_articleauthoring', [], [
            'article' => ['type' => 'brief-report'],
            'title' => 'Article Authoring Review',
            'author' => [
                [
                    'surname' => 'Reviewer',
                    'given-names' => 'Nia',
                    'affiliation' => [
                        [
                            'id' => 'desk',
                            'organization' => 'Editorial Desk',
                            'country' => 'United States',
                        ],
                    ],
                ],
            ],
            'abstract' => '<p>Authoring abstract.</p>',
            'body' => '<p>Authoring body.</p>',
        ]);
        $t->contains('<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Article Authoring DTD v1.2 20190208//EN"', $articleAuthoring);
        $t->contains('<article-title>Article Authoring Review</article-title>', $articleAuthoring);
        $t->contains('<aff id="aff-desk">', $articleAuthoring);
        $t->contains('<institution>Editorial Desk</institution>', $articleAuthoring);
        $t->contains('<p>Authoring body.</p>', $articleAuthoring);

        $t->same('custom jats', $renderer->renderResource('templates/default', [
            'templates/default.jats_archiving' => 'custom $body$',
        ], [
            'body' => 'jats',
        ], null, 'jats'));
        $partialOverride = $renderer->renderResource('templates/default.jats_archiving', [
            'templates/article.jats_publishing' => 'partial override $body$',
        ], [
            'body' => 'jats',
        ]);
        $t->contains('<!DOCTYPE article PUBLIC "-//NLM//DTD JATS (Z39.96) Journal Archiving and Interchange DTD v1.2 20190208//EN"', $partialOverride);
        $t->contains('partial override jats', $partialOverride);
        $t->same(false, str_contains($partialOverride, '<article-title>'));
    },

    'renders bounded pandoc default typst template resource' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $typst = $renderer->renderResource('templates/default', [], [
            'table-caption-position' => 'bottom',
            'figure-caption-position' => 'top',
            'highlighting-definitions' => '#let hl = text',
            'smart' => false,
            'header-includes' => ['#set text(fill: navy)'],
            'title' => 'Batch 42 Review',
            'subtitle' => 'Typst metadata packet',
            'author' => [
                ['name' => 'Migration bot', 'affiliation' => 'Migration Desk', 'email' => 'bot@example.test'],
                'Content editor',
            ],
            'keywords' => ['migration', 'wordpress', 'review'],
            'date' => '2026-06-06',
            'lang' => 'en',
            'region' => 'US',
            'abstract-title' => 'Abstract',
            'abstract' => 'Native Typst template review.',
            'thanks' => 'Internal migration packet',
            'margin' => [
                'x' => '1.25in',
                'y' => '1in',
            ],
            'papersize' => 'a4',
            'mainfont' => 'Atkinson Hyperlegible',
            'fontsize' => '11pt',
            'mathfont' => ['New Computer Modern Math'],
            'codefont' => ['JetBrains Mono'],
            'linestretch' => '1.15',
            'section-numbering' => '1.1',
            'page-numbering' => '1',
            'linkcolor' => 'blue',
            'citecolor' => 'green',
            'filecolor' => 'purple',
            'columns' => 2,
            'include-before' => ['#block[Reviewer queue]'],
            'toc' => true,
            'toc-depth' => 3,
            'body' => '#heading[Imported Typst body]',
            'citations' => true,
            'nocite-ids' => ['doe2024'],
            'csl' => 'apa.csl',
            'bibliography' => ['refs.bib', 'archive.bib'],
            'full-bibliography' => true,
            'include-after' => ['#block[Done]'],
        ], null, 'typst');

        foreach ([
            '#let horizontalrule = line(start: (25%,0%), end: (75%,0%))',
            '#let content-to-string(content) = {',
            '#let conf(',
            '#show figure.where(kind: table): set figure.caption(position: bottom)',
            '#show figure.where(kind: image): set figure.caption(position: top)',
            '// syntax highlighting functions from skylighting:',
            '#let hl = text',
            '#set smartquote(enabled: false)',
            '#set text(fill: navy)',
            '#show: doc => conf(',
            'title: [Batch 42 Review],',
            'subtitle: [Typst metadata packet],',
            '(name: [Migration bot], affiliation: [Migration Desk], email: [bot@example.test]),',
            '(name: [Content editor], affiliation: "", email: ""),',
            'keywords: (migration,wordpress,review),',
            'date: [2026-06-06],',
            'lang: "en",',
            'region: "US",',
            'abstract-title: [Abstract],',
            'abstract: [Native Typst template review.],',
            'thanks: [Internal migration packet],',
            'margin: (x: 1.25in,y: 1in,),',
            'paper: "a4",',
            'font: ("Atkinson Hyperlegible",),',
            'fontsize: 11pt,',
            'mathfont: ("New Computer Modern Math",),',
            'codefont: ("JetBrains Mono",),',
            'linestretch: 1.15,',
            'sectionnumbering: "1.1",',
            'pagenumbering: "1",',
            'linkcolor: [blue],',
            'citecolor: [green],',
            'filecolor: [purple],',
            'cols: 2,',
            '#block[Reviewer queue]',
            '#outline(title: auto, depth: 3);',
            '#heading[Imported Typst body]',
            '#cite(label("doe2024"), form: none)',
            '#set bibliography(style: "apa.csl")',
            '#bibliography(("refs.bib","archive.bib"), full: true)',
            '#block[Done]',
        ] as $needle) {
            $t->contains($needle, $typst);
        }

        $imported = $renderer->renderResource('templates/default.typst', [], [
            'template' => 'custom-review.typst',
            'body' => '#heading[Imported body]',
        ]);
        $t->contains('#import "custom-review.typst": conf', $imported);
        $t->same(false, str_contains($imported, '#let conf('));

        $t->same('custom typst', $renderer->renderResource('templates/default', [
            'templates/default.typst' => 'custom $body$',
        ], [
            'body' => 'typst',
        ], null, 'typst'));
    },

    'renders pandoc typst definitions default resource fallback' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $definitions = $renderer->renderResource('templates/definitions.typst', [], []);
        foreach ([
            "// Some definitions presupposed by pandoc's typst output.",
            '#let horizontalrule = [',
            '  #line(start: (25%,0%), end: (75%,0%))',
            '#let endnote(num, contents) = [',
            '  #stack(dir: ltr, spacing: 3pt, super[#num], contents)',
        ] as $needle) {
            $t->contains($needle, $definitions);
        }
        $t->same(false, str_contains($definitions, '$'));

        $t->same($definitions, $renderer->renderResource('definitions.typst', [], []));

        $fallback = $renderer->renderResource('templates/review', [
            'templates/review.typst' => <<<'TYPST'
#let review = [
${ definitions.typst() }
]
#show: review
TYPST,
        ], [], null, 'typst');
        foreach ([
            '#let review = [',
            '#let horizontalrule = [',
            '#let endnote(num, contents) = [',
            '#show: review',
        ] as $needle) {
            $t->contains($needle, $fallback);
        }
        $t->same(false, str_contains($fallback, 'Missing doctemplate partial'));
        $t->same(1, substr_count($fallback, '#let horizontalrule = ['));
        $t->same(1, substr_count($fallback, '#let endnote(num, contents) = ['));

        $t->same('#let horizontalrule = [overridden]', $renderer->renderResource('templates/review', [
            'templates/review.typst' => '${ definitions.typst() }',
            'templates/definitions.typst' => '#let horizontalrule = [overridden]',
        ], [], null, 'typst'));
    },

    'renders pandoc default resources as partial fallbacks inside custom templates' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $typst = $renderer->renderResource('templates/wrapper', [
            'templates/wrapper.typst' => <<<'TYPST'
#let review = [
${ default() }
]
TYPST,
        ], [
            'title' => 'Wrapped Typst Review',
            'body' => '#heading[Wrapped Typst body]',
        ], null, 'typst');

        $t->contains('#let conf(', $typst);
        $t->contains('title: [Wrapped Typst Review],', $typst);
        $t->contains('#heading[Wrapped Typst body]', $typst);
        $t->contains('doc,', $typst);
        $t->same(false, str_contains($typst, 'Missing doctemplate partial'));

        $html = $renderer->renderResource('templates/review', [
            'templates/review.html' => <<<'HTML'
<article>
<style>
${ styles.html() }
</style>
${ default.html5() }
</article>
HTML,
        ], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Wrapped HTML Review',
            'body' => '<p>Wrapped HTML body.</p>',
            'document-css' => true,
            'csl-css' => true,
            'csl-entry-spacing' => '0.25em',
        ], null, 'html');

        $t->contains('/* Default styles provided by pandoc.', $html);
        $t->contains('/* CSS for citations */', $html);
        $t->contains('margin-bottom: 0.25em;', $html);
        $t->contains('<title>Wrapped HTML Review</title>', $html);
        $t->contains('<p>Wrapped HTML body.</p>', $html);

        $custom = $renderer->renderResource('templates/review', [
            'templates/review.html' => '${ styles.html() }',
            'templates/styles.html' => '/* custom local style */',
        ], [], null, 'html');

        $t->same('/* custom local style */', $custom);
    },

    'renders pandoc default partial fallbacks by basename for nested resource paths' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $html = $renderer->renderResource('templates/review.html', [
            'templates/review.html' => <<<'HTML'
<article>
<style>
${ components/styles.html() }
</style>
<section>${ fragments/default.plain() }</section>
</article>
HTML,
        ], [
            'document-css' => true,
            'mainfont' => 'Atkinson Hyperlegible',
            'csl-css' => true,
            'csl-entry-spacing' => '0.75em',
            'body' => 'Plain fallback body',
        ]);

        $t->contains('/* Default styles provided by pandoc.', $html);
        $t->contains('font-family: Atkinson Hyperlegible;', $html);
        $t->contains('/* CSS for citations */', $html);
        $t->contains('margin-bottom: 0.75em;', $html);
        $t->contains('<section>Plain fallback body</section>', $html);

        $custom = $renderer->renderResource('templates/review.html', [
            'templates/review.html' => '${ components/styles.html() }',
            'templates/components/styles.html' => '/* custom component style */',
        ], []);

        $t->same('/* custom component style */', $custom);
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('${ components/styles.html() }', []));
    },

    'prefers pandoc user-data default partial resources before bundled defaults' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $userData = $renderer->renderResource('reports/review.html', [
            'reports/review.html' => <<<'HTML'
<article>
<section>${ default.plain() }</section>
<section>${ default.markdown() }</section>
</article>
HTML,
            'wp-data/templates/default.plain' => 'user plain: $body$',
            'wp-data/templates/default.markdown' => 'user markdown: $body$',
        ], [
            'body' => 'User data body',
        ], 'wp-data');

        $t->same(implode("\n", [
            '<article>',
            '<section>user plain: User data body</section>',
            '<section>user markdown: User data body</section>',
            '</article>',
        ]), $userData);

        $mainTemplateDirectory = $renderer->renderResource('reports/review.html', [
            'reports/review.html' => '<section>${ default.plain() }</section>',
            'reports/default.plain' => 'main plain: $body$',
            'wp-data/templates/default.plain' => 'user plain: $body$',
        ], [
            'body' => 'Main body',
        ], 'wp-data');
        $t->same('<section>main plain: Main body</section>', $mainTemplateDirectory);

        $bundled = $renderer->renderResource('reports/review.html', [
            'reports/review.html' => '<section>${ default.plain() }</section>',
        ], [
            'body' => 'Bundled body',
        ], 'wp-data');
        $t->same('<section>Bundled body</section>', $bundled);
    },

    'prefers pandoc user-data default template resources before bundled defaults' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $userHtmlDefault = $renderer->renderResource('templates/default', [
            'wp-data/templates/default.html5' => '<article class="user-default">$body$ ${ styles.html() }</article>',
            'wp-data/templates/styles.html' => '/* user default styles */',
        ], [
            'body' => 'User data body',
        ], 'wp-data', 'html');
        $t->same('<article class="user-default">User data body /* user default styles */</article>', $userHtmlDefault);

        $extensionQualified = $renderer->renderResource('templates/default', [
            'wp-data/templates/default.html5' => '<article class="extension-user-default">$body$</article>',
        ], [
            'body' => 'Extension user body',
        ], 'wp-data', 'html+smart');
        $t->same('<article class="extension-user-default">Extension user body</article>', $extensionQualified);

        $explicitExtension = $renderer->renderResource('templates/default.markdown', [
            'wp-data/templates/default.markdown' => 'user markdown: $body$',
        ], [
            'body' => 'Markdown body',
        ], 'wp-data');
        $t->same('user markdown: Markdown body', $explicitExtension);

        $mainTemplateDirectory = $renderer->renderResource('templates/default', [
            'templates/default.html5' => '<article class="main-default">$body$</article>',
            'wp-data/templates/default.html5' => '<article class="user-default">$body$</article>',
        ], [
            'body' => 'Main body',
        ], 'wp-data', 'html');
        $t->same('<article class="main-default">Main body</article>', $mainTemplateDirectory);

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->renderResource('/templates/default.html5', [
            'wp-data/templates/default.html5' => '<article>$body$</article>',
        ], [
            'body' => 'Absolute body',
        ], 'wp-data'));
    },

    'renders pandoc default template resources by basename outside templates directory' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $html = $renderer->renderResource('review-packets/default.html5', [], [
            'pandoc-version' => '3.7.0',
            'pagetitle' => 'Basename Fallback Review',
            'body' => '<p>Basename fallback body.</p>',
        ]);

        $t->contains('<!DOCTYPE html>', $html);
        $t->contains('<title>Basename Fallback Review</title>', $html);
        $t->contains('<p>Basename fallback body.</p>', $html);

        $markdown = $renderer->renderResource('review-packets/default.markdown', [], [
            'body' => 'Basename Markdown fallback',
        ]);
        $t->same("Basename Markdown fallback\n", $markdown);

        $custom = $renderer->renderResource('review-packets/default.html5', [
            'review-packets/default.html5' => '<article>$body$</article>',
        ], [
            'body' => 'Custom basename resource wins',
        ]);
        $t->same('<article>Custom basename resource wins</article>', $custom);

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->renderResource('/review-packets/default.html5', [], [
            'body' => 'Absolute paths do not use bundled fallback',
        ]));
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

    'renders pandoc doctemplate filesystem resources with rooted partial discovery' => static function (TestRunner $t) use ($makeTemplateTree, $removeTemplateTree): void {
        $root = $makeTemplateTree([
            'review-packets/review.html' => <<<'HTML'
<article>
${ components/review-header() }
<section>
${ warnings:components/warning-row()[
] }
</section>
${ footer() }
</article>
HTML,
            'review-packets/components/review-header.html' => '<header><h1>$title$</h1><p>$reviewSources/uppercase[, ]$</p></header>' . "\n",
            'review-packets/components/warning-row.html' => '<p data-source="$it.source$">$it.message$</p>' . "\n",
            'review-packets/summary.html' => 'Summary: $~$media links layout status$~$',
            'wp-data/templates/footer.html' => '<footer>$reviewer$</footer>' . "\n",
        ]);

        try {
            $renderer = new DocTemplate();
            $output = $renderer->renderFilesystemResource('review-packets/review', $root, [
                'title' => 'Batch 42 Review',
                'reviewer' => 'Migration desk',
                'reviewSources' => ['media', 'links', 'layout'],
                'warnings' => [
                    ['source' => 'docx', 'message' => 'Imported heading'],
                    ['source' => 'odt', 'message' => 'Styled paragraph'],
                ],
            ], 'wp-data', 'html');

            $t->same(implode("\n", [
                '<article>',
                '<header><h1>Batch 42 Review</h1><p>MEDIA, LINKS, LAYOUT</p></header>',
                '<section>',
                '<p data-source="docx">Imported heading</p>',
                '<p data-source="odt">Styled paragraph</p>',
                '</section>',
                '<footer>Migration desk</footer>',
                '</article>',
            ]), $output);

            $t->same(implode("\n", [
                'Summary: media links',
                'layout status',
            ]), $renderer->renderFilesystemResourceWrapped('review-packets/summary', $root, [], 20, null, 'html'));

            $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderFilesystemResource('../review.html', $root, []));
            $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderFilesystemResource('/review.html', $root, []));
            $t->throws(\InvalidArgumentException::class, static fn (): string => $renderer->renderFilesystemResource('review-packets/review.html', $root, [], '../outside'));
        } finally {
            $removeTemplateTree($root);
        }
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

    'renders colon-qualified pandoc doctemplate metadata variables and applied partials' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('packets/review.html', [
            'packets/review.html' => <<<'HTML'
<article>
Font: $style:font-name$
Link: $link.xlink:href$
Families: $for(style:family)$[$style:family.style:name$/$it.style:display-name$]$sep$, $endfor$
Cards: ${ style:family:components/style-row()[ | ] }
Next: ${ style:family/rest:components/style-row()/uppercase }
</article>
HTML,
            'packets/components/style-row.html' => '$style:family.style:name$=$it.style:font-name$',
        ], [
            'style:font-name' => 'Atkinson Hyperlegible',
            'link' => [
                'xlink:href' => 'Pictures/cover.png',
            ],
            'style:family' => [
                [
                    'style:name' => 'Heading_20_1',
                    'style:display-name' => 'Heading 1',
                    'style:font-name' => 'Alegreya',
                ],
                [
                    'style:name' => 'BodyText',
                    'style:display-name' => 'Body Text',
                    'style:font-name' => 'Atkinson Hyperlegible',
                ],
            ],
        ]);

        $t->same(implode("\n", [
            '<article>',
            'Font: Atkinson Hyperlegible',
            'Link: Pictures/cover.png',
            'Families: [Heading_20_1/Heading 1], [BodyText/Body Text]',
            'Cards: Heading_20_1=Alegreya | BodyText=Atkinson Hyperlegible',
            'Next: BODYTEXT=ATKINSON HYPERLEGIBLE',
            '</article>',
        ]), $output);
    },

    'renders pandoc doctemplate digit-leading child metadata keys' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('packets/review.html', [
            'packets/review.html' => <<<'HTML'
<article>
Model: $article.3d-model$
Revision: $article.2026-review.status$
Checks: $for(checks)$$it.1st-pass$:$it.2nd-pass$$sep$; $endfor$
Cards: ${ article.assets.360-view:components/asset-card()[ | ] }
</article>
HTML,
            'packets/components/asset-card.html' => '$article.assets.360-view.name$=$it.1st-pass$',
        ], [
            'article' => [
                '3d-model' => 'Cover mesh',
                '2026-review' => ['status' => 'queued'],
                'assets' => [
                    '360-view' => [
                        ['name' => 'spin-front', '1st-pass' => 'ok'],
                        ['name' => 'spin-back', '1st-pass' => 'review'],
                    ],
                ],
            ],
            'checks' => [
                ['1st-pass' => 'media-ok', '2nd-pass' => 'link-review'],
                ['1st-pass' => 'layout-ok', '2nd-pass' => 'publish-ready'],
            ],
        ]);

        $t->same(implode("\n", [
            '<article>',
            'Model: Cover mesh',
            'Revision: queued',
            'Checks: media-ok:link-review; layout-ok:publish-ready',
            'Cards: spin-front=ok | spin-back=review',
            '</article>',
        ]), $output);
    },

    'renders pandoc doctemplate digit-leading top-level metadata keys' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('packets/review.html', [
            'packets/review.html' => <<<'HTML'
<article>
Packet: $2026-review$
Spin: $360-view.label$
Model: $3d-model$
Stages: $for(2026-stages)$$it.1st-pass$$sep$; $endfor$
Cards: ${ 2026-stages:components/stage-row()[ | ] }
$if(404-status)$Status: $404-status$$endif$
</article>
HTML,
            'packets/components/stage-row.html' => '$2026-stages.1st-pass$=$it.2nd-pass$',
        ], [
            '2026-review' => 'Migration packet',
            '360-view' => ['label' => 'Spin review'],
            '3d-model' => 'Cover mesh',
            '2026-stages' => [
                ['1st-pass' => 'media', '2nd-pass' => 'links'],
                ['1st-pass' => 'layout', '2nd-pass' => 'publish'],
            ],
            '404-status' => 'not found metadata',
        ]);

        $t->same(implode("\n", [
            '<article>',
            'Packet: Migration packet',
            'Spin: Spin review',
            'Model: Cover mesh',
            'Stages: media; layout',
            'Cards: media=links | layout=publish',
            'Status: not found metadata',
            '</article>',
        ]), $output);
    },

    'renders pandoc doctemplate child metadata keys named like controls' => static function (TestRunner $t): void {
        $output = (new DocTemplate())->renderResource('packets/review.html', [
            'packets/review.html' => <<<'HTML'
<article>
Root: $control.if$ / $control.elseif.sep$
Loop: $for(control.for)$[$it.it$:$control.for.if$:$it.else$:$it.endfor$]$sep$, $endfor$
Applied: ${ control.for:components/control-row()[ | ] }
</article>
HTML,
            'packets/components/control-row.html' => '$control.for.it$->$it.if$/$it.endif$',
        ], [
            'control' => [
                'if' => 'conditional metadata',
                'elseif' => ['sep' => 'branch metadata'],
                'for' => [
                    [
                        'it' => 'first-loop-item',
                        'if' => 'first-child-if',
                        'else' => 'first-child-else',
                        'endif' => 'first-child-endif',
                        'endfor' => 'first-child-endfor',
                    ],
                    [
                        'it' => 'second-loop-item',
                        'if' => 'second-child-if',
                        'else' => 'second-child-else',
                        'endif' => 'second-child-endif',
                        'endfor' => 'second-child-endfor',
                    ],
                ],
            ],
        ]);

        $t->same(implode("\n", [
            '<article>',
            'Root: conditional metadata / branch metadata',
            'Loop: [first-loop-item:first-child-if:first-child-else:first-child-endfor], [second-loop-item:second-child-if:second-child-else:second-child-endfor]',
            'Applied: first-loop-item->first-child-if/first-child-endif | second-loop-item->second-child-if/second-child-endif',
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

    'reports pandoc doctemplate parser failures with source line and column' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render("Title\n" . 'Broken: $title', ['title' => 'Review']),
            'Unclosed doctemplate $...$ directive at <template>:2:9',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render("Intro\r\n" . '$~$review packet', []),
            'Unclosed doctemplate breakable-space region at <template>:2:1',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render("Before\n" . '$if(title)$' . "\nMissing endif", ['title' => 'Review']),
            'Unclosed doctemplate if block at <template>:2:1',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render("Before\n" . '$for(items)$' . "\nMissing endfor", ['items' => ['x']]),
            'Unclosed doctemplate for block at <template>:2:1',
        );
    },

    'reports unclosed pandoc doctemplate pipe quote diagnostics at quote locations' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render("Intro\nBox: " . '$title/left 8 "[$', [
                'title' => 'Review',
            ]),
            'Unclosed doctemplate pipe quoted string at <template>:2:20',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/broken.html', [
                'review-packets/broken.html' => "Intro\n<p>" . '${ title/center 8 "<" " }',
            ], [
                'title' => 'Review',
            ]),
            'Unclosed doctemplate pipe quoted string at review-packets/broken.html:2:26',
        );
    },

    'rejects pandoc doctemplate conditional branches after else' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('$if(primary)$primary$else$fallback$else$second fallback$endif$', [
                'primary' => false,
            ]),
            'Unexpected doctemplate conditional branch else after else at <template>:1:35',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('$if(primary)$primary$else$fallback$elseif(secondary)$secondary$endif$', [
                'primary' => false,
                'secondary' => true,
            ]),
            'Unexpected doctemplate conditional branch elseif after else at <template>:1:35',
        );
    },

    'reports pandoc doctemplate resource and partial parser locations' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/review.html', [
                'review-packets/review.html' => "<article>\n" . '$title/no-such-pipe$' . "\n</article>",
            ], [
                'title' => 'Review',
            ]),
            'Unsupported doctemplate pipe no-such-pipe at review-packets/review.html:2:8',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/review.html', [
                'review-packets/review.html' => "<article>\n" . '${ components/footer() }' . "\n</article>",
                'review-packets/components/footer.html' => "<footer>\n" . '$if(show)$Missing endif',
            ], [
                'show' => true,
            ]),
            'Unclosed doctemplate if block at review-packets/components/footer.html:2:1',
        );
    },

    'reports pandoc doctemplate nested partial include provenance' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/review.html', [
                'review-packets/review.html' => "<article>\n" . '${ components/footer() }' . "\n</article>",
                'review-packets/components/footer.html' => "<footer>\n" . '${ components/missing-row() }',
            ], []),
            'Missing doctemplate partial components/missing-row at review-packets/components/footer.html:2:1 included from review-packets/review.html:2:1',
        );
    },

    'reports pandoc doctemplate parser columns by unicode characters' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('Résumé $title/no-such-pipe$', [
                'title' => 'Review',
            ]),
            'Unsupported doctemplate pipe no-such-pipe at <template>:1:15',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/review.html', [
                'review-packets/review.html' => "Résumé packet\n" . '${ components/footer() }',
                'review-packets/components/footer.html' => 'État $if(show)$broken',
            ], [
                'show' => true,
            ]),
            'Unclosed doctemplate if block at review-packets/components/footer.html:1:6',
        );
    },

    'reports unsupported pandoc doctemplate pipe names at pipe source locations' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('${ title/missing }', [
                'title' => 'Review',
            ]),
            'Unsupported doctemplate pipe missing at <template>:1:10',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('${ components/row()/unknown }', [], [
                'components/row' => 'Review',
            ]),
            'Unsupported doctemplate pipe unknown at <template>:1:21',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/broken.html', [
                'review-packets/broken.html' => "<p>\n" . '$title/unknown$',
            ], [
                'title' => 'Review',
            ]),
            'Unsupported doctemplate pipe unknown at review-packets/broken.html:2:8',
        );
    },

    'validates inactive pandoc doctemplate branches and empty loops before rendering' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('$if(title)$ok$else$$title/no-such-pipe$$endif$', [
                'title' => 'Review',
            ]),
            'Unsupported doctemplate pipe no-such-pipe at <template>:1:27',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('$if(title)$ok$else$${ missing() }$endif$', [
                'title' => 'Review',
            ]),
            'Missing doctemplate partial missing at <template>:1:20',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('$for(items)$${ missing() }$endfor$', [
                'items' => [],
            ]),
            'Missing doctemplate partial missing at <template>:1:13',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('$if(title)$ok$else$${ broken() }$endif$', [
                'title' => 'Review',
            ], [
                'broken' => '$if(show)$broken',
            ]),
            'Unclosed doctemplate if block at broken:1:1',
        );
    },

    'throws on unclosed pandoc doctemplate control blocks' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$if(title)$missing endif', ['title' => true]));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('$for(items)$missing endfor', ['items' => ['x']]));
    },

    'throws on unclosed pandoc doctemplate dollar directives while keeping escaped dollars' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same('Cost: $5', $renderer->render('Cost: $$5', []));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('Title: $title', ['title' => 'Review']));
        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('Cost: $5', []));
    },

    'reports unclosed pandoc doctemplate separators at bracket source locations' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('$reviewSources[ / $', [
                'reviewSources' => ['media', 'links'],
            ]),
            'Unclosed doctemplate separator at <template>:1:15',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('${ components/row()[; }', [], [
                'components/row' => '$it$',
            ]),
            'Unclosed doctemplate separator at <template>:1:20',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('${ warnings:components/warning-row()[, }', [
                'warnings' => [['source' => 'media']],
            ], [
                'components/warning-row' => '$it.source$',
            ]),
            'Unclosed doctemplate separator at <template>:1:37',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/review.html', [
                'review-packets/review.html' => "Intro\n" . '${ components/row()[; }',
            ], []),
            'Unclosed doctemplate separator at review-packets/review.html:2:20',
        );
    },

    'rejects malformed pandoc doctemplate separator payloads with extra closing brackets' => static function (TestRunner $t) use ($expectTemplateErrorContains): void {
        $renderer = new DocTemplate();

        $t->same('Sources: media[ links[ layout', $renderer->render('Sources: $sources[[ ]$', [
            'sources' => ['media', 'links', 'layout'],
        ]));

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('Sources: $sources[a]b]$', [
                'sources' => ['media', 'links'],
            ]),
            'Malformed doctemplate separator in sources[a]b] at <template>:1:10',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('Rows: ${ components/row()[a]b] }', [], [
                'components/row' => '$it$',
            ]),
            'Malformed doctemplate separator in components/row()[a]b] at <template>:1:7',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->render('Rows: ${ sources:components/row()[a]b] }', [
                'sources' => ['media'],
            ], [
                'components/row' => '$it$',
            ]),
            'Malformed doctemplate separator in components/row()[a]b] at <template>:1:7',
        );

        $expectTemplateErrorContains(
            $t,
            static fn (): string => $renderer->renderResource('review-packets/review.html', [
                'review-packets/review.html' => "Intro\nSources: " . '${ sources[a]b] }',
            ], [
                'sources' => ['media'],
            ]),
            'Malformed doctemplate separator in sources[a]b] at review-packets/review.html:2:10',
        );
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

    'matches upstream recursive bare partial loop sentinel newline handling' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();

        $t->same("(loop)\n", $renderer->render('$loop1()$' . "\n\n", [], [
            'loop1' => '$loop2()$' . "\n",
            'loop2' => '$loop1()$' . "\n",
        ]));
    },

    'returns pandoc doctemplate loop literal before resolving over-limit partials' => static function (TestRunner $t): void {
        $renderer = new DocTemplate();
        $partials = [];
        for ($index = 0; $index < 49; $index++) {
            $partials['review-depth-' . $index] = '${ review-depth-' . ($index + 1) . '() }';
        }
        $partials['review-depth-49'] = '${ missing-over-limit() }';

        $t->same('(loop)', $renderer->render('${ review-depth-0() }', [], $partials));

        $beforeLimit = $partials;
        unset($beforeLimit['review-depth-48'], $beforeLimit['review-depth-49']);
        $beforeLimit['review-depth-48'] = '${ missing-before-limit() }';

        $t->throws(\UnexpectedValueException::class, static fn (): string => $renderer->render('${ review-depth-0() }', [], $beforeLimit));
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
