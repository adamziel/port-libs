<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$codeBlock = static fn (string $text, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_merge(['text' => $text], $attrs)
);

$formatProfiles = [
    'commonmark' => ['format' => 'commonmark'],
    'gfm' => ['format' => 'gfm'],
    'markdown strict' => ['format' => 'markdown_strict'],
    'markdown phpextra' => ['format' => 'markdown_phpextra'],
    'markdown mmd' => ['format' => 'markdown_mmd'],
];

$richAttributeCases = [
    '01 id and language class' => [
        $codeBlock('echo <alpha>;', ['id' => 'snippet', 'classes' => ['php']]),
        '<pre><code id="snippet" class="php">echo &lt;alpha&gt;;</code></pre>',
    ],
    '02 multi class language metadata' => [
        $codeBlock('echo beta;', ['classes' => ['php', 'numberLines']]),
        '<pre><code class="php numberLines">echo beta;</code></pre>',
    ],
    '03 data attribute only' => [
        $codeBlock('wp post list', ['attributes' => ['data-kind' => 'fixture']]),
        '<pre><code data-kind="fixture">wp post list</code></pre>',
    ],
    '04 id class and data start' => [
        $codeBlock('echo gamma;', [
            'id' => 'review-snippet',
            'classes' => ['php', 'numberLines'],
            'attributes' => ['data-startfrom' => '7'],
        ]),
        '<pre><code id="review-snippet" class="php numberLines" data-startfrom="7">echo gamma;</code></pre>',
    ],
    '05 quoted title is escaped' => [
        $codeBlock('echo delta;', [
            'id' => 'quoted',
            'classes' => ['bash'],
            'attributes' => ['title' => 'Review "snippet"'],
        ]),
        '<pre><code id="quoted" class="bash" title="Review &quot;snippet&quot;">echo delta;</code></pre>',
    ],
    '06 html attribute source merges with classes' => [
        $codeBlock('echo epsilon;', [
            'htmlAttributes' => ['id' => 'html-src', 'class' => 'sourceCode'],
            'classes' => ['php'],
            'attributes' => ['data-source' => 'html'],
        ]),
        '<pre><code id="html-src" class="sourceCode php" data-source="html">echo epsilon;</code></pre>',
    ],
    '07 unsafe event attribute is dropped' => [
        $codeBlock('echo zeta;', ['attributes' => ['onclick' => 'bad()', 'data-safe' => '1']]),
        '<pre><code data-safe="1">echo zeta;</code></pre>',
    ],
    '08 unsafe style url is dropped' => [
        $codeBlock('echo eta;', ['attributes' => ['style' => 'background:url(javascript:bad)', 'data-safe' => '1']]),
        '<pre><code data-safe="1">echo eta;</code></pre>',
    ],
    '09 safe role lang dir metadata' => [
        $codeBlock('echo theta;', ['attributes' => ['role' => 'note', 'lang' => 'en', 'dir' => 'ltr']]),
        '<pre><code role="note" lang="en" dir="ltr">echo theta;</code></pre>',
    ],
    '10 aria and xml language metadata' => [
        $codeBlock('echo iota;', ['attributes' => ['aria-label' => 'Source', 'xml:lang' => 'en-US']]),
        '<pre><code aria-label="Source" xml:lang="en-US">echo iota;</code></pre>',
    ],
    '11 empty attributes are omitted' => [
        $codeBlock('echo kappa;', ['id' => 'non-empty', 'attributes' => ['data-empty' => '', 'data-kind' => 'audit']]),
        '<pre><code id="non-empty" data-kind="audit">echo kappa;</code></pre>',
    ],
    '12 multiline code stays literal html content' => [
        $codeBlock("first <line>\nsecond & line", ['id' => 'multi', 'classes' => ['text']]),
        "<pre><code id=\"multi\" class=\"text\">first &lt;line&gt;\nsecond &amp; line</code></pre>",
    ],
    '13 quotes and apostrophes escape in code text' => [
        $codeBlock('echo "quoted" && echo \'single\';', ['id' => 'quotes']),
        '<pre><code id="quotes">echo &quot;quoted&quot; &amp;&amp; echo &#039;single&#039;;</code></pre>',
    ],
    '14 html class de-duplicates source classes' => [
        $codeBlock('echo lambda;', [
            'htmlAttributes' => ['class' => 'php sourceCode'],
            'classes' => ['php', 'numberLines'],
        ]),
        '<pre><code class="php sourceCode numberLines">echo lambda;</code></pre>',
    ],
    '15 invalid info class uses html fallback' => [
        $codeBlock('echo mu;', ['classes' => ['language php']]),
        '<pre><code class="language php">echo mu;</code></pre>',
    ],
    '16 id only code block' => [
        $codeBlock('echo nu;', ['id' => 'id-only']),
        '<pre><code id="id-only">echo nu;</code></pre>',
    ],
];

$tests = [];

foreach ($formatProfiles as $formatLabel => $options) {
    foreach ($richAttributeCases as $caseLabel => $case) {
        $tests["maps upstream markdown writer code block html fallback {$formatLabel} {$caseLabel}"] =
            static function (TestRunner $t) use ($document, $case, $options): void {
                [$node, $expected] = $case;
                $markdown = (new MarkdownWriter($options))->write($document([$node]));

                $t->same($expected, $markdown);
                $t->contains('<pre><code', $markdown);
                $t->true(!str_contains($markdown, '```{'), 'Pandoc fenced attribute tuple must not leak into fallback output');
                $t->true(!str_contains($markdown, '~~~{'), 'Pandoc tilde fenced attribute tuple must not leak into fallback output');
            };
    }
}

$tests['keeps portable single language class as fenced info string under commonmark'] =
    static function (TestRunner $t) use ($document, $codeBlock): void {
        $markdown = (new MarkdownWriter(['format' => 'commonmark']))->write($document([
            $codeBlock('echo portable;', ['classes' => ['php']]),
        ]));

        $t->same("```php\necho portable;\n```", $markdown);
    };

$tests['keeps fenced code attributes when raw attribute extension is enabled'] =
    static function (TestRunner $t) use ($document, $codeBlock): void {
        $markdown = (new MarkdownWriter(['format' => 'commonmark+raw_attribute']))->write($document([
            $codeBlock('echo attributed;', ['id' => 'snippet', 'classes' => ['php'], 'attributes' => ['data-kind' => 'fixture']]),
        ]));

        $t->same("```{#snippet .php data-kind=\"fixture\"}\necho attributed;\n```", $markdown);
    };

$tests['keeps fenced code attributes when fenced code attribute extension is enabled'] =
    static function (TestRunner $t) use ($document, $codeBlock): void {
        $markdown = (new MarkdownWriter(['format' => 'gfm+fenced_code_attributes']))->write($document([
            $codeBlock('echo attributed;', ['id' => 'snippet', 'classes' => ['php']]),
        ]));

        $t->same("```{#snippet .php}\necho attributed;\n```", $markdown);
    };

return $tests;
