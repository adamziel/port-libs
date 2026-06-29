<?php

declare(strict_types=1);

use PortLibs\Pandoc\MathTexConverter;

return [
    'promotes static texmath reader fixtures into plainmath conformance corpus' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $fixtures = [
            'test/reader/tex/quadratic_formula.test' => [
                'tex' => 'x=\\frac{-b\\pm\\sqrt{b^2-4ac}}{2a}',
                'fragments' => [
                    '<mi>x</mi><mo>=</mo><mfrac>',
                    '<mo>-</mo><mi>b</mi><mo>±</mo><msqrt><mrow><msup><mi>b</mi><mn>2</mn></msup><mo>-</mo><mn>4</mn><mi>a</mi><mi>c</mi></mrow></msqrt>',
                    '<mrow><mn>2</mn><mi>a</mi></mrow>',
                    '<annotation encoding="application/x-tex">x=\\frac{-b\\pm\\sqrt{b^2-4ac}}{2a}</annotation>',
                ],
            ],
            'test/reader/tex/simple_sum_formula.test' => [
                'tex' => '\\sum_{i=1}^100 x = \\frac{100*101}{2}',
                'fragments' => [
                    '<msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mn>100</mn></msubsup>',
                    '<mi>x</mi><mo>=</mo><mfrac><mrow><mn>100</mn><mo>*</mo><mn>101</mn></mrow><mn>2</mn></mfrac>',
                    '<annotation encoding="application/x-tex">\\sum_{i=1}^100 x = \\frac{100*101}{2}</annotation>',
                ],
            ],
            'test/reader/tex/binomial_coefficient.test' => [
                'tex' => '\\mathbf{C}(n,k) = \\mathbf{C}_k^n = {}_n\\mathbf{C}_k = \\binom{n}{k} = \\frac{n!}{k!\\,(n-k)!}',
                'fragments' => [
                    '<mstyle mathvariant="bold"><mi>𝐂</mi></mstyle><mo>(</mo><mi>n</mi><mo>,</mo><mi>k</mi><mo>)</mo>',
                    '<msubsup><mstyle mathvariant="bold"><mi>𝐂</mi></mstyle><mi>k</mi><mi>n</mi></msubsup>',
                    '<mrow><mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo></mrow>',
                    '<mspace width="0.1667em"></mspace><mo>(</mo><mi>n</mi><mo>-</mo><mi>k</mi><mo>)</mo><mo>!</mo>',
                    '<annotation encoding="application/x-tex">\\mathbf{C}(n,k) = \\mathbf{C}_k^n = {}_n\\mathbf{C}_k = \\binom{n}{k} = \\frac{n!}{k!\\,(n-k)!}</annotation>',
                ],
            ],
            'test/reader/tex/boxed.test' => [
                'tex' => '\\boxed{x^2 + y^2 + z^2}',
                'fragments' => [
                    '<menclose notation="box"><mrow><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><msup><mi>y</mi><mn>2</mn></msup><mo>+</mo><msup><mi>z</mi><mn>2</mn></msup></mrow></menclose>',
                    '<annotation encoding="application/x-tex">\\boxed{x^2 + y^2 + z^2}</annotation>',
                ],
            ],
            'test/reader/tex/phantom.test' => [
                'tex' => '\\left(\\phantom{\\frac{1}{2}}\\right)',
                'fragments' => [
                    '<mo fence="true" stretchy="true">(</mo><mphantom><mfrac><mn>1</mn><mn>2</mn></mfrac></mphantom><mo fence="true" stretchy="true">)</mo>',
                    '<annotation encoding="application/x-tex">\\left(\\phantom{\\frac{1}{2}}\\right)</annotation>',
                ],
            ],
            'test/reader/tex/stackrel.test' => [
                'tex' => 'u_n \\stackrel{w}{\\to} u',
                'fragments' => [
                    '<msub><mi>u</mi><mi>n</mi></msub><mover><mo>→</mo><mi>w</mi></mover><mi>u</mi>',
                    '<annotation encoding="application/x-tex">u_n \\stackrel{w}{\\to} u</annotation>',
                ],
            ],
            'test/reader/tex/substack.test' => [
                'tex' => '\\sum_{\\substack{0<i<m \\\\ 0<j<n}} P(i,j)',
                'fragments' => [
                    '<msub><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em">',
                    '<mtd><mn>0</mn><mo>&lt;</mo><mi>i</mi><mo>&lt;</mo><mi>m</mi></mtd>',
                    '<mtd><mn>0</mn><mo>&lt;</mo><mi>j</mi><mo>&lt;</mo><mi>n</mi></mtd>',
                    '<mi>P</mi><mo>(</mo><mi>i</mi><mo>,</mo><mi>j</mi><mo>)</mo>',
                    '<annotation encoding="application/x-tex">\\sum_{\\substack{0&lt;i&lt;m \\\\ 0&lt;j&lt;n}} P(i,j)</annotation>',
                ],
            ],
        ];

        foreach ($fixtures as $path => $fixture) {
            $mathml = $converter->texToMathMl($fixture['tex'], true);

            $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
            foreach ($fixture['fragments'] as $fragment) {
                $t->contains($fragment, $mathml);
            }
            $t->true(!str_contains($mathml, '<mi>\\'), $path . ' leaves no literal TeX command identifiers in MathML');
        }
    },
    'promotes additional texmath reader fixtures into plainmath conformance corpus' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $fixtures = [
            'test/reader/tex/choose.test' => [
                'tex' => 'a \\choose {b \\brace c + 2}',
                'fragments' => [
                    '<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>a</mi>',
                    '<mo fence="true" stretchy="true">{</mo><mfrac linethickness="0"><mi>b</mi><mrow><mi>c</mi><mo>+</mo><mn>2</mn></mrow></mfrac><mo fence="true" stretchy="true">}</mo>',
                    '<annotation encoding="application/x-tex">a \\choose {b \\brace c + 2}</annotation>',
                ],
            ],
            'test/reader/tex/genfrac.test' => [
                'tex' => '\\genfrac{\\{}{\\}}{0pt}{}{x}{y}',
                'fragments' => [
                    '<mo fence="true" stretchy="true">{</mo><mfrac linethickness="0"><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">}</mo>',
                    '<annotation encoding="application/x-tex">\\genfrac{\\{}{\\}}{0pt}{}{x}{y}</annotation>',
                ],
            ],
            'test/reader/tex/notin.test' => [
                'tex' => "x \\in y\n\\wedge\nx \\not\\in y",
                'fragments' => [
                    '<mi>x</mi><mo>∈</mo><mi>y</mi><mo>∧</mo><mi>x</mi><mo>∉</mo><mi>y</mi>',
                    "<annotation encoding=\"application/x-tex\">x \\in y\n\\wedge\nx \\not\\in y</annotation>",
                ],
            ],
            'test/reader/tex/cancel.test' => [
                'tex' => '\\boxed{\\cancel{x} + \\bcancel{y} = \\xcancel{z}}',
                'fragments' => [
                    '<menclose notation="box"><mrow><menclose notation="updiagonalstrike"><mi>x</mi></menclose><mo>+</mo>',
                    '<menclose notation="downdiagonalstrike"><mi>y</mi></menclose><mo>=</mo><menclose notation="updiagonalstrike downdiagonalstrike"><mi>z</mi></menclose>',
                    '<annotation encoding="application/x-tex">\\boxed{\\cancel{x} + \\bcancel{y} = \\xcancel{z}}</annotation>',
                ],
            ],
        ];

        foreach ($fixtures as $path => $fixture) {
            $mathml = $converter->texToMathMl($fixture['tex'], true);
            $dom = new \DOMDocument('1.0', 'UTF-8');

            $t->true(
                $dom->loadXML($mathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
                $path . ' emits well-formed MathML'
            );
            $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
            foreach ($fixture['fragments'] as $fragment) {
                $t->contains($fragment, $mathml);
            }
            $t->true(!str_contains($mathml, '<mi>\\'), $path . ' leaves no literal TeX command identifiers in MathML');
        }
    },
    'promotes texmath atom coercion fixtures into plainmath conformance corpus' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $fixtures = [
            'test/reader/tex/mathrel_text_coercion.test' => [
                'tex' => 'a > b \\mathrel{\\text{or}} a > c',
                'fragments' => [
                    '<mi>a</mi><mo>&gt;</mo><mi>b</mi><mrow data-tex-math-class="relation"><mtext>or</mtext></mrow><mi>a</mi><mo>&gt;</mo><mi>c</mi>',
                    '<annotation encoding="application/x-tex">a &gt; b \\mathrel{\\text{or}} a &gt; c</annotation>',
                ],
                'explicitAtoms' => [
                    'Rel' => ['or'],
                ],
            ],
            'test/reader/tex/mathbin_mathord_coercion.test' => [
                'tex' => 'x \\mathbin{*} y + \\mathord{+}',
                'fragments' => [
                    '<mi>x</mi><mrow data-tex-math-class="binary"><mo>*</mo></mrow><mi>y</mi><mo>+</mo><mrow data-tex-math-class="ordinary"><mo>+</mo></mrow>',
                    '<annotation encoding="application/x-tex">x \\mathbin{*} y + \\mathord{+}</annotation>',
                ],
                'explicitAtoms' => [
                    'Bin' => ['*'],
                    'Ord' => ['+'],
                ],
            ],
            'test/reader/tex/mathopen_mathclose_mathpunct_coercion.test' => [
                'tex' => '\\mathopen{[}x\\mathclose{]}\\mathpunct{,}y',
                'fragments' => [
                    '<mrow data-tex-math-class="open"><mo>[</mo></mrow><mi>x</mi><mrow data-tex-math-class="close"><mo>]</mo></mrow><mrow data-tex-math-class="punctuation"><mo>,</mo></mrow><mi>y</mi>',
                    '<annotation encoding="application/x-tex">\\mathopen{[}x\\mathclose{]}\\mathpunct{,}y</annotation>',
                ],
                'explicitAtoms' => [
                    'Open' => ['['],
                    'Close' => [']'],
                    'Pun' => [','],
                ],
            ],
            'test/reader/tex/mathop_styled_operator_name.test' => [
                'tex' => '\\mathop{\\mathrm{lim}} x_n',
                'fragments' => [
                    '<mrow data-tex-math-class="operator"><mstyle mathvariant="normal"><mrow><mi>l</mi><mi>i</mi><mi>m</mi></mrow></mstyle></mrow><msub><mi>x</mi><mi>n</mi></msub>',
                    '<annotation encoding="application/x-tex">\\mathop{\\mathrm{lim}} x_n</annotation>',
                ],
                'explicitAtoms' => [
                    'Op' => ['lim'],
                ],
            ],
        ];

        foreach ($fixtures as $path => $fixture) {
            $mathml = $converter->texToMathMl($fixture['tex'], true);
            $dom = new \DOMDocument('1.0', 'UTF-8');

            $t->true(
                $dom->loadXML($mathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
                $path . ' emits well-formed MathML'
            );
            $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
            foreach ($fixture['fragments'] as $fragment) {
                $t->contains($fragment, $mathml);
            }
            $t->true(!str_contains($mathml, '<mi>\\'), $path . ' leaves no literal TeX command identifiers in MathML');

            $explicitAtoms = [];
            foreach ($converter->texAtomCategorySummary($fixture['tex'], true)['atoms'] as $atom) {
                if ($atom['source'] === 'explicit-math-class') {
                    $explicitAtoms[$atom['category']][] = $atom['text'];
                }
            }

            foreach ($fixture['explicitAtoms'] as $category => $texts) {
                $t->same($texts, $explicitAtoms[$category] ?? [], $path . ' records explicit ' . $category . ' atom coercions');
            }
        }
    },
    'promotes unbraced texmath atom coercion tokens into plainmath conformance corpus' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $tex = '\\mathop\\sum_i + x \\mathrel= y \\mathbin+ z \\mathord+ \\mathopen( q \\mathclose) \\mathpunct, r';
        $mathml = $converter->texToMathMl($tex, true);
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $t->true(
            $dom->loadXML($mathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
            'unbraced atom coercion tokens emit well-formed MathML'
        );
        $t->contains('<msub><mrow data-tex-math-class="operator"><mo>∑</mo></mrow><mi>i</mi></msub>', $mathml);
        $t->contains('<mrow data-tex-math-class="relation"><mo>=</mo></mrow>', $mathml);
        $t->contains('<mrow data-tex-math-class="binary"><mo>+</mo></mrow>', $mathml);
        $t->contains('<mrow data-tex-math-class="ordinary"><mo>+</mo></mrow>', $mathml);
        $t->contains('<mrow data-tex-math-class="open"><mo>(</mo></mrow>', $mathml);
        $t->contains('<mrow data-tex-math-class="close"><mo>)</mo></mrow>', $mathml);
        $t->contains('<mrow data-tex-math-class="punctuation"><mo>,</mo></mrow>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathop\\sum_i + x \\mathrel= y \\mathbin+ z \\mathord+ \\mathopen( q \\mathclose) \\mathpunct, r</annotation>', $mathml);
        $t->true(!str_contains($mathml, '<mi>\\'), 'unbraced atom coercions leave no literal TeX command identifiers in MathML');

        $explicitAtoms = [];
        foreach ($converter->texAtomCategorySummary($tex, true)['atoms'] as $atom) {
            if ($atom['source'] === 'explicit-math-class') {
                $explicitAtoms[$atom['category']][] = $atom['text'];
            }
        }

        $t->same(['∑'], $explicitAtoms['Op'] ?? []);
        $t->same(['='], $explicitAtoms['Rel'] ?? []);
        $t->same(['+'], $explicitAtoms['Bin'] ?? []);
        $t->same(['+'], $explicitAtoms['Ord'] ?? []);
        $t->same(['('], $explicitAtoms['Open'] ?? []);
        $t->same([')'], $explicitAtoms['Close'] ?? []);
        $t->same([','], $explicitAtoms['Pun'] ?? []);
    },
];
