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
$$\sum_{i=1}^{n} \operatorname{migrate}(p_i) + \frac{a_1}{\sqrt{b^2}} + \widehat{\operatorname{quality}} + \vec{v}_i + \begin{pmatrix}p_1 & m_1 \\ p_2 & m_2\end{pmatrix} + \begin{aligned}x_i &= \operatorname{score}(p_i) \\ y_i &= \frac{a_i}{b_i}\end{aligned} + \forall p_i \in P \Rightarrow p_i \notin \emptyset + \alpha \times \omega$$
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
];

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<span class="math inline">\\(\\langle post_id,media_id \\rangle\\)</span>',
        '<span class="math display">\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]</span>',
        '<mo>⟨</mo>',
        '<mo>⟩</mo>',
        '<annotation encoding="application/x-tex">\\langle post_id,media_id \\rangle</annotation>',
        '<msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup>',
        '<mi>migrate</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>',
        '<mfrac><msub><mi>a</mi><mn>1</mn></msub><msqrt><msup><mi>b</mi><mn>2</mn></msup></msqrt></mfrac>',
        '<mover accent="true"><mi>quality</mi><mo>^</mo></mover>',
        '<msub><mover accent="true"><mi>v</mi><mo>→</mo></mover><mi>i</mi></msub>',
        '<mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>',
        '<mtable columnalign="right left"><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mi>score</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd></mtr>',
        '<mtr><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>',
        '<mo>∀</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi><mo>⇒</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∉</mo><mo>∅</mo>',
        '<annotation encoding="application/x-tex">\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega</annotation>',
        '\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{pmatrix} + \\begin{aligned}x_i &= \\operatorname{score}(p_i) \\\\ y_i &= \\frac{a_i}{b_i}\\end{aligned} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]',
    ] as $needle) {
        if (!str_contains(implode("\n", $summary), $needle)) {
            throw new RuntimeException('Math TeX handoff self-test missing: ' . $needle);
        }
    }

    echo "math tex handoff self-test ok\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
