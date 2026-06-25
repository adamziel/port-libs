<?php

declare(strict_types=1);

return [
    'mathml' => [
        [
            'id' => 'script-super',
            'family' => 'scripts',
            'tex' => 'x^2',
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/03.test subset',
                'writer' => 'test/writer/mml/03.test subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><msup><mi>x</mi><mn>2</mn></msup><annotation encoding="application/x-tex">x^2</annotation></semantics></math>',
        ],
        [
            'id' => 'sqrt-subscript',
            'family' => 'roots-and-scripts',
            'tex' => '\sqrt{x_2}',
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/tokens.test subset',
                'writer' => 'test/writer/mml/tokens.test subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><msqrt><msub><mi>x</mi><mn>2</mn></msub></msqrt><annotation encoding="application/x-tex">\sqrt{x_2}</annotation></semantics></math>',
        ],
        [
            'id' => 'display-fraction-root',
            'family' => 'fractions-and-roots',
            'tex' => '\frac{x^2}{\sqrt{y_1}}',
            'display' => true,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/01.test structure subset',
                'writer' => 'test/writer/mml/01.test structure subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mfrac><msup><mi>x</mi><mn>2</mn></msup><msqrt><msub><mi>y</mi><mn>1</mn></msub></msqrt></mfrac><annotation encoding="application/x-tex">\frac{x^2}{\sqrt{y_1}}</annotation></semantics></math>',
        ],
        [
            'id' => 'boxed-enclosure',
            'family' => 'enclosures',
            'tex' => '\boxed{x^2 + y^2 + z^2}',
            'display' => true,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/boxed.test',
                'writer' => 'test/writer/mml/boxed.test',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><menclose notation="box"><mrow><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><msup><mi>y</mi><mn>2</mn></msup><mo>+</mo><msup><mi>z</mi><mn>2</mn></msup></mrow></menclose><annotation encoding="application/x-tex">\boxed{x^2 + y^2 + z^2}</annotation></semantics></math>',
        ],
        [
            'id' => 'pmatrix-two-by-two',
            'family' => 'arrays-and-delimiters',
            'tex' => '\begin{pmatrix}1 & 2 \\\\ 3 & 4\end{pmatrix}',
            'display' => true,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/19.test subset',
                'writer' => 'test/writer/mml/19.test subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><mo stretchy="true">(</mo><mtable><mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd></mtr><mtr><mtd><mn>3</mn></mtd><mtd><mn>4</mn></mtd></mtr></mtable><mo stretchy="true">)</mo></mrow><annotation encoding="application/x-tex">\begin{pmatrix}1 &amp; 2 \\\\ 3 &amp; 4\end{pmatrix}</annotation></semantics></math>',
        ],
        [
            'id' => 'infix-choose',
            'family' => 'infix-fractions',
            'tex' => 'n \choose k',
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/choose.test subset',
                'writer' => 'test/writer/mml/choose.test subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><mo stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo stretchy="true">)</mo></mrow><annotation encoding="application/x-tex">n \choose k</annotation></semantics></math>',
        ],
    ],
    'fallback' => [
        [
            'id' => 'empty-source-span',
            'tex' => '',
            'display' => false,
            'expectedHtml' => '<span class="math inline"></span>',
            'reason' => 'Empty TeX has no TexMath expression and must not emit malformed MathML.',
        ],
    ],
    'knownGaps' => [
        [
            'id' => 'implicit-product-identifiers',
            'upstream' => 'test/reader/tex/03.test and test/writer/mml/03.test',
            'tex' => 'ax^2 + bx + c = 0',
            'gap' => 'TexMath tokenizes adjacent letters as individual identifiers; current HtmlWriter emits mi ax and mi bx.',
        ],
        [
            'id' => 'labels-ignored',
            'upstream' => 'test/reader/tex/labels.test and test/writer/mml/labels.test',
            'tex' => '2 + 2\label{myeq}',
            'gap' => 'TexMath ignores labels; current HtmlWriter treats label and its argument as visible identifiers.',
        ],
        [
            'id' => 'macro-expansion',
            'upstream' => 'test/reader/tex/macros.test and test/writer/mml/macros.test',
            'tex' => '\newcommand{\abc}{5}\abc',
            'gap' => 'TexMath expands user macros; current HtmlWriter has no macro-definition pass.',
        ],
    ],
];
