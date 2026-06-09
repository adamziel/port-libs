<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocTemplate;

return [
    'renders upstream doctemplates basic for loop conditionals and dollar escapes' => static function (TestRunner $t): void {
        $context = [
            'employee' => [
                ['name' => ['first' => 'John', 'last' => 'Doe']],
                ['name' => ['first' => 'Omar', 'last' => 'Smith'], 'salary' => '30000'],
                ['name' => ['first' => 'Sara', 'last' => 'Chen'], 'salary' => '60000'],
            ],
        ];
        $template = <<<'TEMPLATE'
$for(employee)$
Hi, $employee.name.first$. $if(employee.salary)$You make $$$employee.salary$.$else$No salary data.$endif$
$ endfor $
TEMPLATE;

        $t->same("Hi, John. No salary data.\nHi, Omar. You make $30000.\nHi, Sara. You make $60000.\n", PandocTemplate::renderString($template, $context));
    },
    'renders upstream doctemplates brace delimiters and it keyword' => static function (TestRunner $t): void {
        $context = [
            'employee' => [
                ['name' => ['first' => 'John', 'last' => 'Doe']],
                ['name' => ['first' => 'Omar', 'last' => 'Smith'], 'salary' => '30000'],
            ],
        ];
        $braced = <<<'TEMPLATE'
${ for(employee) }
Hi, ${employee.name.first}. ${ if(employee.salary) }You make $$${ employee.salary }.${ else }No salary data.${ endif }
${ endfor }
TEMPLATE;
        $withIt = <<<'TEMPLATE'
$for(employee)$
Hi, $it.name.first$. $if(it.salary)$You make $$$it.salary$.$else$No salary data.$endif$
$endfor$
TEMPLATE;

        $t->same("Hi, John. No salary data.\nHi, Omar. You make $30000.\n", PandocTemplate::renderString($braced, $context));
        $t->same("Hi, John. No salary data.\nHi, Omar. You make $30000.\n", PandocTemplate::renderString($withIt, $context));
    },
    'renders upstream doctemplates boolean interpolation and conditional truthiness' => static function (TestRunner $t): void {
        $template = <<<'TEMPLATE'
$foo$
$bar$
$if(foo)$XXX$else$YYY$endif$
$if(bar)$XXX$else$YYY$endif$
TEMPLATE;

        $t->same("true\nfalse\nXXX\nYYY", PandocTemplate::renderString($template, ['foo' => true, 'bar' => false]));
    },
    'renders upstream doctemplates map list null and empty conditional branches' => static function (TestRunner $t): void {
        $context = [
            'foo' => 1,
            'bar' => null,
            'baz' => ['a', 'b'],
            'bim' => ['zub' => 'sim'],
            'sup' => [['biz' => 'qux'], ['sax' => '']],
        ];
        $template = <<<'TEMPLATE'
${if(sup.sax)}
XXX
${else}
YYY
${endif}
${if(bar)}
BAR
${endif}
${if(bar)}BAR${endif}
${if(foo)}
FOO
${endif}
${if(baz)}
BAZ
${endif}
${if(bim)}
BIM
${endif}
${if(sup)}
SUP
${endif}
TEMPLATE;

        $t->same("YYY\n\nFOO\nBAZ\nBIM\nSUP\n", PandocTemplate::renderString($template, $context));
    },
    'renders upstream doctemplates elseif chain as nested conditionals' => static function (TestRunner $t): void {
        $context = [
            'bar' => null,
            'baz' => ['a', 'b'],
            'sup' => [['biz' => 'qux'], ['sax' => '']],
        ];
        $template = <<<'TEMPLATE'
$if(sup.sax)$
XXX
$elseif(baz)$
YYY
$else$
ZZZ
$endif$

$if(sup.sax)$
XXX
$elseif(baz.nonexist)$
YYY
$elseif(sup.sax)$
ZZZ
$else$
WWW
$endif$
TEMPLATE;

        $t->same("YYY\n\nWWW\n", PandocTemplate::renderString($template, $context));
    },
    'renders upstream doctemplates loop separators and omits missing iterations' => static function (TestRunner $t): void {
        $context = [
            'employee' => [
                ['name' => ['first' => 'John', 'last' => 'Doe'], 'salary' => '30000'],
                ['name' => ['first' => 'Omar', 'last' => 'Smith'], 'salary' => '60000'],
                ['name' => ['first' => 'Sara', 'last' => 'Chen']],
            ],
        ];
        $template = <<<'TEMPLATE'
$for(employee)$
$employee.name.first$ $employee.name.last$$sep$;
$endfor$


$for(employee)$$employee.salary$$sep$; $endfor$
---
$for(nonexistent)$

$endfor$
---
TEMPLATE;

        $t->same("John Doe;\nOmar Smith;\nSara Chen\n\n30000; 60000; \n---\n---", PandocTemplate::renderString($template, $context));
    },
    'renders upstream doctemplates values and removes one final newline from interpolations' => static function (TestRunner $t): void {
        $values = <<<'TEMPLATE'
$foo$
$bar$
$baz$
$bim$
$sup$
TEMPLATE;
        $finalNewline = <<<'TEMPLATE'
$for(employee)$
$employee.name$
$ endfor $
TEMPLATE;

        $t->same("1\n\nab\ntrue\ntruetrue", PandocTemplate::renderString($values, [
            'foo' => 1,
            'bar' => null,
            'baz' => ['a', 'b'],
            'bim' => ['zub' => 'sim'],
            'sup' => [['biz' => 'qux'], ['sax' => 2]],
        ]));
        $t->same("John\nSara\n\nOmar\n", PandocTemplate::renderString($finalNewline, [
            'employee' => [
                ['name' => "John\n"],
                ['name' => "Sara\n\n"],
                ['name' => 'Omar'],
            ],
        ]));
    },
    'renders upstream doctemplates comments and literal dollar escapes' => static function (TestRunner $t): void {
        $template = <<<'TEMPLATE'
$-- a comment
$--${foo} more comment
$$-- not a comment
a$-- comment
TEMPLATE;

        $t->same("$-- not a comment\na", PandocTemplate::renderString($template, ['foo' => 3]));
    },
    'renders upstream doctemplates nested loops with variable path rebinding' => static function (TestRunner $t): void {
        $template = <<<'TEMPLATE'
$-- see #15
$for(pages)$
/$pages.slug$
$for(pages.subpages)$
  /$pages.slug$/$pages.subpages.slug$
$endfor$
$endfor$
TEMPLATE;

        $t->same("/page-1\n  /page-1/subpage-1\n  /page-1/subpage-2\n/page-2\n  /page-2/subpage-1\n  /page-2/subpage-2\n", PandocTemplate::renderString($template, [
            'pages' => [
                [
                    'slug' => 'page-1',
                    'subpages' => [['slug' => 'subpage-1'], ['slug' => 'subpage-2']],
                ],
                [
                    'slug' => 'page-2',
                    'subpages' => [['slug' => 'subpage-1'], ['slug' => 'subpage-2']],
                ],
            ],
        ]));
    },
    'renders upstream doctemplates nested object loops' => static function (TestRunner $t): void {
        $template = <<<'TEMPLATE'
${ for(worksite.workers) }
${it.name.last}, ${it.name.first}
${ endfor }
TEMPLATE;

        $t->same("Doe, John\nSmith, Omar\nChen, Sara\n", PandocTemplate::renderString($template, [
            'worksite' => [
                'name' => 'canyon',
                'workers' => [
                    ['name' => ['first' => 'John', 'last' => 'Doe']],
                    ['name' => ['first' => 'Omar', 'last' => 'Smith']],
                    ['name' => ['first' => 'Sara', 'last' => 'Chen']],
                ],
            ],
        ]));
    },
    'renders upstream doctemplates literal separators and scalar pipes' => static function (TestRunner $t): void {
        $template = <<<'TEMPLATE'
$baz/uppercase[, ]$
$baz/pairs/reverse[, ]$
$digits/roman[ ]$
$digits/first$/$digits/last$/$digits/rest[, ]$/$digits/allbutlast[, ]$
$title/length$ $title/uppercase$ $title/lowercase$ $title/reverse$
TEMPLATE;

        $t->same("A, B\ntrue, true\ni v xx\n1/20/5, 20/1, 5\n6 REVIEW review weiveR", PandocTemplate::renderString($template, [
            'baz' => ['a', 'b'],
            'digits' => [1, 5, 20],
            'title' => 'Review',
        ]));
    },
    'renders wordpress import review handoff template without escaping interpolated values' => static function (TestRunner $t): void {
        $template = <<<'TEMPLATE'
---
title: $title$
needs_review: $needsReview$
---
$if(needsReview)$
<!-- wp:paragraph -->
<p>Review source: <a href="$source.url$">$source.label$</a></p>
<!-- /wp:paragraph -->
$endif$
$for(items)$
- $it.title$ ($it.status$)
$endfor$
TEMPLATE;

        $rendered = PandocTemplate::renderString($template, [
            'title' => 'Migration packet',
            'needsReview' => true,
            'source' => ['url' => 'https://example.test/export?post=42&raw=1', 'label' => 'original export'],
            'items' => [
                ['title' => 'Post 42', 'status' => 'ready'],
                ['title' => 'Media 7', 'status' => 'needs alt text'],
            ],
        ]);

        $t->contains("title: Migration packet\nneeds_review: true", $rendered);
        $t->contains('<p>Review source: <a href="https://example.test/export?post=42&raw=1">original export</a></p>', $rendered);
        $t->contains("- Post 42 (ready)\n- Media 7 (needs alt text)", $rendered);
    },
];
