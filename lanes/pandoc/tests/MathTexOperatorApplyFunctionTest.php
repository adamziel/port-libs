<?php

declare(strict_types=1);

use PortLibs\Pandoc\MathTexConverter;

return [
    'emits invisible apply function for declared and direct operators' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $macros = [
            'reviewop' => ['arity' => 0, 'template' => '\\operatorname{review score}'],
            'argreview' => ['arity' => 0, 'template' => '\\operatorname*{arg review}'],
        ];
        $mathml = $converter->texToMathMl(
            '\\reviewop_i(p_i) + \\argreview_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname*{rank}\\nolimits_j q_j',
            true,
            $macros
        );

        $t->contains('<msub><mi>review score</mi><mi>i</mi></msub><mo>⁡</mo><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>', $mathml);
        $t->contains('<munderover><mi>arg review</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover><mo>⁡</mo><mi>f</mi>', $mathml);
        $t->contains('<msub><mi>rank</mi><mi>j</mi></msub><mo>⁡</mo><msub><mi>q</mi><mi>j</mi></msub>', $mathml);
        $t->true(!str_contains($mathml, 'data-tex-function-operator'));
    },
    'emits invisible apply function for built in function commands' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $mathml = $converter->texToMathMl('\\sin^2 \\theta + \\log_{10} x + \\gcd(m,n)');
        $accessibleMathml = $converter->texToAccessibleMathMl('\\sin^2 \\theta');

        $t->contains('<msup><mi>sin</mi><mn>2</mn></msup><mo>⁡</mo><mi>θ</mi>', $mathml);
        $t->contains('<msub><mi>log</mi><mn>10</mn></msub><mo>⁡</mo><mi>x</mi>', $mathml);
        $t->contains('<mi>gcd</mi><mo>⁡</mo><mo>(</mo><mi>m</mi><mo>,</mo><mi>n</mi><mo>)</mo>', $mathml);
        $t->contains('alttext="sin superscript 2 of theta"', $accessibleMathml);
        $t->contains('intent="row(superscript(sin,2),of,theta)"', $accessibleMathml);
    },
    'keeps symbolic operatorname tokens from becoming function application heads' => static function (TestRunner $t): void {
        $converter = new MathTexConverter();
        $mathml = $converter->texToMathMl('\\operatorname\\leq p + \\operatorname{post}\\thinspace\\operatorname{media}');

        $t->contains('<mi>≤</mi><mi>p</mi>', $mathml);
        $t->contains('<mi>post</mi><mspace width="0.1667em"></mspace><mi>media</mi>', $mathml);
        $t->true(!str_contains($mathml, '<mi>≤</mi><mo>⁡</mo><mi>p</mi>'));
        $t->true(!str_contains($mathml, '<mi>post</mi><mspace width="0.1667em"></mspace><mo>⁡</mo><mi>media</mi>'));
    },
];
