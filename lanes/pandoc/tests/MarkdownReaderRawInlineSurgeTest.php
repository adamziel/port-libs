<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return list<string>
 */
$rawHtmlInlines = static function (AstNode $paragraph): array {
    $raw = [];
    foreach ($paragraph->children as $child) {
        if ($child->type === 'raw_html_inline') {
            $raw[] = (string) $child->attr('html', '');
        }
    }

    return $raw;
};

$cases = [
    '01 span tag pair' => [
        'markdown' => '<span>review</span>',
        'raw' => ['<span>', '</span>'],
    ],
    '02 span double quoted attribute' => [
        'markdown' => '<span data-source="batch">review</span>',
        'raw' => ['<span data-source="batch">', '</span>'],
    ],
    '03 span single quoted greater-than attribute' => [
        'markdown' => '<span data-title=\'a > b\'>review</span>',
        'raw' => ['<span data-title=\'a > b\'>', '</span>'],
    ],
    '04 span double quoted greater-than attribute' => [
        'markdown' => '<span data-title="a > b">review</span>',
        'raw' => ['<span data-title="a > b">', '</span>'],
    ],
    '05 span unquoted attribute' => [
        'markdown' => '<span data-source=batch-1>review</span>',
        'raw' => ['<span data-source=batch-1>', '</span>'],
    ],
    '06 span multiple unquoted attributes' => [
        'markdown' => '<span class=review data-id=42>review</span>',
        'raw' => ['<span class=review data-id=42>', '</span>'],
    ],
    '07 span boolean attribute' => [
        'markdown' => '<span hidden>review</span>',
        'raw' => ['<span hidden>', '</span>'],
    ],
    '08 br open tag' => [
        'markdown' => '<br>',
        'raw' => ['<br>'],
    ],
    '09 br self-closing compact tag' => [
        'markdown' => '<br/>',
        'raw' => ['<br/>'],
    ],
    '10 br self-closing spaced tag' => [
        'markdown' => '<br />',
        'raw' => ['<br />'],
    ],
    '11 wbr void tag' => [
        'markdown' => '<wbr>',
        'raw' => ['<wbr>'],
    ],
    '12 img void tag with attributes' => [
        'markdown' => '<img src="x.png" alt="x">',
        'raw' => ['<img src="x.png" alt="x">'],
    ],
    '13 input boolean attribute' => [
        'markdown' => '<input type="checkbox" checked>',
        'raw' => ['<input type="checkbox" checked>'],
    ],
    '14 kbd tag pair' => [
        'markdown' => '<kbd>Ctrl</kbd>',
        'raw' => ['<kbd>', '</kbd>'],
    ],
    '15 samp tag pair' => [
        'markdown' => '<samp>output</samp>',
        'raw' => ['<samp>', '</samp>'],
    ],
    '16 var tag pair' => [
        'markdown' => '<var>x</var>',
        'raw' => ['<var>', '</var>'],
    ],
    '17 abbr title attribute' => [
        'markdown' => '<abbr title="HyperText">HTML</abbr>',
        'raw' => ['<abbr title="HyperText">', '</abbr>'],
    ],
    '18 time datetime attribute' => [
        'markdown' => '<time datetime="2026-06-15">today</time>',
        'raw' => ['<time datetime="2026-06-15">', '</time>'],
    ],
    '19 data value attribute' => [
        'markdown' => '<data value="42">answer</data>',
        'raw' => ['<data value="42">', '</data>'],
    ],
    '20 cite tag pair' => [
        'markdown' => '<cite>Source</cite>',
        'raw' => ['<cite>', '</cite>'],
    ],
    '21 q cite attribute' => [
        'markdown' => '<q cite="https://example.test">quote</q>',
        'raw' => ['<q cite="https://example.test">', '</q>'],
    ],
    '22 dfn id attribute' => [
        'markdown' => '<dfn id="term">term</dfn>',
        'raw' => ['<dfn id="term">', '</dfn>'],
    ],
    '23 ruby nested rt tags' => [
        'markdown' => '<ruby>kan<rt>reading</rt></ruby>',
        'raw' => ['<ruby>', '<rt>', '</rt>', '</ruby>'],
    ],
    '24 bdi direction attribute' => [
        'markdown' => '<bdi dir="rtl">source</bdi>',
        'raw' => ['<bdi dir="rtl">', '</bdi>'],
    ],
    '25 bdo direction attribute' => [
        'markdown' => '<bdo dir="rtl">abc</bdo>',
        'raw' => ['<bdo dir="rtl">', '</bdo>'],
    ],
    '26 mark tag pair' => [
        'markdown' => '<mark>flag</mark>',
        'raw' => ['<mark>', '</mark>'],
    ],
    '27 small tag pair' => [
        'markdown' => '<small>fine print</small>',
        'raw' => ['<small>', '</small>'],
    ],
    '28 sub tag pair' => [
        'markdown' => '<sub>2</sub>',
        'raw' => ['<sub>', '</sub>'],
    ],
    '29 sup tag pair' => [
        'markdown' => '<sup>3</sup>',
        'raw' => ['<sup>', '</sup>'],
    ],
    '30 ins datetime attribute' => [
        'markdown' => '<ins datetime="2026-06-15">added</ins>',
        'raw' => ['<ins datetime="2026-06-15">', '</ins>'],
    ],
    '31 del datetime attribute' => [
        'markdown' => '<del datetime="2026-06-15">removed</del>',
        'raw' => ['<del datetime="2026-06-15">', '</del>'],
    ],
    '32 u tag pair' => [
        'markdown' => '<u>under</u>',
        'raw' => ['<u>', '</u>'],
    ],
    '33 i tag pair' => [
        'markdown' => '<i>italic</i>',
        'raw' => ['<i>', '</i>'],
    ],
    '34 b tag pair' => [
        'markdown' => '<b>bold</b>',
        'raw' => ['<b>', '</b>'],
    ],
    '35 code tag pair with attribute' => [
        'markdown' => '<code data-lang="php">$x</code>',
        'raw' => ['<code data-lang="php">', '</code>'],
    ],
    '36 button type attribute' => [
        'markdown' => '<button type="button">Approve</button>',
        'raw' => ['<button type="button">', '</button>'],
    ],
    '37 label for attribute' => [
        'markdown' => '<label for="x">Label</label>',
        'raw' => ['<label for="x">', '</label>'],
    ],
    '38 select and option tags' => [
        'markdown' => '<select><option>One</option></select>',
        'raw' => ['<select>', '<option>', '</option>', '</select>'],
    ],
    '39 html comment' => [
        'markdown' => '<!-- reviewer note -->',
        'raw' => ['<!-- reviewer note -->'],
    ],
    '40 html comment with markdown-looking content' => [
        'markdown' => '<!-- reviewer *note* -->',
        'raw' => ['<!-- reviewer *note* -->'],
    ],
    '41 processing instruction compact' => [
        'markdown' => '<?review import?>',
        'raw' => ['<?review import?>'],
    ],
    '42 processing instruction with data' => [
        'markdown' => '<?review import data?>',
        'raw' => ['<?review import data?>'],
    ],
    '43 doctype declaration' => [
        'markdown' => '<!DOCTYPE html>',
        'raw' => ['<!DOCTYPE html>'],
    ],
    '44 custom declaration' => [
        'markdown' => '<!review data-source="batch">',
        'raw' => ['<!review data-source="batch">'],
    ],
    '45 cdata section' => [
        'markdown' => '<![CDATA[<source> & **raw**]]>',
        'raw' => ['<![CDATA[<source> & **raw**]]>'],
    ],
    '46 search tag pair' => [
        'markdown' => '<search data-x="1">custom</search>',
        'raw' => ['<search data-x="1">', '</search>'],
    ],
    '47 slot single quoted attribute' => [
        'markdown' => '<slot name=\'v\'>x</slot>',
        'raw' => ['<slot name=\'v\'>', '</slot>'],
    ],
    '48 mathml inline tags' => [
        'markdown' => '<math><mi>x</mi><mo>=</mo><mn>1</mn></math>',
        'raw' => ['<math>', '<mi>', '</mi>', '<mo>', '</mo>', '<mn>', '</mn>', '</math>'],
    ],
    '49 svg inline tags' => [
        'markdown' => '<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>',
        'raw' => ['<svg viewBox="0 0 1 1">', '<path d="M0 0"/>', '</svg>'],
    ],
    '50 json-looking single quoted attribute' => [
        'markdown' => '<span data-json=\'{"a":1}\'>json</span>',
        'raw' => ['<span data-json=\'{"a":1}\'>', '</span>'],
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader raw inline html surge ' . $name] = static function (TestRunner $t) use ($case, $rawHtmlInlines): void {
        $markdown = 'Lead ' . $case['markdown'] . ' trail';
        $document = (new MarkdownReader())->read($markdown);
        $paragraph = $document->children[0] ?? new AstNode('missing');

        $t->same('paragraph', $paragraph->type);
        $t->same($case['raw'], $rawHtmlInlines($paragraph));
        $blocks = (new WordPressBlockWriter())->write($document);
        foreach ($case['raw'] as $rawHtml) {
            $t->contains($rawHtml, $blocks);
        }
    };
}

return $tests;
