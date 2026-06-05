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
\newcommand{\wpreview}[2][draft]{#2 + #1}

Reviewer equation $\wptuple{post_id,media_id}$ stays editable.

Optional macro audit $\wpreview{p_i} + \wpreview[final]{m_i}$ stays editable.

Display audit:
$$\sum_{i=1}^{n} \operatorname{migrate}(p_i) + \frac{a_1}{\sqrt{b^2}} + \sqrt[3]{x_i + y_i} + \binom{n}{k} + \tbinom{p_i}{2} + \dbinom{a+b}{c} + \dfrac{q_i}{r_i} + \genfrac{\langle}{\rangle}{0pt}{0}{n}{k} + \widehat{\operatorname{quality}} + \vec{v}_i + \begin{pmatrix}p_1 & m_1 \\ p_2 & m_2\end{pmatrix} + \begin{aligned}x_i &= \operatorname{score}(p_i) \\ y_i &= \frac{a_i}{b_i}\end{aligned} + \begin{array}{l|c|r}\alpha & \beta & \omega \\ 1 & 2 & 3\end{array} + \begin{cases}p_i & p_i \in P \\ 0 & \text{otherwise}\end{cases} + \forall p_i \in P \Rightarrow p_i \notin \emptyset + \alpha \times \omega$$

Above and below audit $\overset{\text{new}}{p_i} + \underset{0}{\lim}_{n \to \infty} a_n + \overbrace{x + y}^{\text{sum}} + \underbrace{m_i}_{\text{media}} + \displaystyle \frac{q}{r}$ stays semantic.

Infix audit ${a+b \over c+d} + {n \choose k} + {n \atop k} + {p_i \brack m_i} + {x+y \brace z}$ stays semantic.

With-delims audit ${a+b \overwithdelims() c+d} + {n \atopwithdelims\langle\rangle k} + {p_i \abovewithdelims[]1pt m_i}$ stays semantic.

Review controls $\color{red}{p_i} + \textcolor{#336699}{\operatorname{media}} + \phantom{p_i + m_i} + \hphantom{draft} + \vphantom{\frac{a}{b}} + \cancel{x_i} + \bcancel{y_i} + \xcancel{z_i} + \cancelto{0}{\operatorname{draft}_i}$ stay explicit.

Math alphabet audit $\mathrm{d}x + \mathbf{v_i} + \mathit{n} + \mathsf{S} + \mathtt{code} + \mathcal{F}_n + \mathbb{R} + \mathfrak{g} + \mathscr{L} + \boldsymbol{\alpha}_i$ stays semantic.

Stacked limits audit $\sum_{\substack{i=1 \\ i\ne j}}^{n} a_i + \lim_{\substack{x \to 0 \\ x > 0}} f(x)$ stays semantic.

AMS layout audit $\begin{align}f(p_i) &= m_i \\ g(p_i) &= \frac{a_i}{b_i}\end{align} + \begin{gathered}x+y \\ z\end{gathered} + \begin{split}S &= \sum_{i=1}^{n} p_i \\ &= \frac{a}{b}\end{split}$ stays semantic.

Alignedat audit $\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\ x &= y & u &= v\end{alignedat}$ stays semantic.

Spacing audit $p_i\,m_i\;n_i\!q_i + a\quad b\qquad c + \operatorname{post}\thinspace\operatorname{media}\negthinspace\operatorname{review} + x\:y\>z$ stays semantic.

Explicit spacing audit $p_i\hspace{1.5em}m_i\mspace{-2mu}q_i + a\hspace*{.25in}b$ stays semantic.

Sized delimiters audit $\bigl( p_i \bigr) + \Bigl\langle m_i \Bigr\rangle + \bigm| x \in S + \Bigg/ y \Bigg/$ stays semantic.

Middle delimiter audit $\left\{p_i \middle| p_i \in P\right\} + \left\langle x \middle/ y \right\rangle$ stays semantic.

Extensible arrow audit $\xrightarrow[\text{review}]{\operatorname{publish}} p_i + \xleftarrow{draft} m_i + \overrightarrow{AB}_i$ stays semantic.

Tagged equation audit:
$$p_i + m_i \label{eq:review-flow} \tag{WP-2}$$
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
    'optionalMacroMathml' => $converter->texToMathMl('\\wpreview{p_i} + \\wpreview[final]{m_i}', false, $converter->macroDefinitionsFromDocument($document)),
    'aboveBelowMathml' => $converter->texToMathMl('\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}'),
    'infixFractionMathml' => $converter->texToMathMl('{a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}'),
    'withDelimsFractionMathml' => $converter->texToMathMl('{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}'),
    'colorPhantomCancelMathml' => $converter->texToMathMl('\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}'),
    'mathVariantMathml' => $converter->texToMathMl('\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i'),
    'substackMathml' => $converter->texToMathMl('\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x > 0}} f(x)'),
    'amsEnvironmentMathml' => $converter->texToMathMl('\\begin{align}f(p_i) &= m_i \\\\ g(p_i) &= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &= \\sum_{i=1}^{n} p_i \\\\ &= \\frac{a}{b}\\end{split}'),
    'alignedAtMathml' => $converter->texToMathMl('\\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\\\ x &= y & u &= v\\end{alignedat}'),
    'spacingMathml' => $converter->texToMathMl('p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\>z'),
    'explicitSpacingMathml' => $converter->texToMathMl('p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b'),
    'sizedDelimiterMathml' => $converter->texToMathMl('\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/'),
    'middleDelimiterMathml' => $converter->texToMathMl('\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle'),
    'extensibleArrowMathml' => $converter->texToMathMl('\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i'),
    'taggedEquationMathml' => $converter->texToMathMl('p_i + m_i \\label{eq:review-flow} \\tag{WP-2}', true),
];

if (($argv[1] ?? '') === '--self-test') {
    if (str_contains($summary['macroExpandedMathml'], '<mi>\\wptuple</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test left bounded macro unexpanded');
    }

    foreach ([
        '<span class="math inline">\\(\\langle post_id,media_id \\rangle\\)</span>',
        '<span class="math inline">\\(\\wpreview{p_i} + \\wpreview[final]{m_i}\\)</span>',
        '<span class="math display">\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha &amp; \\beta &amp; \\omega \\\\ 1 &amp; 2 &amp; 3\\end{array} + \\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]</span>',
        '<span class="math inline">\\(\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}\\)</span>',
        '<span class="math inline">\\({a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}\\)</span>',
        '<span class="math inline">\\({a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}\\)</span>',
        '<span class="math inline">\\(\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}\\)</span>',
        '<span class="math inline">\\(\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i\\)</span>',
        '<span class="math inline">\\(\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)\\)</span>',
        '<span class="math inline">\\(\\begin{align}f(p_i) &amp;= m_i \\\\ g(p_i) &amp;= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}\\)</span>',
        '<span class="math inline">\\(\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}\\)</span>',
        '<span class="math inline">\\(p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\&gt;z\\)</span>',
        '<span class="math inline">\\(p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b\\)</span>',
        '<span class="math inline">\\(\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/\\)</span>',
        '<span class="math inline">\\(\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle\\)</span>',
        '<span class="math inline">\\(\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i\\)</span>',
        '<span class="math display">\\[p_i + m_i \\label{eq:review-flow} \\tag{WP-2}\\]</span>',
        '<mo>⟨</mo>',
        '<mo>⟩</mo>',
        '<annotation encoding="application/x-tex">\\langle post_id,media_id \\rangle</annotation>',
        '<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mi>f</mi><mi>i</mi><mi>n</mi><mi>a</mi><mi>l</mi>',
        '<annotation encoding="application/x-tex">\\wpreview{p_i} + \\wpreview[final]{m_i}</annotation>',
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
        '<mo fence="true" stretchy="true">(</mo><mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow></mfrac><mo fence="true" stretchy="true">)</mo>',
        '<mo fence="true" stretchy="true">⟨</mo><mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac><mo fence="true" stretchy="true">⟩</mo>',
        '<mo fence="true" stretchy="true">[</mo><mfrac linethickness="1pt"><msub><mi>p</mi><mi>i</mi></msub><msub><mi>m</mi><mi>i</mi></msub></mfrac><mo fence="true" stretchy="true">]</mo>',
        '<mstyle mathcolor="red"><msub><mi>p</mi><mi>i</mi></msub></mstyle>',
        '<mstyle mathcolor="#336699"><mi>media</mi></mstyle>',
        '<mphantom><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mphantom>',
        '<mpadded height="0" depth="0"><mphantom><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mphantom></mpadded>',
        '<mpadded width="0"><mphantom><mfrac><mi>a</mi><mi>b</mi></mfrac></mphantom></mpadded>',
        '<menclose notation="updiagonalstrike"><msub><mi>x</mi><mi>i</mi></msub></menclose>',
        '<menclose notation="downdiagonalstrike"><msub><mi>y</mi><mi>i</mi></msub></menclose>',
        '<menclose notation="updiagonalstrike downdiagonalstrike"><msub><mi>z</mi><mi>i</mi></msub></menclose>',
        '<mover><menclose notation="updiagonalstrike"><msub><mi>draft</mi><mi>i</mi></msub></menclose><mn>0</mn></mover>',
        '<mstyle mathvariant="normal"><mi>d</mi></mstyle><mi>x</mi>',
        '<mstyle mathvariant="bold"><msub><mi>v</mi><mi>i</mi></msub></mstyle>',
        '<mstyle mathvariant="italic"><mi>n</mi></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mi>S</mi></mstyle>',
        '<mstyle mathvariant="monospace"><mrow><mi>c</mi><mi>o</mi><mi>d</mi><mi>e</mi></mrow></mstyle>',
        '<msub><mstyle mathvariant="script"><mi>F</mi></mstyle><mi>n</mi></msub><mo>+</mo><mstyle mathvariant="double-struck"><mi>R</mi></mstyle>',
        '<mstyle mathvariant="fraktur"><mi>g</mi></mstyle><mo>+</mo><mstyle mathvariant="script"><mi>L</mi></mstyle>',
        '<msub><mstyle mathvariant="bold"><mi>α</mi></mstyle><mi>i</mi></msub>',
        '<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>',
        '<msub><mo>lim</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>x</mi><mo>→</mo><mn>0</mn></mtd></mtr><mtr><mtd><mi>x</mi><mo>&gt;</mo><mn>0</mn></mtd></mtr></mtable></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>',
        '<mtable columnalign="right left"><mtr><mtd><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>g</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>',
        '<mtable columnalign="center"><mtr><mtd><mi>x</mi><mo>+</mo><mi>y</mi></mtd></mtr><mtr><mtd><mi>z</mi></mtd></mtr></mtable>',
        '<mtable columnalign="right left"><mtr><mtd><mi>S</mi></mtd><mtd><mo>=</mo><msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd></mtd><mtd><mo>=</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd></mtr></mtable>',
        '<mtable columnalign="right left right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><msub><mi>a</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd></mtr></mtable>',
        '<msub><mi>p</mi><mi>i</mi></msub><mspace width="0.1667em"></mspace><msub><mi>m</mi><mi>i</mi></msub><mspace width="0.2778em"></mspace><msub><mi>n</mi><mi>i</mi></msub><mspace width="-0.1667em"></mspace><msub><mi>q</mi><mi>i</mi></msub>',
        '<mi>a</mi><mspace width="1em"></mspace><mi>b</mi><mspace width="2em"></mspace><mi>c</mi>',
        '<mi>post</mi><mspace width="0.1667em"></mspace><mi>media</mi><mspace width="-0.1667em"></mspace><mi>review</mi>',
        '<mi>x</mi><mspace width="0.2222em"></mspace><mi>y</mi><mspace width="0.2222em"></mspace><mi>z</mi>',
        '<msub><mi>p</mi><mi>i</mi></msub><mspace width="1.5em"></mspace><msub><mi>m</mi><mi>i</mi></msub><mspace width="-2mu"></mspace><msub><mi>q</mi><mi>i</mi></msub>',
        '<mi>a</mi><mspace width=".25in" linebreak="nobreak"></mspace><mi>b</mi>',
        '<mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">(</mo><msub><mi>p</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.2em" maxsize="1.2em">)</mo>',
        '<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟨</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">⟩</mo>',
        '<mo fence="true" stretchy="true" separator="true" minsize="1.2em" maxsize="1.2em">|</mo><mi>x</mi><mo>∈</mo><mi>S</mi>',
        '<mo fence="true" stretchy="true" minsize="3em" maxsize="3em">/</mo><mi>y</mi><mo fence="true" stretchy="true" minsize="3em" maxsize="3em">/</mo>',
        '<mo fence="true" stretchy="true">{</mo><msub><mi>p</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">|</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi><mo fence="true" stretchy="true">}</mo>',
        '<mo fence="true" stretchy="true">⟨</mo><mi>x</mi><mo fence="true" stretchy="true" separator="true">/</mo><mi>y</mi><mo fence="true" stretchy="true">⟩</mo>',
        '<munderover><mo stretchy="true">→</mo><mtext>review</mtext><mi>publish</mi></munderover><msub><mi>p</mi><mi>i</mi></msub>',
        '<mover><mo stretchy="true">←</mo><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mover><msub><mi>m</mi><mi>i</mi></msub>',
        '<msub><mover accent="true"><mrow><mi>A</mi><mi>B</mi></mrow><mo stretchy="true">→</mo></mover><mi>i</mi></msub>',
        '<mtable><mlabeledtr><mtd><mtext>(WP-2)</mtext></mtd><mtd id="eq:review-flow"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>',
        '<annotation encoding="application/x-tex">\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}</annotation>',
        '<annotation encoding="application/x-tex">{a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}</annotation>',
        '<annotation encoding="application/x-tex">{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}</annotation>',
        '<annotation encoding="application/x-tex">\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}</annotation>',
        '<annotation encoding="application/x-tex">\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i</annotation>',
        '<annotation encoding="application/x-tex">\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)</annotation>',
        '<annotation encoding="application/x-tex">\\begin{align}f(p_i) &amp;= m_i \\\\ g(p_i) &amp;= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}</annotation>',
        '<annotation encoding="application/x-tex">\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}</annotation>',
        '<annotation encoding="application/x-tex">p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\&gt;z</annotation>',
        '<annotation encoding="application/x-tex">p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b</annotation>',
        '<annotation encoding="application/x-tex">\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/</annotation>',
        '<annotation encoding="application/x-tex">\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle</annotation>',
        '<annotation encoding="application/x-tex">\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i</annotation>',
        '<annotation encoding="application/x-tex">p_i + m_i \\label{eq:review-flow} \\tag{WP-2}</annotation>',
        '<annotation encoding="application/x-tex-label">eq:review-flow</annotation>',
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
