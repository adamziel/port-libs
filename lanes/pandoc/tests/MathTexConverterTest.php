<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MathTexConverter;

return [
    'converts bounded tex fractions scripts roots symbols and text to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $mathml = $converter->texToMathMl('\\frac{a_1}{\\sqrt{b^2}} + \\alpha \\times \\omega', true);
        $textMathml = $converter->texToMathMl('\\text{posts & media} \\in S');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mfrac><msub><mi>a</mi><mn>1</mn></msub><msqrt><msup><mi>b</mi><mn>2</mn></msup></msqrt></mfrac>', $mathml);
        $t->contains('<mo>+</mo><mi>α</mi><mo>×</mo><mi>ω</mi>', $mathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">', $textMathml);
        $t->contains('<mtext>posts &amp; media</mtext><mo>∈</mo><mi>S</mi>', $textMathml);
    },
    'converts bounded tex indexed roots to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $rootMathml = $converter->texToMathMl('\\sqrt[3]{x} + \\sqrt[n+1]{\\frac{a}{b}}', true);
        $docxRootMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\sqrt[k]{x_i + y_i}',
            'display' => false,
            'sourceFormat' => 'docx-omml',
        ]));

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $rootMathml);
        $t->contains('<mroot><mi>x</mi><mn>3</mn></mroot><mo>+</mo><mroot><mfrac><mi>a</mi><mi>b</mi></mfrac><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mroot>', $rootMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sqrt[3]{x} + \\sqrt[n+1]{\\frac{a}{b}}</annotation>', $rootMathml);
        $t->contains('<mroot><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mi>k</mi></mroot>', $docxRootMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sqrt[k]{x_i + y_i}</annotation>', $docxRootMathml);
    },
    'converts bounded tex token command arguments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $rootFractionMathml = $converter->texToMathMl('\\sqrt x_i + \\sqrt[3]y_j + \\frac12 + \\dfrac a b + \\binom n k', true);
        $wrapperMathml = $converter->texToMathMl('\\overset\\alpha x_i + \\underset 0 y_i + \\boxed q_i + \\phantom r_i + \\hphantom s_i + \\vphantom t_i + \\cancel u_i + \\bcancel v + \\xcancel w', true);
        $combinedMathml = $rootFractionMathml . $wrapperMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $rootFractionMathml);
        $t->contains('<msub><msqrt><mi>x</mi></msqrt><mi>i</mi></msub>', $rootFractionMathml);
        $t->contains('<msub><mroot><mi>y</mi><mn>3</mn></mroot><mi>j</mi></msub>', $rootFractionMathml);
        $t->contains('<mfrac><mn>1</mn><mn>2</mn></mfrac>', $rootFractionMathml);
        $t->contains('<mstyle displaystyle="true"><mfrac><mi>a</mi><mi>b</mi></mfrac></mstyle>', $rootFractionMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo>', $rootFractionMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sqrt x_i + \\sqrt[3]y_j + \\frac12 + \\dfrac a b + \\binom n k</annotation>', $rootFractionMathml);
        $t->contains('<msub><mover><mi>x</mi><mi>α</mi></mover><mi>i</mi></msub>', $wrapperMathml);
        $t->contains('<msub><munder><mi>y</mi><mn>0</mn></munder><mi>i</mi></msub>', $wrapperMathml);
        $t->contains('<msub><menclose notation="box"><mi>q</mi></menclose><mi>i</mi></msub>', $wrapperMathml);
        $t->contains('<msub><mphantom><mi>r</mi></mphantom><mi>i</mi></msub>', $wrapperMathml);
        $t->contains('<msub><mpadded height="0" depth="0"><mphantom><mi>s</mi></mphantom></mpadded><mi>i</mi></msub>', $wrapperMathml);
        $t->contains('<msub><mpadded width="0"><mphantom><mi>t</mi></mphantom></mpadded><mi>i</mi></msub>', $wrapperMathml);
        $t->contains('<msub><menclose notation="updiagonalstrike"><mi>u</mi></menclose><mi>i</mi></msub>', $wrapperMathml);
        $t->contains('<menclose notation="downdiagonalstrike"><mi>v</mi></menclose><mo>+</mo><menclose notation="updiagonalstrike downdiagonalstrike"><mi>w</mi></menclose>', $wrapperMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overset\\alpha x_i + \\underset 0 y_i + \\boxed q_i + \\phantom r_i + \\hphantom s_i + \\vphantom t_i + \\cancel u_i + \\bcancel v + \\xcancel w</annotation>', $wrapperMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\boxed</mi>') && !str_contains($combinedMathml, '<mi>\\cancel</mi>') && !str_contains($combinedMathml, '<mi>\\overset</mi>'));
    },
    'converts bounded plain tex root of syntax to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $plainRootMathml = $converter->texToMathMl('\\root 3 \\of{x_i + y_i} + \\root n+1 \\of{\\frac{a}{b}}', true);
        $groupDegreeMathml = $converter->texToMathMl('\\root {k+2} \\of{\\operatorname{media}_i}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $plainRootMathml);
        $t->contains('<mroot><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mn>3</mn></mroot>', $plainRootMathml);
        $t->contains('<mroot><mfrac><mi>a</mi><mi>b</mi></mfrac><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mroot>', $plainRootMathml);
        $t->contains('<annotation encoding="application/x-tex">\\root 3 \\of{x_i + y_i} + \\root n+1 \\of{\\frac{a}{b}}</annotation>', $plainRootMathml);
        $t->contains('<mroot><msub><mi>media</mi><mi>i</mi></msub><mrow><mi>k</mi><mo>+</mo><mn>2</mn></mrow></mroot>', $groupDegreeMathml);
        $t->true(!str_contains($plainRootMathml . $groupDegreeMathml, '<mi>\\root</mi>'));
        $t->true(!str_contains($plainRootMathml . $groupDegreeMathml, '<mi>\\of</mi>'));
    },
    'converts bounded texmath command aliases and wrappers to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $stackrelMathml = $converter->texToMathMl('\\stackrel{\\text{audit}}{p_i} + \\stackrel\\alpha\\beta', true);
        $ensureMathml = $converter->texToMathMl('\\ensuremath{p_i + m_i} + q');
        $surdMathml = $converter->texToMathMl('\\surd x + \\surd{p_i + m_i}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\stackrel{\\text{audit}}{p_i} + \\surd{x}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $stackrelMathml);
        $t->contains('<mover><msub><mi>p</mi><mi>i</mi></msub><mtext>audit</mtext></mover><mo>+</mo><mover><mi>β</mi><mi>α</mi></mover>', $stackrelMathml);
        $t->contains('<annotation encoding="application/x-tex">\\stackrel{\\text{audit}}{p_i} + \\stackrel\\alpha\\beta</annotation>', $stackrelMathml);
        $t->contains('<mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow><mo>+</mo><mi>q</mi>', $ensureMathml);
        $t->contains('<annotation encoding="application/x-tex">\\ensuremath{p_i + m_i} + q</annotation>', $ensureMathml);
        $t->contains('<msqrt><mi>x</mi></msqrt><mo>+</mo><msqrt><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></msqrt>', $surdMathml);
        $t->contains('<annotation encoding="application/x-tex">\\surd x + \\surd{p_i + m_i}</annotation>', $surdMathml);
        $t->contains('alttext="p sub i over audit plus square root of x"', $accessibleMathml);
        $t->contains('intent="row(over(subscript(p,i),audit),plus,sqrt(x))"', $accessibleMathml);
    },
    'converts bounded texmath command table aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $greekMathml = $converter->texToMathMl('\\Gamma + \\Delta + \\varepsilon + \\varphi + \\vartheta + \\xi + \\zeta', true);
        $operatorMathml = $converter->texToMathMl('\\limsup_{n} a_n + \\liminf_{m} b_m + \\min x + \\max y + \\det A + \\gcd(m,n)');
        $relationMathml = $converter->texToMathMl('A \\preceq B + C \\succeq D + x \\sim y + p \\models q + u \\mid v');
        $arrowDotMathml = $converter->texToMathMl('x \\longrightarrow y + a \\Longleftrightarrow b + \\dots + \\dotsb + \\vdots + \\therefore q');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\Gamma + x \\preceq y + \\therefore z');
        $combinedMathml = $greekMathml . $operatorMathml . $relationMathml . $arrowDotMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $greekMathml);
        $t->contains('<mi>Γ</mi><mo>+</mo><mi>Δ</mi><mo>+</mo><mi>ε</mi><mo>+</mo><mi>φ</mi><mo>+</mo><mi>ϑ</mi><mo>+</mo><mi>ξ</mi><mo>+</mo><mi>ζ</mi>', $greekMathml);
        $t->contains('<annotation encoding="application/x-tex">\\Gamma + \\Delta + \\varepsilon + \\varphi + \\vartheta + \\xi + \\zeta</annotation>', $greekMathml);
        $t->contains('<msub><mi>limsup</mi><mi>n</mi></msub><mo>⁡</mo><msub><mi>a</mi><mi>n</mi></msub><mo>+</mo><msub><mi>liminf</mi><mi>m</mi></msub><mo>⁡</mo><msub><mi>b</mi><mi>m</mi></msub>', $operatorMathml);
        $t->contains('<mi>min</mi><mo>⁡</mo><mi>x</mi><mo>+</mo><mi>max</mi><mo>⁡</mo><mi>y</mi><mo>+</mo><mi>det</mi><mo>⁡</mo><mi>A</mi><mo>+</mo><mi>gcd</mi><mo>⁡</mo><mo>(</mo><mi>m</mi><mo>,</mo><mi>n</mi><mo>)</mo>', $operatorMathml);
        $t->contains('<mi>A</mi><mo>≼</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>≽</mo><mi>D</mi><mo>+</mo><mi>x</mi><mo>∼</mo><mi>y</mi><mo>+</mo><mi>p</mi><mo>⊨</mo><mi>q</mi><mo>+</mo><mi>u</mi><mo>∣</mo><mi>v</mi>', $relationMathml);
        $t->contains('<mi>x</mi><mo>→</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>⇔</mo><mi>b</mi><mo>+</mo><mo>…</mo><mo>+</mo><mo>⋯</mo><mo>+</mo><mo>⋮</mo><mo>+</mo><mo>∴</mo><mi>q</mi>', $arrowDotMathml);
        $t->contains('alttext="gamma plus x precedes or equal y plus therefore z"', $accessibleMathml);
        $t->contains('intent="row(gamma,plus,x,precedes_or_equal,y,plus,therefore,z)"', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\Gamma</mi>') && !str_contains($combinedMathml, '<mi>\\limsup</mi>') && !str_contains($combinedMathml, '<mi>\\preceq</mi>') && !str_contains($combinedMathml, '<mi>\\therefore</mi>'));
    },
    'converts bounded texmath dot relation and named symbol aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $dotAndNamedMathml = $converter->texToMathMl('\\ldots + \\cdots + \\ddots + \\aleph + \\ell + \\Re + \\Im + \\wp', true);
        $relationMathml = $converter->texToMathMl('a \\cong b + c \\simeq d + x \\propto y + u \\parallel v + r \\perp s + \\angle x + \\nabla f + \\top + \\bot');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\nabla f \\cong g + \\angle x');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $dotAndNamedMathml);
        $t->contains('<mo>…</mo><mo>+</mo><mo>⋯</mo><mo>+</mo><mo>⋱</mo><mo>+</mo><mi>ℵ</mi><mo>+</mo><mi>ℓ</mi><mo>+</mo><mi>ℜ</mi><mo>+</mo><mi>ℑ</mi><mo>+</mo><mi>℘</mi>', $dotAndNamedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\ldots + \\cdots + \\ddots + \\aleph + \\ell + \\Re + \\Im + \\wp</annotation>', $dotAndNamedMathml);
        $t->contains('<mi>a</mi><mo>≅</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>≃</mo><mi>d</mi><mo>+</mo><mi>x</mi><mo>∝</mo><mi>y</mi><mo>+</mo><mi>u</mi><mo>∥</mo><mi>v</mi><mo>+</mo><mi>r</mi><mo>⊥</mo><mi>s</mi><mo>+</mo><mo>∠</mo><mi>x</mi><mo>+</mo><mo>∇</mo><mi>f</mi><mo>+</mo><mo>⊤</mo><mo>+</mo><mo>⊥</mo>', $relationMathml);
        $t->contains('<annotation encoding="application/x-tex">a \\cong b + c \\simeq d + x \\propto y + u \\parallel v + r \\perp s + \\angle x + \\nabla f + \\top + \\bot</annotation>', $relationMathml);
        $t->contains('alttext="nabla f congruent to g plus angle x"', $accessibleMathml);
        $t->contains('intent="row(nabla,f,congruent_to,g,plus,angle,x)"', $accessibleMathml);
        $t->true(!str_contains($dotAndNamedMathml . $relationMathml, '<mi>\\ldots</mi>'));
        $t->true(!str_contains($dotAndNamedMathml . $relationMathml, '<mi>\\cong</mi>'));
        $t->true(!str_contains($dotAndNamedMathml . $relationMathml, '<mi>\\nabla</mi>'));
    },
    'converts bounded texmath negative approximate relation aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $relationMathml = $converter->texToMathMl('x \\approxeq y + a \\napprox b + c \\ncong d', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('x \\approxeq y + a \\napprox b + c \\ncong d');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $relationMathml);
        $t->contains('<mi>x</mi><mo>≊</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>≉</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>≇</mo><mi>d</mi>', $relationMathml);
        $t->contains('<annotation encoding="application/x-tex">x \\approxeq y + a \\napprox b + c \\ncong d</annotation>', $relationMathml);
        $t->contains('alttext="x approximately equal or equal to y plus a not approximately equal to b plus c not congruent to d"', $accessibleMathml);
        $t->contains('intent="row(x,approximately_equal_or_equal_to,y,plus,a,not_approximately_equal_to,b,plus,c,not_congruent_to,d)"', $accessibleMathml);
        $t->true(!str_contains($relationMathml, '<mi>\\approxeq</mi>') && !str_contains($relationMathml, '<mi>\\napprox</mi>') && !str_contains($relationMathml, '<mi>\\ncong</mi>'));
    },
    'converts bounded texmath comparison relation aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $relationMathml = $converter->texToMathMl('x \\nless y + a \\ngtr b + c \\leqgtr d + e \\geqless f', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('x \\nless y + a \\ngtr b + c \\leqgtr d + e \\geqless f');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $relationMathml);
        $t->contains('<mi>x</mi><mo>≮</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>≯</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⋚</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>⋛</mo><mi>f</mi>', $relationMathml);
        $t->contains('<annotation encoding="application/x-tex">x \\nless y + a \\ngtr b + c \\leqgtr d + e \\geqless f</annotation>', $relationMathml);
        $t->contains('alttext="x not less than y plus a not greater than b plus c less equal greater d plus e greater equal less f"', $accessibleMathml);
        $t->contains('intent="row(x,not_less_than,y,plus,a,not_greater_than,b,plus,c,less_equal_greater,d,plus,e,greater_equal_less,f)"', $accessibleMathml);
        $t->true(!str_contains($relationMathml, '<mi>\\nless</mi>') && !str_contains($relationMathml, '<mi>\\ngtr</mi>') && !str_contains($relationMathml, '<mi>\\leqgtr</mi>') && !str_contains($relationMathml, '<mi>\\geqless</mi>'));
    },
    'unwraps bounded tex hyperref wrappers for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $mathml = $converter->texToMathMl('\\hyperref[eq:review-flow]{p_i + m_i} + \\hyperref{q_i}', true);
        $resolvedMathml = $converter->texToMathMl('\\hyperref[eq:review-flow]{\\eqref{eq:review-flow} + x}', false, [], [
            'eq:review-flow' => [
                'label' => 'eq:review-flow',
                'reference' => 'WP-2',
                'tag' => 'WP-2',
            ],
        ]);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\hyperref[review]{x+y}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow><mo>+</mo><msub><mi>q</mi><mi>i</mi></msub>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\hyperref[eq:review-flow]{p_i + m_i} + \\hyperref{q_i}</annotation>', $mathml);
        $t->true(!str_contains($mathml, '<mi>\\hyperref</mi>'));
        $t->true(!str_contains($mathml, '<mo>[</mo><mi>e</mi><mi>q</mi>'));
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:review-flow">WP-2</mtext><mo>)</mo></mrow><mo>+</mo><mi>x</mi>', $resolvedMathml);
        $t->contains('alttext="x plus y"', $accessibleMathml);
        $t->contains('intent="row(x,plus,y)"', $accessibleMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hyperref'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hyperref[eq:review]'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hyperref[eq:review]{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hyperref[eq:review{x}'));
    },
    'converts bounded tex mathchoice branches to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $displayMathml = $converter->texToMathMl('\\mathchoice{D}{T}{S}{SS} + x', true);
        $inlineMathml = $converter->texToMathMl('\\mathchoice{D}{T}{S}{SS} + x');
        $scriptMathml = $converter->texToMathMl('x_{\\mathchoice{D}{T}{S}{SS}}');
        $scriptStyleMathml = $converter->texToMathMl('\\scriptstyle\\mathchoice{D}{T}{S}{SS} + q');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\mathchoice{\\text{display}}{\\text{text}}{\\text{script}}{\\text{scriptscript}} + x', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $displayMathml);
        $t->contains('<mrow><mi>D</mi><mo>+</mo><mi>x</mi></mrow>', $displayMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathchoice{D}{T}{S}{SS} + x</annotation>', $displayMathml);
        $t->contains('<mrow><mi>T</mi><mo>+</mo><mi>x</mi></mrow>', $inlineMathml);
        $t->contains('<msub><mi>x</mi><mi>S</mi></msub>', $scriptMathml);
        $t->contains('<mstyle scriptlevel="1"><mi>S</mi></mstyle><mo>+</mo><mi>q</mi>', $scriptStyleMathml);
        $t->contains('alttext="display plus x"', $accessibleMathml);
        $t->contains('intent="row(display,plus,x)"', $accessibleMathml);
        $t->true(!str_contains($displayMathml . $inlineMathml . $scriptMathml, '<mi>\\mathchoice</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathchoice{D}{T}{S}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathchoice{D}{}{S}{SS}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathchoice{D}{T}{\\frac{a}}{SS}'));
    },
    'converts bounded siunitx scalar commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $numberMathml = $converter->texToMathMl('\\num{1.25e3} + \\num{-0.5}');
        $unitMathml = $converter->texToMathMl('\\si[per-mode=symbol]{\\kg\\per\\s} + \\unit{\\m\\squared}', true);
        $quantityMathml = $converter->texToMathMl('\\SI{12.5}{\\kg\\per\\s} + \\qty[mode=text]{3.5}{\\m}');
        $angleMathml = $converter->texToMathMl('\\ang{30;15;0} + \\ang{90}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\SI{2}{\\m\\per\\s}');

        $t->contains('<mn>1.25</mn><mo>×</mo><msup><mn>10</mn><mn>3</mn></msup><mo>+</mo><mn>-0.5</mn>', $numberMathml);
        $t->contains('<annotation encoding="application/x-tex">\\num{1.25e3} + \\num{-0.5}</annotation>', $numberMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $unitMathml);
        $t->contains('<mrow><mtext>kg</mtext><mtext>/</mtext><mtext>s</mtext></mrow><mo>+</mo><msup><mtext>m</mtext><mn>2</mn></msup>', $unitMathml);
        $t->contains('<annotation encoding="application/x-tex">\\si[per-mode=symbol]{\\kg\\per\\s} + \\unit{\\m\\squared}</annotation>', $unitMathml);
        $t->contains('<mrow><mn>12.5</mn><mspace width="0.2222em"></mspace><mrow><mtext>kg</mtext><mtext>/</mtext><mtext>s</mtext></mrow></mrow><mo>+</mo><mrow><mn>3.5</mn><mspace width="0.2222em"></mspace><mtext>m</mtext></mrow>', $quantityMathml);
        $t->contains('<annotation encoding="application/x-tex">\\SI{12.5}{\\kg\\per\\s} + \\qty[mode=text]{3.5}{\\m}</annotation>', $quantityMathml);
        $t->contains('<mrow><mn>30</mn><mtext>°</mtext><mn>15</mn><mtext>′</mtext><mn>0</mn><mtext>″</mtext></mrow><mo>+</mo><mrow><mn>90</mn><mtext>°</mtext></mrow>', $angleMathml);
        $t->contains('alttext="2 space m slash s"', $accessibleMathml);
        $t->contains('intent="row(2,space,row(m,slash,s))"', $accessibleMathml);
        $t->true(!str_contains($quantityMathml, '<mi>\\SI</mi>'));
        $t->true(!str_contains($quantityMathml, '<mi>\\qty</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\num{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\si{\\unknownunit}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\SI{1}{}'));
    },
    'converts bounded siunitx range and list commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $numberRangeMathml = $converter->texToMathMl('\\numrange{1.25e3}{2,500} + \\numlist{1;2.5;3e2}');
        $quantityRangeMathml = $converter->texToMathMl('\\SIrange{12}{15}{\\kg\\per\\s} + \\qtyrange[mode=text]{3.5}{4.25}{\\m}', true);
        $prefixedRangeMathml = $converter->texToMathMl('\\qtyrange{2}{4}[\\text{about}]{\\m\\squared}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\numrange{1}{2}');

        $t->contains('<mn>1.25</mn><mo>×</mo><msup><mn>10</mn><mn>3</mn></msup><mspace width="0.2222em"></mspace><mo>–</mo><mspace width="0.2222em"></mspace><mn>2500</mn>', $numberRangeMathml);
        $t->contains('<mn>1</mn><mo>,</mo><mspace width="0.2222em"></mspace><mn>2.5</mn><mspace width="0.2222em"></mspace><mtext>and</mtext><mspace width="0.2222em"></mspace><mn>3</mn><mo>×</mo><msup><mn>10</mn><mn>2</mn></msup>', $numberRangeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\numrange{1.25e3}{2,500} + \\numlist{1;2.5;3e2}</annotation>', $numberRangeMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $quantityRangeMathml);
        $t->contains('<mrow><mn>12</mn><mspace width="0.2222em"></mspace><mo>–</mo><mspace width="0.2222em"></mspace><mn>15</mn><mspace width="0.2222em"></mspace><mrow><mtext>kg</mtext><mtext>/</mtext><mtext>s</mtext></mrow></mrow>', $quantityRangeMathml);
        $t->contains('<mrow><mn>3.5</mn><mspace width="0.2222em"></mspace><mo>–</mo><mspace width="0.2222em"></mspace><mn>4.25</mn><mspace width="0.2222em"></mspace><mtext>m</mtext></mrow>', $quantityRangeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\SIrange{12}{15}{\\kg\\per\\s} + \\qtyrange[mode=text]{3.5}{4.25}{\\m}</annotation>', $quantityRangeMathml);
        $t->contains('<mrow><mtext>about</mtext><mspace width="0.2222em"></mspace><mn>2</mn><mspace width="0.2222em"></mspace><mo>–</mo><mspace width="0.2222em"></mspace><mn>4</mn><mspace width="0.2222em"></mspace><msup><mtext>m</mtext><mn>2</mn></msup></mrow>', $prefixedRangeMathml);
        $t->contains('alttext="1 space to space 2"', $accessibleMathml);
        $t->contains('intent="row(1,space,to,space,2)"', $accessibleMathml);
        $t->true(!str_contains($numberRangeMathml, '<mi>\\numrange</mi>'));
        $t->true(!str_contains($numberRangeMathml, '<mi>\\numlist</mi>'));
        $t->true(!str_contains($quantityRangeMathml, '<mi>\\SIrange</mi>'));
        $t->true(!str_contains($quantityRangeMathml, '<mi>\\qtyrange</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\numrange{1}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\numrange{}{2}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\numlist{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\numlist{1; ; 2}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\SIrange{1}{2}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\qtyrange{1}{}{\\m}'));
    },
    'converts bounded siunitx upstream unit aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $travelMathml = $converter->texToMathMl('\\si{\\km\\per\\hour} + \\qty{9.81}{\\metre\\per\\second\\squared}', true);
        $mechanicalMathml = $converter->texToMathMl('\\SI{12}{\\newton\\metre} + \\unit{\\joule\\per\\mole\\per\\kelvin}');
        $namedUnitsMathml = $converter->texToMathMl('\\si{\\pascal\\second} + \\si{\\liter\\per\\minute} + \\si{\\angstrom\\cubed}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\SI{273}{\\kelvin}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $travelMathml);
        $t->contains('<mrow><mtext>km</mtext><mtext>/</mtext><mtext>h</mtext></mrow><mo>+</mo><mrow><mn>9.81</mn><mspace width="0.2222em"></mspace><mrow><mtext>m</mtext><mtext>/</mtext><msup><mtext>s</mtext><mn>2</mn></msup></mrow></mrow>', $travelMathml);
        $t->contains('<annotation encoding="application/x-tex">\\si{\\km\\per\\hour} + \\qty{9.81}{\\metre\\per\\second\\squared}</annotation>', $travelMathml);
        $t->contains('<mrow><mn>12</mn><mspace width="0.2222em"></mspace><mrow><mtext>N</mtext><mtext>m</mtext></mrow></mrow><mo>+</mo><mrow><mtext>J</mtext><mtext>/</mtext><mtext>mol</mtext><mtext>/</mtext><mtext>K</mtext></mrow>', $mechanicalMathml);
        $t->contains('<annotation encoding="application/x-tex">\\SI{12}{\\newton\\metre} + \\unit{\\joule\\per\\mole\\per\\kelvin}</annotation>', $mechanicalMathml);
        $t->contains('<mrow><mtext>Pa</mtext><mtext>s</mtext></mrow><mo>+</mo><mrow><mtext>L</mtext><mtext>/</mtext><mtext>min</mtext></mrow><mo>+</mo><msup><mtext>Å</mtext><mn>3</mn></msup>', $namedUnitsMathml);
        $t->contains('alttext="273 space K"', $accessibleMathml);
        $t->contains('intent="row(273,space,k)"', $accessibleMathml);
        $t->true(!str_contains($travelMathml . $mechanicalMathml . $namedUnitsMathml, '<mi>\\km</mi>'));
        $t->true(!str_contains($travelMathml . $mechanicalMathml . $namedUnitsMathml, '<mi>\\newton</mi>'));
        $t->true(!str_contains($travelMathml . $mechanicalMathml . $namedUnitsMathml, '<mi>\\joule</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\si{\\unknownunit}'));
    },
    'converts bounded siunitx prefixed unit aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $concentrationMathml = $converter->texToMathMl('\\si{\\mg\\per\\mL} + \\qty{532}{\\nm}', true);
        $frequencyPressureMathml = $converter->texToMathMl('\\SI{20}{\\MHz} + \\unit{\\kPa} + \\si{\\us}');
        $powerAmountMathml = $converter->texToMathMl('\\qty{1.5}{\\uJ\\per\\umol} + \\si{\\kWh\\per\\kmol}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\qty{5}{\\mg\\per\\mL}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $concentrationMathml);
        $t->contains('<mrow><mtext>mg</mtext><mtext>/</mtext><mtext>mL</mtext></mrow><mo>+</mo><mrow><mn>532</mn><mspace width="0.2222em"></mspace><mtext>nm</mtext></mrow>', $concentrationMathml);
        $t->contains('<annotation encoding="application/x-tex">\\si{\\mg\\per\\mL} + \\qty{532}{\\nm}</annotation>', $concentrationMathml);
        $t->contains('<mrow><mn>20</mn><mspace width="0.2222em"></mspace><mtext>MHz</mtext></mrow><mo>+</mo><mtext>kPa</mtext><mo>+</mo><mtext>μs</mtext>', $frequencyPressureMathml);
        $t->contains('<annotation encoding="application/x-tex">\\SI{20}{\\MHz} + \\unit{\\kPa} + \\si{\\us}</annotation>', $frequencyPressureMathml);
        $t->contains('<mrow><mn>1.5</mn><mspace width="0.2222em"></mspace><mrow><mtext>μJ</mtext><mtext>/</mtext><mtext>μmol</mtext></mrow></mrow><mo>+</mo><mrow><mtext>kWh</mtext><mtext>/</mtext><mtext>kmol</mtext></mrow>', $powerAmountMathml);
        $t->contains('alttext="5 space mg slash mL"', $accessibleMathml);
        $t->contains('intent="row(5,space,row(mg,slash,ml))"', $accessibleMathml);
        $t->true(!str_contains($concentrationMathml . $frequencyPressureMathml . $powerAmountMathml, '<mi>\\mg</mi>'));
        $t->true(!str_contains($concentrationMathml . $frequencyPressureMathml . $powerAmountMathml, '<mi>\\MHz</mi>'));
        $t->true(!str_contains($concentrationMathml . $frequencyPressureMathml . $powerAmountMathml, '<mi>\\us</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\si{\\qqmol}'));
    },
    'converts bounded siunitx electric energy and derived unit aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $resistanceMathml = $converter->texToMathMl('\\si{\\mohm\\per\\kohm} + \\unit{\\Mohm}', true);
        $voltageForceMathml = $converter->texToMathMl('\\qty{12}{\\pV\\per\\uV} + \\SI{3}{\\MN} + \\si{\\nV}');
        $energyCapacitanceMathml = $converter->texToMathMl('\\si{\\meV\\per\\GeV} + \\unit{\\keV\\per\\MeV} + \\unit{\\fF\\per\\pF}');
        $doseMathml = $converter->texToMathMl('\\qty{5}{\\gray\\per\\sievert}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\qty{5}{\\gray\\per\\sievert}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $resistanceMathml);
        $t->contains('<mrow><mtext>mΩ</mtext><mtext>/</mtext><mtext>kΩ</mtext></mrow><mo>+</mo><mtext>MΩ</mtext>', $resistanceMathml);
        $t->contains('<annotation encoding="application/x-tex">\\si{\\mohm\\per\\kohm} + \\unit{\\Mohm}</annotation>', $resistanceMathml);
        $t->contains('<mrow><mn>12</mn><mspace width="0.2222em"></mspace><mrow><mtext>pV</mtext><mtext>/</mtext><mtext>μV</mtext></mrow></mrow><mo>+</mo><mrow><mn>3</mn><mspace width="0.2222em"></mspace><mtext>MN</mtext></mrow><mo>+</mo><mtext>nV</mtext>', $voltageForceMathml);
        $t->contains('<annotation encoding="application/x-tex">\\qty{12}{\\pV\\per\\uV} + \\SI{3}{\\MN} + \\si{\\nV}</annotation>', $voltageForceMathml);
        $t->contains('<mrow><mtext>meV</mtext><mtext>/</mtext><mtext>GeV</mtext></mrow><mo>+</mo><mrow><mtext>keV</mtext><mtext>/</mtext><mtext>MeV</mtext></mrow><mo>+</mo><mrow><mtext>fF</mtext><mtext>/</mtext><mtext>pF</mtext></mrow>', $energyCapacitanceMathml);
        $t->contains('<mrow><mn>5</mn><mspace width="0.2222em"></mspace><mrow><mtext>Gy</mtext><mtext>/</mtext><mtext>Sv</mtext></mrow></mrow>', $doseMathml);
        $t->contains('alttext="5 space Gy slash Sv"', $accessibleMathml);
        $t->contains('intent="row(5,space,row(gy,slash,sv))"', $accessibleMathml);
        $t->true(!str_contains($resistanceMathml . $voltageForceMathml . $energyCapacitanceMathml . $doseMathml, '<mi>\\mohm</mi>'));
        $t->true(!str_contains($resistanceMathml . $voltageForceMathml . $energyCapacitanceMathml . $doseMathml, '<mi>\\pV</mi>'));
        $t->true(!str_contains($resistanceMathml . $voltageForceMathml . $energyCapacitanceMathml . $doseMathml, '<mi>\\meV</mi>'));
        $t->true(!str_contains($resistanceMathml . $voltageForceMathml . $energyCapacitanceMathml . $doseMathml, '<mi>\\gray</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\si{\\qqV}'));
    },
    'converts bounded siunitx extended upstream unit aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $frequencyVolumeMathml = $converter->texToMathMl('\\si{\\mHz\\per\\hL} + \\unit{\\hl\\per\\knot}', true);
        $physicsMathml = $converter->texToMathMl('\\unit{\\TeV\\per\\mmHg} + \\qty{42}{\\becquerel\\per\\candela}');
        $namedUnitMathml = $converter->texToMathMl('\\si{\\dalton\\per\\tonne} + \\si{\\neper\\per\\bel} + \\si{\\barn\\per\\katal}');
        $prefixMathml = $converter->texToMathMl('\\si{\\yocto\\meter\\per\\zetta\\gram} + \\si{\\astronomicalunit\\per\\atomicmassunit}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\qty{42}{\\becquerel\\per\\candela}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $frequencyVolumeMathml);
        $t->contains('<mrow><mtext>mHz</mtext><mtext>/</mtext><mtext>hL</mtext></mrow><mo>+</mo><mrow><mtext>hl</mtext><mtext>/</mtext><mtext>kn</mtext></mrow>', $frequencyVolumeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\si{\\mHz\\per\\hL} + \\unit{\\hl\\per\\knot}</annotation>', $frequencyVolumeMathml);
        $t->contains('<mrow><mtext>TeV</mtext><mtext>/</mtext><mtext>mmHg</mtext></mrow><mo>+</mo><mrow><mn>42</mn><mspace width="0.2222em"></mspace><mrow><mtext>Bq</mtext><mtext>/</mtext><mtext>cd</mtext></mrow></mrow>', $physicsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\unit{\\TeV\\per\\mmHg} + \\qty{42}{\\becquerel\\per\\candela}</annotation>', $physicsMathml);
        $t->contains('<mrow><mtext>Da</mtext><mtext>/</mtext><mtext>t</mtext></mrow><mo>+</mo><mrow><mtext>Np</mtext><mtext>/</mtext><mtext>B</mtext></mrow><mo>+</mo><mrow><mtext>b</mtext><mtext>/</mtext><mtext>kat</mtext></mrow>', $namedUnitMathml);
        $t->contains('<mrow><mtext>y</mtext><mtext>m</mtext><mtext>/</mtext><mtext>Z</mtext><mtext>g</mtext></mrow><mo>+</mo><mrow><mtext>ua</mtext><mtext>/</mtext><mtext>u</mtext></mrow>', $prefixMathml);
        $t->contains('alttext="42 space Bq slash cd"', $accessibleMathml);
        $t->contains('intent="row(42,space,row(bq,slash,cd))"', $accessibleMathml);
        $t->true(!str_contains($frequencyVolumeMathml . $physicsMathml . $namedUnitMathml . $prefixMathml, '<mi>\\mHz</mi>'));
        $t->true(!str_contains($frequencyVolumeMathml . $physicsMathml . $namedUnitMathml . $prefixMathml, '<mi>\\TeV</mi>'));
        $t->true(!str_contains($frequencyVolumeMathml . $physicsMathml . $namedUnitMathml . $prefixMathml, '<mi>\\becquerel</mi>'));
        $t->true(!str_contains($frequencyVolumeMathml . $physicsMathml . $namedUnitMathml . $prefixMathml, '<mi>\\dalton</mi>'));
        $t->true(!str_contains($frequencyVolumeMathml . $physicsMathml . $namedUnitMathml . $prefixMathml, '<mi>\\yocto</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\si{\\qqHz}'));
    },
    'converts bounded tex binomial commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $binomialMathml = $converter->texToMathMl('\\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $binomialMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo>', $binomialMathml);
        $t->contains('<mstyle displaystyle="false"><mrow><mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><msub><mi>p</mi><mi>i</mi></msub><mn>2</mn></mfrac><mo fence="true" stretchy="true">)</mo></mrow></mstyle>', $binomialMathml);
        $t->contains('<mstyle displaystyle="true"><mrow><mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mi>c</mi></mfrac><mo fence="true" stretchy="true">)</mo></mrow></mstyle>', $binomialMathml);
        $t->contains('<annotation encoding="application/x-tex">\\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c}</annotation>', $binomialMathml);
    },
    'converts bounded tex generalized fractions to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $fractionMathml = $converter->texToMathMl('\\dfrac{a_1}{b_1} + \\tfrac{x}{y} + \\genfrac{[}{]}{0pt}{0}{n}{k} + \\genfrac{\\langle}{\\rangle}{1pt}{2}{p_i}{m_i}', true);
        $plainGenfracMathml = $converter->texToMathMl('\\genfrac{}{}{.5pt}{}{a+b}{c}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $fractionMathml);
        $t->contains('<mstyle displaystyle="true"><mfrac><msub><mi>a</mi><mn>1</mn></msub><msub><mi>b</mi><mn>1</mn></msub></mfrac></mstyle>', $fractionMathml);
        $t->contains('<mstyle displaystyle="false"><mfrac><mi>x</mi><mi>y</mi></mfrac></mstyle>', $fractionMathml);
        $t->contains('<mstyle displaystyle="true"><mrow><mo fence="true" stretchy="true">[</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">]</mo></mrow></mstyle>', $fractionMathml);
        $t->contains('<mstyle scriptlevel="1"><mrow><mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="1pt"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">⟩</mo></mrow></mstyle>', $fractionMathml);
        $t->contains('<annotation encoding="application/x-tex">\\dfrac{a_1}{b_1} + \\tfrac{x}{y} + \\genfrac{[}{]}{0pt}{0}{n}{k} + \\genfrac{\\langle}{\\rangle}{1pt}{2}{p_i}{m_i}</annotation>', $fractionMathml);
        $t->contains('<mfrac linethickness=".5pt"><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mi>c</mi></mfrac>', $plainGenfracMathml);
    },
    'converts bounded tex infix fraction commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $infixMathml = $converter->texToMathMl('{a+b \\over c+d} + {n \\atop k} + {n \\choose k}', true);
        $delimitedMathml = $converter->texToMathMl('{p_i \\brack m_i} + {x+y \\brace z}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $infixMathml);
        $t->contains('<mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow></mfrac>', $infixMathml);
        $t->contains('<mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac>', $infixMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo>', $infixMathml);
        $t->contains('<annotation encoding="application/x-tex">{a+b \\over c+d} + {n \\atop k} + {n \\choose k}</annotation>', $infixMathml);
        $t->contains('<mo fence="true" stretchy="true">[</mo><mfrac linethickness="0"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">]</mo>', $delimitedMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mfrac linethickness="0"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mi>z</mi></mfrac><mo fence="true" stretchy="true">}</mo>', $delimitedMathml);
    },
    'converts bounded tex bangle infix fractions to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $mathml = $converter->texToMathMl('{n \\bangle k} + {p_i \\bangle m_i}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">⟩</mo>', $mathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="0"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">⟩</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">{n \\bangle k} + {p_i \\bangle m_i}</annotation>', $mathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\bangle}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{\\bangle k}'));
    },
    'converts bounded tex infix fractions with explicit delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $withDelimsMathml = $converter->texToMathMl('{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}', true);
        $invisibleDelimsMathml = $converter->texToMathMl('{x \\overwithdelims.\\rbrace y}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $withDelimsMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow></mfrac><mo fence="true" stretchy="true">)</mo>', $withDelimsMathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">⟩</mo>', $withDelimsMathml);
        $t->contains('<mo fence="true" stretchy="true">[</mo><mfrac linethickness="1pt"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">]</mo>', $withDelimsMathml);
        $t->contains('<annotation encoding="application/x-tex">{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}</annotation>', $withDelimsMathml);
        $t->contains('<mfrac><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">}</mo>', $invisibleDelimsMathml);
    },
    'adds source tex semantics annotations to bounded mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $annotated = $converter->texToMathMl('\\text{posts & media} \\in S');
        $nodeMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\frac{x}{y}',
            'display' => true,
        ]));

        $t->contains('<semantics><mrow><mtext>posts &amp; media</mtext><mo>∈</mo><mi>S</mi></mrow><annotation encoding="application/x-tex">\\text{posts &amp; media} \\in S</annotation></semantics>', $annotated);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mfrac><mi>x</mi><mi>y</mi></mfrac>', $nodeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\frac{x}{y}</annotation></semantics></math>', $nodeMathml);
    },
    'converts bounded tex text mode aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $aliasMathml = $converter->texToMathMl('\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}', true);
        $escapedTextMathml = $converter->texToMathMl('\\text{posts \\& media \\% draft} + \\textnormal{plain} + \\emph{note}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $aliasMathml);
        $t->contains('<mtext>review mode</mtext><mo>+</mo><mstyle mathvariant="normal"><mtext>media label</mtext></mstyle>', $aliasMathml);
        $t->contains('<mstyle mathvariant="bold"><mtext>draft</mtext></mstyle><mo>+</mo><mstyle mathvariant="italic"><mtext>review</mtext></mstyle>', $aliasMathml);
        $t->contains('<mstyle mathvariant="monospace"><mtext>code_1</mtext></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mtext>sans group</mtext></mstyle>', $aliasMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}</annotation>', $aliasMathml);
        $t->contains('<mtext>posts &amp; media % draft</mtext><mo>+</mo><mstyle mathvariant="normal"><mtext>plain</mtext></mstyle><mo>+</mo><mstyle mathvariant="italic"><mtext>note</mtext></mstyle>', $escapedTextMathml);
        $t->contains('<annotation encoding="application/x-tex">\\text{posts \\&amp; media \\% draft} + \\textnormal{plain} + \\emph{note}</annotation>', $escapedTextMathml);
    },
    'converts bounded tex text mode token arguments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $tokenTextMathml = $converter->texToMathMl('\\textbf x_i + \\textit\\% + \\mbox~ + \\texttt\\& + \\textnormal\\TeX + \\textsf\\ldots', true);
        $escapedTokenMathml = $converter->texToMathMl('\\textrm\\textbackslash + \\textup\\LaTeX + \\emph z');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\textbf x_i + \\mbox\\#');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $tokenTextMathml);
        $t->contains('<msub><mstyle mathvariant="bold"><mtext>x</mtext></mstyle><mi>i</mi></msub>', $tokenTextMathml);
        $t->contains('<mstyle mathvariant="italic"><mtext>%</mtext></mstyle><mo>+</mo><mtext>~</mtext><mo>+</mo><mstyle mathvariant="monospace"><mtext>&amp;</mtext></mstyle>', $tokenTextMathml);
        $t->contains('<mstyle mathvariant="normal"><mtext>TeX</mtext></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mtext>…</mtext></mstyle>', $tokenTextMathml);
        $t->contains('<annotation encoding="application/x-tex">\\textbf x_i + \\textit\\% + \\mbox~ + \\texttt\\&amp; + \\textnormal\\TeX + \\textsf\\ldots</annotation>', $tokenTextMathml);
        $t->contains('<mstyle mathvariant="normal"><mtext>\\</mtext></mstyle><mo>+</mo><mstyle mathvariant="normal"><mtext>LaTeX</mtext></mstyle><mo>+</mo><mstyle mathvariant="italic"><mtext>z</mtext></mstyle>', $escapedTokenMathml);
        $t->contains('alttext="x sub i plus number sign"', $accessibleMathml);
        $t->contains('intent="row(subscript(x,i),plus,number_sign)"', $accessibleMathml);
        $t->true(!str_contains($tokenTextMathml . $escapedTokenMathml, '<mi>\\textbf</mi>'));
        $t->true(!str_contains($tokenTextMathml . $escapedTokenMathml, '<mi>\\mbox</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textbf\\unknown'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textbf_1'));
    },
    'converts bounded tex recursive text mode groups to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $groupMathml = $converter->texToMathMl('\\text{pre {grouped text} post} + q');
        $innerMathml = $converter->texToMathMl('\\text{if $x_i \\in S$ then \\mbox{review}}', true);
        $styledMathml = $converter->texToMathMl('\\mbox{plain \\textbf{bold} and \\emph{note}} + \\textit{mix \\texttt{code}}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\text{if $x_i$ then \\textbf{ok}}');
        $groupBody = strstr($groupMathml, '<annotation', true) ?: $groupMathml;
        $nestedBody = (strstr($innerMathml, '<annotation', true) ?: $innerMathml)
            . (strstr($styledMathml, '<annotation', true) ?: $styledMathml);

        $t->contains('<mrow><mtext>pre </mtext><mtext>grouped text</mtext><mtext> post</mtext></mrow><mo>+</mo><mi>q</mi>', $groupMathml);
        $t->contains('<annotation encoding="application/x-tex">\\text{pre {grouped text} post} + q</annotation>', $groupMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $innerMathml);
        $t->contains('<mtext>if </mtext><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>∈</mo><mi>S</mi></mrow><mtext> then </mtext><mtext>review</mtext>', $innerMathml);
        $t->contains('<annotation encoding="application/x-tex">\\text{if $x_i \\in S$ then \\mbox{review}}</annotation>', $innerMathml);
        $t->contains('<mtext>plain </mtext><mstyle mathvariant="bold"><mtext>bold</mtext></mstyle><mtext> and </mtext><mstyle mathvariant="italic"><mtext>note</mtext></mstyle>', $styledMathml);
        $t->contains('<mstyle mathvariant="italic"><mtext>mix </mtext></mstyle><mstyle mathvariant="monospace"><mtext>code</mtext></mstyle>', $styledMathml);
        $t->contains('alttext="if x sub i then ok"', $accessibleMathml);
        $t->contains('intent="row(if,subscript(x,i),then,ok)"', $accessibleMathml);
        $t->true(!str_contains($groupBody, '{grouped text}'));
        $t->true(!str_contains($nestedBody, '<mtext>\\mbox'));
        $t->true(!str_contains($nestedBody, '<mtext>\\textbf'));
    },
    'converts bounded tex text mode spacing and style wrappers to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $spacingMathml = $converter->texToMathMl('\\text{alpha\\thinspace beta\\quad \\textsf{sans\\!tight}\\enspace\\mbox{box\\;semi}}', true);
        $styleMathml = $converter->texToMathMl('\\textstyle \\mbox{mode $x_i$ \\textbf{bold}} + q');
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $t->true(
            $dom->loadXML($spacingMathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
            'text-mode spacing fixture emits well-formed MathML'
        );
        $t->true(
            $dom->loadXML($styleMathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
            'textstyle text-mode fixture emits well-formed MathML'
        );
        $t->contains('<mtext>alpha</mtext><mspace width="0.1667em"></mspace><mtext> beta</mtext><mspace width="1em"></mspace><mtext> </mtext>', $spacingMathml);
        $t->contains('<mrow><mstyle mathvariant="sans-serif"><mtext>sans</mtext></mstyle><mspace width="-0.1667em"></mspace><mstyle mathvariant="sans-serif"><mtext>tight</mtext></mstyle></mrow>', $spacingMathml);
        $t->contains('<mspace width="0.5em"></mspace><mrow><mtext>box</mtext><mspace width="0.2778em"></mspace><mtext>semi</mtext></mrow>', $spacingMathml);
        $t->contains('<annotation encoding="application/x-tex">\\text{alpha\\thinspace beta\\quad \\textsf{sans\\!tight}\\enspace\\mbox{box\\;semi}}</annotation>', $spacingMathml);
        $t->contains('<mstyle displaystyle="false"><mrow><mtext>mode </mtext><msub><mi>x</mi><mi>i</mi></msub><mtext> </mtext><mstyle mathvariant="bold"><mtext>bold</mtext></mstyle></mrow></mstyle><mo>+</mo><mi>q</mi>', $styleMathml);
        $t->contains('<annotation encoding="application/x-tex">\\textstyle \\mbox{mode $x_i$ \\textbf{bold}} + q</annotation>', $styleMathml);
        $t->true(!str_contains($spacingMathml . $styleMathml, '<mi>\\textstyle</mi>'));
        $t->true(!str_contains($spacingMathml . $styleMathml, '<mtext>\\thinspace'));
        $t->true(!str_contains($spacingMathml . $styleMathml, '<mtext>\\mbox'));
    },
    'converts texmath text fixture glyphs and ligatures in text mode' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $normalMathml = $converter->texToMathMl('\\text{(\\L ukasiewicz, G\\"{o}del, and G\\"odel)}', true);
        $italicMathml = $converter->texToMathMl('\\textit{\\ldots{}--``double quotes\'\'---`single quotes\'}');
        $fallbackMathml = $converter->texToMathMl('\\text{\\"z}', true);
        $unknownMathml = $converter->texToMathMl('\\text{\\unknown word}', true);
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $t->true(
            $dom->loadXML($normalMathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
            'text fixture glyph MathML remains well-formed XML'
        );
        $t->true(
            $dom->loadXML($italicMathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
            'text fixture ligature MathML remains well-formed XML'
        );
        $t->true(
            $dom->loadXML($fallbackMathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
            'unsupported text accent fallback remains well-formed XML'
        );
        $t->true(
            $dom->loadXML($unknownMathml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING),
            'unknown text command fallback remains well-formed XML'
        );
        $t->contains('<mtext>(Łukasiewicz, Gödel, and Gödel)</mtext>', $normalMathml);
        $t->contains('<annotation encoding="application/x-tex">\\text{(\\L ukasiewicz, G\\&quot;{o}del, and G\\&quot;odel)}</annotation>', $normalMathml);
        $t->contains('<mstyle mathvariant="italic"><mtext>…</mtext></mstyle>', $italicMathml);
        $t->contains('<mstyle mathvariant="italic"><mtext>–“double quotes”—‘single quotes’</mtext></mstyle>', $italicMathml);
        $t->contains('<annotation encoding="application/x-tex">\\textit{\\ldots{}--``double quotes&#039;&#039;---`single quotes&#039;}</annotation>', $italicMathml);
        $t->contains('<mtext>&quot;z</mtext>', $fallbackMathml);
        $t->contains('<mtext>\\unknown word</mtext>', $unknownMathml);
        $t->true(!str_contains($normalMathml . $italicMathml, '<mtext>\\L'));
        $t->true(!str_contains($normalMathml . $italicMathml, '<mtext>G&quot;'));
    },
    'converts bounded tex escaped special symbols to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $symbolMathml = $converter->texToMathMl('\\{p_i\\} + a\\#b + c\\&d + e\\$f + g\\%h + i\\_j + \\textbackslash', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('a\\#b + c\\_d + \\textbackslash');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $symbolMathml);
        $t->contains('<mo>{</mo><msub><mi>p</mi><mi>i</mi></msub><mo>}</mo><mo>+</mo><mi>a</mi><mo>#</mo><mi>b</mi>', $symbolMathml);
        $t->contains('<mi>c</mi><mo>&amp;</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>$</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>%</mo><mi>h</mi><mo>+</mo><mi>i</mi><mo>_</mo><mi>j</mi><mo>+</mo><mo>\\</mo>', $symbolMathml);
        $t->contains('<annotation encoding="application/x-tex">\\{p_i\\} + a\\#b + c\\&amp;d + e\\$f + g\\%h + i\\_j + \\textbackslash</annotation>', $symbolMathml);
        $t->contains('alttext="a number sign b plus c underbar d plus backslash"', $accessibleMathml);
        $t->contains('intent="row(a,number_sign,b,plus,c,underbar,d,plus,backslash)"', $accessibleMathml);
        $t->true(!str_contains($symbolMathml, '<mi>\\#</mi>'));
        $t->true(!str_contains($symbolMathml, '<mi>\\&amp;</mi>'));
        $t->true(!str_contains($symbolMathml, '<mi>\\textbackslash</mi>'));
    },
    'adds bounded mathml accessibility text and intent annotations' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $accessible = $converter->texToAccessibleMathMl('\\frac{a_1}{\\sqrt{b^2}} + \\alpha', true);
        $nodeMathml = $converter->accessibleMathMlFor(new AstNode('math', [
            'text' => '\\sum_{i=1}^{n} p_i',
            'display' => true,
        ]));

        $t->contains('display="block" alttext="fraction a sub 1 over square root of b superscript 2 plus alpha" intent="row(fraction(subscript(a,1),sqrt(superscript(b,2))),plus,alpha)"', $accessible);
        $t->contains('<annotation encoding="application/x-tex">\\frac{a_1}{\\sqrt{b^2}} + \\alpha</annotation>', $accessible);
        $t->contains('<annotation encoding="application/x-portlibs-math-alttext">fraction a sub 1 over square root of b superscript 2 plus alpha</annotation>', $accessible);
        $t->contains('<annotation encoding="application/x-portlibs-math-intent">row(fraction(subscript(a,1),sqrt(superscript(b,2))),plus,alpha)</annotation>', $accessible);
        $t->contains('alttext="sum sub i equals 1 superscript n p sub i"', $nodeMathml);
        $t->contains('intent="row(subsup(sum,row(i,equals,1),n),subscript(p,i))"', $nodeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sum_{i=1}^{n} p_i</annotation>', $nodeMathml);
    },
    'expands bounded raw tex macros for mathml handoff while preserving source annotations' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\newcommand{\\tuple}[1]{\\langle #1 \\rangle}',
            '\\providecommand{\\pair}[2]{\\langle #1,#2 \\rangle}',
            '',
            '$\\tuple{x,y}$',
        ]));
        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\\tuple{x,y} + \\pair{p_i}{m_i}', false, $macros);
        $nodeMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\pair{a}{b}',
            'display' => true,
        ]), $macros);

        $t->same([
            'tuple' => ['arity' => 1, 'template' => '\\langle #1 \\rangle'],
            'pair' => ['arity' => 2, 'template' => '\\langle #1,#2 \\rangle'],
        ], $macros);
        $t->contains('<mo>⟨</mo><mi>x</mi><mo>,</mo><mi>y</mi><mo>⟩</mo><mo>+</mo><mo>⟨</mo><msub><mi>p</mi><mi>i</mi></msub><mo>,</mo><msub><mi>m</mi><mi>i</mi></msub><mo>⟩</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\tuple{x,y} + \\pair{p_i}{m_i}</annotation>', $mathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><mo>⟨</mo><mi>a</mi><mo>,</mo><mi>b</mi><mo>⟩</mo></mrow>', $nodeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\pair{a}{b}</annotation></semantics></math>', $nodeMathml);
    },
    'expands bounded raw tex macros with optional arguments for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\newcommand{\\reviewpair}[2][draft]{#2 + #1}',
            '\\providecommand{\\withlabel}[3][audit]{#2 + #3 + #1}',
            '',
            '$\\reviewpair{p_i}$',
        ]));
        $macros = $converter->macroDefinitionsFromDocument($document);
        $defaultMathml = $converter->texToMathMl('\\reviewpair{p_i} + \\withlabel{m}{1}', false, $macros);
        $overrideMathml = $converter->texToMathMl('\\reviewpair[final]{p_i} + \\withlabel[source]{m}{2}', false, $macros);

        $t->same([
            'reviewpair' => ['arity' => 2, 'template' => '#2 + #1', 'optionalDefault' => 'draft'],
            'withlabel' => ['arity' => 3, 'template' => '#2 + #3 + #1', 'optionalDefault' => 'audit'],
        ], $macros);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi><mo>+</mo><mi>m</mi><mo>+</mo><mn>1</mn><mo>+</mo><mi>a</mi><mi>u</mi><mi>d</mi><mi>i</mi><mi>t</mi>', $defaultMathml);
        $t->contains('<annotation encoding="application/x-tex">\\reviewpair{p_i} + \\withlabel{m}{1}</annotation>', $defaultMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mi>f</mi><mi>i</mi><mi>n</mi><mi>a</mi><mi>l</mi><mo>+</mo><mi>m</mi><mo>+</mo><mn>2</mn><mo>+</mo><mi>s</mi><mi>o</mi><mi>u</mi><mi>r</mi><mi>c</mi><mi>e</mi>', $overrideMathml);
        $t->contains('<annotation encoding="application/x-tex">\\reviewpair[final]{p_i} + \\withlabel[source]{m}{2}</annotation>', $overrideMathml);
    },
    'expands bounded declared math operators for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = new AstNode('document', [], [
            new AstNode('raw_tex', [
                'tex' => '\\DeclareMathOperator{\\reviewop}{review\\,score}',
                'command' => 'DeclareMathOperator',
            ]),
            new AstNode('raw_tex', [
                'tex' => '\\DeclareMathOperator*{\\argreview}{arg\\,review}',
                'command' => 'DeclareMathOperator',
            ]),
        ]);
        $markdownDocument = (new MarkdownReader())->read(implode("\n", [
            '\\DeclareMathOperator{\\stageop}{stage\\;score}',
            '',
            '$\\stageop_i(p_i)$',
        ]));

        $macros = $converter->macroDefinitionsFromDocument($document);
        $markdownMacros = $converter->macroDefinitionsFromDocument($markdownDocument);
        $mathml = $converter->texToMathMl('\\reviewop_i(p_i) + \\argreview_{p_i \\in P}^{\\text{draft}} f(p_i)', true, $macros);
        $directOperatorMathml = $converter->texToMathMl('\\operatorname{arg\\,max}_{p_i} f(p_i)');

        $t->same([
            'reviewop' => ['arity' => 0, 'template' => '\\operatorname{review score}'],
            'argreview' => ['arity' => 0, 'template' => '\\operatorname*{arg review}'],
        ], $macros);
        $t->same([
            'stageop' => ['arity' => 0, 'template' => '\\operatorname{stage score}'],
        ], $markdownMacros);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<msub><mi>review score</mi><mi>i</mi></msub><mo>⁡</mo><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $mathml);
        $t->contains('<munderover><mi>arg review</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover><mo>⁡</mo><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\reviewop_i(p_i) + \\argreview_{p_i \\in P}^{\\text{draft}} f(p_i)</annotation>', $mathml);
        $t->contains('<msub><mi>arg max</mi><msub><mi>p</mi><mi>i</mi></msub></msub><mo>⁡</mo><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $directOperatorMathml);
        $t->true(!str_contains($mathml, '<mi>\\reviewop</mi>'));
        $t->true(!str_contains($mathml, '<mi>\\argreview</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclareMathOperator{\\bad}{\\input{secret}}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclareMathOperator{\\bad}{}']),
        ])));
    },
    'captures starred declared math operators from markdown for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\DeclareMathOperator*{\\argreview}{arg\\,review}',
            '',
            '$\\argreview_{p_i \\in P}^{\\text{draft}} f(p_i)$',
        ]));
        $declaration = $document->children[0];
        $math = $document->children[1]->children[0];
        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\\argreview_{p_i \\in P}^{\\text{draft}} f(p_i)', true, $macros);
        $readerMathml = $converter->mathMlFor($math);

        $t->same('raw_tex', $declaration->type);
        $t->same('DeclareMathOperator', $declaration->attr('command'));
        $t->same('\\DeclareMathOperator*{\\argreview}{arg\\,review}', $declaration->attr('tex'));
        $t->same([
            'argreview' => ['arity' => 0, 'template' => '\\operatorname*{arg review}'],
        ], $macros);
        $t->same('math', $math->type);
        $t->same('\\operatorname*{arg\\,review}_{p_i \\in P}^{\\text{draft}} f(p_i)', $math->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<munderover><mi>arg review</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\argreview_{p_i \\in P}^{\\text{draft}} f(p_i)</annotation>', $mathml);
        $t->contains('<munderover><mi>arg review</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover>', $readerMathml);
        $t->true(!str_contains($mathml . $readerMathml, '<mi>\\argreview</mi>'));
    },
    'captures bounded declared paired delimiters from markdown for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\DeclarePairedDelimiter{\\wpabs}{\\lvert}{\\rvert}',
            '\\DeclarePairedDelimiter\\wpangle{\\langle}{\\rangle}',
            '',
            '$\\wpabs{p_i + m_i} + \\wpangle{q_i}$',
        ]));
        $firstDeclaration = $document->children[0];
        $secondDeclaration = $document->children[1];
        $math = $document->children[2]->children[0];
        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\\wpabs{p_i + m_i} + \\wpangle{q_i}', true, $macros);
        $readerMathml = $converter->mathMlFor($math);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\wpabs{x_i}', false, $macros);

        $t->same('raw_tex', $firstDeclaration->type);
        $t->same('DeclarePairedDelimiter', $firstDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiter{\\wpabs}{\\lvert}{\\rvert}', $firstDeclaration->attr('tex'));
        $t->same('raw_tex', $secondDeclaration->type);
        $t->same('DeclarePairedDelimiter', $secondDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiter\\wpangle{\\langle}{\\rangle}', $secondDeclaration->attr('tex'));
        $t->same([
            'wpabs' => ['arity' => 1, 'template' => '\\left\\lvert #1 \\right\\rvert'],
            'wpangle' => ['arity' => 1, 'template' => '\\left\\langle #1 \\right\\rangle'],
        ], $macros);
        $t->same('\\left\\lvert p_i + m_i \\right\\rvert + \\left\\langle q_i \\right\\rangle', $math->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mo fence="true" stretchy="true">|</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">|</mo><mo>+</mo><mo fence="true" stretchy="true">⟨</mo><msub><mi>q</mi><mi>i</mi></msub><mo fence="true" stretchy="true">⟩</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\wpabs{p_i + m_i} + \\wpangle{q_i}</annotation>', $mathml);
        $t->contains('<mo fence="true" stretchy="true">|</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">|</mo>', $readerMathml);
        $t->contains('alttext="vertical bar x sub i vertical bar"', $accessibleMathml);
        $t->true(!str_contains($mathml . $readerMathml, '<mi>\\wpabs</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiter{\\bad}{\\input{secret}}{\\rvert}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiter{\\bad}{\\lvert}{}']),
        ])));
    },
    'expands bounded declared paired delimiter star and size invocations' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $macros = $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiter{\\wpabs}{\\lvert}{\\rvert}']),
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiter\\wpangle{\\langle}{\\rangle}']),
        ]));
        $mathml = $converter->texToMathMl('\\wpabs*{p_i + m_i} + \\wpabs[\\Big]{q_i} + \\wpangle[\\bigg]{r_i}', true, $macros);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\wpabs*{x_i}', false, $macros);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mo fence="true" stretchy="true">|</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">|</mo>', $mathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">|</mo><msub><mi>q</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">|</mo>', $mathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="2.4em" maxsize="2.4em">⟨</mo><msub><mi>r</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="2.4em" maxsize="2.4em">⟩</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\wpabs*{p_i + m_i} + \\wpabs[\\Big]{q_i} + \\wpangle[\\bigg]{r_i}</annotation>', $mathml);
        $t->contains('alttext="vertical bar x sub i vertical bar"', $accessibleMathml);
        $t->true(!str_contains($mathml, '<mi>\\wpabs</mi>') && !str_contains($mathml, '<mi>\\wpangle</mi>'));
        $t->true(!str_contains($mathml, '<mo>*</mo>') && !str_contains($mathml, '<mo>[</mo>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\wpabs[\\small]{x}', false, $macros));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\wpabs*', false, $macros));
    },
    'captures bounded declared paired delimiter x templates from markdown for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\DeclarePairedDelimiterX{\\wpnorm}[1]{\\lVert}{\\rVert}{#1}',
            '\\DeclarePairedDelimiterX\\wpinner[2]{\\langle}{\\rangle}{#1 , #2}',
            '',
            '$\\wpnorm{p_i + m_i} + \\wpinner{q_i}{r_i}$',
        ]));
        $firstDeclaration = $document->children[0];
        $secondDeclaration = $document->children[1];
        $math = $document->children[2]->children[0];
        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\\wpnorm*{p_i + m_i} + \\wpinner[\\Big]{q_i}{r_i}', true, $macros);
        $readerMathml = $converter->mathMlFor($math);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\wpnorm{x_i}', false, $macros);

        $t->same('raw_tex', $firstDeclaration->type);
        $t->same('DeclarePairedDelimiterX', $firstDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiterX{\\wpnorm}[1]{\\lVert}{\\rVert}{#1}', $firstDeclaration->attr('tex'));
        $t->same('raw_tex', $secondDeclaration->type);
        $t->same('DeclarePairedDelimiterX', $secondDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiterX\\wpinner[2]{\\langle}{\\rangle}{#1 , #2}', $secondDeclaration->attr('tex'));
        $t->same([
            'wpnorm' => ['arity' => 1, 'template' => '\\left\\lVert #1 \\right\\rVert'],
            'wpinner' => ['arity' => 2, 'template' => '\\left\\langle #1 , #2 \\right\\rangle'],
        ], $macros);
        $t->same('\\left\\lVert p_i + m_i \\right\\rVert + \\left\\langle q_i , r_i \\right\\rangle', $math->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mo fence="true" stretchy="true">‖</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">‖</mo>', $mathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟨</mo><msub><mi>q</mi><mi>i</mi></msub><mo>,</mo><msub><mi>r</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟩</mo>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\wpnorm*{p_i + m_i} + \\wpinner[\\Big]{q_i}{r_i}</annotation>', $mathml);
        $t->contains('<mo fence="true" stretchy="true">‖</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">‖</mo>', $readerMathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><msub><mi>q</mi><mi>i</mi></msub><mo>,</mo><msub><mi>r</mi><mi>i</mi></msub><mo fence="true" stretchy="true">⟩</mo>', $readerMathml);
        $t->contains('alttext="double vertical bar x sub i double vertical bar"', $accessibleMathml);
        $t->true(!str_contains($mathml . $readerMathml, '<mi>\\wpnorm</mi>') && !str_contains($mathml . $readerMathml, '<mi>\\wpinner</mi>'));
        $t->true(!str_contains($mathml, '<mo>*</mo>') && !str_contains($mathml, '<mo>[</mo>'));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiterX{\\bad}[1]{\\input{secret}}{\\rvert}{#1}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiterX{\\bad}[1]{\\lvert}{\\rvert}{#2}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiterX{\\bad}[1]{\\lvert}{\\rvert}{#1 #}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\wpinner[\\small]{x}{y}', false, $macros));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\wpinner*{x}', false, $macros));
    },
    'captures bounded declared paired delimiter xpp prefix suffix templates for mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\DeclarePairedDelimiterXPP{\\wprelated}[2]{\\alpha\\,}{\\lbrack}{\\rbrack}{\\,\\omega}{#1 \\mid #2}',
            '\\DeclarePairedDelimiterXPP\\wpannotated[1]{\\beta\\,}{\\lvert}{\\rvert}{\\,\\gamma}{#1}',
            '',
            '$\\wprelated{p_i}{m_i} + \\wpannotated{x_i}$',
        ]));
        $firstDeclaration = $document->children[0];
        $secondDeclaration = $document->children[1];
        $math = $document->children[2]->children[0];
        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\\wprelated*{p_i}{m_i} + \\wprelated[\\Big]{q_i}{r_i}', true, $macros);
        $readerMathml = $converter->mathMlFor($math);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\wprelated{x}{y}', false, $macros);

        $t->same('raw_tex', $firstDeclaration->type);
        $t->same('DeclarePairedDelimiterXPP', $firstDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiterXPP{\\wprelated}[2]{\\alpha\\,}{\\lbrack}{\\rbrack}{\\,\\omega}{#1 \\mid #2}', $firstDeclaration->attr('tex'));
        $t->same('raw_tex', $secondDeclaration->type);
        $t->same('DeclarePairedDelimiterXPP', $secondDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiterXPP\\wpannotated[1]{\\beta\\,}{\\lvert}{\\rvert}{\\,\\gamma}{#1}', $secondDeclaration->attr('tex'));
        $t->same([
            'wprelated' => ['arity' => 2, 'template' => '\\alpha\\, \\left\\lbrack #1 \\mid #2 \\right\\rbrack \\,\\omega'],
            'wpannotated' => ['arity' => 1, 'template' => '\\beta\\, \\left\\lvert #1 \\right\\rvert \\,\\gamma'],
        ], $macros);
        $t->same('\\alpha\\, \\left\\lbrack p_i \\mid m_i \\right\\rbrack \\,\\omega + \\beta\\, \\left\\lvert x_i \\right\\rvert \\,\\gamma', $math->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mi>α</mi><mspace width="0.1667em"></mspace><mo fence="true" stretchy="true">[</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∣</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">]</mo><mspace width="0.1667em"></mspace><mi>ω</mi>', $mathml);
        $t->contains('<mi>α</mi><mspace width="0.1667em"></mspace><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">[</mo><msub><mi>q</mi><mi>i</mi></msub><mo>∣</mo><msub><mi>r</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">]</mo><mspace width="0.1667em"></mspace><mi>ω</mi>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\wprelated*{p_i}{m_i} + \\wprelated[\\Big]{q_i}{r_i}</annotation>', $mathml);
        $t->contains('<mi>β</mi><mspace width="0.1667em"></mspace><mo fence="true" stretchy="true">|</mo><msub><mi>x</mi><mi>i</mi></msub><mo fence="true" stretchy="true">|</mo><mspace width="0.1667em"></mspace><mi>γ</mi>', $readerMathml);
        $t->contains('alttext="alpha space left bracket x divides y right bracket space omega"', $accessibleMathml);
        $t->true(!str_contains($mathml . $readerMathml, '<mi>\\wprelated</mi>') && !str_contains($mathml . $readerMathml, '<mi>\\wpannotated</mi>'));
        $t->true(!str_contains($mathml, '<mo>*</mo>') && !str_contains($mathml, '<mo>[</mo>'));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiterXPP{\\bad}[1]{#1}{\\lvert}{\\rvert}{}{#1}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiterXPP{\\bad}[1]{}{\\input{secret}}{\\rvert}{}{#1}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->macroDefinitionsFromDocument(new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\DeclarePairedDelimiterXPP{\\bad}[1]{}{\\lvert}{\\rvert}{#1}{#1}']),
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\wprelated[\\small]{x}{y}', false, $macros));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\wprelated*{x}', false, $macros));
    },
    'captures bounded raw tex declarations with nested balanced templates from markdown' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = (new MarkdownReader())->read(implode("\n", [
            '\\newcommand{\\wpnestedscore}[1]{\\operatorname{nested\\,review}_{#1}}',
            '\\DeclarePairedDelimiterX{\\wpfilter}[2]{\\langle}{\\rangle}{#1 , \\operatorname{media}_{#2}}',
            '\\DeclarePairedDelimiterXPP{\\wpwrapped}[1]{\\operatorname{pre}_{0}}{\\lbrack}{\\rbrack}{\\operatorname{suf}_{1}}{#1}',
            '',
            '$\\wpnestedscore{p_i} + \\wpfilter{p_i}{m_i} + \\wpwrapped{q_i}$',
        ]));
        $firstDeclaration = $document->children[0];
        $secondDeclaration = $document->children[1];
        $thirdDeclaration = $document->children[2];
        $math = $document->children[3]->children[0];
        $macros = $converter->macroDefinitionsFromDocument($document);
        $mathml = $converter->texToMathMl('\\wpnestedscore{p_i} + \\wpfilter{p_i}{m_i} + \\wpwrapped{q_i}', true, $macros);
        $readerMathml = $converter->mathMlFor($math);

        $t->same('raw_tex', $firstDeclaration->type);
        $t->same('newcommand', $firstDeclaration->attr('command'));
        $t->same('\\newcommand{\\wpnestedscore}[1]{\\operatorname{nested\\,review}_{#1}}', $firstDeclaration->attr('tex'));
        $t->same('raw_tex', $secondDeclaration->type);
        $t->same('DeclarePairedDelimiterX', $secondDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiterX{\\wpfilter}[2]{\\langle}{\\rangle}{#1 , \\operatorname{media}_{#2}}', $secondDeclaration->attr('tex'));
        $t->same('raw_tex', $thirdDeclaration->type);
        $t->same('DeclarePairedDelimiterXPP', $thirdDeclaration->attr('command'));
        $t->same('\\DeclarePairedDelimiterXPP{\\wpwrapped}[1]{\\operatorname{pre}_{0}}{\\lbrack}{\\rbrack}{\\operatorname{suf}_{1}}{#1}', $thirdDeclaration->attr('tex'));
        $t->same([
            'wpnestedscore' => ['arity' => 1, 'template' => '\\operatorname{nested\\,review}_{#1}'],
            'wpfilter' => ['arity' => 2, 'template' => '\\left\\langle #1 , \\operatorname{media}_{#2} \\right\\rangle'],
            'wpwrapped' => ['arity' => 1, 'template' => '\\operatorname{pre}_{0} \\left\\lbrack #1 \\right\\rbrack \\operatorname{suf}_{1}'],
        ], $macros);
        $t->same('\\operatorname{nested\\,review}_{p_i} + \\left\\langle p_i , \\operatorname{media}_{m_i} \\right\\rangle + \\operatorname{pre}_{0} \\left\\lbrack q_i \\right\\rbrack \\operatorname{suf}_{1}', $math->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<annotation encoding="application/x-tex">\\wpnestedscore{p_i} + \\wpfilter{p_i}{m_i} + \\wpwrapped{q_i}</annotation>', $mathml);
        $t->contains('<msub><mi>nested review</mi><msub><mi>p</mi><mi>i</mi></msub></msub>', $mathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><msub><mi>p</mi><mi>i</mi></msub><mo>,</mo><msub><mi>media</mi><msub><mi>m</mi><mi>i</mi></msub></msub><mo fence="true" stretchy="true">⟩</mo>', $mathml);
        $t->contains('<msub><mi>pre</mi><mn>0</mn></msub><mo fence="true" stretchy="true">[</mo><msub><mi>q</mi><mi>i</mi></msub><mo fence="true" stretchy="true">]</mo><msub><mi>suf</mi><mn>1</mn></msub>', $mathml);
        $t->contains('<msub><mi>nested review</mi><msub><mi>p</mi><mi>i</mi></msub></msub>', $readerMathml);
        $t->contains('<msub><mi>media</mi><msub><mi>m</mi><mi>i</mi></msub></msub>', $readerMathml);
        $t->true(!str_contains($mathml . $readerMathml, '<mi>\\wpnestedscore</mi>') && !str_contains($mathml . $readerMathml, '<mi>\\wpfilter</mi>') && !str_contains($mathml . $readerMathml, '<mi>\\wpwrapped</mi>'));
    },
    'rejects unsupported bounded tex macro definitions before mathml conversion' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad-name' => ['arity' => 1, 'template' => '#1']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 10, 'template' => '#1']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 1]]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 0, 'template' => '#1', 'optionalDefault' => 'fallback']]));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\bad{x}', false, ['bad' => ['arity' => 1, 'template' => '#1', 'optionalDefault' => ['fallback']]]));
    },
    'converts bounded tex delimiter commands and stretch fences to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $angleMathml = $converter->texToMathMl('\\langle x,y \\rangle');
        $fencedMathml = $converter->texToMathMl('\\left(\\frac{x}{y}\\right) + \\left\\{a\\right\\}', true);
        $invisibleFenceMathml = $converter->texToMathMl('\\left. x \\right|_{0}^{1}');

        $t->contains('<mo>⟨</mo><mi>x</mi><mo>,</mo><mi>y</mi><mo>⟩</mo>', $angleMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mfrac><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">)</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mi>a</mi><mo fence="true" stretchy="true">}</mo>', $fencedMathml);
        $t->contains('<mi>x</mi><msubsup><mo fence="true" stretchy="true">|</mo><mn>0</mn><mn>1</mn></msubsup>', $invisibleFenceMathml);
    },
    'converts bounded tex ceiling floor bracket and norm delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $plainMathml = $converter->texToMathMl('\\lceil x/y \\rceil + \\lfloor n/2 \\rfloor + \\lbrack p_i \\rbrack', true);
        $fencedMathml = $converter->texToMathMl('\\left\\lVert p_i + m_i \\right\\rVert + \\left\\lceil \\frac{x}{y} \\right\\rfloor');
        $sizedMathml = $converter->texToMathMl('\\Bigl\\lVert v \\Bigr\\rVert + \\bigl\\lbrack x \\bigr\\rbrack');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\lceil x \\rceil + \\lfloor y \\rfloor');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $plainMathml);
        $t->contains('<mo>⌈</mo><mi>x</mi><mo>/</mo><mi>y</mi><mo>⌉</mo><mo>+</mo><mo>⌊</mo><mi>n</mi><mo>/</mo><mn>2</mn><mo>⌋</mo>', $plainMathml);
        $t->contains('<mo>[</mo><msub><mi>p</mi><mi>i</mi></msub><mo>]</mo>', $plainMathml);
        $t->contains('<annotation encoding="application/x-tex">\\lceil x/y \\rceil + \\lfloor n/2 \\rfloor + \\lbrack p_i \\rbrack</annotation>', $plainMathml);
        $t->contains('<mo fence="true" stretchy="true">‖</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">‖</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">⌈</mo><mfrac><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">⌋</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">‖</mo><mi>v</mi><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">‖</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">[</mo><mi>x</mi><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">]</mo>', $sizedMathml);
        $t->contains('alttext="left ceiling x right ceiling plus left floor y right floor"', $accessibleMathml);
    },
    'converts bounded tex double bracket and corner delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $plainMathml = $converter->texToMathMl('\\llbracket p_i \\rrbracket + \\ulcorner x \\urcorner', true);
        $fencedMathml = $converter->texToMathMl('\\left\\llbracket p_i + m_i \\right\\rrbracket + \\left\\ulcorner x/y \\right\\urcorner');
        $sizedMathml = $converter->texToMathMl('\\Bigl\\llbracket n \\Bigr\\rrbracket + \\bigl\\ulcorner q \\bigr\\urcorner');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\llbracket x \\rrbracket + \\ulcorner y \\urcorner');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $plainMathml);
        $t->contains('<mo>⟦</mo><msub><mi>p</mi><mi>i</mi></msub><mo>⟧</mo><mo>+</mo><mo>⌜</mo><mi>x</mi><mo>⌝</mo>', $plainMathml);
        $t->contains('<annotation encoding="application/x-tex">\\llbracket p_i \\rrbracket + \\ulcorner x \\urcorner</annotation>', $plainMathml);
        $t->true(!str_contains($plainMathml, '<mi>\\llbracket</mi>'));
        $t->true(!str_contains($plainMathml, '<mi>\\ulcorner</mi>'));
        $t->contains('<mo fence="true" stretchy="true">⟦</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">⟧</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">⌜</mo><mi>x</mi><mo>/</mo><mi>y</mi><mo fence="true" stretchy="true">⌝</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟦</mo><mi>n</mi><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟧</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">⌜</mo><mi>q</mi><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">⌝</mo>', $sizedMathml);
        $t->contains('alttext="left double bracket x right double bracket plus upper left corner y upper right corner"', $accessibleMathml);
        $t->contains('intent="row(left_double_bracket,x,right_double_bracket,plus,upper_left_corner,y,upper_right_corner)"', $accessibleMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left\\llbracket x \\right\\unknowncorner'));
    },
    'converts bounded tex tortoise shell delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $plainMathml = $converter->texToMathMl('\\lbrbrak p_i \\rbrbrak + \\Lbrbrak q \\Rbrbrak', true);
        $fencedMathml = $converter->texToMathMl('\\left\\lbrbrak p_i + m_i \\right\\rbrbrak + \\left\\Lbrbrak x/y \\right\\Rbrbrak');
        $sizedMathml = $converter->texToMathMl('\\Bigl\\lbrbrak n \\Bigr\\rbrbrak + \\bigl\\Lbrbrak q \\bigr\\Rbrbrak');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\lbrbrak x \\rbrbrak + \\Lbrbrak y \\Rbrbrak');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $plainMathml);
        $t->contains('<mo>〔</mo><msub><mi>p</mi><mi>i</mi></msub><mo>〕</mo><mo>+</mo><mo>〘</mo><mi>q</mi><mo>〙</mo>', $plainMathml);
        $t->contains('<annotation encoding="application/x-tex">\\lbrbrak p_i \\rbrbrak + \\Lbrbrak q \\Rbrbrak</annotation>', $plainMathml);
        $t->true(!str_contains($plainMathml, '<mi>\\lbrbrak</mi>'));
        $t->true(!str_contains($plainMathml, '<mi>\\Lbrbrak</mi>'));
        $t->contains('<mo fence="true" stretchy="true">〔</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">〕</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">〘</mo><mi>x</mi><mo>/</mo><mi>y</mi><mo fence="true" stretchy="true">〙</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">〔</mo><mi>n</mi><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">〕</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">〘</mo><mi>q</mi><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">〙</mo>', $sizedMathml);
        $t->contains('alttext="left tortoise shell bracket x right tortoise shell bracket plus left white tortoise shell bracket y right white tortoise shell bracket"', $accessibleMathml);
        $t->contains('intent="row(left_tortoise_shell_bracket,x,right_tortoise_shell_bracket,plus,left_white_tortoise_shell_bracket,y,right_white_tortoise_shell_bracket)"', $accessibleMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left\\lbrbrak x \\right\\unknownbrbrak'));
    },
    'converts bounded tex arrow group and moustache delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $arrowFenceMathml = $converter->texToMathMl('\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow', true);
        $groupMathml = $converter->texToMathMl('\\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache + \\arrowvert q \\Arrowvert r \\bracevert');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\left\\uparrow x \\right\\downarrow');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $arrowFenceMathml);
        $t->contains('<mo fence="true" stretchy="true">↑</mo><msub><mi>x</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">⇕</mo><msub><mi>y</mi><mi>i</mi></msub><mo fence="true" stretchy="true">↓</mo>', $arrowFenceMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⇑</mo><mi>z</mi><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⇓</mo>', $arrowFenceMathml);
        $t->contains('<annotation encoding="application/x-tex">\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow</annotation>', $arrowFenceMathml);
        $t->contains('<mo>⟮</mo><msub><mi>p</mi><mi>i</mi></msub><mo>⟯</mo>', $groupMathml);
        $t->contains('<mo fence="true" stretchy="true">⎰</mo><mi>a</mi><mo fence="true" stretchy="true">⎱</mo>', $groupMathml);
        $t->contains('<mo>|</mo><mi>q</mi><mo>‖</mo><mi>r</mi><mo>⎪</mo>', $groupMathml);
        $t->contains('alttext="up arrow x down arrow"', $accessibleMathml);
    },
    'converts bounded tex sized delimiter commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $sizedMathml = $converter->texToMathMl('\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\big|x\\big|', true);
        $middleMathml = $converter->texToMathMl('\\left\\{x \\bigm| x \\in S\\right\\} + \\Bigg/ y \\Bigg/');
        $invisibleMathml = $converter->texToMathMl('\\big. x \\Biggr]');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">(</mo><msub><mi>p</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">)</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟨</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟩</mo>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">|</mo><mi>x</mi><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">|</mo>', $sizedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\big|x\\big|</annotation>', $sizedMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mi>x</mi><mo fence="true" stretchy="true" separator="true" minsize="1.2em" maxsize="1.2em">|</mo><mi>x</mi><mo>∈</mo><mi>S</mi><mo fence="true" stretchy="true">}</mo>', $middleMathml);
        $t->contains('<mo fence="true" stretchy="true" minsize="3em" maxsize="3em">/</mo><mi>y</mi><mo fence="true" stretchy="true" minsize="3em" maxsize="3em">/</mo>', $middleMathml);
        $t->contains('<mi>x</mi><mo fence="true" stretchy="true" minsize="3em" maxsize="3em">]</mo>', $invisibleMathml);
    },
    'converts bounded tex middle delimiters to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $middleMathml = $converter->texToMathMl('\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle', true);
        $invisibleLeftMathml = $converter->texToMathMl('\\left. a \\middle\\vert b \\right]');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $middleMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><msub><mi>p</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">|</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi><mo fence="true" stretchy="true">}</mo>', $middleMathml);
        $t->contains('<mo fence="true" stretchy="true">⟨</mo><mi>x</mi><mo fence="true" stretchy="true" separator="true">/</mo><mi>y</mi><mo fence="true" stretchy="true">⟩</mo>', $middleMathml);
        $t->contains('<annotation encoding="application/x-tex">\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle</annotation>', $middleMathml);
        $t->contains('<mi>a</mi><mo fence="true" stretchy="true" separator="true">|</mo><mi>b</mi><mo fence="true" stretchy="true">]</mo>', $invisibleLeftMathml);
    },
    'converts bounded tex large operators functions and operator names to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $operatorMathml = $converter->texToMathMl('\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\int_{0}^{1} f(x) dx', true);
        $functionMathml = $converter->texToMathMl('\\sin^2 \\theta + \\log_{10} x + \\prod_{k=1}^{3} k');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $operatorMathml);
        $t->contains('<msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup>', $operatorMathml);
        $t->contains('<mi>migrate</mi><mo>⁡</mo><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $operatorMathml);
        $t->contains('<msubsup><mo>∫</mo><mn>0</mn><mn>1</mn></msubsup><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo><mi>d</mi><mi>x</mi>', $operatorMathml);
        $t->contains('<msup><mi>sin</mi><mn>2</mn></msup><mo>⁡</mo><mi>θ</mi>', $functionMathml);
        $t->contains('<msub><mi>log</mi><mn>10</mn></msub><mo>⁡</mo><mi>x</mi><mo>+</mo><msubsup><mo>∏</mo><mrow><mi>k</mi><mo>=</mo><mn>1</mn></mrow><mn>3</mn></msubsup><mi>k</mi>', $functionMathml);
    },
    'converts bounded tex large operator aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $setMathml = $converter->texToMathMl('\\bigcup_{i=1}^{n} A_i + \\bigcap_{j} B_j + \\coprod\\limits_{k=0}^{m} C_k', true);
        $integralMathml = $converter->texToMathMl('\\iint_D f(x,y) dx dy + \\iiint_V g + \\oint_C h + \\oiint_S q + \\oiiint_W r');
        $circledMathml = $converter->texToMathMl('\\bigoplus_i G_i + \\bigotimes_j H_j + \\bigodot_k Z_k + \\bigsqcup_{r} Q_r + \\bigvee P + \\bigwedge R');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\bigcup_i A_i + \\iint_D f');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $setMathml);
        $t->contains('<msubsup><mo>⋃</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>A</mi><mi>i</mi></msub>', $setMathml);
        $t->contains('<msub><mo>⋂</mo><mi>j</mi></msub><msub><mi>B</mi><mi>j</mi></msub>', $setMathml);
        $t->contains('<munderover><mo>∐</mo><mrow><mi>k</mi><mo>=</mo><mn>0</mn></mrow><mi>m</mi></munderover><msub><mi>C</mi><mi>k</mi></msub>', $setMathml);
        $t->contains('<annotation encoding="application/x-tex">\\bigcup_{i=1}^{n} A_i + \\bigcap_{j} B_j + \\coprod\\limits_{k=0}^{m} C_k</annotation>', $setMathml);
        $t->contains('<msub><mo>∬</mo><mi>D</mi></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>,</mo><mi>y</mi><mo>)</mo><mi>d</mi><mi>x</mi><mi>d</mi><mi>y</mi>', $integralMathml);
        $t->contains('<msub><mo>∭</mo><mi>V</mi></msub><mi>g</mi><mo>+</mo><msub><mo>∮</mo><mi>C</mi></msub><mi>h</mi><mo>+</mo><msub><mo>∯</mo><mi>S</mi></msub><mi>q</mi><mo>+</mo><msub><mo>∰</mo><mi>W</mi></msub><mi>r</mi>', $integralMathml);
        $t->contains('<msub><mo>⨁</mo><mi>i</mi></msub><msub><mi>G</mi><mi>i</mi></msub><mo>+</mo><msub><mo>⨂</mo><mi>j</mi></msub><msub><mi>H</mi><mi>j</mi></msub>', $circledMathml);
        $t->contains('<msub><mo>⨀</mo><mi>k</mi></msub><msub><mi>Z</mi><mi>k</mi></msub><mo>+</mo><msub><mo>⨆</mo><mi>r</mi></msub><msub><mi>Q</mi><mi>r</mi></msub><mo>+</mo><mo>⋁</mo><mi>P</mi><mo>+</mo><mo>⋀</mo><mi>R</mi>', $circledMathml);
        $t->contains('big union', $accessibleMathml);
        $t->contains('double integral', $accessibleMathml);
        $t->contains('big_union', $accessibleMathml);
        $t->true(!str_contains($setMathml . $integralMathml . $circledMathml, '<mi>\\bigcup</mi>'));
        $t->true(!str_contains($setMathml . $integralMathml . $circledMathml, '<mi>\\iint</mi>'));
    },
    'converts bounded tex binary operator and relation aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $circledMathml = $converter->texToMathMl('a \\oplus b + c \\ominus d + e \\otimes f + g \\oslash h + i \\odot j', true);
        $operatorMathml = $converter->texToMathMl('x \\bullet y + p \\circ q + r \\star s + u \\diamond v + m \\div n + a \\mp b');
        $relationMathml = $converter->texToMathMl('A \\asymp B + C \\bowtie D + H \\vdash I + J \\dashv K + U \\smile V + W \\frown Z');
        $accessibleMathml = $converter->texToAccessibleMathMl('a \\oplus b + A \\asymp B');
        $combinedMathml = $circledMathml . $operatorMathml . $relationMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $circledMathml);
        $t->contains('<mi>a</mi><mo>⊕</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⊖</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>⊗</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>⊘</mo><mi>h</mi><mo>+</mo><mi>i</mi><mo>⊙</mo><mi>j</mi>', $circledMathml);
        $t->contains('<annotation encoding="application/x-tex">a \\oplus b + c \\ominus d + e \\otimes f + g \\oslash h + i \\odot j</annotation>', $circledMathml);
        $t->contains('<mi>x</mi><mo>∙</mo><mi>y</mi><mo>+</mo><mi>p</mi><mo>∘</mo><mi>q</mi><mo>+</mo><mi>r</mi><mo>⋆</mo><mi>s</mi><mo>+</mo><mi>u</mi><mo>⋄</mo><mi>v</mi><mo>+</mo><mi>m</mi><mo>÷</mo><mi>n</mi><mo>+</mo><mi>a</mi><mo>∓</mo><mi>b</mi>', $operatorMathml);
        $t->contains('<annotation encoding="application/x-tex">x \\bullet y + p \\circ q + r \\star s + u \\diamond v + m \\div n + a \\mp b</annotation>', $operatorMathml);
        $t->contains('<mi>A</mi><mo>≍</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⋈</mo><mi>D</mi><mo>+</mo><mi>H</mi><mo>⊢</mo><mi>I</mi><mo>+</mo><mi>J</mi><mo>⊣</mo><mi>K</mi><mo>+</mo><mi>U</mi><mo>⌣</mo><mi>V</mi><mo>+</mo><mi>W</mi><mo>⌢</mo><mi>Z</mi>', $relationMathml);
        $t->contains('<annotation encoding="application/x-tex">A \\asymp B + C \\bowtie D + H \\vdash I + J \\dashv K + U \\smile V + W \\frown Z</annotation>', $relationMathml);
        $t->contains('circled plus', $accessibleMathml);
        $t->contains('asymptotically equal to', $accessibleMathml);
        $t->contains('circled_plus', $accessibleMathml);
        $t->contains('asymptotically_equal_to', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\oplus</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\bullet</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\asymp</mi>'));
    },
    'converts bounded generated texmath operator and relation aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $boxedMathml = $converter->texToMathMl('a \\dotplus b + c \\boxplus d + e \\boxminus f + g \\boxtimes h + i \\boxdot j', true);
        $squareRelationMathml = $converter->texToMathMl('A \\sqsubset B + C \\sqsupset D + E \\sqsubseteq F + G \\sqsupseteq H');
        $approxRelationMathml = $converter->texToMathMl('x \\lesssim y + u \\gtrsim v + p \\lessapprox q + r \\gtrapprox s + m \\curlyeqprec n + o \\curlyeqsucc p');
        $arrowRelationMathml = $converter->texToMathMl('p \\Bumpeq q + r \\bumpeq s + x \\rightsquigarrow y + a \\nRightarrow b + f \\twoheadrightarrow g + h \\hookrightarrow i + m \\nleq n + u \\ngeq v');
        $accessibleMathml = $converter->texToAccessibleMathMl('a \\boxplus b + x \\rightsquigarrow y + m \\nleq n');
        $combinedMathml = $boxedMathml . $squareRelationMathml . $approxRelationMathml . $arrowRelationMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $boxedMathml);
        $t->contains('<mi>a</mi><mo>∔</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⊞</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>⊟</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>⊠</mo><mi>h</mi><mo>+</mo><mi>i</mi><mo>⊡</mo><mi>j</mi>', $boxedMathml);
        $t->contains('<annotation encoding="application/x-tex">a \\dotplus b + c \\boxplus d + e \\boxminus f + g \\boxtimes h + i \\boxdot j</annotation>', $boxedMathml);
        $t->contains('<mi>A</mi><mo>⊏</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⊐</mo><mi>D</mi><mo>+</mo><mi>E</mi><mo>⊑</mo><mi>F</mi><mo>+</mo><mi>G</mi><mo>⊒</mo><mi>H</mi>', $squareRelationMathml);
        $t->contains('<mi>x</mi><mo>≲</mo><mi>y</mi><mo>+</mo><mi>u</mi><mo>≳</mo><mi>v</mi><mo>+</mo><mi>p</mi><mo>⪅</mo><mi>q</mi><mo>+</mo><mi>r</mi><mo>⪆</mo><mi>s</mi><mo>+</mo><mi>m</mi><mo>⋞</mo><mi>n</mi><mo>+</mo><mi>o</mi><mo>⋟</mo><mi>p</mi>', $approxRelationMathml);
        $t->contains('<mi>p</mi><mo>≎</mo><mi>q</mi><mo>+</mo><mi>r</mi><mo>≏</mo><mi>s</mi><mo>+</mo><mi>x</mi><mo>⇝</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>⇏</mo><mi>b</mi><mo>+</mo><mi>f</mi><mo>↠</mo><mi>g</mi><mo>+</mo><mi>h</mi><mo>↪</mo><mi>i</mi><mo>+</mo><mi>m</mi><mo>≰</mo><mi>n</mi><mo>+</mo><mi>u</mi><mo>≱</mo><mi>v</mi>', $arrowRelationMathml);
        $t->contains('<annotation encoding="application/x-tex">p \\Bumpeq q + r \\bumpeq s + x \\rightsquigarrow y + a \\nRightarrow b + f \\twoheadrightarrow g + h \\hookrightarrow i + m \\nleq n + u \\ngeq v</annotation>', $arrowRelationMathml);
        $t->contains('alttext="a box plus b plus x right squiggle arrow y plus m not less than or equal to n"', $accessibleMathml);
        $t->contains('intent="row(a,box_plus,b,plus,x,right_squiggle_arrow,y,plus,m,not_less_than_or_equal_to,n)"', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\dotplus</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\sqsubset</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\lesssim</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\rightsquigarrow</mi>'));
    },
    'converts bounded texmath unicode symbol map aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $arrowMathml = $converter->texToMathMl('\\twoheadleftarrow + \\hookleftarrow + \\nleftarrow + \\nrightarrow + \\nleftrightarrow', true);
        $setMathml = $converter->texToMathMl('A \\nsubset B + C \\nsupset D + \\AC');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\AC + A \\nleftarrow B + C \\nsubset D');
        $combinedMathml = $arrowMathml . $setMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $arrowMathml);
        $t->contains('<mo>↞</mo><mo>+</mo><mo>↩</mo><mo>+</mo><mo>↚</mo><mo>+</mo><mo>↛</mo><mo>+</mo><mo>↮</mo>', $arrowMathml);
        $t->contains('<annotation encoding="application/x-tex">\\twoheadleftarrow + \\hookleftarrow + \\nleftarrow + \\nrightarrow + \\nleftrightarrow</annotation>', $arrowMathml);
        $t->contains('<mi>A</mi><mo>⊄</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⊅</mo><mi>D</mi><mo>+</mo><mo>⏦</mo>', $setMathml);
        $t->contains('<annotation encoding="application/x-tex">A \\nsubset B + C \\nsupset D + \\AC</annotation>', $setMathml);
        $t->contains('alttext="AC current plus A not left arrow B plus C not subset D"', $accessibleMathml);
        $t->contains('intent="row(ac_current,plus,a,not_left_arrow,b,plus,c,not_subset,d)"', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\twoheadleftarrow</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\hookleftarrow</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\nleftarrow</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\AC</mi>'));
    },
    'converts bounded texmath relation and harpoon aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $orderMathml = $converter->texToMathMl('A \\prec B + C \\succ D + E \\ll F + G \\gg H + P \\precsim Q + R \\succsim S + U \\subsetneq V + W \\supsetneq X', true);
        $arrowMathml = $converter->texToMathMl('x \\nearrow y + a \\searrow b + c \\swarrow d + e \\nwarrow f + L \\leftharpoonup M + N \\rightharpoondown O + P \\rightleftharpoons Q + R \\leftrightharpoons S');
        $logicMathml = $converter->texToMathMl('p \\because q + f \\multimap g + h \\pitchfork i + x \\leadsto y');
        $accessibleMathml = $converter->texToAccessibleMathMl('A \\prec B + x \\nearrow y + p \\because q');
        $combinedMathml = $orderMathml . $arrowMathml . $logicMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $orderMathml);
        $t->contains('<mi>A</mi><mo>≺</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>≻</mo><mi>D</mi><mo>+</mo><mi>E</mi><mo>≪</mo><mi>F</mi><mo>+</mo><mi>G</mi><mo>≫</mo><mi>H</mi>', $orderMathml);
        $t->contains('<mi>P</mi><mo>≾</mo><mi>Q</mi><mo>+</mo><mi>R</mi><mo>≿</mo><mi>S</mi><mo>+</mo><mi>U</mi><mo>⊊</mo><mi>V</mi><mo>+</mo><mi>W</mi><mo>⊋</mo><mi>X</mi>', $orderMathml);
        $t->contains('<annotation encoding="application/x-tex">A \\prec B + C \\succ D + E \\ll F + G \\gg H + P \\precsim Q + R \\succsim S + U \\subsetneq V + W \\supsetneq X</annotation>', $orderMathml);
        $t->contains('<mi>x</mi><mo>↗</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>↘</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>↙</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>↖</mo><mi>f</mi>', $arrowMathml);
        $t->contains('<mi>L</mi><mo>↼</mo><mi>M</mi><mo>+</mo><mi>N</mi><mo>⇁</mo><mi>O</mi><mo>+</mo><mi>P</mi><mo>⇌</mo><mi>Q</mi><mo>+</mo><mi>R</mi><mo>⇋</mo><mi>S</mi>', $arrowMathml);
        $t->contains('<annotation encoding="application/x-tex">x \\nearrow y + a \\searrow b + c \\swarrow d + e \\nwarrow f + L \\leftharpoonup M + N \\rightharpoondown O + P \\rightleftharpoons Q + R \\leftrightharpoons S</annotation>', $arrowMathml);
        $t->contains('<mi>p</mi><mo>∵</mo><mi>q</mi><mo>+</mo><mi>f</mi><mo>⊸</mo><mi>g</mi><mo>+</mo><mi>h</mi><mo>⋔</mo><mi>i</mi><mo>+</mo><mi>x</mi><mo>⤳</mo><mi>y</mi>', $logicMathml);
        $t->contains('alttext="A precedes B plus x north east arrow y plus p because q"', $accessibleMathml);
        $t->contains('intent="row(a,precedes,b,plus,x,north_east_arrow,y,plus,p,because,q)"', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\prec</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\nearrow</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\because</mi>'));
    },
    'converts bounded texmath symbol override aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $identifierMathml = $converter->texToMathMl('\\arg z + \\hbar\\omega + \\digamma + \\varnothing', true);
        $binaryMathml = $converter->texToMathMl('a \\dag b + c \\ddag d + e \\barwedge f + g \\wr h');
        $relationMathml = $converter->texToMathMl('A \\lhd B + C \\rhd D + E \\unlhd F + G \\unrhd H + I \\Join J + K \\eqcolon L + M \\longmapsto N');
        $shapeMathml = $converter->texToMathMl('\\Box + \\Diamond + \\lozenge + \\blacklozenge + \\blacksquare + \\blacktriangleleft + \\blacktriangleright');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\hbar + A \\longmapsto B + \\blacklozenge');
        $combinedMathml = $identifierMathml . $binaryMathml . $relationMathml . $shapeMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $identifierMathml);
        $t->contains('<mi>arg</mi><mo>⁡</mo><mi>z</mi><mo>+</mo><mi>ℏ</mi><mi>ω</mi><mo>+</mo><mi>ϝ</mi><mo>+</mo><mo>⌀</mo>', $identifierMathml);
        $t->contains('<annotation encoding="application/x-tex">\\arg z + \\hbar\\omega + \\digamma + \\varnothing</annotation>', $identifierMathml);
        $t->contains('<mi>a</mi><mo>†</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>‡</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>⌅</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>≀</mo><mi>h</mi>', $binaryMathml);
        $t->contains('<mi>A</mi><mo>⊲</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⊳</mo><mi>D</mi><mo>+</mo><mi>E</mi><mo>⊴</mo><mi>F</mi><mo>+</mo><mi>G</mi><mo>⊵</mo><mi>H</mi><mo>+</mo><mi>I</mi><mo>⋈</mo><mi>J</mi><mo>+</mo><mi>K</mi><mo>≕</mo><mi>L</mi><mo>+</mo><mi>M</mi><mo>⟼</mo><mi>N</mi>', $relationMathml);
        $t->contains('<mo>□</mo><mo>+</mo><mo>◇</mo><mo>+</mo><mo>◊</mo><mo>+</mo><mo>⬧</mo><mo>+</mo><mo>■</mo><mo>+</mo><mo>◂</mo><mo>+</mo><mo>▸</mo>', $shapeMathml);
        $t->contains('alttext="h bar plus A long maps to B plus black lozenge"', $accessibleMathml);
        $t->contains('intent="row(h_bar,plus,a,long_maps_to,b,plus,black_lozenge)"', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\hbar</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\dag</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\longmapsto</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\blacklozenge</mi>'));
    },
    'converts bounded texmath extended named and relation aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $identifierMathml = $converter->texToMathMl('\\beth + \\gimel + \\daleth + \\eth + \\imath + \\jmath + \\Finv + \\Game', true);
        $orderMathml = $converter->texToMathMl('a \\leqq b + c \\geqq d + e \\lneqq f + g \\gneqq h + i \\lessgtr j + k \\gtrless l + m \\lll n + o \\ggg p');
        $dottedMathml = $converter->texToMathMl('x \\doteq y + u \\Doteq v + p \\fallingdotseq q + r \\risingdotseq s + A \\triangleq B + C \\backsimeq D');
        $negatedMathml = $converter->texToMathMl('P \\nsubseteq Q + R \\nsupseteq S + x \\nmid y + u \\nparallel v + A \\nprec B + C \\nsucceq D');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\beth + a \\leqq b + x \\nparallel y');
        $combinedMathml = $identifierMathml . $orderMathml . $dottedMathml . $negatedMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $identifierMathml);
        $t->contains('<mi>ℶ</mi><mo>+</mo><mi>ℷ</mi><mo>+</mo><mi>ℸ</mi><mo>+</mo><mi>ð</mi><mo>+</mo><mi>ı</mi><mo>+</mo><mi>ȷ</mi><mo>+</mo><mi>Ⅎ</mi><mo>+</mo><mi>⅁</mi>', $identifierMathml);
        $t->contains('<annotation encoding="application/x-tex">\\beth + \\gimel + \\daleth + \\eth + \\imath + \\jmath + \\Finv + \\Game</annotation>', $identifierMathml);
        $t->contains('<mi>a</mi><mo>≦</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>≧</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>≨</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>≩</mo><mi>h</mi>', $orderMathml);
        $t->contains('<mi>i</mi><mo>≶</mo><mi>j</mi><mo>+</mo><mi>k</mi><mo>≷</mo><mi>l</mi><mo>+</mo><mi>m</mi><mo>⋘</mo><mi>n</mi><mo>+</mo><mi>o</mi><mo>⋙</mo><mi>p</mi>', $orderMathml);
        $t->contains('<mi>x</mi><mo>≐</mo><mi>y</mi><mo>+</mo><mi>u</mi><mo>≑</mo><mi>v</mi><mo>+</mo><mi>p</mi><mo>≒</mo><mi>q</mi><mo>+</mo><mi>r</mi><mo>≓</mo><mi>s</mi>', $dottedMathml);
        $t->contains('<mi>A</mi><mo>≜</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⋍</mo><mi>D</mi>', $dottedMathml);
        $t->contains('<mi>P</mi><mo>⊈</mo><mi>Q</mi><mo>+</mo><mi>R</mi><mo>⊉</mo><mi>S</mi><mo>+</mo><mi>x</mi><mo>∤</mo><mi>y</mi><mo>+</mo><mi>u</mi><mo>∦</mo><mi>v</mi>', $negatedMathml);
        $t->contains('<mi>A</mi><mo>⊀</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⋡</mo><mi>D</mi>', $negatedMathml);
        $t->contains('alttext="beth plus a less than over equal b plus x not parallel y"', $accessibleMathml);
        $t->contains('intent="row(beth,plus,a,less_than_over_equal,b,plus,x,not_parallel,y)"', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\beth</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\leqq</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\doteq</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\nsubseteq</mi>'));
    },
    'converts bounded texmath generated symbol map relation aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $orderMathml = $converter->texToMathMl('a \\lneq b + c \\gneq d + e \\lnsim f + g \\gnsim h + p \\precapprox q + r \\succapprox s + A \\subsetneqq B + C \\supsetneqq D', true);
        $logicMathml = $converter->texToMathMl('\\nexists x + p \\nvdash q + r \\nvDash s + t \\nVdash u + v \\nVDash w + a \\Vvdash b');
        $shapeMathml = $converter->texToMathMl('x \\varpropto y + U \\smallsetminus V + A \\backcong B + \\blacktriangle');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\nexists x + A \\varpropto B + \\blacktriangle');
        $combinedMathml = $orderMathml . $logicMathml . $shapeMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $orderMathml);
        $t->contains('<mi>a</mi><mo>⪇</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⪈</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>⋦</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>⋧</mo><mi>h</mi>', $orderMathml);
        $t->contains('<mi>p</mi><mo>⪷</mo><mi>q</mi><mo>+</mo><mi>r</mi><mo>⪸</mo><mi>s</mi><mo>+</mo><mi>A</mi><mo>⫋</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⫌</mo><mi>D</mi>', $orderMathml);
        $t->contains('<annotation encoding="application/x-tex">a \\lneq b + c \\gneq d + e \\lnsim f + g \\gnsim h + p \\precapprox q + r \\succapprox s + A \\subsetneqq B + C \\supsetneqq D</annotation>', $orderMathml);
        $t->contains('<mo>∄</mo><mi>x</mi><mo>+</mo><mi>p</mi><mo>⊬</mo><mi>q</mi><mo>+</mo><mi>r</mi><mo>⊭</mo><mi>s</mi>', $logicMathml);
        $t->contains('<mi>t</mi><mo>⊮</mo><mi>u</mi><mo>+</mo><mi>v</mi><mo>⊯</mo><mi>w</mi><mo>+</mo><mi>a</mi><mo>⊪</mo><mi>b</mi>', $logicMathml);
        $t->contains('<mi>x</mi><mo>∝</mo><mi>y</mi><mo>+</mo><mi>U</mi><mo>∖</mo><mi>V</mi><mo>+</mo><mi>A</mi><mo>≌</mo><mi>B</mi><mo>+</mo><mo>▴</mo>', $shapeMathml);
        $t->contains('alttext=', $accessibleMathml);
        $t->contains('alttext="∄ x plus A proportional to B plus ▴"', $accessibleMathml);
        $t->contains('intent=', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\lneq</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\gneq</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\precapprox</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\subsetneqq</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\nexists</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\nvdash</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\varpropto</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\blacktriangle</mi>'));
    },
    'converts bounded texmath variant greek and underbar aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $variantGreekMathml = $converter->texToMathMl('\\varGamma + \\varDelta + \\varTheta + \\varLambda + \\varXi + \\varPi + \\varSigma + \\varUpsilon + \\varPhi + \\varPsi + \\varOmega', true);
        $lowerVariantMathml = $converter->texToMathMl('\\varrho_i + \\varsigma + \\upUpsilon');
        $barMathml = $converter->texToMathMl('\\overbar{x_i + y_i} + \\underbar{\\operatorname{draft}} + \\overbar z_0');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\varDelta + \\varrho + \\overbar{x} + \\underbar{y}');
        $combinedMathml = $variantGreekMathml . $lowerVariantMathml . $barMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $variantGreekMathml);
        $t->contains('<mi>𝛤</mi><mo>+</mo><mi>𝛥</mi><mo>+</mo><mi>𝛩</mi><mo>+</mo><mi>𝛬</mi><mo>+</mo><mi>𝛯</mi><mo>+</mo><mi>𝛱</mi><mo>+</mo><mi>𝛴</mi><mo>+</mo><mi>𝛶</mi><mo>+</mo><mi>𝛷</mi><mo>+</mo><mi>𝛹</mi><mo>+</mo><mi>𝛺</mi>', $variantGreekMathml);
        $t->contains('<annotation encoding="application/x-tex">\\varGamma + \\varDelta + \\varTheta + \\varLambda + \\varXi + \\varPi + \\varSigma + \\varUpsilon + \\varPhi + \\varPsi + \\varOmega</annotation>', $variantGreekMathml);
        $t->contains('<msub><mi>𝜚</mi><mi>i</mi></msub><mo>+</mo><mi>𝜍</mi><mo>+</mo><mi>ϒ</mi>', $lowerVariantMathml);
        $t->contains('<annotation encoding="application/x-tex">\\varrho_i + \\varsigma + \\upUpsilon</annotation>', $lowerVariantMathml);
        $t->contains('<mover accent="true"><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mo>¯</mo></mover>', $barMathml);
        $t->contains('<munder accentunder="true"><mi>draft</mi><mo>̱</mo></munder><mo>+</mo><msub><mover accent="true"><mi>z</mi><mo>¯</mo></mover><mn>0</mn></msub>', $barMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overbar{x_i + y_i} + \\underbar{\\operatorname{draft}} + \\overbar z_0</annotation>', $barMathml);
        $t->contains('alttext="delta plus rho plus x over bar plus y under underbar"', $accessibleMathml);
        $t->contains('intent="row(delta,plus,rho,plus,over(x,bar),plus,under(y,underbar))"', $accessibleMathml);
        $t->true(!str_contains($combinedMathml, '<mi>\\varGamma</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\varrho</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\overbar</mi>'));
        $t->true(!str_contains($combinedMathml, '<mi>\\underbar</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overbar'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underbar'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underbar_1'));
    },
    'converts bounded tex explicit operator limits to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $limitsMathml = $converter->texToMathMl('\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\prod\\limits^{N} q', true);
        $nolimitsMathml = $converter->texToMathMl('\\int\\nolimits_{0}^{1} f(x) dx + \\sum\\nolimits_{j} a_j');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $limitsMathml);
        $t->contains('<munderover><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><msub><mi>p</mi><mi>i</mi></msub>', $limitsMathml);
        $t->contains('<munder><mo>lim</mo><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>', $limitsMathml);
        $t->contains('<mover><mo>∏</mo><mi>N</mi></mover><mi>q</mi>', $limitsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\prod\\limits^{N} q</annotation>', $limitsMathml);
        $t->contains('<msubsup><mo>∫</mo><mn>0</mn><mn>1</mn></msubsup><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo><mi>d</mi><mi>x</mi><mo>+</mo><msub><mo>∑</mo><mi>j</mi></msub><msub><mi>a</mi><mi>j</mi></msub>', $nolimitsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\int\\nolimits_{0}^{1} f(x) dx + \\sum\\nolimits_{j} a_j</annotation>', $nolimitsMathml);
    },
    'converts bounded tex sideset operator scripts to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $sidesetMathml = $converter->texToMathMl('\\sideset{_a^b}{_c^d}\\sum_{i=1}^{n} x_i + \\sideset{_{L}}{}\\prod q', true);
        $primeMathml = $converter->texToMathMl('\\sideset{}{\\prime}\\sum + \\sideset{}{\\prime\\prime}\\prod');
        $quadruplePrimeMathml = $converter->texToMathMl('\\sideset{}{\\prime\\prime\\prime\\prime}\\sum + \\sideset{}{\\prime\\prime\\prime\\prime\\prime}\\prod');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\sideset{_a^b}{_c^d}\\sum');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $sidesetMathml);
        $t->contains('<msubsup><mmultiscripts><mo>∑</mo><mi>c</mi><mi>d</mi><mprescripts/><mi>a</mi><mi>b</mi></mmultiscripts><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>x</mi><mi>i</mi></msub>', $sidesetMathml);
        $t->contains('<mmultiscripts><mo>∏</mo><mprescripts/><mi>L</mi><none/></mmultiscripts><mi>q</mi>', $sidesetMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sideset{_a^b}{_c^d}\\sum_{i=1}^{n} x_i + \\sideset{_{L}}{}\\prod q</annotation>', $sidesetMathml);
        $t->contains('<mmultiscripts><mo>∑</mo><none/><mo>′</mo></mmultiscripts><mo>+</mo><mmultiscripts><mo>∏</mo><none/><mo>″</mo></mmultiscripts>', $primeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sideset{}{\\prime}\\sum + \\sideset{}{\\prime\\prime}\\prod</annotation>', $primeMathml);
        $t->contains('<mmultiscripts><mo>∑</mo><none/><mo>⁗</mo></mmultiscripts><mo>+</mo><mmultiscripts><mo>∏</mo><none/><mrow><mo>⁗</mo><mo>′</mo></mrow></mmultiscripts>', $quadruplePrimeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sideset{}{\\prime\\prime\\prime\\prime}\\sum + \\sideset{}{\\prime\\prime\\prime\\prime\\prime}\\prod</annotation>', $quadruplePrimeMathml);
        $t->contains('alttext="sum post-sub c post-sup d pre-sub a pre-sup b"', $accessibleMathml);
        $t->contains('intent="multiscripts(sum,postsub(c),postsup(d),presub(a),presup(b))"', $accessibleMathml);
        $t->true(!str_contains($sidesetMathml, '<mi>\\sideset</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sideset{_a^b}\\sum'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sideset{_a}{_b}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sideset{a}{_b}\\sum'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sideset{_a_a}{_b}\\sum'));
    },
    'converts bounded tex prescript atoms to mathml multiscripts' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $prescriptMathml = $converter->texToMathMl('\\prescript{14}{6}{C} + \\prescript{\\text{pre}}{}{p_i} + \\prescript{}{L}{\\operatorname{score}}_j', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\prescript{14}{6}{C}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $prescriptMathml);
        $t->contains('<mmultiscripts><mi>C</mi><mprescripts/><mn>6</mn><mn>14</mn></mmultiscripts>', $prescriptMathml);
        $t->contains('<mmultiscripts><msub><mi>p</mi><mi>i</mi></msub><mprescripts/><none/><mtext>pre</mtext></mmultiscripts>', $prescriptMathml);
        $t->contains('<msub><mmultiscripts><mi>score</mi><mprescripts/><mi>L</mi><none/></mmultiscripts><mi>j</mi></msub>', $prescriptMathml);
        $t->contains('<annotation encoding="application/x-tex">\\prescript{14}{6}{C} + \\prescript{\\text{pre}}{}{p_i} + \\prescript{}{L}{\\operatorname{score}}_j</annotation>', $prescriptMathml);
        $t->contains('alttext="C pre-sub 6 pre-sup 14"', $accessibleMathml);
        $t->contains('intent="multiscripts(c,presub(6),presup(14))"', $accessibleMathml);
        $t->true(!str_contains($prescriptMathml, '<mi>\\prescript</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\prescript{14}{6}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\prescript{}{}{C}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\prescript{14}{6}{}'));
    },
    'converts bounded tex starred operator names and displaylimits to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $starredMathml = $converter->texToMathMl('\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname*{limsup}^{n} a_n', true);
        $displayLimitsMathml = $converter->texToMathMl('\\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j');
        $operatorWithLimitsMathml = $converter->texToMathMl('\\operatornamewithlimits{argmin}_{x \\in S}^{\\text{draft}} f(x) + \\operatornamewithlimits\\max_{j} q_j', true);
        $plainStarredMathml = $converter->texToMathMl('\\operatorname*{review} + x');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $starredMathml);
        $t->contains('<munderover><mi>argmax</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover><mo>⁡</mo><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $starredMathml);
        $t->contains('<mover><mi>limsup</mi><mi>n</mi></mover><mo>⁡</mo><msub><mi>a</mi><mi>n</mi></msub>', $starredMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname*{limsup}^{n} a_n</annotation>', $starredMathml);
        $t->contains('<munderover><mi>median</mi><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><mo>⁡</mo><msub><mi>p</mi><mi>i</mi></msub>', $displayLimitsMathml);
        $t->contains('<msub><mi>rank</mi><mi>j</mi></msub><mo>⁡</mo><msub><mi>q</mi><mi>j</mi></msub>', $displayLimitsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j</annotation>', $displayLimitsMathml);
        $t->contains('<munderover><mi>argmin</mi><mrow><mi>x</mi><mo>∈</mo><mi>S</mi></mrow><mtext>draft</mtext></munderover><mo>⁡</mo><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>', $operatorWithLimitsMathml);
        $t->contains('<munder><mi>max</mi><mi>j</mi></munder><mo>⁡</mo><msub><mi>q</mi><mi>j</mi></msub>', $operatorWithLimitsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatornamewithlimits{argmin}_{x \\in S}^{\\text{draft}} f(x) + \\operatornamewithlimits\\max_{j} q_j</annotation>', $operatorWithLimitsMathml);
        $t->true(!str_contains($operatorWithLimitsMathml, '<mi>\\operatornamewithlimits</mi>'));
        $t->contains('<mi>review</mi><mo>+</mo><mi>x</mi>', $plainStarredMathml);
    },
    'converts bounded tex unbraced operatorname tokens to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $charMathml = $converter->texToMathMl('\\operatorname x_i');
        $commandMathml = $converter->texToMathMl('\\operatorname\\alpha_i + \\operatorname\\leq p', true);
        $starredMathml = $converter->texToMathMl('\\operatorname*\\max_{i=1}^{n} p_i', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\operatorname\\alpha_i + \\operatorname*\\max_{j}^{n} p_j', true);

        $t->contains('<msub><mi>x</mi><mi>i</mi></msub>', $charMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname x_i</annotation>', $charMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $commandMathml);
        $t->contains('<msub><mi>α</mi><mi>i</mi></msub><mo>+</mo><mi>≤</mi><mi>p</mi>', $commandMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname\\alpha_i + \\operatorname\\leq p</annotation>', $commandMathml);
        $t->contains('<munderover><mi>max</mi><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><mo>⁡</mo><msub><mi>p</mi><mi>i</mi></msub>', $starredMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname*\\max_{i=1}^{n} p_i</annotation>', $starredMathml);
        $t->contains('alttext="alpha sub i plus max under j over n of p sub j"', $accessibleMathml);
        $t->contains('intent="row(subscript(alpha,i),plus,underover(max,j,n),of,subscript(p,j))"', $accessibleMathml);
        $t->contains('<annotation encoding="application/x-tex">\\operatorname\\alpha_i + \\operatorname*\\max_{j}^{n} p_j</annotation>', $accessibleMathml);
        $t->true(!str_contains($commandMathml, '<mi>\\operatorname</mi>'));
        $t->true(!str_contains($starredMathml, '<mi>\\operatorname</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname\\input'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname*^2'));
    },
    'converts bounded tex math class wrappers to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $classMathml = $converter->texToMathMl('\\mathop{\\operatorname{argmax}}\\limits_{p_i \\in P}^{\\text{draft}} f(p_i) + a \\mathrel{\\approx} b + x \\mathbin{\\cdot} y + \\mathord{0}', true);
        $fenceClassMathml = $converter->texToMathMl('\\mathopen{[}q_i\\mathclose{]} + f\\mathpunct{,}g + \\mathinner{\\frac{a}{b}}');
        $noLimitsMathml = $converter->texToMathMl('\\mathop{\\operatorname{rank}}\\nolimits_i q_i');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $classMathml);
        $t->contains('<munderover><mrow data-tex-math-class="operator"><mi>argmax</mi></mrow><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $classMathml);
        $t->contains('<mi>a</mi><mrow data-tex-math-class="relation"><mo>≈</mo></mrow><mi>b</mi><mo>+</mo><mi>x</mi><mrow data-tex-math-class="binary"><mo>⋅</mo></mrow><mi>y</mi><mo>+</mo><mrow data-tex-math-class="ordinary"><mn>0</mn></mrow>', $classMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathop{\\operatorname{argmax}}\\limits_{p_i \\in P}^{\\text{draft}} f(p_i) + a \\mathrel{\\approx} b + x \\mathbin{\\cdot} y + \\mathord{0}</annotation>', $classMathml);
        $t->contains('<mrow data-tex-math-class="open"><mo>[</mo></mrow><msub><mi>q</mi><mi>i</mi></msub><mrow data-tex-math-class="close"><mo>]</mo></mrow>', $fenceClassMathml);
        $t->contains('<mi>f</mi><mrow data-tex-math-class="punctuation"><mo>,</mo></mrow><mi>g</mi><mo>+</mo><mrow data-tex-math-class="inner"><mfrac><mi>a</mi><mi>b</mi></mfrac></mrow>', $fenceClassMathml);
        $t->contains('<msub><mrow data-tex-math-class="operator"><mi>rank</mi></mrow><mi>i</mi></msub><msub><mi>q</mi><mi>i</mi></msub>', $noLimitsMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathop'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathrel{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathbin_1'));
    },
    'summarizes bounded tex atom categories for plainmath prototype handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $tex = '\\mathop{\\operatorname{argmax}}\\limits_{p_i \\in P}^{\\text{draft}} f(p_i) + a \\mathrel{\\approx} b + x \\mathbin{\\cdot} y + \\mathopen{[}q\\mathclose{]} + f\\mathpunct{,}g + \\mathinner{\\frac{a}{b}} + \\sin \\theta';
        $mathmlBefore = $converter->texToMathMl($tex, true);
        $summary = $converter->texAtomCategorySummary($tex, true);
        $explicitByCategory = [];
        $inferredByCategory = [];

        foreach ($summary['atoms'] as $atom) {
            if ($atom['source'] === 'explicit-math-class') {
                $explicitByCategory[$atom['category']][] = $atom['text'];
            } else {
                $inferredByCategory[$atom['category']][] = $atom['text'];
            }
        }

        $t->same($tex, $summary['tex']);
        $t->same(true, $summary['display']);
        $t->same($mathmlBefore, $converter->texToMathMl($tex, true));
        $t->same(['Ord', 'Op', 'Bin', 'Rel', 'Open', 'Close', 'Pun', 'Inner'], $summary['atomCategories']);
        $t->true($summary['atomCount'] >= 24, 'summary should collect token and explicit math-class atoms');
        $t->true($summary['atomCategoryCounts']['Ord'] >= 10, 'summary should count ordinary identifiers and numbers');
        $t->true($summary['atomCategoryCounts']['Op'] >= 2, 'summary should count explicit and inferred operators');
        $t->true($summary['atomCategoryCounts']['Bin'] >= 4, 'summary should count binary operators');
        $t->true($summary['atomCategoryCounts']['Rel'] >= 2, 'summary should count relation operators');
        $t->true(in_array('argmax', $explicitByCategory['Op'] ?? [], true), 'explicit mathop should produce Op atom');
        $t->true(in_array('≈', $explicitByCategory['Rel'] ?? [], true), 'explicit mathrel should produce Rel atom');
        $t->true(in_array('⋅', $explicitByCategory['Bin'] ?? [], true), 'explicit mathbin should produce Bin atom');
        $t->true(in_array('[', $explicitByCategory['Open'] ?? [], true), 'explicit mathopen should produce Open atom');
        $t->true(in_array(']', $explicitByCategory['Close'] ?? [], true), 'explicit mathclose should produce Close atom');
        $t->true(in_array(',', $explicitByCategory['Pun'] ?? [], true), 'explicit mathpunct should produce Pun atom');
        $t->true(in_array('ab', $explicitByCategory['Inner'] ?? [], true), 'explicit mathinner should produce Inner atom');
        $t->true(in_array('sin', $inferredByCategory['Op'] ?? [], true), 'function tokens should infer Op atoms');
        $t->true(in_array('+', $inferredByCategory['Bin'] ?? [], true), 'plus tokens should infer Bin atoms');
        $t->true(in_array('∈', $inferredByCategory['Rel'] ?? [], true), 'membership tokens should infer Rel atoms');
        $t->true(in_array('θ', $inferredByCategory['Ord'] ?? [], true), 'identifier tokens should infer Ord atoms');
        $t->true(!str_contains($mathmlBefore, 'data-tex-atom'), 'summary prototype should not alter emitted MathML');
    },
    'converts bounded tex substack limits to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $substackMathml = $converter->texToMathMl('\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x > 0}} f(x)', true);
        $standaloneMathml = $converter->texToMathMl('\\substack{p_i \\\\ m_i}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $substackMathml);
        $t->contains('<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>', $substackMathml);
        $t->contains('<msub><mo>lim</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>x</mi><mo>→</mo><mn>0</mn></mtd></mtr><mtr><mtd><mi>x</mi><mo>&gt;</mo><mn>0</mn></mtd></mtr></mtable></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>', $substackMathml);
        $t->contains('<annotation encoding="application/x-tex">\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)</annotation>', $substackMathml);
        $t->contains('<mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr></mtable>', $standaloneMathml);
    },
    'converts bounded tex ams align gather and split environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignMathml = $converter->texToMathMl('\\begin{align}f(x) &= x^2 \\\\ g(x) &= x + 1\\end{align}', true);
        $gatherMathml = $converter->texToMathMl('\\begin{gathered}a+b \\\\ c+d\\end{gathered}');
        $splitMathml = $converter->texToMathMl('\\begin{split}S &= \\sum_{i=1}^{n} p_i \\\\ &= \\frac{a}{b}\\end{split}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><msup><mi>x</mi><mn>2</mn></msup></mtd></mtr><mtr><mtd><mi>g</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><mi>x</mi><mo>+</mo><mn>1</mn></mtd></mtr></mtable>', $alignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{align}f(x) &amp;= x^2 \\\\ g(x) &amp;= x + 1\\end{align}</annotation>', $alignMathml);
        $t->contains('<mtable columnalign="center"><mtr><mtd><mi>a</mi><mo>+</mo><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi><mo>+</mo><mi>d</mi></mtd></mtr></mtable>', $gatherMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><mi>S</mi></mtd><mtd><mo>=</mo><msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd></mtd><mtd><mo>=</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd></mtr></mtable>', $splitMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}</annotation>', $splitMathml);
    },
    'converts bounded tex alignedat environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignedAtMathml = $converter->texToMathMl('\\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\\\ x &= y & u &= v\\end{alignedat}', true);
        $alignAtMathml = $converter->texToMathMl('\\begin{alignat*}{2}f(x) &= x^2 & g(x) &= x + 1\\end{alignat*}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignedAtMathml);
        $t->contains('<mtable columnalign="right left right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><msub><mi>a</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd></mtr></mtable>', $alignedAtMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}</annotation>', $alignedAtMathml);
        $t->contains('<mtable columnalign="right left right left"><mtr><mtd><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><msup><mi>x</mi><mn>2</mn></msup></mtd><mtd><mi>g</mi><mo>(</mo><mi>x</mi><mo>)</mo></mtd><mtd><mo>=</mo><mi>x</mi><mo>+</mo><mn>1</mn></mtd></mtr></mtable>', $alignAtMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{alignat*}{2}f(x) &amp;= x^2 &amp; g(x) &amp;= x + 1\\end{alignat*}</annotation>', $alignAtMathml);
    },
    'converts bounded tex ams intertext rows to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignMathml = $converter->texToMathMl('\\begin{align}p_i &= m_i \\\\ \\intertext{review \\& media} x_i &= y_i \\tag{I}\\end{align}', true);
        $alignedAtMathml = $converter->texToMathMl('\\begin{alignat}{2}a &= b & c &= d \\\\ \\shortintertext{compact review} u &= v & w &= z\\end{alignat}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\begin{align}p_i &= m_i \\\\ \\intertext{review note} x_i &= y_i\\end{align}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr data-tex-intertext="normal"><mtd columnspan="2"><mtext>review &amp; media</mtext></mtd></mtr><mlabeledtr><mtd><mtext>(I)</mtext></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>', $alignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\\\ \\intertext{review \\&amp; media} x_i &amp;= y_i \\tag{I}\\end{align}</annotation>', $alignMathml);
        $t->contains('<mtable columnalign="right left right left"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr><mtr data-tex-intertext="short"><mtd columnspan="4"><mtext>compact review</mtext></mtd></mtr><mtr><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd><mtd><mi>w</mi></mtd><mtd><mo>=</mo><mi>z</mi></mtd></mtr></mtable>', $alignedAtMathml);
        $t->contains('alttext="table row p sub i, equals m sub i; row review note; row x sub i, equals y sub i"', $accessibleMathml);
        $t->true(!str_contains($alignMathml . $alignedAtMathml, '<mi>\\intertext</mi>'));
        $t->true(!str_contains($alignMathml . $alignedAtMathml, '<mi>\\shortintertext</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}\\intertext{bad} p &= q\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}p &= q \\intertext{bad} \\\\ r &= s\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}p &= q \\\\ \\intertext{} r &= s\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}p &= q \\\\ \\intertext{tail}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}p &= q \\\\ \\intertext{review \\label{bad}} r &= s\\end{align}'));
    },
    'converts bounded tex optional ams environment positions to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignedTop = $converter->texToMathMl('\\begin{aligned}[t]p_i &= m_i \\\\ x &= y\\end{aligned}', true);
        $gatheredBottom = $converter->texToMathMl('\\begin{gathered}[b]x+y \\\\ z\\end{gathered}');
        $alignedAtCenter = $converter->texToMathMl('\\begin{alignedat}[c]{2}a &= b & c &= d\\end{alignedat}');
        $multlinedBottom = $converter->texToMathMl('\\left(\\begin{multlined}[b]u+v \\\\ w\\end{multlined}\\right)');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignedTop);
        $t->contains('<mtable columnalign="right left" align="top" data-tex-env-position="top"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd></mtr></mtable>', $alignedTop);
        $t->contains('<annotation encoding="application/x-tex">\\begin{aligned}[t]p_i &amp;= m_i \\\\ x &amp;= y\\end{aligned}</annotation>', $alignedTop);
        $t->contains('<mtable columnalign="center" align="bottom" data-tex-env-position="bottom"><mtr><mtd><mi>x</mi><mo>+</mo><mi>y</mi></mtd></mtr><mtr><mtd><mi>z</mi></mtd></mtr></mtable>', $gatheredBottom);
        $t->contains('<mtable columnalign="right left right left" align="center" data-tex-env-position="center"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr></mtable>', $alignedAtCenter);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mtable columnalign="center" align="bottom" data-tex-env-position="bottom"><mtr><mtd><mi>u</mi><mo>+</mo><mi>v</mi></mtd></mtr><mtr><mtd><mi>w</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>', $multlinedBottom);
        $t->true(!str_contains($alignedTop, '<mo>[</mo><mi>t</mi>'));
        $t->true(!str_contains($gatheredBottom, '<mo>[</mo><mi>b</mi>'));
        $t->true(!str_contains($multlinedBottom, '<mo>[</mo><mi>b</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{aligned}[x]a &= b\\end{aligned}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{gathered}[]a\\end{gathered}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}[tb]{1}a &= b\\end{alignedat}'));
    },
    'converts bounded tex eqnarray environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $eqnarrayMathml = $converter->texToMathMl('\\begin{eqnarray}p_i &=& m_i \\\\ x_i &=& y_i \\label{eq:eqn-row} \\tag{E-1}\\end{eqnarray}', true);
        $starredMathml = $converter->texToMathMl('\\begin{eqnarray*}a &=& b \\\\ c &=& d\\end{eqnarray*}');
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{eqnarray}p_i &=& m_i \\label{eq:eqn-auto} \\\\ x_i &=& y_i \\notag \\\\ u_i &=& v_i \\label{eq:eqn-tag} \\tag*{review}\\end{eqnarray}',
                'display' => true,
            ]),
        ]);
        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:eqn-auto} + \\eqref{eq:eqn-tag}', false, [], $labels);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $eqnarrayMathml);
        $t->contains('<mtable columnalign="right center left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mlabeledtr id="eq:eqn-row"><mtd><mtext>(E-1)</mtext></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo></mtd><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>', $eqnarrayMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{eqnarray}p_i &amp;=&amp; m_i \\\\ x_i &amp;=&amp; y_i \\label{eq:eqn-row} \\tag{E-1}\\end{eqnarray}</annotation>', $eqnarrayMathml);
        $t->contains('<mtable columnalign="right center left"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mo>=</mo></mtd><mtd><mi>d</mi></mtd></mtr></mtable>', $starredMathml);
        $t->same([
            'eq:eqn-auto' => [
                'label' => 'eq:eqn-auto',
                'id' => 'eq:eqn-auto',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:eqn-tag' => [
                'label' => 'eq:eqn-tag',
                'id' => 'eq:eqn-tag',
                'reference' => 'review',
                'tag' => 'review',
                'tagStarred' => true,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:eqn-auto">1</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:eqn-tag">review</mtext><mo>)</mo></mrow>', $resolvedMathml);
    },
    'converts bounded tex flalign environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $flalignMathml = $converter->texToMathMl('\\begin{flalign}\\text{source} && p_i &= m_i && \\text{review} \\\\ \\text{target} && x_i &= y_i \\tag{F-1} && \\text{done}\\end{flalign}', true);
        $starredMathml = $converter->texToMathMl('\\begin{flalign*}a &= b & c &= d\\end{flalign*}');
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{flalign}p_i &= m_i \\label{eq:flush-row} & \\text{review}\\end{flalign}',
                'display' => true,
            ]),
        ]);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $flalignMathml);
        $t->contains('<mtable columnalign="left right left right left right"><mtr><mtd><mtext>source</mtext></mtd><mtd></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd></mtd><mtd><mtext>review</mtext></mtd></mtr><mlabeledtr><mtd><mtext>(F-1)</mtext></mtd><mtd><mtext>target</mtext></mtd><mtd></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd></mtd><mtd><mtext>done</mtext></mtd></mlabeledtr></mtable>', $flalignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{flalign}\\text{source} &amp;&amp; p_i &amp;= m_i &amp;&amp; \\text{review} \\\\ \\text{target} &amp;&amp; x_i &amp;= y_i \\tag{F-1} &amp;&amp; \\text{done}\\end{flalign}</annotation>', $flalignMathml);
        $t->contains('<mtable columnalign="left right left right"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr></mtable>', $starredMathml);
        $t->same([
            'eq:flush-row' => [
                'label' => 'eq:flush-row',
                'id' => 'eq:flush-row',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $converter->equationReferenceLabelsFromDocument($document));
    },
    'converts bounded tex multline environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $multlineMathml = $converter->texToMathMl('\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline}', true);
        $multlinedMathml = $converter->texToMathMl('\\left(\\begin{multlined}x+y \\\\ z\\end{multlined}\\right)');
        $taggedMultlineMathml = $converter->texToMathMl('\\begin{multline}p_i + m_i \\label{eq:multi-review} \\\\ q_i \\tag{ML}\\end{multline}', true);
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{multline}p_i \\label{eq:multi-auto} \\\\ q_i\\end{multline}',
                'display' => true,
            ]),
        ]);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $multlineMathml);
        $t->contains('<mtable columnalign="center" rowspacing=".5em normal" data-tex-rowspacing="after-row-1:.5em"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>=</mo><msub><mi>a</mi><mi>i</mi></msub><mo>+</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>+</mo><mfrac><mi>x</mi><mi>y</mi></mfrac></mtd></mtr></mtable>', $multlineMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline}</annotation>', $multlineMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mtable columnalign="center"><mtr><mtd><mi>x</mi><mo>+</mo><mi>y</mi></mtd></mtr><mtr><mtd><mi>z</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>', $multlinedMathml);
        $t->contains('<mtable columnalign="center"><mtr id="eq:multi-review"><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mlabeledtr><mtd><mtext>(ML)</mtext></mtd><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>', $taggedMultlineMathml);
        $t->same([
            'eq:multi-auto' => [
                'label' => 'eq:multi-auto',
                'id' => 'eq:multi-auto',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $converter->equationReferenceLabelsFromDocument($document));
    },
    'converts bounded tex equation wrapper environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $equationMathml = $converter->texToMathMl('\\begin{equation}p_i + m_i \\label{eq:wrapped} \\tag{WP-3}\\end{equation}', true);
        $starredMathml = $converter->texToMathMl('\\begin{equation*}\\operatorname{review}(p_i) + \\eqref{eq:wrapped}\\end{equation*}', false, [], [
            'eq:wrapped' => [
                'label' => 'eq:wrapped',
                'reference' => 'WP-3',
                'tag' => 'WP-3',
            ],
        ]);
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{equation}p_i + m_i \\label{eq:wrapped-auto}\\end{equation}',
                'display' => true,
            ]),
            new AstNode('math', [
                'text' => '\\begin{equation*}x_i + y_i \\label{eq:wrapped-star}\\end{equation*}',
                'display' => true,
            ]),
            new AstNode('math', [
                'text' => '\\begin{equation}q_i \\label{eq:wrapped-tag} \\tag*{audit}\\end{equation}',
                'display' => true,
            ]),
        ]);
        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:wrapped-auto} + \\eqref{eq:wrapped-star} + \\eqref{eq:wrapped-tag}', false, [], $labels);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $equationMathml);
        $t->contains('<mtable><mlabeledtr><mtd><mtext>(WP-3)</mtext></mtd><mtd id="eq:wrapped"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>', $equationMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{equation}p_i + m_i \\label{eq:wrapped} \\tag{WP-3}\\end{equation}</annotation>', $equationMathml);
        $t->contains('<mi>review</mi><mo>⁡</mo><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped">WP-3</mtext><mo>)</mo></mrow>', $starredMathml);
        $t->same([
            'eq:wrapped-auto' => [
                'label' => 'eq:wrapped-auto',
                'id' => 'eq:wrapped-auto',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:wrapped-star' => [
                'label' => 'eq:wrapped-star',
                'id' => 'eq:wrapped-star',
                'reference' => 'eq:wrapped-star',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:wrapped-tag' => [
                'label' => 'eq:wrapped-tag',
                'id' => 'eq:wrapped-tag',
                'reference' => 'audit',
                'tag' => 'audit',
                'tagStarred' => true,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:wrapped-auto">1</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped-star">eq:wrapped-star</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped-tag">audit</mtext><mo>)</mo></mrow>', $resolvedMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}a & b\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}a \\\\ b\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}\\label{eq:empty}\\end{equation}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{equation}a \\tag{}\\end{equation}'));
    },
    'converts bounded tex ams row tags and labels to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $taggedAlignMathml = $converter->texToMathMl('\\begin{align}p_i &= m_i \\tag{WP-1} \\\\ x_i &= y_i \\label{eq:row-review} \\tag*{review}\\end{align}', true);
        $taggedAlignAtMathml = $converter->texToMathMl('\\begin{alignat}{2}a &= b & c &= d \\tag{A}\\\\ u &= v & w &= z\\end{alignat}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $taggedAlignMathml);
        $t->contains('<mtable columnalign="right left"><mlabeledtr><mtd><mtext>(WP-1)</mtext></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mlabeledtr><mlabeledtr id="eq:row-review"><mtd><mtext>review</mtext></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>', $taggedAlignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\tag{WP-1} \\\\ x_i &amp;= y_i \\label{eq:row-review} \\tag*{review}\\end{align}</annotation>', $taggedAlignMathml);
        $t->contains('<mtable columnalign="right left right left"><mlabeledtr><mtd><mtext>(A)</mtext></mtd><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mlabeledtr><mtr><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd><mtd><mi>w</mi></mtd><mtd><mo>=</mo><mi>z</mi></mtd></mtr></mtable>', $taggedAlignAtMathml);
    },
    'converts bounded tex spacing commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $punctuationSpacingMathml = $converter->texToMathMl('p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c', true);
        $namedSpacingMathml = $converter->texToMathMl('\\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\medspace y\\thickspace z');
        $mediumSpacingMathml = $converter->texToMathMl('a\\:b\\>c\\enspace d\\negmedspace e\\negthickspace f');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $punctuationSpacingMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mspace width="0.1667em"></mspace><msub><mi>m</mi><mi>i</mi></msub><mspace width="0.2778em"></mspace><msub><mi>n</mi><mi>i</mi></msub><mspace width="-0.1667em"></mspace><msub><mi>q</mi><mi>i</mi></msub>', $punctuationSpacingMathml);
        $t->contains('<mi>a</mi><mspace width="1em"></mspace><mi>b</mi><mspace width="2em"></mspace><mi>c</mi>', $punctuationSpacingMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c</annotation>', $punctuationSpacingMathml);
        $t->contains('<mi>post</mi><mspace width="0.1667em"></mspace><mi>media</mi><mspace width="-0.1667em"></mspace><mi>review</mi>', $namedSpacingMathml);
        $t->contains('<mi>x</mi><mspace width="0.2222em"></mspace><mi>y</mi><mspace width="0.2778em"></mspace><mi>z</mi>', $namedSpacingMathml);
        $t->contains('<mi>a</mi><mspace width="0.2222em"></mspace><mi>b</mi><mspace width="0.2222em"></mspace><mi>c</mi><mspace width="0.5em"></mspace><mi>d</mi><mspace width="-0.2222em"></mspace><mi>e</mi><mspace width="-0.2778em"></mspace><mi>f</mi>', $mediumSpacingMathml);
    },
    'ignores bounded tex allowbreak commands in mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $allowBreakMathml = $converter->texToMathMl('p_i\\allowbreak + m_i + \\operatorname{slug}\\allowbreak', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('x\\allowbreak+y');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $allowBreakMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mi>slug</mi>', $allowBreakMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i\\allowbreak + m_i + \\operatorname{slug}\\allowbreak</annotation>', $allowBreakMathml);
        $t->contains('alttext="x plus y"', $accessibleMathml);
        $t->contains('intent="row(x,plus,y)"', $accessibleMathml);
        $t->true(!str_contains($allowBreakMathml, '<mi>\\allowbreak</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('x \\allowbreak_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('x \\allowbreak^2'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('x \\allowbreak\\limits_1'));
    },
    'ignores bounded tex comments in mathml handoff' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $commentMathml = $converter->texToMathMl("p_i % reviewer note with \\badcommand\n+ m_i + \\operatorname{slug}% trailing reviewer note\n", true);
        $groupMathml = $converter->texToMathMl("\\frac{a % numerator note\n+ b}{c % denominator note\n+ d}");
        $accessibleMathml = $converter->texToAccessibleMathMl("x% hidden review note\n+y");

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $commentMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mi>slug</mi>', $commentMathml);
        $t->contains("<annotation encoding=\"application/x-tex\">p_i % reviewer note with \\badcommand\n+ m_i + \\operatorname{slug}% trailing reviewer note\n</annotation>", $commentMathml);
        $t->contains('<mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow></mfrac>', $groupMathml);
        $t->contains('alttext="x plus y"', $accessibleMathml);
        $t->contains('intent="row(x,plus,y)"', $accessibleMathml);
        $t->true(!str_contains($commentMathml, '<mo>%</mo>'), 'Expected raw TeX comments to be omitted from rendered MathML');
        $t->true(!str_contains($commentMathml, '<mi>\\badcommand</mi>'), 'Expected raw TeX comment payload to remain annotation-only');
    },
    'ignores bounded tex comments while splitting environment rows' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignedMathml = $converter->texToMathMl("\\begin{aligned}p_i &= m_i % hidden & ignored\n\\\\ x_i &= y_i\\end{aligned}", true);
        $arrayMathml = $converter->texToMathMl("\\begin{array}{cc}p_i & m_i % hidden \\\\ no row sep\n\\\\ x_i & y_i\\end{array}", true);
        $fullRowCommentMathml = $converter->texToMathMl("\\begin{array}{cc}p_i & m_i \\\\ % full row comment & hidden\n x_i & y_i\\end{array}", true);
        $alignedRendered = (string) strstr($alignedMathml, '<annotation', true);
        $arrayRendered = (string) strstr($arrayMathml, '<annotation', true);
        $fullRowCommentRendered = (string) strstr($fullRowCommentMathml, '<annotation', true);

        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr></mtable>', $alignedMathml);
        $t->contains("<annotation encoding=\"application/x-tex\">\\begin{aligned}p_i &amp;= m_i % hidden &amp; ignored\n\\\\ x_i &amp;= y_i\\end{aligned}</annotation>", $alignedMathml);
        $t->true(!str_contains($alignedRendered, '<mi>h</mi><mi>i</mi><mi>d</mi><mi>d</mi><mi>e</mi><mi>n</mi>'));
        $t->true(!str_contains($alignedRendered, '<mi>i</mi><mi>g</mi><mi>n</mi><mi>o</mi><mi>r</mi><mi>e</mi><mi>d</mi>'));
        $t->contains('<mtable columnalign="center center"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr></mtable>', $arrayMathml);
        $t->contains("<annotation encoding=\"application/x-tex\">\\begin{array}{cc}p_i &amp; m_i % hidden \\\\ no row sep\n\\\\ x_i &amp; y_i\\end{array}</annotation>", $arrayMathml);
        $t->true(!str_contains($arrayRendered, '<mi>n</mi><mi>o</mi><mi>r</mi><mi>o</mi><mi>w</mi><mi>s</mi><mi>e</mi><mi>p</mi>'));
        $t->contains('<mtable columnalign="center center"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr></mtable>', $fullRowCommentMathml);
        $t->contains("<annotation encoding=\"application/x-tex\">\\begin{array}{cc}p_i &amp; m_i \\\\ % full row comment &amp; hidden\n x_i &amp; y_i\\end{array}</annotation>", $fullRowCommentMathml);
        $t->true(!str_contains($fullRowCommentRendered, '<mi>f</mi><mi>u</mi><mi>l</mi><mi>l</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl("\\begin{smallmatrix}a & b \\\\ % ignored final row\n\\end{smallmatrix}"));
    },
    'ignores bounded tex comments while scanning environment endings' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignedMathml = $converter->texToMathMl("\\begin{aligned}a &= b % hidden \\end{aligned}\n\\\\ c &= d\\end{aligned}", true);
        $arrayMathml = $converter->texToMathMl("\\begin{array}{cc}a & b % hidden \\end{array}\n\\\\ c & d\\end{array}", true);
        $escapedPercentMathml = $converter->texToMathMl('\\begin{aligned}p_i\\% &= m_i\\end{aligned}', true);
        $alignedRendered = (string) strstr($alignedMathml, '<annotation', true);
        $arrayRendered = (string) strstr($arrayMathml, '<annotation', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignedMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr></mtable>', $alignedMathml);
        $t->contains("<annotation encoding=\"application/x-tex\">\\begin{aligned}a &amp;= b % hidden \\end{aligned}\n\\\\ c &amp;= d\\end{aligned}</annotation>", $alignedMathml);
        $t->true(!str_contains($alignedRendered, '<mi>h</mi><mi>i</mi><mi>d</mi><mi>d</mi><mi>e</mi><mi>n</mi>'));
        $t->contains('<mtable columnalign="center center"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>', $arrayMathml);
        $t->contains("<annotation encoding=\"application/x-tex\">\\begin{array}{cc}a &amp; b % hidden \\end{array}\n\\\\ c &amp; d\\end{array}</annotation>", $arrayMathml);
        $t->true(!str_contains($arrayRendered, '<mi>h</mi><mi>i</mi><mi>d</mi><mi>d</mi><mi>e</mi><mi>n</mi>'));
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>%</mo></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr></mtable>', $escapedPercentMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{aligned}p_i\\% &amp;= m_i\\end{aligned}</annotation>', $escapedPercentMathml);
    },
    'converts bounded tex explicit dimensioned spacing commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $explicitMathml = $converter->texToMathMl('p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b', true);
        $metricMathml = $converter->texToMathMl('x\\hspace{12pt}y\\mspace{0em}z');
        $kernMathml = $converter->texToMathMl('r\\kern1pt s\\mkern-3mu t + u\\kern .5em v', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $explicitMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mspace width="1.5em"></mspace><msub><mi>m</mi><mi>i</mi></msub><mspace width="-2mu"></mspace><msub><mi>q</mi><mi>i</mi></msub>', $explicitMathml);
        $t->contains('<mi>a</mi><mspace width=".25in" linebreak="nobreak"></mspace><mi>b</mi>', $explicitMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b</annotation>', $explicitMathml);
        $t->contains('<mi>x</mi><mspace width="12pt"></mspace><mi>y</mi><mspace width="0em"></mspace><mi>z</mi>', $metricMathml);
        $t->contains('<mi>r</mi><mspace width="1pt"></mspace><mi>s</mi><mspace width="-3mu"></mspace><mi>t</mi><mo>+</mo><mi>u</mi><mspace width=".5em"></mspace><mi>v</mi>', $kernMathml);
        $t->contains('<annotation encoding="application/x-tex">r\\kern1pt s\\mkern-3mu t + u\\kern .5em v</annotation>', $kernMathml);
    },
    'converts bounded tex modulo commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $infixMathml = $converter->texToMathMl('a \\mod n + b \\bmod m_i', true);
        $parenthesizedMathml = $converter->texToMathMl('x \\pmod {n+1} + y \\pod m_i');
        $accessibleMathml = $converter->texToAccessibleMathMl('a \\bmod n');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $infixMathml);
        $t->contains('<mi>a</mi><mspace width="0.4444em"></mspace><mi>mod</mi><mspace width="0.2222em"></mspace><mi>n</mi><mo>+</mo><mi>b</mi><mspace width="0.2222em"></mspace><mi>mod</mi><mspace width="0.2222em"></mspace><msub><mi>m</mi><mi>i</mi></msub>', $infixMathml);
        $t->contains('<annotation encoding="application/x-tex">a \\mod n + b \\bmod m_i</annotation>', $infixMathml);
        $t->contains('<mi>x</mi><mspace width="0.2222em"></mspace><mo>(</mo><mi>mod</mi><mspace width="0.2222em"></mspace><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow><mo>)</mo><mo>+</mo><mi>y</mi><mspace width="0.2222em"></mspace><mo>(</mo><msub><mi>m</mi><mi>i</mi></msub><mo>)</mo>', $parenthesizedMathml);
        $t->contains('alttext="a space mod space n"', $accessibleMathml);
        $t->contains('intent="row(a,space,mod,space,n)"', $accessibleMathml);
        $t->true(!str_contains($infixMathml, '<mi>\\mod</mi>'));
        $t->true(!str_contains($infixMathml, '<mi>\\bmod</mi>'));
        $t->true(!str_contains($parenthesizedMathml, '<mi>\\pmod</mi>'));
        $t->true(!str_contains($parenthesizedMathml, '<mi>\\pod</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('a \\mod'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('a \\pmod{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('a \\pod_1'));
    },
    'converts bounded tex equation tags and labels to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $taggedMathml = $converter->texToMathMl('p_i + m_i \\label{eq:review-flow} \\tag{WP-2}', true);
        $starredTagMathml = $converter->texToMathMl('p_i + m_i \\tag*{review}');
        $labelOnlyMathml = $converter->texToMathMl('\\label{eq:plain}x_i + y_i');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $taggedMathml);
        $t->contains('<mtable><mlabeledtr><mtd><mtext>(WP-2)</mtext></mtd><mtd id="eq:review-flow"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>', $taggedMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i + m_i \\label{eq:review-flow} \\tag{WP-2}</annotation><annotation encoding="application/x-tex-label">eq:review-flow</annotation>', $taggedMathml);
        $t->contains('<mtext>review</mtext></mtd><mtd><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd>', $starredTagMathml);
        $t->contains('<mrow id="eq:plain"><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow>', $labelOnlyMathml);
    },
    'converts bounded tex equation references to mathml handoff links' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $referenceMathml = $converter->texToMathMl('\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}', true);
        $rowReferenceMathml = $converter->texToMathMl('\\begin{align}p_i &= m_i \\label{eq:row-review}\\end{align} + \\eqref{eq:row-review}');

        $t->contains('<mrow id="eq:plain"><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:plain">eq:plain</mtext><mo>)</mo></mrow><mo>+</mo><mtext href="#review-row-2">review row/2</mtext></mrow>', $referenceMathml);
        $t->contains('<annotation encoding="application/x-tex">\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}</annotation><annotation encoding="application/x-tex-label">eq:plain</annotation>', $referenceMathml);
        $t->contains('<mtr id="eq:row-review"><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr></mtable><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:row-review">eq:row-review</mtext><mo>)</mo></mrow>', $rowReferenceMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\ref{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\eqref{###}'));
    },
    'converts bounded tex href and url commands to linked mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $hrefMathml = $converter->texToMathMl('\\href{https://example.test/review}{p_i + m_i}', true);
        $anchorMathml = $converter->texToMathMl('\\href{#eq:review-flow}{\\eqref{eq:review-flow}}', false, [], [
            'eq:review-flow' => [
                'reference' => 'WP-2',
            ],
        ]);
        $urlMathml = $converter->texToMathMl('\\url{mailto:reviewer@example.test} + \\url{/wp-content/uploads/math-note}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\href{https://example.test/review}{x+y} + \\url{https://example.test/source}');

        $t->contains('<mrow href="https://example.test/review"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mrow>', $hrefMathml);
        $t->contains('<annotation encoding="application/x-tex">\\href{https://example.test/review}{p_i + m_i}</annotation>', $hrefMathml);
        $t->true(!str_contains($hrefMathml, '<mi>\\href</mi>'), 'Expected \\href to be parsed as a link wrapper');
        $t->contains('<mrow href="#eq:review-flow"><mrow><mo>(</mo><mtext href="#eq:review-flow">WP-2</mtext><mo>)</mo></mrow></mrow>', $anchorMathml);
        $t->contains('<mtext href="mailto:reviewer@example.test">mailto:reviewer@example.test</mtext>', $urlMathml);
        $t->contains('<mtext href="/wp-content/uploads/math-note">/wp-content/uploads/math-note</mtext>', $urlMathml);
        $t->true(!str_contains($urlMathml, '<mi>\\url</mi>'), 'Expected \\url to be parsed as linked text');
        $t->contains('alttext="x plus y plus https://example.test/source"', $accessibleMathml);
        $t->contains('intent="row(row(x,plus,y),plus,https_example_test_source)"', $accessibleMathml);

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\href{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\href{javascript:alert(1)}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\href{//example.test/review}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\href{https://example.test/review}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\href{https://example.test/review}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\url{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\url{javascript:alert(1)}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\url{https://example.test/bad path}'));
    },
    'resolves bounded tex equation references through a document label map' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => 'p_i + m_i \\label{eq:review-flow} \\tag{WP-2}',
                    'display' => true,
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => '\\begin{align}x_i &= y_i \\label{review row/2} \\tag*{review} \\\\ u_i &= v_i\\end{align}',
                    'display' => true,
                ]),
            ]),
        ]);
        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:review-flow} + \\ref{review row/2} + \\eqref{missing}', false, [], $labels);
        $nodeMathml = $converter->mathMlFor(new AstNode('math', [
            'text' => '\\eqref{eq:review-flow} + \\eqref{review row/2}',
            'display' => false,
        ]), [], $labels);

        $t->same([
            'eq:review-flow' => [
                'label' => 'eq:review-flow',
                'id' => 'eq:review-flow',
                'reference' => 'WP-2',
                'tag' => 'WP-2',
                'tagStarred' => false,
            ],
            'review-row-2' => [
                'label' => 'review row/2',
                'id' => 'review-row-2',
                'reference' => 'review',
                'tag' => 'review',
                'tagStarred' => true,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:review-flow">WP-2</mtext><mo>)</mo></mrow><mo>+</mo><mtext href="#review-row-2">review</mtext><mo>+</mo><mrow><mo>(</mo><mtext href="#missing">missing</mtext><mo>)</mo></mrow>', $resolvedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\eqref{eq:review-flow} + \\ref{review row/2} + \\eqref{missing}</annotation>', $resolvedMathml);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:review-flow">WP-2</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#review-row-2">review</mtext><mo>)</mo></mrow>', $nodeMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): array => $converter->equationReferenceLabelsFromDocument(new AstNode('document', [], [
            new AstNode('math', ['text' => 'x \\label{eq:dup}', 'display' => true]),
            new AstNode('math', ['text' => 'y \\label{eq:dup}', 'display' => true]),
        ])));
    },
    'resolves bounded automatic numbers for untagged display equation references' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => 'p_i + m_i \\label{eq:first}',
                    'display' => true,
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => 'inline \\label{eq:inline}',
                    'display' => false,
                ]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('math', [
                    'text' => '\\begin{align}x_i &= y_i \\label{eq:row-one} \\\\ u_i &= v_i \\tag{manual}\\end{align}',
                    'display' => true,
                ]),
            ]),
        ]);

        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:first} + \\eqref{eq:inline} + \\eqref{eq:row-one} + \\eqref{eq:missing}', false, [], $labels);

        $t->same([
            'eq:first' => [
                'label' => 'eq:first',
                'id' => 'eq:first',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:inline' => [
                'label' => 'eq:inline',
                'id' => 'eq:inline',
                'reference' => 'eq:inline',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:row-one' => [
                'label' => 'eq:row-one',
                'id' => 'eq:row-one',
                'reference' => '2',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:first">1</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:inline">eq:inline</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:row-one">2</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:missing">eq:missing</mtext><mo>)</mo></mrow>', $resolvedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\eqref{eq:first} + \\eqref{eq:inline} + \\eqref{eq:row-one} + \\eqref{eq:missing}</annotation>', $resolvedMathml);
    },
    'converts bounded tex notag and nonumber row suppression to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $suppressedMathml = $converter->texToMathMl('\\begin{align}p_i &= m_i \\notag \\\\ x_i &= y_i \\nonumber \\\\ u_i &= v_i \\label{eq:counted}\\end{align}', true);
        $taggedSuppressedMathml = $converter->texToMathMl('\\begin{align}a &= b \\notag \\tag{manual} \\\\ c &= d \\nonumber\\end{align}', true);
        $document = new AstNode('document', [], [
            new AstNode('math', [
                'text' => '\\begin{align}p_i &= m_i \\label{eq:suppressed} \\notag \\\\ x_i &= y_i \\label{eq:counted}\\end{align}',
                'display' => true,
            ]),
        ]);
        $labels = $converter->equationReferenceLabelsFromDocument($document);
        $resolvedMathml = $converter->texToMathMl('\\eqref{eq:suppressed} + \\eqref{eq:counted}', false, [], $labels);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $suppressedMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr><mtr id="eq:counted"><mtd><msub><mi>u</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>v</mi><mi>i</mi></msub></mtd></mtr></mtable>', $suppressedMathml);
        $t->true(!str_contains($suppressedMathml, '<mi>\\notag</mi>'), 'Expected \\notag to be stripped from row MathML');
        $t->true(!str_contains($suppressedMathml, '<mi>\\nonumber</mi>'), 'Expected \\nonumber to be stripped from row MathML');
        $t->contains('<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\notag \\\\ x_i &amp;= y_i \\nonumber \\\\ u_i &amp;= v_i \\label{eq:counted}\\end{align}</annotation>', $suppressedMathml);
        $t->contains('<mlabeledtr><mtd><mtext>(manual)</mtext></mtd><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd></mlabeledtr><mtr><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr>', $taggedSuppressedMathml);
        $t->same([
            'eq:suppressed' => [
                'label' => 'eq:suppressed',
                'id' => 'eq:suppressed',
                'reference' => 'eq:suppressed',
                'tag' => null,
                'tagStarred' => false,
            ],
            'eq:counted' => [
                'label' => 'eq:counted',
                'id' => 'eq:counted',
                'reference' => '1',
                'tag' => null,
                'tagStarred' => false,
            ],
        ], $labels);
        $t->contains('<mrow><mo>(</mo><mtext href="#eq:suppressed">eq:suppressed</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:counted">1</mtext><mo>)</mo></mrow>', $resolvedMathml);
    },
    'converts bounded tex relation set logic and arrow commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $setMathml = $converter->texToMathMl('\\forall p_i \\in P \\land m_i \\notin M \\Rightarrow p_i \\subseteq Q \\cup R', true);
        $logicMathml = $converter->texToMathMl('\\exists x \\in S \\colon x \\approx y \\lor \\neg (y \\equiv z)');
        $emptyMathml = $converter->texToMathMl('A \\cap B = \\emptyset \\iff A \\subset B \\setminus C');
        $arrowMathml = $converter->texToMathMl('a \\leftarrow b \\leftrightarrow c \\mapsto d \\partial f');
        $aliasMathml = $converter->texToMathMl('U \\supseteq V \\supset W \\vee a \\le b \\ge c \\ne d');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $setMathml);
        $t->contains('<mo>∀</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi>', $setMathml);
        $t->contains('<mo>∧</mo><msub><mi>m</mi><mi>i</mi></msub><mo>∉</mo><mi>M</mi><mo>⇒</mo><msub><mi>p</mi><mi>i</mi></msub><mo>⊆</mo><mi>Q</mi><mo>∪</mo><mi>R</mi>', $setMathml);
        $t->contains('<mo>∃</mo><mi>x</mi><mo>∈</mo><mi>S</mi><mo>:</mo><mi>x</mi><mo>≈</mo><mi>y</mi><mo>∨</mo><mo>¬</mo><mo>(</mo><mi>y</mi><mo>≡</mo><mi>z</mi><mo>)</mo>', $logicMathml);
        $t->contains('<mi>A</mi><mo>∩</mo><mi>B</mi><mo>=</mo><mo>∅</mo><mo>⇔</mo><mi>A</mi><mo>⊂</mo><mi>B</mi><mo>∖</mo><mi>C</mi>', $emptyMathml);
        $t->contains('<mi>a</mi><mo>←</mo><mi>b</mi><mo>↔</mo><mi>c</mi><mo>↦</mo><mi>d</mi><mo>∂</mo><mi>f</mi>', $arrowMathml);
        $t->contains('<mi>U</mi><mo>⊇</mo><mi>V</mi><mo>⊃</mo><mi>W</mi><mo>∨</mo><mi>a</mi><mo>≤</mo><mi>b</mi><mo>≥</mo><mi>c</mi><mo>≠</mo><mi>d</mi>', $aliasMathml);
    },
    'converts bounded tex lnot logical alias to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $logicMathml = $converter->texToMathMl('\\lnot p \\lor \\neg q', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\lnot p');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $logicMathml);
        $t->contains('<mo>¬</mo><mi>p</mi><mo>∨</mo><mo>¬</mo><mi>q</mi>', $logicMathml);
        $t->contains('<annotation encoding="application/x-tex">\\lnot p \\lor \\neg q</annotation>', $logicMathml);
        $t->contains('alttext="not p"', $accessibleMathml);
        $t->contains('intent="row(not,p)"', $accessibleMathml);
        $t->true(!str_contains($logicMathml, '<mi>\\lnot</mi>'));
    },
    'converts bounded tex not relation overlays to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $notRelationMathml = $converter->texToMathMl('p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + f \\not\\approx g + q \\not\\Rightarrow r', true);
        $notTokenMathml = $converter->texToMathMl('x \\not< y + y \\not> z + A \\not\\leftrightarrow B');
        $fallbackMathml = $converter->texToMathMl('\\not\\alpha_i + \\not{p_i + m_i}');
        $accessibleMathml = $converter->texToAccessibleMathMl('p_i \\not\\in P + a \\not= b', false);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $notRelationMathml);
        $t->contains('<msub><mi>p</mi><mi>i</mi></msub><mo>∉</mo><mi>P</mi><mo>+</mo><mi>a</mi><mo>≠</mo><mi>b</mi><mo>+</mo><mi>x</mi><mo>≰</mo><mi>y</mi>', $notRelationMathml);
        $t->contains('<mi>A</mi><mo>⊈</mo><mi>B</mi><mo>+</mo><mi>f</mi><mo>≉</mo><mi>g</mi><mo>+</mo><mi>q</mi><mo>⇏</mo><mi>r</mi>', $notRelationMathml);
        $t->contains('<annotation encoding="application/x-tex">p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + f \\not\\approx g + q \\not\\Rightarrow r</annotation>', $notRelationMathml);
        $t->contains('<mi>x</mi><mo>≮</mo><mi>y</mi><mo>+</mo><mi>y</mi><mo>≯</mo><mi>z</mi><mo>+</mo><mi>A</mi><mo>↮</mo><mi>B</mi>', $notTokenMathml);
        $t->contains('<msub><menclose notation="updiagonalstrike"><mi>α</mi></menclose><mi>i</mi></msub><mo>+</mo><menclose notation="updiagonalstrike"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></menclose>', $fallbackMathml);
        $t->contains('alttext="p sub i not in P plus a not equal b"', $accessibleMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\not'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\not_1'));
    },
    'canonicalizes bounded tex braced not relations to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $bracedRelationMathml = $converter->texToMathMl('x \\not{\\in} S + y \\not{=} z + q \\not{\\leqslant} r', true);
        $slantAliasMathml = $converter->texToMathMl('x \\not\\geqslant y + y \\not\\leqslant z');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $bracedRelationMathml);
        $t->contains('<mi>x</mi><mo>∉</mo><mi>S</mi><mo>+</mo><mi>y</mi><mo>≠</mo><mi>z</mi><mo>+</mo><mi>q</mi><mo>≰</mo><mi>r</mi>', $bracedRelationMathml);
        $t->contains('<annotation encoding="application/x-tex">x \\not{\\in} S + y \\not{=} z + q \\not{\\leqslant} r</annotation>', $bracedRelationMathml);
        $t->contains('<mi>x</mi><mo>≱</mo><mi>y</mi><mo>+</mo><mi>y</mi><mo>≰</mo><mi>z</mi>', $slantAliasMathml);
        $t->true(!str_contains($bracedRelationMathml . $slantAliasMathml, '<menclose notation="updiagonalstrike"><mo>'));
    },
    'converts bounded tex prime shorthand and prime commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $primeMathml = $converter->texToMathMl("f'(x) + g''_i + h_i''' + x^{2}' + r'''' + s'''''_j", true);
        $commandMathml = $converter->texToMathMl('\\partial^\\prime f + y^\\backprime + z^{\\prime\\prime}');
        $accessibleMathml = $converter->texToAccessibleMathMl("f'(x) + g''_i + r''''");

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $primeMathml);
        $t->contains('<msup><mi>f</mi><mo>′</mo></msup><mo>(</mo><mi>x</mi><mo>)</mo>', $primeMathml);
        $t->contains('<msubsup><mi>g</mi><mi>i</mi><mo>″</mo></msubsup>', $primeMathml);
        $t->contains('<msubsup><mi>h</mi><mi>i</mi><mo>‴</mo></msubsup>', $primeMathml);
        $t->contains('<msup><mi>x</mi><mrow><mn>2</mn><mo>′</mo></mrow></msup>', $primeMathml);
        $t->contains('<msup><mi>r</mi><mo>⁗</mo></msup>', $primeMathml);
        $t->contains('<msubsup><mi>s</mi><mi>j</mi><mrow><mo>⁗</mo><mo>′</mo></mrow></msubsup>', $primeMathml);
        $t->contains('<annotation encoding="application/x-tex">f&#039;(x) + g&#039;&#039;_i + h_i&#039;&#039;&#039; + x^{2}&#039; + r&#039;&#039;&#039;&#039; + s&#039;&#039;&#039;&#039;&#039;_j</annotation>', $primeMathml);
        $t->contains('<msup><mo>∂</mo><mo>′</mo></msup><mi>f</mi><mo>+</mo><msup><mi>y</mi><mo>‵</mo></msup><mo>+</mo><msup><mi>z</mi><mrow><mo>′</mo><mo>′</mo></mrow></msup>', $commandMathml);
        $t->contains('alttext="f superscript prime left parenthesis x right parenthesis plus g sub i superscript double prime plus r superscript quadruple prime"', $accessibleMathml);
        $t->contains('intent="row(superscript(f,prime),left_parenthesis,x,right_parenthesis,plus,subsup(g,i,double_prime),plus,superscript(r,quadruple_prime))"', $accessibleMathml);
    },
    'converts bounded tex accents overlines and underlines to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $accentMathml = $converter->texToMathMl('\\hat{x} + \\widehat{ab} + \\bar y + \\overline{AB} + \\vec{v}_i');
        $dotMathml = $converter->texToMathMl('\\dot{x} + \\ddot{y} + \\tilde n');
        $underMathml = $converter->texToMathMl('\\underline{\\operatorname{draft}}');

        $t->contains('<mover accent="true"><mi>x</mi><mo>^</mo></mover>', $accentMathml);
        $t->contains('<mover accent="true"><mrow><mi>a</mi><mi>b</mi></mrow><mo>^</mo></mover>', $accentMathml);
        $t->contains('<mover accent="true"><mi>y</mi><mo>¯</mo></mover><mo>+</mo><mover accent="true"><mrow><mi>A</mi><mi>B</mi></mrow><mo>‾</mo></mover>', $accentMathml);
        $t->contains('<msub><mover accent="true"><mi>v</mi><mo>→</mo></mover><mi>i</mi></msub>', $accentMathml);
        $t->contains('<mover accent="true"><mi>x</mi><mo>˙</mo></mover><mo>+</mo><mover accent="true"><mi>y</mi><mo>¨</mo></mover><mo>+</mo><mover accent="true"><mi>n</mi><mo>~</mo></mover>', $dotMathml);
        $t->contains('<munder accentunder="true"><mi>draft</mi><mo>_</mo></munder>', $underMathml);
    },
    'converts bounded tex accent aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $accentAliasMathml = $converter->texToMathMl('\\acute{x} + \\grave{y} + \\breve{z} + \\check{a} + \\mathring{A}_0 + \\widetilde{mn}', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\acute{x} + \\grave{y} + \\mathring{A}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $accentAliasMathml);
        $t->contains('<mover accent="true"><mi>x</mi><mo>´</mo></mover><mo>+</mo><mover accent="true"><mi>y</mi><mo>`</mo></mover>', $accentAliasMathml);
        $t->contains('<mover accent="true"><mi>z</mi><mo>˘</mo></mover><mo>+</mo><mover accent="true"><mi>a</mi><mo>ˇ</mo></mover>', $accentAliasMathml);
        $t->contains('<msub><mover accent="true"><mi>A</mi><mo>˚</mo></mover><mn>0</mn></msub><mo>+</mo><mover accent="true"><mrow><mi>m</mi><mi>n</mi></mrow><mo>~</mo></mover>', $accentAliasMathml);
        $t->contains('<annotation encoding="application/x-tex">\\acute{x} + \\grave{y} + \\breve{z} + \\check{a} + \\mathring{A}_0 + \\widetilde{mn}</annotation>', $accentAliasMathml);
        $t->contains('alttext="x over acute plus y over grave plus A over ring"', $accessibleMathml);
        $t->contains('intent="row(over(x,acute),plus,over(y,grave),plus,over(a,ring))"', $accessibleMathml);
    },
    'converts bounded tex dot and under tilde accent aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $dotAccentMathml = $converter->texToMathMl('\\dddot{x_i} + \\ddddot{y} + \\DDDot z', true);
        $underTildeMathml = $converter->texToMathMl('\\utilde{x_i} + \\wideutilde{mn}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\dddot{x} + \\utilde{y}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $dotAccentMathml);
        $t->contains('<mover accent="true"><msub><mi>x</mi><mi>i</mi></msub><mo>⃛</mo></mover><mo>+</mo><mover accent="true"><mi>y</mi><mo>⃜</mo></mover><mo>+</mo><mover accent="true"><mi>z</mi><mo>⃛</mo></mover>', $dotAccentMathml);
        $t->contains('<annotation encoding="application/x-tex">\\dddot{x_i} + \\ddddot{y} + \\DDDot z</annotation>', $dotAccentMathml);
        $t->contains('<munder accentunder="true"><msub><mi>x</mi><mi>i</mi></msub><mo>̰</mo></munder><mo>+</mo><munder accentunder="true"><mrow><mi>m</mi><mi>n</mi></mrow><mo>̰</mo></munder>', $underTildeMathml);
        $t->contains('<annotation encoding="application/x-tex">\\utilde{x_i} + \\wideutilde{mn}</annotation>', $underTildeMathml);
        $t->contains('alttext="x over triple dot plus y under tilde below"', $accessibleMathml);
        $t->contains('intent="row(over(x,triple_dot),plus,under(y,tilde_below))"', $accessibleMathml);
        $t->true(!str_contains($dotAccentMathml . $underTildeMathml, '<mi>\\dddot</mi>'));
        $t->true(!str_contains($dotAccentMathml . $underTildeMathml, '<mi>\\ddddot</mi>'));
        $t->true(!str_contains($dotAccentMathml . $underTildeMathml, '<mi>\\utilde</mi>'));
    },
    'converts bounded tex extensible arrows to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $xArrowMathml = $converter->texToMathMl('\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i', true);
        $mapArrowMathml = $converter->texToMathMl('A \\xleftrightarrow[n+1]{\\text{sync}} B + C \\xmapsto{f} D');
        $aliasArrowMathml = $converter->texToMathMl('A \\xlongequal{\\text{same}} B + C \\xhookrightarrow[\\text{map}]{f} D + E \\xtwoheadleftarrow{g} F', true);
        $harpoonArrowMathml = $converter->texToMathMl('P \\xleftharpoonup{\\text{pull}} Q + R \\xrightharpoondown[low]{high} S');
        $accessibleAliasMathml = $converter->texToAccessibleMathMl('A \\xhookrightarrow[\\text{map}]{f} B');
        $accentArrowMathml = $converter->texToMathMl('\\overrightarrow{AB}_i + \\underleftarrow{\\operatorname{media}} + \\overleftrightarrow{x+y}');
        $tokenArrowMathml = $converter->texToMathMl('\\xrightarrow\\alpha p_i + \\xleftarrow[\\text{low}]\\beta m_i + \\xhookrightarrow[map] f q', true);
        $tokenAccentArrowMathml = $converter->texToMathMl('\\overrightarrow A_i + \\underrightarrow\\operatorname{media} + \\overleftrightarrow x+y');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $xArrowMathml);
        $t->contains('<munderover><mo stretchy="true">→</mo><mtext>review</mtext><mi>publish</mi></munderover><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mover><mo stretchy="true">←</mo><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mover><msub><mi>m</mi><mi>i</mi></msub>', $xArrowMathml);
        $t->contains('<annotation encoding="application/x-tex">\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i</annotation>', $xArrowMathml);
        $t->contains('<mi>A</mi><munderover><mo stretchy="true">↔</mo><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow><mtext>sync</mtext></munderover><mi>B</mi><mo>+</mo><mi>C</mi><mover><mo stretchy="true">↦</mo><mi>f</mi></mover><mi>D</mi>', $mapArrowMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $aliasArrowMathml);
        $t->contains('<mi>A</mi><mover><mo stretchy="true">=</mo><mtext>same</mtext></mover><mi>B</mi><mo>+</mo><mi>C</mi><munderover><mo stretchy="true">↪</mo><mtext>map</mtext><mi>f</mi></munderover><mi>D</mi><mo>+</mo><mi>E</mi><mover><mo stretchy="true">↞</mo><mi>g</mi></mover><mi>F</mi>', $aliasArrowMathml);
        $t->contains('<annotation encoding="application/x-tex">A \\xlongequal{\\text{same}} B + C \\xhookrightarrow[\\text{map}]{f} D + E \\xtwoheadleftarrow{g} F</annotation>', $aliasArrowMathml);
        $t->contains('<mi>P</mi><mover><mo stretchy="true">↼</mo><mtext>pull</mtext></mover><mi>Q</mi><mo>+</mo><mi>R</mi><munderover><mo stretchy="true">⇁</mo><mrow><mi>l</mi><mi>o</mi><mi>w</mi></mrow><mrow><mi>h</mi><mi>i</mi><mi>g</mi><mi>h</mi></mrow></munderover><mi>S</mi>', $harpoonArrowMathml);
        $t->contains('alttext="A right hook arrow under map over f B"', $accessibleAliasMathml);
        $t->contains('intent="row(a,underover(right_hook_arrow,map,f),b)"', $accessibleAliasMathml);
        $t->contains('<msub><mover accent="true"><mrow><mi>A</mi><mi>B</mi></mrow><mo stretchy="true">→</mo></mover><mi>i</mi></msub>', $accentArrowMathml);
        $t->contains('<munder accentunder="true"><mi>media</mi><mo stretchy="true">←</mo></munder><mo>+</mo><mover accent="true"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mo stretchy="true">↔</mo></mover>', $accentArrowMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $tokenArrowMathml);
        $t->contains('<mover><mo stretchy="true">→</mo><mi>α</mi></mover><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><munderover><mo stretchy="true">←</mo><mtext>low</mtext><mi>β</mi></munderover><msub><mi>m</mi><mi>i</mi></msub>', $tokenArrowMathml);
        $t->contains('<munderover><mo stretchy="true">↪</mo><mrow><mi>m</mi><mi>a</mi><mi>p</mi></mrow><mi>f</mi></munderover><mi>q</mi>', $tokenArrowMathml);
        $t->contains('<annotation encoding="application/x-tex">\\xrightarrow\\alpha p_i + \\xleftarrow[\\text{low}]\\beta m_i + \\xhookrightarrow[map] f q</annotation>', $tokenArrowMathml);
        $t->contains('<msub><mover accent="true"><mi>A</mi><mo stretchy="true">→</mo></mover><mi>i</mi></msub><mo>+</mo><munder accentunder="true"><mi>media</mi><mo stretchy="true">→</mo></munder><mo>+</mo><mover accent="true"><mi>x</mi><mo stretchy="true">↔</mo></mover><mo>+</mo><mi>y</mi>', $tokenAccentArrowMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overrightarrow A_i + \\underrightarrow\\operatorname{media} + \\overleftrightarrow x+y</annotation>', $tokenAccentArrowMathml);
        $t->true(!str_contains($tokenArrowMathml . $tokenAccentArrowMathml, '<mi>\\xrightarrow</mi>'));
        $t->true(!str_contains($tokenArrowMathml . $tokenAccentArrowMathml, '<mi>\\overrightarrow</mi>'));
    },
    'converts bounded reciprocal harpoon extensible arrows to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $mathml = $converter->texToMathMl('A \\xrightleftharpoons[\\text{review}]{\\operatorname{publish}} B + C \\xleftrightharpoons{draft} D', true);
        $tokenMathml = $converter->texToMathMl('\\xrightleftharpoons\\alpha p_i + \\xleftrightharpoons[low] \\beta m_i');
        $accessibleMathml = $converter->texToAccessibleMathMl('A \\xrightleftharpoons[low]{high} B');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $mathml);
        $t->contains('<mi>A</mi><munderover><mo stretchy="true">⇌</mo><mtext>review</mtext><mi>publish</mi></munderover><mi>B</mi><mo>+</mo><mi>C</mi><mover><mo stretchy="true">⇋</mo><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mover><mi>D</mi>', $mathml);
        $t->contains('<annotation encoding="application/x-tex">A \\xrightleftharpoons[\\text{review}]{\\operatorname{publish}} B + C \\xleftrightharpoons{draft} D</annotation>', $mathml);
        $t->contains('<mover><mo stretchy="true">⇌</mo><mi>α</mi></mover><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><munderover><mo stretchy="true">⇋</mo><mrow><mi>l</mi><mi>o</mi><mi>w</mi></mrow><mi>β</mi></munderover><msub><mi>m</mi><mi>i</mi></msub>', $tokenMathml);
        $t->contains('alttext="A right harpoon over left under l o w over h i g h B"', $accessibleMathml);
        $t->contains('intent="row(a,underover(right_harpoon_over_left,row(l,o,w),row(h,i,g,h)),b)"', $accessibleMathml);
        $t->true(!str_contains($mathml . $tokenMathml, '<mi>\\xrightleftharpoons</mi>'));
        $t->true(!str_contains($mathml . $tokenMathml, '<mi>\\xleftrightharpoons</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xrightleftharpoons'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xleftrightharpoons[]'));
    },
    'converts bounded tex matrix and aligned environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $matrixMathml = $converter->texToMathMl('\\begin{matrix}a & b \\\\ c & d\\end{matrix}');
        $fencedMathml = $converter->texToMathMl('\\begin{pmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{pmatrix}', true);
        $bracketMathml = $converter->texToMathMl('\\begin{bmatrix}\\frac{a}{b} & \\sqrt{x} \\\\ \\alpha & \\omega\\end{bmatrix}');
        $alignedMathml = $converter->texToMathMl('\\begin{aligned}x_i &= \\operatorname{score}(p_i) \\\\ y_i &= \\frac{a_i}{b_i}\\end{aligned}');

        $t->contains('<mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>', $matrixMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>', $fencedMathml);
        $t->contains('<mo fence="true" stretchy="true">[</mo><mtable><mtr><mtd><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd><mtd><msqrt><mi>x</mi></msqrt></mtd></mtr><mtr><mtd><mi>α</mi></mtd><mtd><mi>ω</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">]</mo>', $bracketMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mi>score</mi><mo>⁡</mo><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd></mtr>', $alignedMathml);
        $t->contains('<mtr><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>', $alignedMathml);
    },
    'converts bounded plain tex matrix commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $matrixMathml = $converter->texToMathMl('\\matrix{p_1 & m_1 \\cr p_2 & m_2}', true);
        $pmatrixMathml = $converter->texToMathMl('\\pmatrix{a & b \\cr c & d}');
        $bmatrixMathml = $converter->texToMathMl('\\bmatrix{x & y \\cr z & w}');
        $casesMathml = $converter->texToMathMl('\\cases{p_i & p_i \\in P \\cr 0 & \\text{otherwise}}');
        $spacedRowsMathml = $converter->texToMathMl('\\pmatrix{a & b \\cr[2pt] c & d\\cr}');
        $crcrRowsMathml = $converter->texToMathMl('\\matrix{a & b \\crcr c & d}');
        $combined = $matrixMathml . $pmatrixMathml . $bmatrixMathml . $casesMathml . $spacedRowsMathml . $crcrRowsMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $matrixMathml);
        $t->contains('<mtable><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable>', $matrixMathml);
        $t->contains('<annotation encoding="application/x-tex">\\matrix{p_1 &amp; m_1 \\cr p_2 &amp; m_2}</annotation>', $matrixMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>', $pmatrixMathml);
        $t->contains('<mo fence="true" stretchy="true">[</mo><mtable><mtr><mtd><mi>x</mi></mtd><mtd><mi>y</mi></mtd></mtr><mtr><mtd><mi>z</mi></mtd><mtd><mi>w</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">]</mo>', $bmatrixMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable>', $casesMathml);
        $t->contains('<annotation encoding="application/x-tex">\\cases{p_i &amp; p_i \\in P \\cr 0 &amp; \\text{otherwise}}</annotation>', $casesMathml);
        $t->contains('<mtable rowspacing="2pt" data-tex-rowspacing="after-row-1:2pt"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>', $spacedRowsMathml);
        $t->contains('<annotation encoding="application/x-tex">\\pmatrix{a &amp; b \\cr[2pt] c &amp; d\\cr}</annotation>', $spacedRowsMathml);
        $t->contains('<mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>', $crcrRowsMathml);
        $t->true(!str_contains($combined, '<mi>\\matrix</mi>'));
        $t->true(!str_contains($combined, '<mi>\\pmatrix</mi>'));
        $t->true(!str_contains($combined, '<mi>\\bmatrix</mi>'));
        $t->true(!str_contains($combined, '<mi>\\cases</mi>'));
        $t->true(!str_contains($combined, '<mi>\\cr</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\matrix'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\matrix{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\matrix{a & {b \\cr c}'));
    },
    'converts bounded plain tex alignment commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $eqalignMathml = $converter->texToMathMl('\\eqalign{p_i &= m_i \\cr q_i &= n_i}', true);
        $displayLinesMathml = $converter->texToMathMl('\\displaylines{p_i + m_i \\cr q_i + n_i}');
        $spacedRowsMathml = $converter->texToMathMl('\\eqalign{x &= y \\cr[1ex] u &= v\\cr}');
        $nestedMatrixMathml = $converter->texToMathMl('\\eqalign{A &= \\matrix{a & b \\cr c & d} \\cr B &= C}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\displaylines{x+y \\cr z}');
        $combined = $eqalignMathml . $displayLinesMathml . $spacedRowsMathml . $nestedMatrixMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $eqalignMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>n</mi><mi>i</mi></msub></mtd></mtr></mtable>', $eqalignMathml);
        $t->contains('<annotation encoding="application/x-tex">\\eqalign{p_i &amp;= m_i \\cr q_i &amp;= n_i}</annotation>', $eqalignMathml);
        $t->contains('<mtable columnalign="center"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub><mo>+</mo><msub><mi>n</mi><mi>i</mi></msub></mtd></mtr></mtable>', $displayLinesMathml);
        $t->contains('<annotation encoding="application/x-tex">\\displaylines{p_i + m_i \\cr q_i + n_i}</annotation>', $displayLinesMathml);
        $t->contains('<mtable columnalign="right left" rowspacing="1ex" data-tex-rowspacing="after-row-1:1ex"><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd></mtr><mtr><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd></mtr></mtable>', $spacedRowsMathml);
        $t->contains('<mtd><mo>=</mo><mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable></mtd>', $nestedMatrixMathml);
        $t->contains('alttext="table row x plus y; row z"', $accessibleMathml);
        $t->true(!str_contains($combined, '<mi>\\eqalign</mi>'));
        $t->true(!str_contains($combined, '<mi>\\displaylines</mi>'));
        $t->true(!str_contains($combined, '<mi>\\cr</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\eqalign'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\eqalign{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\eqalign{x = y \\cr z = w}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\displaylines{x & y}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\displaylines{x \\cr[bad] y}'));
    },
    'preserves bounded tex optional row spacing as mathml review metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $alignedMathml = $converter->texToMathMl('\\begin{aligned}a &= b \\\\[.5em] c &= d\\end{aligned}', true);
        $arrayMathml = $converter->texToMathMl('\\begin{array}{l|cr}p_i & m_i & 1 \\\\[1ex] q_i & n_i & 2 \\\\ r_i & s_i & 3\\end{array}');
        $matrixMathml = $converter->texToMathMl('\\begin{matrix}a \\\\[2pt] b\\end{matrix}');
        $alignedAtMathml = $converter->texToMathMl('\\begin{alignedat}{2}a &= b & c &= d \\\\[3mm] e &= f & g &= h\\end{alignedat}');
        $eqnarrayMathml = $converter->texToMathMl('\\begin{eqnarray}a &=& b \\\\[4px] c &=& d\\end{eqnarray}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alignedMathml);
        $t->contains('<mtable columnalign="right left" rowspacing=".5em" data-tex-rowspacing="after-row-1:.5em"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr></mtable>', $alignedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{aligned}a &amp;= b \\\\[.5em] c &amp;= d\\end{aligned}</annotation>', $alignedMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="solid none" rowspacing="1ex normal" data-tex-rowspacing="after-row-1:1ex"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><mn>1</mn></mtd></mtr>', $arrayMathml);
        $t->contains('<mtr><mtd><msub><mi>r</mi><mi>i</mi></msub></mtd><mtd><msub><mi>s</mi><mi>i</mi></msub></mtd><mtd><mn>3</mn></mtd></mtr></mtable>', $arrayMathml);
        $t->contains('<mtable rowspacing="2pt" data-tex-rowspacing="after-row-1:2pt"><mtr><mtd><mi>a</mi></mtd></mtr><mtr><mtd><mi>b</mi></mtd></mtr></mtable>', $matrixMathml);
        $t->contains('<mtable columnalign="right left right left" rowspacing="3mm" data-tex-rowspacing="after-row-1:3mm">', $alignedAtMathml);
        $t->contains('<mtable columnalign="right center left" rowspacing="4px" data-tex-rowspacing="after-row-1:4px">', $eqnarrayMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{aligned}a &= b \\\\[bad] c &= d\\end{aligned}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}a \\\\[] b\\end{matrix}'));
    },
    'converts bounded tex cases environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $casesMathml = $converter->texToMathMl('\\begin{cases}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{cases}', true);
        $nestedCasesMathml = $converter->texToMathMl('\\begin{cases}\\frac{a}{b} & a \\ge b \\\\ \\sqrt[n]{x} & a < b\\end{cases}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $casesMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable>', $casesMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases}</annotation>', $casesMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd><mtd><mi>a</mi><mo>≥</mo><mi>b</mi></mtd></mtr><mtr><mtd><mroot><mi>x</mi><mi>n</mi></mroot></mtd><mtd><mi>a</mi><mo>&lt;</mo><mi>b</mi></mtd></mtr></mtable>', $nestedCasesMathml);
    },
    'converts bounded tex mathtools cases aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $dcasesMathml = $converter->texToMathMl('\\begin{dcases}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{dcases}', true);
        $rcasesMathml = $converter->texToMathMl('\\begin{rcases}q_i & q_i \\in Q \\\\ 0 & \\text{otherwise}\\end{rcases}', true);
        $drcasesStarMathml = $converter->texToMathMl('\\begin{drcases*}r_i & r_i \\in R \\\\ 0 & \\text{otherwise}\\end{drcases*}', true);
        $accessibleRcasesMathml = $converter->texToAccessibleMathMl('\\begin{rcases}x & y\\end{rcases}');
        $combined = $dcasesMathml . $rcasesMathml . $drcasesStarMathml;

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $dcasesMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mstyle displaystyle="true"><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable></mstyle>', $dcasesMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{dcases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{dcases}</annotation>', $dcasesMathml);
        $t->contains('<mrow><mtable columnalign="left left"><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>q</mi><mi>i</mi></msub><mo>∈</mo><mi>Q</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable><mo fence="true" stretchy="true">}</mo></mrow>', $rcasesMathml);
        $t->contains('<mstyle displaystyle="true"><mtable columnalign="left left"><mtr><mtd><msub><mi>r</mi><mi>i</mi></msub></mtd><mtd><msub><mi>r</mi><mi>i</mi></msub><mo>∈</mo><mi>R</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable></mstyle><mo fence="true" stretchy="true">}</mo>', $drcasesStarMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{drcases*}r_i &amp; r_i \\in R \\\\ 0 &amp; \\text{otherwise}\\end{drcases*}</annotation>', $drcasesStarMathml);
        $t->contains('alttext="table row x, y right brace"', $accessibleRcasesMathml);
        $t->contains('intent="row(table(row(x,y)),right_brace)"', $accessibleRcasesMathml);
        $t->true(!str_contains($combined, '<mi>\\dcases</mi>'));
        $t->true(!str_contains($combined, '<mi>\\rcases</mi>'));
        $t->true(!str_contains($combined, '<mi>\\drcases</mi>'));
        $t->true(!str_contains($drcasesStarMathml, '<mo>*</mo>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{dcases}\\end{dcases}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{rcases}x & y\\end{dcases}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{drcases*}x & y\\end{drcases}'));
    },
    'converts bounded tex starred matrix environment aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $starredMatrixMathml = $converter->texToMathMl('\\begin{pmatrix*}p_i & m_i \\\\ q_i & n_i\\end{pmatrix*}', true);
        $starredCasesMathml = $converter->texToMathMl('\\begin{cases*}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{cases*}', true);
        $starredSmallMatrixMathml = $converter->texToMathMl('\\left(\\begin{smallmatrix*}p_1 & m_1 \\\\ p_2 & m_2\\end{smallmatrix*}\\right)', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $starredMatrixMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>', $starredMatrixMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{pmatrix*}p_i &amp; m_i \\\\ q_i &amp; n_i\\end{pmatrix*}</annotation>', $starredMatrixMathml);
        $t->contains('<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable>', $starredCasesMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{cases*}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases*}</annotation>', $starredCasesMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mstyle scriptlevel="1"><mtable rowspacing="0.1em" columnspacing="0.2778em"><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable></mstyle><mo fence="true" stretchy="true">)</mo>', $starredSmallMatrixMathml);
        $t->contains('<annotation encoding="application/x-tex">\\left(\\begin{smallmatrix*}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{smallmatrix*}\\right)</annotation>', $starredSmallMatrixMathml);
        $t->true(!str_contains($starredMatrixMathml, '<mo>*</mo>'), 'Expected starred matrix suffix to stay metadata-only instead of rendering as an operator');
        $t->true(!str_contains($starredCasesMathml, '<mi>*</mi>'), 'Expected starred cases suffix to stay metadata-only instead of rendering as an identifier');
    },
    'converts bounded tex array environments with column specs to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $arrayMathml = $converter->texToMathMl('\\begin{array}{rl}x_i &= p_i \\\\ y_i &= \\frac{a_i}{b_i}\\end{array}', true);
        $ruledArrayMathml = $converter->texToMathMl('\\begin{array}{l|c|r}\\alpha & \\beta & \\omega \\\\ 1 & 2 & 3\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $arrayMathml);
        $t->contains('<mtable columnalign="right left"><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>', $arrayMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{rl}x_i &amp;= p_i \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{array}</annotation>', $arrayMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="solid solid"><mtr><mtd><mi>α</mi></mtd><mtd><mi>β</mi></mtd><mtd><mi>ω</mi></mtd></mtr><mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd><mtd><mn>3</mn></mtd></mtr></mtable>', $ruledArrayMathml);
    },
    'converts bounded tex array paragraph width columns to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $widthMathml = $converter->texToMathMl('\\begin{array}{p{2cm}|m{1.5em}|b{8pt}}p_i & \\text{middle review} & 1 \\\\ q_i & n_i & 2\\end{array}', true);
        $mixedMathml = $converter->texToMathMl('\\begin{array}{l|p{3em}r}a & \\text{wrapped note} & b\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $widthMathml);
        $t->contains('<mtable columnalign="left left left" columnwidth="2cm 1.5em 8pt" data-tex-column-valign="top middle bottom" columnlines="solid solid"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mtext>middle review</mtext></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd><mtd><mn>2</mn></mtd></mtr></mtable>', $widthMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{p{2cm}|m{1.5em}|b{8pt}}p_i &amp; \\text{middle review} &amp; 1 \\\\ q_i &amp; n_i &amp; 2\\end{array}</annotation>', $widthMathml);
        $t->contains('<mtable columnalign="left left right" columnwidth="auto 3em auto" data-tex-column-valign="baseline top baseline" columnlines="solid none"><mtr><mtd><mi>a</mi></mtd><mtd><mtext>wrapped note</mtext></mtd><mtd><mi>b</mi></mtd></mtr></mtable>', $mixedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{l|p{3em}r}a &amp; \\text{wrapped note} &amp; b\\end{array}</annotation>', $mixedMathml);
    },
    'converts bounded tex array repeated preambles to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $repeatMathml = $converter->texToMathMl('\\begin{array}{*{2}{c|}r}p_1 & m_1 & 1 \\\\ p_2 & m_2 & 2\\end{array}', true);
        $widthRepeatMathml = $converter->texToMathMl('\\begin{array}{l|*{2}{p{2cm}|}r}a & \\text{draft} & \\text{final} & b\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $repeatMathml);
        $t->contains('<mtable columnalign="center center right" columnlines="solid solid"><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd><mtd><mn>2</mn></mtd></mtr></mtable>', $repeatMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{*{2}{c|}r}p_1 &amp; m_1 &amp; 1 \\\\ p_2 &amp; m_2 &amp; 2\\end{array}</annotation>', $repeatMathml);
        $t->contains('<mtable columnalign="left left left right" columnwidth="auto 2cm 2cm auto" data-tex-column-valign="baseline top top baseline" columnlines="solid solid solid"><mtr><mtd><mi>a</mi></mtd><mtd><mtext>draft</mtext></mtd><mtd><mtext>final</mtext></mtd><mtd><mi>b</mi></mtd></mtr></mtable>', $widthRepeatMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{l|*{2}{p{2cm}|}r}a &amp; \\text{draft} &amp; \\text{final} &amp; b\\end{array}</annotation>', $widthRepeatMathml);
    },
    'converts bounded tex array preamble hooks to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $hookMathml = $converter->texToMathMl('\\begin{array}{>{\\text{src}}l<{\\hspace{.25em}}@{\\,}c}p_i & m_i \\\\ q_i & n_i\\end{array}', true);
        $leadingHookMathml = $converter->texToMathMl('\\begin{array}{@{\\quad}r>{\\mbox{review}}l}a & b\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $hookMathml);
        $t->contains('<mtable columnalign="left center" data-tex-column-hooks="pre-1:\\text{src} | post-1:\\hspace{.25em} | gap-after-1:\\,"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd></mtr></mtable>', $hookMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{&gt;{\\text{src}}l&lt;{\\hspace{.25em}}@{\\,}c}p_i &amp; m_i \\\\ q_i &amp; n_i\\end{array}</annotation>', $hookMathml);
        $t->contains('<mtable columnalign="right left" data-tex-column-hooks="gap-before-1:\\quad | pre-2:\\mbox{review}"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr></mtable>', $leadingHookMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{>{\\bfseries}l}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{@{\\input{secret}}l}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{l<{}c}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{>{\\hspace{bad}}l}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{>{\\text{}}l}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{>{\\text{src}}}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sum_{\\begin{subarray}{>{\\text{src}}c}i=1\\end{subarray}}^{n} a_i'));
    },
    'converts bounded tex array bang separator hooks to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $separatorMathml = $converter->texToMathMl('\\begin{array}{l!{\\quad}c|!{\\text{review}}r}p_i & m_i & q_i \\\\ a & b & c\\end{array}', true);
        $leadingSeparatorMathml = $converter->texToMathMl('\\begin{array}{!{\\mspace{2mu}}r!{\\hspace{.25em}}l}a & b\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $separatorMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="none solid" data-tex-column-hooks="separator-after-1:\\quad | separator-after-2:\\text{review}"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd><mtd><mi>c</mi></mtd></mtr></mtable>', $separatorMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{l!{\\quad}c|!{\\text{review}}r}p_i &amp; m_i &amp; q_i \\\\ a &amp; b &amp; c\\end{array}</annotation>', $separatorMathml);
        $t->contains('<mtable columnalign="right left" data-tex-column-hooks="separator-before-1:\\mspace{2mu} | separator-after-1:\\hspace{.25em}"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr></mtable>', $leadingSeparatorMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{l!{}c}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{l!{\\input{secret}}c}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sum_{\\begin{subarray}{!{\\quad}c}i=1\\end{subarray}}^{n} a_i'));
    },
    'converts bounded tex array multicolumn cells to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $multicolumnMathml = $converter->texToMathMl('\\begin{array}{lcr}p_i & \\multicolumn{2}{|c|}{m_i + q_i} \\\\ a & b & c\\end{array}', true);
        $hookedMulticolumnMathml = $converter->texToMathMl('\\begin{array}{lcr}\\multicolumn{1}{>{\\text{src}}p{2cm}}{\\text{review}} & x & y\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $multicolumnMathml);
        $t->contains('<mtable columnalign="left center right"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd columnspan="2" columnalign="center" data-tex-column-lines="left right"><mrow><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><msub><mi>q</mi><mi>i</mi></msub></mrow></mtd></mtr><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd><mtd><mi>c</mi></mtd></mtr></mtable>', $multicolumnMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{lcr}p_i &amp; \\multicolumn{2}{|c|}{m_i + q_i} \\\\ a &amp; b &amp; c\\end{array}</annotation>', $multicolumnMathml);
        $t->true(!str_contains($multicolumnMathml, '<mi>\\multicolumn</mi>'), 'Expected TeX \\multicolumn to become array cell span metadata');
        $t->contains('<mtd columnspan="1" columnalign="left" columnwidth="2cm" data-tex-column-valign="top" data-tex-column-hooks="pre-1:\\text{src}"><mtext>review</mtext></mtd>', $hookedMulticolumnMathml);
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{lcr}\\multicolumn{1}{&gt;{\\text{src}}p{2cm}}{\\text{review}} &amp; x &amp; y\\end{array}</annotation>', $hookedMulticolumnMathml);

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}\\multicolumn{0}{c}{x} & y\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}\\multicolumn{3}{c}{x}\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}\\multicolumn{2}{c}{x} & y\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}\\multicolumn{1}{cc}{x} & y\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}\\multicolumn{1}{c}{} & y\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}\\multicolumn{1}{c}{x} + y & z\\end{array}'));
    },
    'converts bounded tex array rule commands to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $ruleMathml = $converter->texToMathMl('\\begin{array}{l|c|r}\\hline p_i & m_i & 1 \\\\ \\hline q_i & n_i & 2 \\\\ \\hline\\end{array}', true);
        $sparseRulesMathml = $converter->texToMathMl('\\begin{array}{lc|r}a & b & c \\\\ x & y & z\\end{array}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $ruleMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="solid solid" data-tex-topline="solid" rowlines="solid" data-tex-bottomline="solid"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd><mtd><mn>2</mn></mtd></mtr></mtable>', $ruleMathml);
        $t->true(!str_contains($ruleMathml, '<mi>\\hline</mi>'), 'Expected TeX \\hline to become array rule metadata');
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{l|c|r}\\hline p_i &amp; m_i &amp; 1 \\\\ \\hline q_i &amp; n_i &amp; 2 \\\\ \\hline\\end{array}</annotation>', $ruleMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="none solid"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd><mtd><mi>c</mi></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mi>y</mi></mtd><mtd><mi>z</mi></mtd></mtr></mtable>', $sparseRulesMathml);
    },
    'converts bounded tex array cline commands to partial rule metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $clineMathml = $converter->texToMathMl('\\begin{array}{l|c|r}p_i & m_i & 1 \\\\ \\cline{2-3} q_i & n_i & 2 \\\\ \\cline{1-1}\\cline{3-3} r_i & s_i & 3\\end{array}', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $clineMathml);
        $t->contains('<mtable columnalign="left center right" columnlines="solid solid" data-tex-clines="after-row-1:2-3 after-row-2:1-1,3-3"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd><mtd><mn>2</mn></mtd></mtr><mtr><mtd><msub><mi>r</mi><mi>i</mi></msub></mtd><mtd><msub><mi>s</mi><mi>i</mi></msub></mtd><mtd><mn>3</mn></mtd></mtr></mtable>', $clineMathml);
        $t->true(!str_contains($clineMathml, '<mi>\\cline</mi>'), 'Expected TeX \\cline to become array partial-rule metadata');
        $t->contains('<annotation encoding="application/x-tex">\\begin{array}{l|c|r}p_i &amp; m_i &amp; 1 \\\\ \\cline{2-3} q_i &amp; n_i &amp; 2 \\\\ \\cline{1-1}\\cline{3-3} r_i &amp; s_i &amp; 3\\end{array}</annotation>', $clineMathml);
    },
    'converts bounded tex compact matrix and subarray environments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $compactMathml = $converter->texToMathMl('\\left(\\begin{smallmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i', true);
        $alignedSubarrayMathml = $converter->texToMathMl('\\prod_{\\begin{subarray}{r} k \\to \\infty \\\\ k > 0 \\end{subarray}} k');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $compactMathml);
        $t->contains('<mo fence="true" stretchy="true">(</mo><mstyle scriptlevel="1"><mtable rowspacing="0.1em" columnspacing="0.2778em"><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable></mstyle><mo fence="true" stretchy="true">)</mo>', $compactMathml);
        $t->contains('<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>', $compactMathml);
        $t->contains('<annotation encoding="application/x-tex">\\left(\\begin{smallmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i</annotation>', $compactMathml);
        $t->contains('<msub><mo>∏</mo><mtable columnalign="right" rowspacing="0.1em"><mtr><mtd><mi>k</mi><mo>→</mo><mi>∞</mi></mtd></mtr><mtr><mtd><mi>k</mi><mo>&gt;</mo><mn>0</mn></mtd></mtr></mtable></msub><mi>k</mi>', $alignedSubarrayMathml);
    },
    'converts bounded tex above below and style wrappers to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $aboveBelowMathml = $converter->texToMathMl('\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n', true);
        $braceMathml = $converter->texToMathMl('\\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}}');
        $tokenBraceMathml = $converter->texToMathMl('\\overbrace x_i^n + \\underbrace y_j', true);
        $styleMathml = $converter->texToMathMl('\\displaystyle \\frac{a}{b} + \\textstyle c + \\scriptstyle d_i + \\scriptscriptstyle e');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $aboveBelowMathml);
        $t->contains('<mover><msub><mi>p</mi><mi>i</mi></msub><mtext>new</mtext></mover>', $aboveBelowMathml);
        $t->contains('<msub><munder><mo>lim</mo><mn>0</mn></munder><mrow><mi>n</mi><mo>→</mo><mi>∞</mi></mrow></msub><msub><mi>a</mi><mi>n</mi></msub>', $aboveBelowMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n</annotation>', $aboveBelowMathml);
        $t->contains('<msup><mover><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mo>⏞</mo></mover><mtext>sum</mtext></msup>', $braceMathml);
        $t->contains('<msub><munder><msub><mi>m</mi><mi>i</mi></msub><mo>⏟</mo></munder><mtext>media</mtext></msub>', $braceMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $tokenBraceMathml);
        $t->contains('<msubsup><mover><mi>x</mi><mo>⏞</mo></mover><mi>i</mi><mi>n</mi></msubsup><mo>+</mo><msub><munder><mi>y</mi><mo>⏟</mo></munder><mi>j</mi></msub>', $tokenBraceMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overbrace x_i^n + \\underbrace y_j</annotation>', $tokenBraceMathml);
        $t->contains('<mstyle displaystyle="true"><mfrac><mi>a</mi><mi>b</mi></mfrac></mstyle><mo>+</mo><mstyle displaystyle="false"><mi>c</mi></mstyle>', $styleMathml);
        $t->contains('<mstyle scriptlevel="1"><msub><mi>d</mi><mi>i</mi></msub></mstyle><mo>+</mo><mstyle scriptlevel="2"><mi>e</mi></mstyle>', $styleMathml);
    },
    'converts bounded tex combined over under wrappers to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $stackMathml = $converter->texToMathMl('\\overunderset{\\text{publish}}{\\operatorname{draft}}{p_i} + \\underoverset{0}{\\infty}{\\lim}_{n \\to \\infty} a_n', true);
        $tokenMathml = $converter->texToMathMl('\\overunderset\\alpha0x_i + \\underoverset\\beta\\gamma y^2', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\overunderset{\\text{publish}}{\\text{draft}}{x}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $stackMathml);
        $t->contains('<munderover><msub><mi>p</mi><mi>i</mi></msub><mi>draft</mi><mtext>publish</mtext></munderover>', $stackMathml);
        $t->contains('<msub><munderover><mo>lim</mo><mn>0</mn><mi>∞</mi></munderover><mrow><mi>n</mi><mo>→</mo><mi>∞</mi></mrow></msub><msub><mi>a</mi><mi>n</mi></msub>', $stackMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overunderset{\\text{publish}}{\\operatorname{draft}}{p_i} + \\underoverset{0}{\\infty}{\\lim}_{n \\to \\infty} a_n</annotation>', $stackMathml);
        $t->contains('<msub><munderover><mi>x</mi><mn>0</mn><mi>α</mi></munderover><mi>i</mi></msub>', $tokenMathml);
        $t->contains('<msup><munderover><mi>y</mi><mi>β</mi><mi>γ</mi></munderover><mn>2</mn></msup>', $tokenMathml);
        $t->contains('alttext="x under draft over publish"', $accessibleMathml);
        $t->contains('intent="underover(x,draft,publish)"', $accessibleMathml);
        $t->true(!str_contains($stackMathml . $tokenMathml, '<mi>\\overunderset</mi>'), 'Expected TeX \\overunderset to become munderover, not a literal identifier');
        $t->true(!str_contains($stackMathml . $tokenMathml, '<mi>\\underoverset</mi>'), 'Expected TeX \\underoverset to become munderover, not a literal identifier');

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overunderset{a}{b}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overunderset{}{b}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underoverset{a}{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underoverset{a}{b}_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overunderset_1 x'));
    },
    'converts bounded plain tex buildrel relations to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $buildrelMathml = $converter->texToMathMl('\\buildrel{\\text{def}}\\over= + A \\buildrel{\\operatorname{iso}}\\over\\longrightarrow B + x \\buildrel\\star\\over\\Rightarrow y', true);
        $scriptedMathml = $converter->texToMathMl('a \\buildrel{n+1}\\over{\\sim}_i b');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\buildrel{\\text{def}}\\over=');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $buildrelMathml);
        $t->contains('<mover><mo>=</mo><mtext>def</mtext></mover><mo>+</mo><mi>A</mi><mover><mo>→</mo><mi>iso</mi></mover><mi>B</mi><mo>+</mo><mi>x</mi><mover><mo>⇒</mo><mo>⋆</mo></mover><mi>y</mi>', $buildrelMathml);
        $t->contains('<annotation encoding="application/x-tex">\\buildrel{\\text{def}}\\over= + A \\buildrel{\\operatorname{iso}}\\over\\longrightarrow B + x \\buildrel\\star\\over\\Rightarrow y</annotation>', $buildrelMathml);
        $t->contains('<mi>a</mi><msub><mover><mo>∼</mo><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mover><mi>i</mi></msub><mi>b</mi>', $scriptedMathml);
        $t->contains('alttext="equals over def"', $accessibleMathml);
        $t->contains('intent="over(equals,def)"', $accessibleMathml);
        $t->true(!str_contains($buildrelMathml, '<mi>\\buildrel</mi>'), 'Expected TeX \\buildrel to become a mover, not a literal identifier');
        $t->true(!str_contains($buildrelMathml, '<mfrac><mrow><mi>\\buildrel</mi>'), 'Expected TeX \\buildrel to avoid infix-fraction fallback');

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\buildrel'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\buildrel{}\\over='));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\buildrel{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\buildrel{x}\\atop='));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\buildrel{x}\\over'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\buildrel{x}\\over_1'));
    },
    'converts bounded tex overbracket and underbracket accents to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $bracketMathml = $converter->texToMathMl('\\overbracket{p_i + m_i}^{\\text{review}} + \\underbracket{q_i}_{0}', true);
        $tokenBracketMathml = $converter->texToMathMl('\\overbracket x^2 + \\underbracket y_0');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\overbracket{x+y} + \\underbracket{z}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $bracketMathml);
        $t->contains('<msup><mover><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow><mo>⎴</mo></mover><mtext>review</mtext></msup>', $bracketMathml);
        $t->contains('<msub><munder><msub><mi>q</mi><mi>i</mi></msub><mo>⎵</mo></munder><mn>0</mn></msub>', $bracketMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overbracket{p_i + m_i}^{\\text{review}} + \\underbracket{q_i}_{0}</annotation>', $bracketMathml);
        $t->contains('<msup><mover><mi>x</mi><mo>⎴</mo></mover><mn>2</mn></msup><mo>+</mo><msub><munder><mi>y</mi><mo>⎵</mo></munder><mn>0</mn></msub>', $tokenBracketMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overbracket x^2 + \\underbracket y_0</annotation>', $tokenBracketMathml);
        $t->contains('alttext="x plus y over over bracket plus z under under bracket"', $accessibleMathml);
        $t->contains('intent="row(over(row(x,plus,y),over_bracket),plus,under(z,under_bracket))"', $accessibleMathml);
    },
    'converts bounded tex overparen underparen overgroup and undergroup accents to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $parenMathml = $converter->texToMathMl('\\overparen{p_i + m_i}^{\\text{review}} + \\underparen{q_i}_{0}', true);
        $groupMathml = $converter->texToMathMl('\\overgroup{x+y} + \\undergroup{z}');
        $tokenMathml = $converter->texToMathMl('\\overparen x^2 + \\undergroup y_0');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\overparen{x+y} + \\undergroup{z}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $parenMathml);
        $t->contains('<msup><mover><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow><mo>⏜</mo></mover><mtext>review</mtext></msup>', $parenMathml);
        $t->contains('<msub><munder><msub><mi>q</mi><mi>i</mi></msub><mo>⏝</mo></munder><mn>0</mn></msub>', $parenMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overparen{p_i + m_i}^{\\text{review}} + \\underparen{q_i}_{0}</annotation>', $parenMathml);
        $t->contains('<mover><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mo>⏠</mo></mover><mo>+</mo><munder><mi>z</mi><mo>⏡</mo></munder>', $groupMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overgroup{x+y} + \\undergroup{z}</annotation>', $groupMathml);
        $t->contains('<msup><mover><mi>x</mi><mo>⏜</mo></mover><mn>2</mn></msup><mo>+</mo><msub><munder><mi>y</mi><mo>⏡</mo></munder><mn>0</mn></msub>', $tokenMathml);
        $t->contains('<annotation encoding="application/x-tex">\\overparen x^2 + \\undergroup y_0</annotation>', $tokenMathml);
        $t->contains('alttext="x plus y over over parenthesis plus z under under group"', $accessibleMathml);
        $t->contains('intent="row(over(row(x,plus,y),over_parenthesis),plus,under(z,under_group))"', $accessibleMathml);
        $t->true(!str_contains($parenMathml . $groupMathml . $tokenMathml, '<mi>\\overparen</mi>'));
        $t->true(!str_contains($parenMathml . $groupMathml . $tokenMathml, '<mi>\\undergroup</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overparen{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\undergroup_1'));
    },
    'converts bounded tex color phantom and cancel commands to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $colorMathml = $converter->texToMathMl('\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\color{review-blue}{x+y}', true);
        $phantomMathml = $converter->texToMathMl('\\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}}');
        $cancelMathml = $converter->texToMathMl('\\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $colorMathml);
        $t->contains('<mstyle mathcolor="red"><msub><mi>p</mi><mi>i</mi></msub></mstyle>', $colorMathml);
        $t->contains('<mstyle mathcolor="#336699"><mi>media</mi></mstyle>', $colorMathml);
        $t->contains('<mstyle mathcolor="review-blue"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow></mstyle>', $colorMathml);
        $t->contains('<annotation encoding="application/x-tex">\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\color{review-blue}{x+y}</annotation>', $colorMathml);
        $t->contains('<mphantom><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mphantom>', $phantomMathml);
        $t->contains('<mpadded height="0" depth="0"><mphantom><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mphantom></mpadded>', $phantomMathml);
        $t->contains('<mpadded width="0"><mphantom><mfrac><mi>a</mi><mi>b</mi></mfrac></mphantom></mpadded>', $phantomMathml);
        $t->contains('<menclose notation="updiagonalstrike"><msub><mi>x</mi><mi>i</mi></msub></menclose>', $cancelMathml);
        $t->contains('<menclose notation="downdiagonalstrike"><msub><mi>y</mi><mi>i</mi></msub></menclose>', $cancelMathml);
        $t->contains('<menclose notation="updiagonalstrike downdiagonalstrike"><msub><mi>z</mi><mi>i</mi></msub></menclose>', $cancelMathml);
    },
    'converts bounded tex color token arguments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $tokenMathml = $converter->texToMathMl('\\textcolor{red}x_i + \\textcolor{#336699}\\operatorname{media} + \\textcolor{review-blue}\\sqrt q_i', true);
        $modelTokenMathml = $converter->texToMathMl('\\textcolor[HTML]{336699}\\operatorname{media} + \\textcolor[rgb]{0.2,0.4,0.6}p_i + \\color[RGB]{51,102,153}\\frac12', true);
        $groupMathml = $converter->texToMathMl('\\textcolor{red}{x_i + y_i} + \\color{green}{a+b}');
        $declarationMathml = $converter->texToMathMl('\\color{red} p_i + m_i + \\frac{a}{b}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $tokenMathml);
        $t->contains('<msub><mstyle mathcolor="red"><mi>x</mi></mstyle><mi>i</mi></msub>', $tokenMathml);
        $t->contains('<mstyle mathcolor="#336699"><mi>media</mi></mstyle>', $tokenMathml);
        $t->contains('<msub><mstyle mathcolor="review-blue"><msqrt><mi>q</mi></msqrt></mstyle><mi>i</mi></msub>', $tokenMathml);
        $t->contains('<annotation encoding="application/x-tex">\\textcolor{red}x_i + \\textcolor{#336699}\\operatorname{media} + \\textcolor{review-blue}\\sqrt q_i</annotation>', $tokenMathml);
        $t->contains('<mstyle mathcolor="#336699"><mi>media</mi></mstyle>', $modelTokenMathml);
        $t->contains('<msub><mstyle mathcolor="#336699"><mi>p</mi></mstyle><mi>i</mi></msub>', $modelTokenMathml);
        $t->contains('<mstyle mathcolor="#336699"><mfrac><mn>1</mn><mn>2</mn></mfrac></mstyle>', $modelTokenMathml);
        $t->contains('<mstyle mathcolor="red"><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow></mstyle>', $groupMathml);
        $t->contains('<mstyle mathcolor="green"><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow></mstyle>', $groupMathml);
        $t->contains('<mstyle mathcolor="red"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mrow></mstyle>', $declarationMathml);
        $t->true(!str_contains($tokenMathml . $modelTokenMathml, '<mi>\\textcolor</mi>') && !str_contains($modelTokenMathml, '<mo>[</mo>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor{red}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor{red}_1'));
    },
    'converts bounded tex color declarations to scoped mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $declarationMathml = $converter->texToMathMl('\\color{red} p_i + m_i + \\frac{a}{b}', true);
        $groupScopedMathml = $converter->texToMathMl('x + {\\color{blue} y + z} + q');
        $arrayScopedMathml = $converter->texToMathMl('\\begin{array}{cc}\\color{#336699}p_i & m_i \\\\ q_i & \\color{review-blue}\\frac{a}{b}\\end{array}');
        $groupCommandMathml = $converter->texToMathMl('\\color{green}{x+y} + z');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\color{red} x + y');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $declarationMathml);
        $t->contains('<mstyle mathcolor="red"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mrow></mstyle>', $declarationMathml);
        $t->contains('<annotation encoding="application/x-tex">\\color{red} p_i + m_i + \\frac{a}{b}</annotation>', $declarationMathml);
        $t->contains('<mi>x</mi><mo>+</mo><mstyle mathcolor="blue"><mrow><mi>y</mi><mo>+</mo><mi>z</mi></mrow></mstyle><mo>+</mo><mi>q</mi>', $groupScopedMathml);
        $t->contains('<mtd><mstyle mathcolor="#336699"><msub><mi>p</mi><mi>i</mi></msub></mstyle></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd>', $arrayScopedMathml);
        $t->contains('<mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><mstyle mathcolor="review-blue"><mfrac><mi>a</mi><mi>b</mi></mfrac></mstyle></mtd>', $arrayScopedMathml);
        $t->contains('<mstyle mathcolor="green"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow></mstyle><mo>+</mo><mi>z</mi>', $groupCommandMathml);
        $t->contains('alttext="x plus y"', $accessibleMathml);
        $t->contains('intent="row(x,plus,y)"', $accessibleMathml);
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{red}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{red}_1'));
    },
    'converts bounded tex xcolor model arguments to mathml colors' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $modelMathml = $converter->texToMathMl('\\textcolor[HTML]{336699}{\\operatorname{media}} + \\color[RGB]{51,102,153}{p_i} + \\textcolor[rgb]{0.2,0.4,0.6}{m_i}', true);
        $declarationMathml = $converter->texToMathMl('\\color[gray]{.5} p_i + \\textcolor[named]{reviewblue}{m_i}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\textcolor[HTML]{336699}{x+y}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $modelMathml);
        $t->contains('<mstyle mathcolor="#336699"><mi>media</mi></mstyle>', $modelMathml);
        $t->contains('<mstyle mathcolor="#336699"><msub><mi>p</mi><mi>i</mi></msub></mstyle>', $modelMathml);
        $t->contains('<mstyle mathcolor="#336699"><msub><mi>m</mi><mi>i</mi></msub></mstyle>', $modelMathml);
        $t->contains('<annotation encoding="application/x-tex">\\textcolor[HTML]{336699}{\\operatorname{media}} + \\color[RGB]{51,102,153}{p_i} + \\textcolor[rgb]{0.2,0.4,0.6}{m_i}</annotation>', $modelMathml);
        $t->contains('<mstyle mathcolor="#808080"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mstyle mathcolor="reviewblue"><msub><mi>m</mi><mi>i</mi></msub></mstyle></mrow></mstyle>', $declarationMathml);
        $t->contains('alttext="x plus y"', $accessibleMathml);
        $t->contains('intent="row(x,plus,y)"', $accessibleMathml);
        $t->true(!str_contains($modelMathml . $declarationMathml, '<mo>[</mo>'), 'Expected xcolor model brackets to stay out of MathML tokens');
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor[HTML]{33669}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor[RGB]{256,0,0}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor[rgb]{1.1,0,0}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor[gray]{2}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor[cmyk]{0,0,0,1}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color[]{red}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color[HTML]{336699}'));
    },
    'converts bounded tex colorbox and fcolorbox commands to mathml metadata' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $backgroundMathml = $converter->texToMathMl('\\colorbox{yellow}{p_i + m_i} + \\colorbox[HTML]{fff9cc}{\\operatorname{media}}', true);
        $framedMathml = $converter->texToMathMl('\\fcolorbox{red}{yellow}{p_i} + \\fcolorbox[RGB]{51,102,153}{255,249,204}{\\frac{a}{b}}');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\fcolorbox{red}{yellow}{x_i}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $backgroundMathml);
        $t->contains('<mstyle mathbackground="yellow"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mstyle>', $backgroundMathml);
        $t->contains('<mstyle mathbackground="#fff9cc"><mi>media</mi></mstyle>', $backgroundMathml);
        $t->contains('<annotation encoding="application/x-tex">\\colorbox{yellow}{p_i + m_i} + \\colorbox[HTML]{fff9cc}{\\operatorname{media}}</annotation>', $backgroundMathml);
        $t->contains('<menclose notation="box" mathbackground="yellow" data-tex-framecolor="red"><msub><mi>p</mi><mi>i</mi></msub></menclose>', $framedMathml);
        $t->contains('<menclose notation="box" mathbackground="#fff9cc" data-tex-framecolor="#336699"><mfrac><mi>a</mi><mi>b</mi></mfrac></menclose>', $framedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\fcolorbox{red}{yellow}{p_i} + \\fcolorbox[RGB]{51,102,153}{255,249,204}{\\frac{a}{b}}</annotation>', $framedMathml);
        $t->contains('alttext="enclosed x sub i"', $accessibleMathml);
        $t->contains('intent="enclose(subscript(x,i))"', $accessibleMathml);
        $t->true(!str_contains($backgroundMathml . $framedMathml, '<mi>\\colorbox</mi>'));
        $t->true(!str_contains($backgroundMathml . $framedMathml, '<mi>\\fcolorbox</mi>'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\colorbox{yellow}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\colorbox{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\colorbox{yellow}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\fcolorbox{red}{yellow}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\fcolorbox{red}{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\fcolorbox[HTML]{336699}{fff9}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\fcolorbox[RGB]{51,102,153}[rgb]{1,1,1}{x}'));
    },
    'converts bounded tex colorbox fcolorbox and cancelto token arguments to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $backgroundMathml = $converter->texToMathMl('\\colorbox{yellow}x_i + \\colorbox[HTML]{fff9cc}\\operatorname{media}', true);
        $framedMathml = $converter->texToMathMl('\\fcolorbox{red}{yellow}q_i + \\fcolorbox[RGB]{51,102,153}{255,249,204}\\frac12', true);
        $cancelToMathml = $converter->texToMathMl('\\cancelto0x_i + \\cancelto\\alpha\\frac12', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\cancelto0x_i');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $backgroundMathml);
        $t->contains('<msub><mstyle mathbackground="yellow"><mi>x</mi></mstyle><mi>i</mi></msub><mo>+</mo><mstyle mathbackground="#fff9cc"><mi>media</mi></mstyle>', $backgroundMathml);
        $t->contains('<annotation encoding="application/x-tex">\\colorbox{yellow}x_i + \\colorbox[HTML]{fff9cc}\\operatorname{media}</annotation>', $backgroundMathml);
        $t->contains('<msub><menclose notation="box" mathbackground="yellow" data-tex-framecolor="red"><mi>q</mi></menclose><mi>i</mi></msub>', $framedMathml);
        $t->contains('<menclose notation="box" mathbackground="#fff9cc" data-tex-framecolor="#336699"><mfrac><mn>1</mn><mn>2</mn></mfrac></menclose>', $framedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\fcolorbox{red}{yellow}q_i + \\fcolorbox[RGB]{51,102,153}{255,249,204}\\frac12</annotation>', $framedMathml);
        $t->contains('<msub><mover><menclose notation="updiagonalstrike"><mi>x</mi></menclose><mn>0</mn></mover><mi>i</mi></msub><mo>+</mo><mover><menclose notation="updiagonalstrike"><mfrac><mn>1</mn><mn>2</mn></mfrac></menclose><mi>α</mi></mover>', $cancelToMathml);
        $t->contains('<annotation encoding="application/x-tex">\\cancelto0x_i + \\cancelto\\alpha\\frac12</annotation>', $cancelToMathml);
        $t->contains('alttext="enclosed x over 0 sub i"', $accessibleMathml);
        $t->contains('intent="subscript(over(enclose(x),0),i)"', $accessibleMathml);
        $t->true(!str_contains($backgroundMathml . $framedMathml . $cancelToMathml, '<mi>\\colorbox</mi>'));
        $t->true(!str_contains($backgroundMathml . $framedMathml . $cancelToMathml, '<mi>\\fcolorbox</mi>'));
        $t->true(!str_contains($backgroundMathml . $framedMathml . $cancelToMathml, '<mi>\\cancelto</mi>'));
    },
    'converts bounded tex boxed expressions to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $boxedMathml = $converter->texToMathMl('\\boxed{p_i + m_i} + \\boxed{\\frac{a}{b}}_j', true);
        $accessibleMathml = $converter->texToAccessibleMathMl('\\boxed{x_i}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $boxedMathml);
        $t->contains('<menclose notation="box"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></menclose>', $boxedMathml);
        $t->contains('<msub><menclose notation="box"><mfrac><mi>a</mi><mi>b</mi></mfrac></menclose><mi>j</mi></msub>', $boxedMathml);
        $t->contains('<annotation encoding="application/x-tex">\\boxed{p_i + m_i} + \\boxed{\\frac{a}{b}}_j</annotation>', $boxedMathml);
        $t->contains('alttext="enclosed x sub i"', $accessibleMathml);
        $t->contains('intent="enclose(subscript(x,i))"', $accessibleMathml);
    },
    'converts bounded tex smash and overlap boxes to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $smashMathml = $converter->texToMathMl('\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i}', true);
        $overlapMathml = $converter->texToMathMl('\\mathllap{p_i} + \\mathrlap{m_i} + \\mathclap{x+y} + \\llap{L} + \\rlap{R} + \\clap{C}');
        $tokenLayoutMathml = $converter->texToMathMl('\\smash x_i + \\smash[t] y^2 + \\mathllap L_i + \\mathrlap R + \\clap C', true);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $smashMathml);
        $t->contains('<mpadded height="0" depth="0"><mfrac><mi>a</mi><mi>b</mi></mfrac></mpadded>', $smashMathml);
        $t->contains('<mpadded height="0"><msub><mi>p</mi><mi>i</mi></msub></mpadded>', $smashMathml);
        $t->contains('<mpadded depth="0"><msub><mi>m</mi><mi>i</mi></msub></mpadded>', $smashMathml);
        $t->contains('<annotation encoding="application/x-tex">\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i}</annotation>', $smashMathml);
        $t->contains('<mpadded width="0" lspace="-1width"><msub><mi>p</mi><mi>i</mi></msub></mpadded>', $overlapMathml);
        $t->contains('<mpadded width="0"><msub><mi>m</mi><mi>i</mi></msub></mpadded>', $overlapMathml);
        $t->contains('<mpadded width="0" lspace="-0.5width"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow></mpadded>', $overlapMathml);
        $t->contains('<mpadded width="0" lspace="-1width"><mi>L</mi></mpadded><mo>+</mo><mpadded width="0"><mi>R</mi></mpadded><mo>+</mo><mpadded width="0" lspace="-0.5width"><mi>C</mi></mpadded>', $overlapMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathllap{p_i} + \\mathrlap{m_i} + \\mathclap{x+y} + \\llap{L} + \\rlap{R} + \\clap{C}</annotation>', $overlapMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $tokenLayoutMathml);
        $t->contains('<msub><mpadded height="0" depth="0"><mi>x</mi></mpadded><mi>i</mi></msub><mo>+</mo><msup><mpadded height="0"><mi>y</mi></mpadded><mn>2</mn></msup>', $tokenLayoutMathml);
        $t->contains('<msub><mpadded width="0" lspace="-1width"><mi>L</mi></mpadded><mi>i</mi></msub><mo>+</mo><mpadded width="0"><mi>R</mi></mpadded><mo>+</mo><mpadded width="0" lspace="-0.5width"><mi>C</mi></mpadded>', $tokenLayoutMathml);
        $t->contains('<annotation encoding="application/x-tex">\\smash x_i + \\smash[t] y^2 + \\mathllap L_i + \\mathrlap R + \\clap C</annotation>', $tokenLayoutMathml);
    },
    'converts bounded tex cancelto target annotations to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $cancelToMathml = $converter->texToMathMl('\\cancelto{0}{x_i} + \\cancelto{\\text{draft}}{\\frac{a}{b}}', true);
        $scriptedMathml = $converter->texToMathMl('\\cancelto{n+1}{\\operatorname{media}_i}_j');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $cancelToMathml);
        $t->contains('<mover><menclose notation="updiagonalstrike"><msub><mi>x</mi><mi>i</mi></msub></menclose><mn>0</mn></mover>', $cancelToMathml);
        $t->contains('<mover><menclose notation="updiagonalstrike"><mfrac><mi>a</mi><mi>b</mi></mfrac></menclose><mtext>draft</mtext></mover>', $cancelToMathml);
        $t->contains('<annotation encoding="application/x-tex">\\cancelto{0}{x_i} + \\cancelto{\\text{draft}}{\\frac{a}{b}}</annotation>', $cancelToMathml);
        $t->contains('<msub><mover><menclose notation="updiagonalstrike"><msub><mi>media</mi><mi>i</mi></msub></menclose><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mover><mi>j</mi></msub>', $scriptedMathml);
    },
    'converts bounded tex math alphabet variants to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $u = static fn (int $codepoint): string => html_entity_decode('&#x' . strtoupper(dechex($codepoint)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $variantMathml = $converter->texToMathMl('\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code}', true);
        $scriptVariantMathml = $converter->texToMathMl('\\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i');
        $singleTokenMathml = $converter->texToMathMl('\\mathbf x + \\mathbb R');
        $greekVariantMathml = $converter->texToMathMl('\\mathit{\\Gamma\\alpha} + \\boldsymbol{\\Omega\\omega}_i', true);
        $accessibleGreekMathml = $converter->texToAccessibleMathMl('\\boldsymbol{\\alpha_i} + \\mathit{\\Gamma}', false);

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $variantMathml);
        $t->contains('<mstyle mathvariant="normal"><mi>d</mi></mstyle><mi>x</mi>', $variantMathml);
        $t->contains('<mstyle mathvariant="bold"><msub><mi>' . $u(0x1D42F) . '</mi><mi>' . $u(0x1D422) . '</mi></msub></mstyle>', $variantMathml);
        $t->contains('<mstyle mathvariant="italic"><mi>' . $u(0x1D45B) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mi>' . $u(0x1D5B2) . '</mi></mstyle>', $variantMathml);
        $t->contains('<mstyle mathvariant="monospace"><mrow><mi>' . $u(0x1D68C) . '</mi><mi>' . $u(0x1D698) . '</mi><mi>' . $u(0x1D68D) . '</mi><mi>' . $u(0x1D68E) . '</mi></mrow></mstyle>', $variantMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code}</annotation>', $variantMathml);
        $t->contains('<msub><mstyle mathvariant="script"><mi>' . $u(0x2131) . '</mi></mstyle><mi>n</mi></msub><mo>+</mo><mstyle mathvariant="double-struck"><mi>' . $u(0x211D) . '</mi></mstyle>', $scriptVariantMathml);
        $t->contains('<mstyle mathvariant="fraktur"><mi>' . $u(0x1D524) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="script"><mi>' . $u(0x2112) . '</mi></mstyle>', $scriptVariantMathml);
        $t->contains('<msub><mstyle mathvariant="bold"><mi>' . $u(0x1D6C2) . '</mi></mstyle><mi>i</mi></msub>', $scriptVariantMathml);
        $t->contains('<mstyle mathvariant="bold"><mi>' . $u(0x1D431) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="double-struck"><mi>' . $u(0x211D) . '</mi></mstyle>', $singleTokenMathml);
        $t->contains('<mstyle mathvariant="italic"><mrow><mi>' . $u(0x1D6E4) . '</mi><mi>' . $u(0x1D6FC) . '</mi></mrow></mstyle>', $greekVariantMathml);
        $t->contains('<msub><mstyle mathvariant="bold"><mrow><mi>' . $u(0x1D6C0) . '</mi><mi>' . $u(0x1D6DA) . '</mi></mrow></mstyle><mi>i</mi></msub>', $greekVariantMathml);
        $t->contains('alttext="alpha sub i plus gamma"', $accessibleGreekMathml);
        $t->contains('intent="row(subscript(alpha,i),plus,gamma)"', $accessibleGreekMathml);
    },
    'converts bounded texmath math alphabet aliases to mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $u = static fn (int $codepoint): string => html_entity_decode('&#x' . strtoupper(dechex($codepoint)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $aliasMathml = $converter->texToMathMl('\\mathup{x} + \\symbf{A1} + \\bm{\\alpha_i} + \\pmb{q} + \\mathbold{z} + \\mathbfup{B}', true);
        $extendedAliasMathml = $converter->texToMathMl('\\mathds{R2} + \\mathbfit{Az} + \\mathbfsfup{R2} + \\mathbfsfit{Az} + \\mathbfscr{F} + \\mathbfcal{L} + \\mathbffrak{g} + \\mathsfit{n}');
        $greekAliasMathml = $converter->texToMathMl('\\mathbfit{\\Gamma\\alpha} + \\mathbfsfup{\\Theta\\beta} + \\mathbfsfit{\\Omega\\omega}', true);
        $accessibleGreekAliasMathml = $converter->texToAccessibleMathMl('\\bm{\\alpha_i} + \\mathbfsfit{\\Omega}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $aliasMathml);
        $t->contains('<mstyle mathvariant="normal"><mi>x</mi></mstyle>', $aliasMathml);
        $t->contains('<mstyle mathvariant="bold"><mrow><mi>' . $u(0x1D400) . '</mi><mn>' . $u(0x1D7CF) . '</mn></mrow></mstyle>', $aliasMathml);
        $t->contains('<mstyle mathvariant="bold"><msub><mi>' . $u(0x1D6C2) . '</mi><mi>' . $u(0x1D422) . '</mi></msub></mstyle>', $aliasMathml);
        $t->contains('<mstyle mathvariant="bold"><mi>' . $u(0x1D42A) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="bold"><mi>' . $u(0x1D433) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="bold"><mi>' . $u(0x1D401) . '</mi></mstyle>', $aliasMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathup{x} + \\symbf{A1} + \\bm{\\alpha_i} + \\pmb{q} + \\mathbold{z} + \\mathbfup{B}</annotation>', $aliasMathml);
        $t->contains('<mstyle mathvariant="double-struck"><mrow><mi>' . $u(0x211D) . '</mi><mn>' . $u(0x1D7DA) . '</mn></mrow></mstyle>', $extendedAliasMathml);
        $t->contains('<mstyle mathvariant="bold-italic"><mrow><mi>' . $u(0x1D468) . '</mi><mi>' . $u(0x1D49B) . '</mi></mrow></mstyle>', $extendedAliasMathml);
        $t->contains('<mstyle mathvariant="bold-sans-serif"><mrow><mi>' . $u(0x1D5E5) . '</mi><mn>' . $u(0x1D7EE) . '</mn></mrow></mstyle>', $extendedAliasMathml);
        $t->contains('<mstyle mathvariant="sans-serif-bold-italic"><mrow><mi>' . $u(0x1D63C) . '</mi><mi>' . $u(0x1D66F) . '</mi></mrow></mstyle>', $extendedAliasMathml);
        $t->contains('<mstyle mathvariant="bold-script"><mi>' . $u(0x1D4D5) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="bold-script"><mi>' . $u(0x1D4DB) . '</mi></mstyle>', $extendedAliasMathml);
        $t->contains('<mstyle mathvariant="bold-fraktur"><mi>' . $u(0x1D58C) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="sans-serif-italic"><mi>' . $u(0x1D62F) . '</mi></mstyle>', $extendedAliasMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathds{R2} + \\mathbfit{Az} + \\mathbfsfup{R2} + \\mathbfsfit{Az} + \\mathbfscr{F} + \\mathbfcal{L} + \\mathbffrak{g} + \\mathsfit{n}</annotation>', $extendedAliasMathml);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $greekAliasMathml);
        $t->contains('<mstyle mathvariant="bold-italic"><mrow><mi>' . $u(0x1D71E) . '</mi><mi>' . $u(0x1D736) . '</mi></mrow></mstyle>', $greekAliasMathml);
        $t->contains('<mstyle mathvariant="bold-sans-serif"><mrow><mi>' . $u(0x1D75D) . '</mi><mi>' . $u(0x1D771) . '</mi></mrow></mstyle>', $greekAliasMathml);
        $t->contains('<mstyle mathvariant="sans-serif-bold-italic"><mrow><mi>' . $u(0x1D7A8) . '</mi><mi>' . $u(0x1D7C2) . '</mi></mrow></mstyle>', $greekAliasMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathbfit{\\Gamma\\alpha} + \\mathbfsfup{\\Theta\\beta} + \\mathbfsfit{\\Omega\\omega}</annotation>', $greekAliasMathml);
        $t->contains('alttext="alpha sub i plus omega"', $accessibleGreekAliasMathml);
        $t->contains('intent="row(subscript(alpha,i),plus,omega)"', $accessibleGreekAliasMathml);
        $t->true(!str_contains($aliasMathml, '<mi>\\bm</mi>'));
        $t->true(!str_contains($extendedAliasMathml, '<mi>\\mathbfit</mi>'));
        $t->true(!str_contains($greekAliasMathml, '<mi>\\mathbfsfit</mi>'));
    },
    'rewrites bounded tex math alphabet ascii runs to unicode alphanumeric mathml' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $u = static fn (int $codepoint): string => html_entity_decode('&#x' . strtoupper(dechex($codepoint)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $alphabetMathml = $converter->texToMathMl('\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}', true);
        $safeFallbackMathml = $converter->texToMathMl('\\mathit{h} + \\boldsymbol{\\alpha}_i + \\mathrm{d}');

        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block">', $alphabetMathml);
        $t->contains('<mstyle mathvariant="double-struck"><mrow><mi>' . $u(0x1D538) . '</mi><mi>' . $u(0x2124) . '</mi><mn>' . $u(0x1D7D8) . $u(0x1D7E1) . '</mn></mrow></mstyle>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="script"><mrow><mi>' . $u(0x2131) . '</mi><mi>' . $u(0x2112) . '</mi><mi>' . $u(0x1D4AA) . '</mi></mrow></mstyle>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="fraktur"><mrow><mi>' . $u(0x1D524) . '</mi><mi>' . $u(0x211C) . '</mi></mrow></mstyle>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="monospace"><mrow><mi>' . $u(0x1D68C) . '</mi><mi>' . $u(0x1D698) . '</mi><mi>' . $u(0x1D68D) . '</mi><mi>' . $u(0x1D68E) . '</mi><mn>' . $u(0x1D7FA) . $u(0x1D7F8) . '</mn></mrow></mstyle>', $alphabetMathml);
        $t->contains('<annotation encoding="application/x-tex">\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}</annotation>', $alphabetMathml);
        $t->contains('<mstyle mathvariant="italic"><mi>' . $u(0x210E) . '</mi></mstyle><mo>+</mo><msub><mstyle mathvariant="bold"><mi>' . $u(0x1D6C2) . '</mi></mstyle><mi>i</mi></msub><mo>+</mo><mstyle mathvariant="normal"><mi>d</mi></mstyle>', $safeFallbackMathml);
    },
    'rejects malformed bounded tex color phantom cancel and variant commands without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{url(javascript:bad)}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\color{red}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textcolor{red}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\colorbox_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\fcolorbox{red}{yellow}_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\phantom'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\phantom{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hphantom_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash[]{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\smash[x]{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathllap'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathrlap{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathclap_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancel'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xcancel{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancelto'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancelto{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\cancelto{0}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathbf'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mathbb{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\boldsymbol_1'));
    },
    'rejects malformed bounded tex above below commands without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overset{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underset{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overunderset{a}{b}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underoverset{a}{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overbrace'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underbrace_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overbracket'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underbracket{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overbracket_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\displaystyle'));
    },
    'rejects malformed bounded tex matrix environments without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{>{\\bfseries}l}a & b\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{p{}}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{m{-1cm}}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{*{0}{c}}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{*{9}{c}}a & b & c & d & e & f & g & h & i\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{*{2}{}}a\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}{*{2}{p{1cm}}}a\\end{subarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}a & b'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}\\frac{a}{b & c\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{matrix}\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{cases}x & y'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{cases}\\end{cases}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{pmatrix*}a & b\\end{pmatrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{unknownmatrix*}a & b\\end{unknownmatrix*}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\end{matrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a & b & c\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{gather}a & b\\end{gather}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{split}S &= x \\\\ \\end{split}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{gathered}\\end{gathered}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}a &= b\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}{0}a &= b\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}{5}a &= b\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignedat}{2}a &= b & c\\end{alignedat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{alignat}{1}a &= b \\\\ \\end{alignat}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{flalign}x\\end{flalign}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{flalign}a &= b & c &= d & e &= f & g &= h & i &= j\\end{flalign}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{flalign}a &= b \\\\ \\end{flalign}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{eqnarray}a &= b\\end{eqnarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{eqnarray}a &=& b \\\\ \\end{eqnarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{eqnarray*}\\end{eqnarray*}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multline}\\end{multline}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multline}a & b\\end{multline}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multlined}a \\\\\\end{multlined}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{multline}a \\\\[.5em \\end{multline}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{0-1} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{2-1} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{array}{cc}a & b \\\\ \\cline{2-3} c & d\\end{array}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a &= b \\tag{}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a &= b \\tag{A} \\tag{B}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}\\tag{A}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{align}a &= b \\label{}\\end{align}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{smallmatrix}\\end{smallmatrix}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}a \\\\ b\\end{subarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}{}a \\\\ b\\end{subarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}{p{2cm}}a \\\\ b\\end{subarray}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\begin{subarray}{cc}a & b \\\\ c\\end{subarray}'));
    },
    'rejects malformed bounded tex math groups without invoking a tex engine' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();

        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\frac{a}{'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\frac_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\frac}1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt[]{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sqrt[3{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\root'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\root \\of{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\root 3 x'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\root 3 \\of{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\surd'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\surd{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\surd_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\binom{n}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\binom{}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\tbinom{n}{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\dfrac{a}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{bad}{0}{n}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{\\unknown}{]}{0pt}{0}{n}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{0pt}{4}{n}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{0pt}{0}{}{k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\genfrac{[}{]}{0pt}{0}{n}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\choose k'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('n \\choose'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{a \\over}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{\\atop b}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overwithdelims() k'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\overwithdelims\\unknown) k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\atopwithdelims( k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\abovewithdelims()bad k}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('{n \\abovewithdelims()1pt }'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\text{unterminated'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mbox'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\textbf'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\limits_{i=1}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\sum\\limits'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\int\\nolimits'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\displaylimits_{i=1}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname{median}\\displaylimits'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatorname*{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\operatornamewithlimits{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack{a & b}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\substack{a \\\\ }'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\ensuremath'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\ensuremath{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\stackrel{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\stackrel{}{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\stackrel{x}_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overset_1 x'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underset 0 _1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underoverset{a}{b}_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overunderset_1 x'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left( x + y'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\right)'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left\\unknown{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\middle|'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left( x \\right) \\middle| y'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\left( x \\middle\\unknown y \\right)'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\big'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\big\\unknown{x}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\Bigg a'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace{1}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hspace{calc(1em)}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mspace{bad}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mspace*{1em}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\kern'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\kern{1pt}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\kern1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mkern bad'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\mkern*{1mu}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\tag{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('x \\tag{A} \\tag{B}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\tag*'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\label{}x'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\ref'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\eqref{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\hat'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\vec_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underline^2'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xrightarrow'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xrightarrow{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\xleftarrow[]{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\overrightarrow'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\underrightarrow_1'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\boxed'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\boxed{}'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $converter->texToMathMl('\\boxed}'));
    },
    'renders latex writer math and raw tex without dropping source content' => static function (TestRunner $t): void {
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $text('Posts & media formula: '),
                new AstNode('math', ['text' => 'E = mc^2', 'display' => false]),
                $text(' with raw cite '),
                new AstNode('raw_tex', ['tex' => '\\cite{source}']),
                $text('.'),
            ]),
            new AstNode('paragraph', [], [
                $text('Display: '),
                new AstNode('math', ['text' => '\\alpha + \\omega', 'display' => true]),
            ]),
            new AstNode('bullet_list', [], [
                new AstNode('list_item', [], [
                    new AstNode('math', ['text' => 'p', 'display' => false]),
                    $text('-Tree keeps '),
                    new AstNode('raw_tex', ['tex' => '\\cite{tree}']),
                ]),
            ]),
        ]);

        $t->same(implode("\n\n", [
            'Posts \\& media formula: $E = mc^2$ with raw cite \\cite{source}.',
            'Display: \\[\\alpha + \\omega\\]',
            implode("\n", [
                '\\begin{itemize}',
                '\\item',
                '  $p$-Tree keeps \\cite{tree}',
                '\\end{itemize}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
    'renders raw tex blocks through latex writer for source handoff' => static function (TestRunner $t): void {
        $document = new AstNode('document', [], [
            new AstNode('raw_tex', ['tex' => '\\newcommand{\\wptuple}[1]{\\langle #1 \\rangle}']),
            new AstNode('raw_block', ['format' => 'latex', 'text' => '\\begin{migration-review}' . "\n" . '\\item keep TeX source' . "\n" . '\\end{migration-review}']),
        ]);

        $t->same(implode("\n\n", [
            '\\newcommand{\\wptuple}[1]{\\langle #1 \\rangle}',
            implode("\n", [
                '\\begin{migration-review}',
                '\\item keep TeX source',
                '\\end{migration-review}',
            ]),
        ]), (new LatexWriter())->write($document));
    },
];
