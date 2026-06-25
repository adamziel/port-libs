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
        [
            'id' => 'macro-square',
            'family' => 'macros',
            'tex' => '\newcommand{\sq}[1]{#1^2}\sq{x}',
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/macros.test subset',
                'writer' => 'test/writer/mml/macros.test derived subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><msup><mi>x</mi><mn>2</mn></msup><annotation encoding="application/x-tex">\newcommand{\sq}[1]{#1^2}\sq{x}</annotation></semantics></math>',
        ],
        [
            'id' => 'macro-optional-default',
            'family' => 'macros',
            'tex' => '\newcommand{\norm}[2][2]{\left\lVert #2 \right\rVert_#1}\norm{x}+\norm[1]{y}',
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/macros.test optional argument subset',
                'writer' => 'test/writer/mml/macros.test derived subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><msub><mrow><mo stretchy="true">‖</mo><mi>x</mi><mo stretchy="true">‖</mo></mrow><mn>2</mn></msub><mo>+</mo><msub><mrow><mo stretchy="true">‖</mo><mi>y</mi><mo stretchy="true">‖</mo></mrow><mn>1</mn></msub></mrow><annotation encoding="application/x-tex">\newcommand{\norm}[2][2]{\left\lVert #2 \right\rVert_#1}\norm{x}+\norm[1]{y}</annotation></semantics></math>',
        ],
        [
            'id' => 'declared-operator',
            'family' => 'macros-and-operators',
            'tex' => '\DeclareMathOperator*{\argmax}{arg\,max}\argmax_{x} f(x)',
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/operatorname.test and TeX/Macros.hs DeclareMathOperator',
                'writer' => 'test/writer/mml/operatorname.test derived subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><msub><mi mathvariant="normal">arg max</mi><mi>x</mi></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo></mrow><annotation encoding="application/x-tex">\DeclareMathOperator*{\argmax}{arg\,max}\argmax_{x} f(x)</annotation></semantics></math>',
        ],
        [
            'id' => 'ignorable-label-tag-comment',
            'family' => 'lexing',
            'tex' => "x% comment\n+ y\\label{eq:a}\\tag*{A}\\nonumber\\allowbreak",
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'test/reader/tex/labels.test and TeX.hs ignorable parser',
                'writer' => 'test/writer/mml/labels.test derived subset',
            ],
            'expectedMathML' => "<math xmlns=\"http://www.w3.org/1998/Math/MathML\"><semantics><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><annotation encoding=\"application/x-tex\">x% comment\n+ y\\label{eq:a}\\tag*{A}\\nonumber\\allowbreak</annotation></semantics></math>",
        ],
        [
            'id' => 'align-environment',
            'family' => 'environments',
            'tex' => '\begin{align}a&=b+c\\\\d&=e\end{align}',
            'display' => true,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'Text.TeXMath.Readers.TeX environments align parser',
                'writer' => 'Text.TeXMath.Writers.MathML EArray subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mtable columnalign="right left"><mtr><mtd><mi>a</mi></mtd><mtd><mrow><mo>=</mo><mi>b</mi><mo>+</mo><mi>c</mi></mrow></mtd></mtr><mtr><mtd><mi>d</mi></mtd><mtd><mrow><mo>=</mo><mi>e</mi></mrow></mtd></mtr></mtable><annotation encoding="application/x-tex">\begin{align}a&amp;=b+c\\\\d&amp;=e\end{align}</annotation></semantics></math>',
        ],
        [
            'id' => 'equation-environment',
            'family' => 'environments',
            'tex' => '\begin{equation}x^2+1\end{equation}',
            'display' => false,
            'upstream' => [
                'texmath' => '17089967',
                'reader' => 'Text.TeXMath.Readers.TeX environments equation parser',
                'writer' => 'Text.TeXMath.Writers.MathML grouped formula subset',
            ],
            'expectedMathML' => '<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mn>1</mn></mrow><annotation encoding="application/x-tex">\begin{equation}x^2+1\end{equation}</annotation></semantics></math>',
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
            'id' => 'macro-environments',
            'upstream' => 'test/reader/tex/macros.test',
            'tex' => '\newenvironment{foo}{\left(}{\right)}\begin{foo}x\end{foo}',
            'gap' => 'TexMath expands newenvironment definitions; current HtmlWriter only expands command macros and declared operators.',
        ],
        [
            'id' => 'direct-unicode-token-types',
            'upstream' => 'test/reader/tex/unicode.test and Text.TeXMath.Unicode.ToTeX',
            'tex' => 'α + β',
            'gap' => 'TexMath classifies direct non-ASCII math characters by Unicode symbol type; current HtmlWriter tokenization is byte-oriented.',
        ],
    ],
];
