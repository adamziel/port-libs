<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return list<string>
 */
$blockTypes = static function (AstNode $document): array {
    return array_map(static fn (AstNode $node): string => $node->type, $document->children);
};

$cases = [
    '01 del basic split' => [
        'markdown' => '<del>removed</del>',
        'open' => '<del>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '02 del uppercase split' => [
        'markdown' => '<DEL>removed</DEL>',
        'open' => '<DEL>',
        'text' => 'removed',
        'close' => '</DEL>',
    ],
    '03 del cite attribute split' => [
        'markdown' => '<del cite="/changes/1">removed</del>',
        'open' => '<del cite="/changes/1">',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '04 del datetime attribute split' => [
        'markdown' => '<del datetime="2026-06-15">removed</del>',
        'open' => '<del datetime="2026-06-15">',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '05 del paired revision attributes split' => [
        'markdown' => '<del cite="/changes/1" datetime="2026-06-15">removed</del>',
        'open' => '<del cite="/changes/1" datetime="2026-06-15">',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '06 del single quoted attribute split' => [
        'markdown' => '<del cite=\'/changes/1\'>removed</del>',
        'open' => '<del cite=\'/changes/1\'>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '07 del double quoted greater-than attribute split' => [
        'markdown' => '<del data-title="a > b">removed</del>',
        'open' => '<del data-title="a > b">',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '08 del single quoted greater-than attribute split' => [
        'markdown' => '<del data-title=\'a > b\'>removed</del>',
        'open' => '<del data-title=\'a > b\'>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '09 del boolean attribute split' => [
        'markdown' => '<del hidden>removed</del>',
        'open' => '<del hidden>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '10 del unquoted attribute split' => [
        'markdown' => '<del data-id=42>removed</del>',
        'open' => '<del data-id=42>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '11 del json attribute split' => [
        'markdown' => '<del data-json=\'{"state":"a > b"}\'>removed</del>',
        'open' => '<del data-json=\'{"state":"a > b"}\'>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '12 del markdown content split' => [
        'markdown' => '<del>removed **claim**</del>',
        'open' => '<del>',
        'text' => 'removed claim',
        'close' => '</del>',
        'inlineTypes' => ['text', 'strong'],
    ],
    '13 del code content split' => [
        'markdown' => '<del>`old_code`</del>',
        'open' => '<del>',
        'text' => 'old_code',
        'close' => '</del>',
        'inlineTypes' => ['code'],
    ],
    '14 del entity content split' => [
        'markdown' => '<del>AT&amp;T</del>',
        'open' => '<del>',
        'text' => 'AT&T',
        'close' => '</del>',
    ],
    '15 del unquoted slash attribute split' => [
        'markdown' => '<del cite=/changes/1>removed</del>',
        'open' => '<del cite=/changes/1>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '16 del indented split' => [
        'markdown' => '   <del>removed</del>',
        'open' => '<del>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '17 del spaced close split' => [
        'markdown' => '<del>removed</del   >',
        'open' => '<del>',
        'text' => 'removed',
        'close' => '</del   >',
    ],
    '18 del trailing spaces split' => [
        'markdown' => '<del>removed</del>   ',
        'open' => '<del>',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '19 del quote with slash-greater text split' => [
        'markdown' => '<del data-frag="/>">removed</del>',
        'open' => '<del data-frag="/>">',
        'text' => 'removed',
        'close' => '</del>',
    ],
    '20 del empty content split' => [
        'markdown' => '<del></del>',
        'open' => '<del>',
        'text' => null,
        'close' => '</del>',
    ],
    '21 button basic split' => [
        'markdown' => '<button>Approve</button>',
        'open' => '<button>',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '22 button type attribute split' => [
        'markdown' => '<button type="button">Approve</button>',
        'open' => '<button type="button">',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '23 button double quoted greater-than attribute split' => [
        'markdown' => '<button data-title="a > b">Approve</button>',
        'open' => '<button data-title="a > b">',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '24 button single quoted greater-than attribute split' => [
        'markdown' => '<button data-title=\'a > b\'>Approve</button>',
        'open' => '<button data-title=\'a > b\'>',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '25 button disabled attribute split' => [
        'markdown' => '<button disabled>Approve</button>',
        'open' => '<button disabled>',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '26 button class attribute split' => [
        'markdown' => '<button class="primary review">Approve</button>',
        'open' => '<button class="primary review">',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '27 button markdown content split' => [
        'markdown' => '<button>Approve **now**</button>',
        'open' => '<button>',
        'text' => 'Approve now',
        'close' => '</button>',
        'inlineTypes' => ['text', 'strong'],
    ],
    '28 button link content split' => [
        'markdown' => '<button>Open [review](https://example.test)</button>',
        'open' => '<button>',
        'text' => 'Open review',
        'close' => '</button>',
        'inlineTypes' => ['text', 'link'],
    ],
    '29 button code content split' => [
        'markdown' => '<button>`save`</button>',
        'open' => '<button>',
        'text' => 'save',
        'close' => '</button>',
        'inlineTypes' => ['code'],
    ],
    '30 button entity content split' => [
        'markdown' => '<button>Save &amp; close</button>',
        'open' => '<button>',
        'text' => 'Save & close',
        'close' => '</button>',
    ],
    '31 button uppercase split' => [
        'markdown' => '<BUTTON>Approve</BUTTON>',
        'open' => '<BUTTON>',
        'text' => 'Approve',
        'close' => '</BUTTON>',
    ],
    '32 button indented split' => [
        'markdown' => '   <button>Approve</button>',
        'open' => '<button>',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '33 button trailing spaces split' => [
        'markdown' => '<button>Approve</button>   ',
        'open' => '<button>',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '34 button spaced close split' => [
        'markdown' => '<button>Approve</button   >',
        'open' => '<button>',
        'text' => 'Approve',
        'close' => '</button   >',
    ],
    '35 button quote with slash-greater text split' => [
        'markdown' => '<button data-frag="/>">Approve</button>',
        'open' => '<button data-frag="/>">',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '36 button unquoted hyphen attribute split' => [
        'markdown' => '<button data-state=needs-review>Approve</button>',
        'open' => '<button data-state=needs-review>',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '37 button aria attribute split' => [
        'markdown' => '<button aria-label="Approve source">Approve</button>',
        'open' => '<button aria-label="Approve source">',
        'text' => 'Approve',
        'close' => '</button>',
    ],
    '38 button name value split' => [
        'markdown' => '<button name="action" value="publish">Publish</button>',
        'open' => '<button name="action" value="publish">',
        'text' => 'Publish',
        'close' => '</button>',
    ],
    '39 button empty content split' => [
        'markdown' => '<button></button>',
        'open' => '<button>',
        'text' => null,
        'close' => '</button>',
    ],
    '40 button plain text split' => [
        'markdown' => '<button>Review choice</button>',
        'open' => '<button>',
        'text' => 'Review choice',
        'close' => '</button>',
    ],
    '41 ins basic split' => [
        'markdown' => '<ins>added</ins>',
        'open' => '<ins>',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '42 ins cite attribute split' => [
        'markdown' => '<ins cite="/changes/2">added</ins>',
        'open' => '<ins cite="/changes/2">',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '43 ins datetime attribute split' => [
        'markdown' => '<ins datetime="2026-06-15">added</ins>',
        'open' => '<ins datetime="2026-06-15">',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '44 ins double quoted greater-than attribute split' => [
        'markdown' => '<ins data-title="a > b">added</ins>',
        'open' => '<ins data-title="a > b">',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '45 ins single quoted greater-than attribute split' => [
        'markdown' => '<ins data-title=\'a > b\'>added</ins>',
        'open' => '<ins data-title=\'a > b\'>',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '46 ins boolean attribute split' => [
        'markdown' => '<ins hidden>added</ins>',
        'open' => '<ins hidden>',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '47 ins unquoted attribute split' => [
        'markdown' => '<ins data-id=43>added</ins>',
        'open' => '<ins data-id=43>',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '48 ins uppercase split' => [
        'markdown' => '<INS>added</INS>',
        'open' => '<INS>',
        'text' => 'added',
        'close' => '</INS>',
    ],
    '49 ins indented split' => [
        'markdown' => '   <ins>added</ins>',
        'open' => '<ins>',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '50 ins trailing spaces split' => [
        'markdown' => '<ins>added</ins>   ',
        'open' => '<ins>',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '51 ins spaced close split' => [
        'markdown' => '<ins>added</ins   >',
        'open' => '<ins>',
        'text' => 'added',
        'close' => '</ins   >',
    ],
    '52 ins markdown content split' => [
        'markdown' => '<ins>added **copy**</ins>',
        'open' => '<ins>',
        'text' => 'added copy',
        'close' => '</ins>',
        'inlineTypes' => ['text', 'strong'],
    ],
    '53 ins code content split' => [
        'markdown' => '<ins>`new_code`</ins>',
        'open' => '<ins>',
        'text' => 'new_code',
        'close' => '</ins>',
        'inlineTypes' => ['code'],
    ],
    '54 ins entity content split' => [
        'markdown' => '<ins>R&amp;D</ins>',
        'open' => '<ins>',
        'text' => 'R&D',
        'close' => '</ins>',
    ],
    '55 ins empty content split' => [
        'markdown' => '<ins></ins>',
        'open' => '<ins>',
        'text' => null,
        'close' => '</ins>',
    ],
    '56 ins json attribute split' => [
        'markdown' => '<ins data-json=\'{"state":"a > b"}\'>added</ins>',
        'open' => '<ins data-json=\'{"state":"a > b"}\'>',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '57 ins class attribute split' => [
        'markdown' => '<ins class="review added">added</ins>',
        'open' => '<ins class="review added">',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '58 ins quote with slash-greater text split' => [
        'markdown' => '<ins data-frag="/>">added</ins>',
        'open' => '<ins data-frag="/>">',
        'text' => 'added',
        'close' => '</ins>',
    ],
    '59 ins link content split' => [
        'markdown' => '<ins>Open [review](https://example.test)</ins>',
        'open' => '<ins>',
        'text' => 'Open review',
        'close' => '</ins>',
        'inlineTypes' => ['text', 'link'],
    ],
    '60 ins emphasis content split' => [
        'markdown' => '<ins>added *copy*</ins>',
        'open' => '<ins>',
        'text' => 'added copy',
        'close' => '</ins>',
        'inlineTypes' => ['text', 'emph'],
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream pandoc raw html single-line container surge ' . $name] =
        static function (TestRunner $t) use ($case, $blockTypes): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $hasPlain = $case['text'] !== null;
            $expectedTypes = $hasPlain ? ['raw_html', 'plain', 'raw_html'] : ['raw_html', 'raw_html'];
            $closeIndex = $hasPlain ? 2 : 1;

            $t->same($expectedTypes, $blockTypes($document), $case['markdown']);
            $t->same($case['open'], $document->children[0]->attr('html'), $case['markdown'] . ' opener');

            if ($hasPlain) {
                $plain = $document->children[1] ?? new AstNode('missing');
                $t->same($case['text'], $plain->attr('text'), $case['markdown'] . ' plain text');
                if (isset($case['inlineTypes'])) {
                    $t->same(
                        $case['inlineTypes'],
                        array_map(static fn (AstNode $node): string => $node->type, $plain->children),
                        $case['markdown'] . ' inline types'
                    );
                }
            }

            $t->same($case['close'], $document->children[$closeIndex]->attr('html'), $case['markdown'] . ' closer');
            $blocks = (new WordPressBlockWriter())->write($document);
            $t->contains($case['open'], $blocks, $case['markdown'] . ' wordpress opener');
            $t->contains($case['close'], $blocks, $case['markdown'] . ' wordpress closer');
        };
}

$tests['records upstream pandoc raw html single-line container surge mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(60, count($cases));
    };

return $tests;
