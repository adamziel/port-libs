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
];
