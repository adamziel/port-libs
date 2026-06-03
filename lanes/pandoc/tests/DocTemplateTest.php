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
];
