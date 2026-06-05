<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\LatexWriter;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MathTexConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Math Import Review

\newcommand{\wptuple}[1]{\langle #1 \rangle}

Reviewer equation $\wptuple{post_id,media_id}$ stays editable.

Display audit:
$$\sum_{i=1}^{n} \operatorname{migrate}(p_i) + \frac{a_1}{\sqrt{b^2}} + \sqrt[3]{x_i + y_i} + \binom{n}{k} + \tbinom{p_i}{2} + \dbinom{a+b}{c} + \dfrac{q_i}{r_i} + \genfrac{\langle}{\rangle}{0pt}{0}{n}{k} + \widehat{\operatorname{quality}} + \vec{v}_i + \begin{pmatrix}p_1 & m_1 \\ p_2 & m_2\end{pmatrix} + \begin{aligned}x_i &= \operatorname{score}(p_i) \\ y_i &= \frac{a_i}{b_i}\end{aligned} + \begin{array}{l|c|r}\alpha & \beta & \omega \\ 1 & 2 & 3\end{array} + \begin{cases}p_i & p_i \in P \\ 0 & \text{otherwise}\end{cases} + \forall p_i \in P \Rightarrow p_i \notin \emptyset + \alpha \times \omega$$

Above and below audit $\overset{\text{new}}{p_i} + \underset{0}{\lim}_{n \to \infty} a_n + \overbrace{x + y}^{\text{sum}} + \underbrace{m_i}_{\text{media}} + \displaystyle \frac{q}{r}$ stays semantic.

Infix audit ${a+b \over c+d} + {n \choose k} + {n \atop k} + {p_i \brack m_i} + {x+y \brace z}$ stays semantic.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$converter = new MathTexConverter();
$inlineMath = null;
$displayMath = null;
foreach ($document->children as $block) {
    if ($block->type !== 'paragraph') {
        continue;
    }
    foreach ($block->children as $child) {
        if ($child->type === 'math' && $child->attr('display') !== true && !$inlineMath instanceof AstNode) {
            $inlineMath = $child;
        }
        if ($child->type === 'math' && $child->attr('display') === true) {
            $displayMath = $child;
            break 2;
        }
    }
}

if (!$inlineMath instanceof AstNode) {
    throw new RuntimeException('Math handoff example could not find inline math node');
}

if (!$displayMath instanceof AstNode) {
    throw new RuntimeException('Math handoff example could not find display math node');
}

$summary = [
    'wordpressBlocks' => (new WordPressBlockWriter())->write($document),
    'latex' => (new LatexWriter())->write($document),
    'inlineMathml' => $converter->mathMlFor($inlineMath),
    'mathml' => $converter->mathMlFor($displayMath),
    'macroExpandedMathml' => $converter->texToMathMl('\\wptuple{post_id,media_id}', false, $converter->macroDefinitionsFromDocument($document)),
    'aboveBelowMathml' => $converter->texToMathMl('\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}'),
    'infixFractionMathml' => $converter->texToMathMl('{a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}'),
];

if (($argv[1] ?? '') === '--self-test') {
    if (str_contains($summary['macroExpandedMathml'], '<mi>\\wptuple</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test left bounded macro unexpanded');
    }

    foreach ([
        '<span class="math inline">\\(\\langle post_id,media_id \\rangle\\)</span>',
        '<span class="math display">\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha &amp; \\beta &amp; \\omega \\\\ 1 &amp; 2 &amp; 3\\end{array} + \\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]</span>',
        '<span class="math inline">\\(\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}\\)</span>',
        '<span class="math inline">\\({a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}\\)</span>',
        '<mo>⟨</mo>',
        '<mo>⟩</mo>',
        '<annotation encoding="application/x-tex">\\langle post_id,media_id \\rangle</annotation>',
        '<msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup>',
        '<mi>migrate</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>',
        '<mfrac><msub><mi>a</mi><mn>1</mn></msub><msqrt><msup><mi>b</mi><mn>2</mn></msup></msqrt></mfrac>',
        '<mroot><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mn>3</mn></mroot>',
        '<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo>',
        '<mstyle displaystyle="false"><mrow><mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><msub><mi>p</mi><mi>i</mi></msub><mn>2</mn></mfrac><mo fence="true" stretchy="true">)</mo></mrow></mstyle>',
        '<mstyle displaystyle="true"><mrow><mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mi>c</mi></mfrac><mo fence="true" stretchy="true">)</mo></mrow></mstyle>',
        '<mstyle displaystyle="true"><mfrac><msub><mi>q</mi><mi>i</mi></msub><msub><mi>r</mi><mi>i</mi></msub></mfrac></mstyle>',
        '<mstyle displaystyle="true"><mrow><mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">⟩</mo></mrow></mstyle>',
        '<mover accent="true"><mi>quality</mi><mo>^</mo></mover>',
        '<msub><mover accent="true"><mi>v</mi><mo>→</mo></mover><mi>i</mi></msub>',
        '<mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>',
        '<mtable columnalign="right left"><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mi>score</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd></mtr>',
        '<mtr><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>',
        '<mtable columnalign="left center right"><mtr><mtd><mi>α</mi></mtd><mtd><mi>β</mi></mtd><mtd><mi>ω</mi></mtd></mtr><mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd><mtd><mn>3</mn></mtd></mtr></mtable>',
        '<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable>',
        '<mo>∀</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi><mo>⇒</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∉</mo><mo>∅</mo>',
        '<mover><msub><mi>p</mi><mi>i</mi></msub><mtext>new</mtext></mover>',
        '<msub><munder><mo>lim</mo><mn>0</mn></munder><mrow><mi>n</mi><mo>→</mo><mi>∞</mi></mrow></msub>',
        '<msup><mover><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mo>⏞</mo></mover><mtext>sum</mtext></msup>',
        '<msub><munder><msub><mi>m</mi><mi>i</mi></msub><mo>⏟</mo></munder><mtext>media</mtext></msub>',
        '<mstyle displaystyle="true"><mfrac><mi>q</mi><mi>r</mi></mfrac></mstyle>',
        '<mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow></mfrac>',
        '<mo fence="true" stretchy="true">(</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">)</mo>',
        '<mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac>',
        '<mo fence="true" stretchy="true">[</mo><mfrac linethickness="0"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">]</mo>',
        '<mo fence="true" stretchy="true">{</mo><mfrac linethickness="0"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mi>z</mi></mfrac><mo fence="true" stretchy="true">}</mo>',
        '<annotation encoding="application/x-tex">\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}</annotation>',
        '<annotation encoding="application/x-tex">{a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}</annotation>',
        '<annotation encoding="application/x-tex">\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha &amp; \\beta &amp; \\omega \\\\ 1 &amp; 2 &amp; 3\\end{array} + \\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega</annotation>',
        '<annotation encoding="application/x-tex">\\wptuple{post_id,media_id}</annotation>',
        '\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{pmatrix} + \\begin{aligned}x_i &= \\operatorname{score}(p_i) \\\\ y_i &= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha & \\beta & \\omega \\\\ 1 & 2 & 3\\end{array} + \\begin{cases}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]',
    ] as $needle) {
        if (!str_contains(implode("\n", $summary), $needle)) {
            throw new RuntimeException('Math TeX handoff self-test missing: ' . $needle);
        }
    }

    echo "math tex handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
