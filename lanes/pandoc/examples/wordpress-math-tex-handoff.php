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

Text alias audit $\mbox{review mode} + \textrm{media label} + \textbf{draft} + \textit{review} + \texttt{code_1} + \textsf{sans group}$ stays semantic.

Display audit:
$$\sum_{i=1}^{n} \operatorname{migrate}(p_i) + \frac{a_1}{\sqrt{b^2}} + \sqrt[3]{x_i + y_i} + \binom{n}{k} + \tbinom{p_i}{2} + \dbinom{a+b}{c} + \dfrac{q_i}{r_i} + \genfrac{\langle}{\rangle}{0pt}{0}{n}{k} + \widehat{\operatorname{quality}} + \vec{v}_i + \begin{pmatrix}p_1 & m_1 \\ p_2 & m_2\end{pmatrix} + \begin{aligned}x_i &= \operatorname{score}(p_i) \\ y_i &= \frac{a_i}{b_i}\end{aligned} + \begin{array}{l|c|r}\alpha & \beta & \omega \\ \hline 1 & 2 & 3\end{array} + \begin{cases}p_i & p_i \in P \\ 0 & \text{otherwise}\end{cases} + \forall p_i \in P \Rightarrow p_i \notin \emptyset + \alpha \times \omega$$

Negated relation audit $p_i \not\in P + a \not= b + x \not\leq y + A \not\subseteq B + \not\alpha_i$ stays semantic.

Prime audit $f'(x) + g''_i + h_i''' + \partial^\prime f + y^\backprime$ stays semantic.

Above and below audit $\overset{\text{new}}{p_i} + \underset{0}{\lim}_{n \to \infty} a_n + \overbrace{x + y}^{\text{sum}} + \underbrace{m_i}_{\text{media}} + \displaystyle \frac{q}{r}$ stays semantic.

Infix audit ${a+b \over c+d} + {n \choose k} + {n \atop k} + {p_i \brack m_i} + {x+y \brace z}$ stays semantic.

With-delims audit ${a+b \overwithdelims() c+d} + {n \atopwithdelims\langle\rangle k} + {p_i \abovewithdelims[]1pt m_i}$ stays semantic.

Review controls $\color{red}{p_i} + \textcolor{#336699}{\operatorname{media}} + \phantom{p_i + m_i} + \hphantom{draft} + \vphantom{\frac{a}{b}} + \cancel{x_i} + \bcancel{y_i} + \xcancel{z_i} + \cancelto{0}{\operatorname{draft}_i}$ stay explicit.

Overlap layout audit $\smash{\frac{a}{b}} + \smash[t]{p_i} + \smash[b]{m_i} + \mathllap{L_i} + \mathrlap{R_i} + \mathclap{x+y}$ stays semantic.

Math alphabet audit $\mathrm{d}x + \mathbf{v_i} + \mathit{n} + \mathsf{S} + \mathtt{code} + \mathcal{F}_n + \mathbb{R} + \mathfrak{g} + \mathscr{L} + \boldsymbol{\alpha}_i$ stays semantic.

Math alphanumeric audit $\mathbb{AZ09} + \mathcal{FLO} + \mathfrak{gR} + \mathtt{code42}$ keeps Unicode MathML review glyphs.

Stacked limits audit $\sum_{\substack{i=1 \\ i\ne j}}^{n} a_i + \lim_{\substack{x \to 0 \\ x > 0}} f(x)$ stays semantic.

Operator limits audit $\sum\limits_{i=1}^{n} p_i + \lim\limits_{x \to 0} f(x) + \int\nolimits_{0}^{1} g(x) dx$ stays semantic.

Starred operator limits audit $\operatorname*{argmax}_{p_i \in P}^{\text{draft}} f(p_i) + \operatorname{median}\displaylimits_{i=1}^{n} p_i + \operatorname*{rank}\nolimits_{j} q_j$ stays semantic.

AMS layout audit $\begin{align}f(p_i) &= m_i \\ g(p_i) &= \frac{a_i}{b_i}\end{align} + \begin{gathered}x+y \\ z\end{gathered} + \begin{split}S &= \sum_{i=1}^{n} p_i \\ &= \frac{a}{b}\end{split}$ stays semantic.

Alignedat audit $\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\ x &= y & u &= v\end{alignedat}$ stays semantic.

Flush alignment audit $\begin{flalign}\text{source} && p_i &= m_i && \text{review} \\ \text{target} && x_i &= y_i \tag{WP-F}\end{flalign}$ stays semantic.

Multline audit $\begin{multline}p_i + m_i \\[.5em] = a_i + b_i \\ + \frac{x}{y}\end{multline} + \left(\begin{multlined}u+v \\ w\end{multlined}\right)$ stays semantic.

Compact environment audit $\left(\begin{smallmatrix}p_1 & m_1 \\ p_2 & m_2\end{smallmatrix}\right) + \sum_{\begin{subarray}{c}i=1 \\ i\ne j\end{subarray}}^{n} a_i$ stays semantic.

Equation wrapper audit:
$$\begin{equation}r_i + s_i \label{eq:wrapped-env} \tag{WP-3}\end{equation}$$

Starred equation wrapper audit $\begin{equation*}\operatorname{review}(p_i) + \eqref{eq:wrapped-env}\end{equation*}$ stays semantic.

Row-tag audit $\begin{align}p_i &= m_i \tag{WP-1} \\ x_i &= y_i \label{eq:row-review} \tag*{review}\end{align}$ stays semantic.

No-number row audit $\begin{align}p_i &= m_i \notag \\ x_i &= y_i \nonumber \\ u_i &= v_i\end{align}$ keeps suppressed rows unnumbered.

Spacing audit $p_i\,m_i\;n_i\!q_i + a\quad b\qquad c + \operatorname{post}\thinspace\operatorname{media}\negthinspace\operatorname{review} + x\:y\>z$ stays semantic.

Explicit spacing audit $p_i\hspace{1.5em}m_i\mspace{-2mu}q_i + a\hspace*{.25in}b$ stays semantic.

Sized delimiters audit $\bigl( p_i \bigr) + \Bigl\langle m_i \Bigr\rangle + \bigm| x \in S + \Bigg/ y \Bigg/$ stays semantic.

Delimiter alias audit $\left\lVert p_i + m_i \right\rVert + \left\lceil \frac{x}{y} \right\rfloor + \lbrack q_i \rbrack$ stays semantic.

Arrow/group delimiter audit $\left\uparrow x_i \middle\Updownarrow y_i \right\downarrow + \Bigl\Uparrow z \Bigr\Downarrow + \lgroup p_i \rgroup + \left\lmoustache a \right\rmoustache$ stays semantic.

Middle delimiter audit $\left\{p_i \middle| p_i \in P\right\} + \left\langle x \middle/ y \right\rangle$ stays semantic.

Extensible arrow audit $\xrightarrow[\text{review}]{\operatorname{publish}} p_i + \xleftarrow{draft} m_i + \overrightarrow{AB}_i$ stays semantic.

Tagged equation audit:
$$p_i + m_i \label{eq:review-flow} \tag{WP-2}$$

Equation reference audit $\label{eq:plain}x_i + \eqref{eq:plain} + \ref{review row/2}$ stays linked.

Resolved equation reference audit $\eqref{eq:review-flow} + \eqref{eq:row-review}$ keeps known tags.

Automatic numbering audit:
$$p_i + m_i \label{eq:auto-one}$$

Automatic row numbering audit:
$$\begin{align}x_i &= y_i \label{eq:auto-row} \\ u_i &= v_i \tag{manual}\end{align}$$

Resolved automatic numbering audit $\eqref{eq:auto-one} + \eqref{eq:auto-row} + \eqref{eq:plain}$ keeps bounded references.

Accessible MathML audit $\frac{a_1}{\sqrt{b^2}} + \alpha$ keeps alt text and intent.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$converter = new MathTexConverter();
$equationReferenceLabels = $converter->equationReferenceLabelsFromDocument($document);
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
    'textAliasMathml' => $converter->texToMathMl('\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}'),
    'notRelationMathml' => $converter->texToMathMl('p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + \\not\\alpha_i'),
    'primeMathml' => $converter->texToMathMl("f'(x) + g''_i + h_i''' + \\partial^\\prime f + y^\\backprime"),
    'aboveBelowMathml' => $converter->texToMathMl('\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}'),
    'infixFractionMathml' => $converter->texToMathMl('{a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}'),
    'withDelimsFractionMathml' => $converter->texToMathMl('{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}'),
    'colorPhantomCancelMathml' => $converter->texToMathMl('\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}'),
    'smashOverlapMathml' => $converter->texToMathMl('\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i} + \\mathllap{L_i} + \\mathrlap{R_i} + \\mathclap{x+y}'),
    'mathVariantMathml' => $converter->texToMathMl('\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i'),
    'mathAlphanumericMathml' => $converter->texToMathMl('\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}'),
    'substackMathml' => $converter->texToMathMl('\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x > 0}} f(x)'),
    'operatorLimitsMathml' => $converter->texToMathMl('\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\int\\nolimits_{0}^{1} g(x) dx'),
    'starredOperatorLimitsMathml' => $converter->texToMathMl('\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j'),
    'amsEnvironmentMathml' => $converter->texToMathMl('\\begin{align}f(p_i) &= m_i \\\\ g(p_i) &= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &= \\sum_{i=1}^{n} p_i \\\\ &= \\frac{a}{b}\\end{split}'),
    'alignedAtMathml' => $converter->texToMathMl('\\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\\\ x &= y & u &= v\\end{alignedat}'),
    'flalignMathml' => $converter->texToMathMl('\\begin{flalign}\\text{source} && p_i &= m_i && \\text{review} \\\\ \\text{target} && x_i &= y_i \\tag{WP-F}\\end{flalign}', true),
    'multlineMathml' => $converter->texToMathMl('\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline} + \\left(\\begin{multlined}u+v \\\\ w\\end{multlined}\\right)'),
    'compactEnvironmentMathml' => $converter->texToMathMl('\\left(\\begin{smallmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i'),
    'equationWrapperMathml' => $converter->texToMathMl('\\begin{equation}r_i + s_i \\label{eq:wrapped-env} \\tag{WP-3}\\end{equation}', true),
    'starredEquationWrapperMathml' => $converter->texToMathMl('\\begin{equation*}\\operatorname{review}(p_i) + \\eqref{eq:wrapped-env}\\end{equation*}', false, [], $equationReferenceLabels),
    'rowTaggedEnvironmentMathml' => $converter->texToMathMl('\\begin{align}p_i &= m_i \\tag{WP-1} \\\\ x_i &= y_i \\label{eq:row-review} \\tag*{review}\\end{align}', true),
    'notagNonumberMathml' => $converter->texToMathMl('\\begin{align}p_i &= m_i \\notag \\\\ x_i &= y_i \\nonumber \\\\ u_i &= v_i\\end{align}', true),
    'spacingMathml' => $converter->texToMathMl('p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\>z'),
    'explicitSpacingMathml' => $converter->texToMathMl('p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b'),
    'sizedDelimiterMathml' => $converter->texToMathMl('\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/'),
    'delimiterAliasMathml' => $converter->texToMathMl('\\left\\lVert p_i + m_i \\right\\rVert + \\left\\lceil \\frac{x}{y} \\right\\rfloor + \\lbrack q_i \\rbrack'),
    'arrowGroupDelimiterMathml' => $converter->texToMathMl('\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow + \\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache'),
    'middleDelimiterMathml' => $converter->texToMathMl('\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle'),
    'extensibleArrowMathml' => $converter->texToMathMl('\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i'),
    'taggedEquationMathml' => $converter->texToMathMl('p_i + m_i \\label{eq:review-flow} \\tag{WP-2}', true),
    'equationReferenceMathml' => $converter->texToMathMl('\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}', true),
    'equationReferenceLabels' => $equationReferenceLabels,
    'resolvedEquationReferenceMathml' => $converter->texToMathMl('\\eqref{eq:review-flow} + \\eqref{eq:row-review}', false, [], $equationReferenceLabels),
    'automaticNumberReferenceMathml' => $converter->texToMathMl('\\eqref{eq:auto-one} + \\eqref{eq:auto-row} + \\eqref{eq:plain}', false, [], $equationReferenceLabels),
    'accessibleMathml' => $converter->texToAccessibleMathMl('\\frac{a_1}{\\sqrt{b^2}} + \\alpha', true),
];
$summaryJson = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($summaryJson)) {
    throw new RuntimeException('Math TeX handoff summary JSON encoding failed');
}
$summaryText = $summaryJson;
$appendSummaryValue = static function (mixed $value) use (&$appendSummaryValue, &$summaryText): void {
    if (is_array($value)) {
        foreach ($value as $nested) {
            $appendSummaryValue($nested);
        }
        return;
    }

    if ($value !== null) {
        $summaryText .= "\n" . (string) $value;
    }
};
$appendSummaryValue($summary);

if (($argv[1] ?? '') === '--self-test') {
    if (str_contains($summary['macroExpandedMathml'], '<mi>\\wptuple</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test left bounded macro unexpanded');
    }

    $mathSymbol = static fn (int $codepoint): string => html_entity_decode('&#x' . strtoupper(dechex($codepoint)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');

    foreach ([
        '<span class="math inline">\\(\\langle post_id,media_id \\rangle\\)</span>',
        '<span class="math inline">\\(\\wpreview{p_i} + \\wpreview[final]{m_i}\\)</span>',
        '<span class="math inline">\\(\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}\\)</span>',
        '<span class="math display">\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha &amp; \\beta &amp; \\omega \\\\ \\hline 1 &amp; 2 &amp; 3\\end{array} + \\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]</span>',
        '<span class="math inline">\\(p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + \\not\\alpha_i\\)</span>',
        '<span class="math inline">\\(f&#039;(x) + g&#039;&#039;_i + h_i&#039;&#039;&#039; + \\partial^\\prime f + y^\\backprime\\)</span>',
        '<span class="math inline">\\(\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}\\)</span>',
        '<span class="math inline">\\({a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z}\\)</span>',
        '<span class="math inline">\\({a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}\\)</span>',
        '<span class="math inline">\\(\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}\\)</span>',
        '<span class="math inline">\\(\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i} + \\mathllap{L_i} + \\mathrlap{R_i} + \\mathclap{x+y}\\)</span>',
        '<span class="math inline">\\(\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i\\)</span>',
        '<span class="math inline">\\(\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}\\)</span>',
        '<span class="math inline">\\(\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)\\)</span>',
        '<span class="math inline">\\(\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\int\\nolimits_{0}^{1} g(x) dx\\)</span>',
        '<span class="math inline">\\(\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j\\)</span>',
        '<span class="math inline">\\(\\begin{align}f(p_i) &amp;= m_i \\\\ g(p_i) &amp;= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}\\)</span>',
        '<span class="math inline">\\(\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}\\)</span>',
        '<span class="math inline">\\(\\begin{flalign}\\text{source} &amp;&amp; p_i &amp;= m_i &amp;&amp; \\text{review} \\\\ \\text{target} &amp;&amp; x_i &amp;= y_i \\tag{WP-F}\\end{flalign}\\)</span>',
        '<span class="math inline">\\(\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline} + \\left(\\begin{multlined}u+v \\\\ w\\end{multlined}\\right)\\)</span>',
        '<span class="math inline">\\(\\left(\\begin{smallmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i\\)</span>',
        '<span class="math display">\\[\\begin{equation}r_i + s_i \\label{eq:wrapped-env} \\tag{WP-3}\\end{equation}\\]</span>',
        '<span class="math inline">\\(\\begin{equation*}\\operatorname{review}(p_i) + \\eqref{eq:wrapped-env}\\end{equation*}\\)</span>',
        '<span class="math inline">\\(\\begin{align}p_i &amp;= m_i \\tag{WP-1} \\\\ x_i &amp;= y_i \\label{eq:row-review} \\tag*{review}\\end{align}\\)</span>',
        '<span class="math inline">\\(\\begin{align}p_i &amp;= m_i \\notag \\\\ x_i &amp;= y_i \\nonumber \\\\ u_i &amp;= v_i\\end{align}\\)</span>',
        '<span class="math inline">\\(p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\&gt;z\\)</span>',
        '<span class="math inline">\\(p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b\\)</span>',
        '<span class="math inline">\\(\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/\\)</span>',
        '<span class="math inline">\\(\\left\\lVert p_i + m_i \\right\\rVert + \\left\\lceil \\frac{x}{y} \\right\\rfloor + \\lbrack q_i \\rbrack\\)</span>',
        '<span class="math inline">\\(\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow + \\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache\\)</span>',
        '<span class="math inline">\\(\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle\\)</span>',
        '<span class="math inline">\\(\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i\\)</span>',
        '<span class="math display">\\[p_i + m_i \\label{eq:review-flow} \\tag{WP-2}\\]</span>',
        '<span class="math inline">\\(\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}\\)</span>',
        '<span class="math inline">\\(\\eqref{eq:review-flow} + \\eqref{eq:row-review}\\)</span>',
        '<span class="math display">\\[p_i + m_i \\label{eq:auto-one}\\]</span>',
        '<span class="math display">\\[\\begin{align}x_i &amp;= y_i \\label{eq:auto-row} \\\\ u_i &amp;= v_i \\tag{manual}\\end{align}\\]</span>',
        '<span class="math inline">\\(\\eqref{eq:auto-one} + \\eqref{eq:auto-row} + \\eqref{eq:plain}\\)</span>',
        '<span class="math inline">\\(\\frac{a_1}{\\sqrt{b^2}} + \\alpha\\)</span>',
        '<mo>⟨</mo>',
        '<mo>⟩</mo>',
        '<annotation encoding="application/x-tex">\\langle post_id,media_id \\rangle</annotation>',
        '<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mi>f</mi><mi>i</mi><mi>n</mi><mi>a</mi><mi>l</mi>',
        '<annotation encoding="application/x-tex">\\wpreview{p_i} + \\wpreview[final]{m_i}</annotation>',
        '<mtext>review mode</mtext><mo>+</mo><mstyle mathvariant="normal"><mtext>media label</mtext></mstyle>',
        '<mstyle mathvariant="bold"><mtext>draft</mtext></mstyle><mo>+</mo><mstyle mathvariant="italic"><mtext>review</mtext></mstyle>',
        '<mstyle mathvariant="monospace"><mtext>code_1</mtext></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mtext>sans group</mtext></mstyle>',
        '<annotation encoding="application/x-tex">\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}</annotation>',
        '<msub><mi>p</mi><mi>i</mi></msub><mo>∉</mo><mi>P</mi><mo>+</mo><mi>a</mi><mo>≠</mo><mi>b</mi><mo>+</mo><mi>x</mi><mo>≰</mo><mi>y</mi><mo>+</mo><mi>A</mi><mo>⊈</mo><mi>B</mi><mo>+</mo><msub><menclose notation="updiagonalstrike"><mi>α</mi></menclose><mi>i</mi></msub>',
        '<annotation encoding="application/x-tex">p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + \\not\\alpha_i</annotation>',
        '<msup><mi>f</mi><mo>′</mo></msup><mo>(</mo><mi>x</mi><mo>)</mo><mo>+</mo><msubsup><mi>g</mi><mi>i</mi><mo>″</mo></msubsup>',
        '<msup><mo>∂</mo><mo>′</mo></msup><mi>f</mi><mo>+</mo><msup><mi>y</mi><mo>‵</mo></msup>',
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
        '<mtable columnalign="left center right" columnlines="solid solid" rowlines="solid"><mtr><mtd><mi>α</mi></mtd><mtd><mi>β</mi></mtd><mtd><mi>ω</mi></mtd></mtr><mtr><mtd><mn>1</mn></mtd><mtd><mn>2</mn></mtd><mtd><mn>3</mn></mtd></mtr></mtable>',
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
        '<mpadded height="0" depth="0"><mfrac><mi>a</mi><mi>b</mi></mfrac></mpadded>',
        '<mpadded height="0"><msub><mi>p</mi><mi>i</mi></msub></mpadded>',
        '<mpadded depth="0"><msub><mi>m</mi><mi>i</mi></msub></mpadded>',
        '<mpadded width="0" lspace="-1width"><msub><mi>L</mi><mi>i</mi></msub></mpadded>',
        '<mpadded width="0"><msub><mi>R</mi><mi>i</mi></msub></mpadded>',
        '<mpadded width="0" lspace="-0.5width"><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow></mpadded>',
        '<mstyle mathvariant="normal"><mi>d</mi></mstyle><mi>x</mi>',
        '<mstyle mathvariant="bold"><msub><mi>' . $mathSymbol(0x1D42F) . '</mi><mi>' . $mathSymbol(0x1D422) . '</mi></msub></mstyle>',
        '<mstyle mathvariant="italic"><mi>' . $mathSymbol(0x1D45B) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mi>' . $mathSymbol(0x1D5B2) . '</mi></mstyle>',
        '<mstyle mathvariant="monospace"><mrow><mi>' . $mathSymbol(0x1D68C) . '</mi><mi>' . $mathSymbol(0x1D698) . '</mi><mi>' . $mathSymbol(0x1D68D) . '</mi><mi>' . $mathSymbol(0x1D68E) . '</mi></mrow></mstyle>',
        '<msub><mstyle mathvariant="script"><mi>' . $mathSymbol(0x2131) . '</mi></mstyle><mi>n</mi></msub><mo>+</mo><mstyle mathvariant="double-struck"><mi>' . $mathSymbol(0x211D) . '</mi></mstyle>',
        '<mstyle mathvariant="fraktur"><mi>' . $mathSymbol(0x1D524) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="script"><mi>' . $mathSymbol(0x2112) . '</mi></mstyle>',
        '<msub><mstyle mathvariant="bold"><mi>α</mi></mstyle><mi>i</mi></msub>',
        '<mstyle mathvariant="double-struck"><mrow><mi>' . $mathSymbol(0x1D538) . '</mi><mi>' . $mathSymbol(0x2124) . '</mi><mn>' . $mathSymbol(0x1D7D8) . $mathSymbol(0x1D7E1) . '</mn></mrow></mstyle>',
        '<mstyle mathvariant="script"><mrow><mi>' . $mathSymbol(0x2131) . '</mi><mi>' . $mathSymbol(0x2112) . '</mi><mi>' . $mathSymbol(0x1D4AA) . '</mi></mrow></mstyle>',
        '<mstyle mathvariant="fraktur"><mrow><mi>' . $mathSymbol(0x1D524) . '</mi><mi>' . $mathSymbol(0x211C) . '</mi></mrow></mstyle>',
        '<mstyle mathvariant="monospace"><mrow><mi>' . $mathSymbol(0x1D68C) . '</mi><mi>' . $mathSymbol(0x1D698) . '</mi><mi>' . $mathSymbol(0x1D68D) . '</mi><mi>' . $mathSymbol(0x1D68E) . '</mi><mn>' . $mathSymbol(0x1D7FA) . $mathSymbol(0x1D7F8) . '</mn></mrow></mstyle>',
        '<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>',
        '<msub><mo>lim</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>x</mi><mo>→</mo><mn>0</mn></mtd></mtr><mtr><mtd><mi>x</mi><mo>&gt;</mo><mn>0</mn></mtd></mtr></mtable></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>',
        '<munderover><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><msub><mi>p</mi><mi>i</mi></msub>',
        '<munder><mo>lim</mo><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>',
        '<msubsup><mo>∫</mo><mn>0</mn><mn>1</mn></msubsup><mi>g</mi><mo>(</mo><mi>x</mi><mo>)</mo><mi>d</mi><mi>x</mi>',
        '<munderover><mi>argmax</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover>',
        '<munderover><mi>median</mi><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover>',
        '<msub><mi>rank</mi><mi>j</mi></msub><msub><mi>q</mi><mi>j</mi></msub>',
        '<mtable columnalign="right left"><mtr><mtd><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>g</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>',
        '<mtable columnalign="center"><mtr><mtd><mi>x</mi><mo>+</mo><mi>y</mi></mtd></mtr><mtr><mtd><mi>z</mi></mtd></mtr></mtable>',
        '<mtable columnalign="right left"><mtr><mtd><mi>S</mi></mtd><mtd><mo>=</mo><msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd></mtd><mtd><mo>=</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd></mtr></mtable>',
        '<mtable columnalign="right left right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><msub><mi>a</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd></mtr></mtable>',
        '<mtable columnalign="left right left right left right"><mtr><mtd><mtext>source</mtext></mtd><mtd></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd></mtd><mtd><mtext>review</mtext></mtd></mtr><mlabeledtr><mtd><mtext>(WP-F)</mtext></mtd><mtd><mtext>target</mtext></mtd><mtd></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>',
        '<mtable columnalign="center"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>=</mo><msub><mi>a</mi><mi>i</mi></msub><mo>+</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>+</mo><mfrac><mi>x</mi><mi>y</mi></mfrac></mtd></mtr></mtable>',
        '<mo fence="true" stretchy="true">(</mo><mtable columnalign="center"><mtr><mtd><mi>u</mi><mo>+</mo><mi>v</mi></mtd></mtr><mtr><mtd><mi>w</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>',
        '<mstyle scriptlevel="1"><mtable rowspacing="0.1em" columnspacing="0.2778em"><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable></mstyle>',
        '<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>',
        '<mtable><mlabeledtr><mtd><mtext>(WP-3)</mtext></mtd><mtd id="eq:wrapped-env"><mrow><msub><mi>r</mi><mi>i</mi></msub><mo>+</mo><msub><mi>s</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{equation}r_i + s_i \\label{eq:wrapped-env} \\tag{WP-3}\\end{equation}</annotation>',
        '<mi>review</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped-env">WP-3</mtext><mo>)</mo></mrow>',
        '<mtable columnalign="right left"><mlabeledtr><mtd><mtext>(WP-1)</mtext></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mlabeledtr><mlabeledtr id="eq:row-review"><mtd><mtext>review</mtext></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>',
        '<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>u</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>v</mi><mi>i</mi></msub></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\notag \\\\ x_i &amp;= y_i \\nonumber \\\\ u_i &amp;= v_i\\end{align}</annotation>',
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
        '<mo fence="true" stretchy="true">‖</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">‖</mo>',
        '<mo fence="true" stretchy="true">⌈</mo><mfrac><mi>x</mi><mi>y</mi></mfrac><mo fence="true" stretchy="true">⌋</mo><mo>+</mo><mo>[</mo><msub><mi>q</mi><mi>i</mi></msub><mo>]</mo>',
        '<annotation encoding="application/x-tex">\\left\\lVert p_i + m_i \\right\\rVert + \\left\\lceil \\frac{x}{y} \\right\\rfloor + \\lbrack q_i \\rbrack</annotation>',
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
        '<annotation encoding="application/x-tex">\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i} + \\mathllap{L_i} + \\mathrlap{R_i} + \\mathclap{x+y}</annotation>',
        '<annotation encoding="application/x-tex">\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i</annotation>',
        '<annotation encoding="application/x-tex">\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}</annotation>',
        '<annotation encoding="application/x-tex">\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)</annotation>',
        '<annotation encoding="application/x-tex">\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\int\\nolimits_{0}^{1} g(x) dx</annotation>',
        '<annotation encoding="application/x-tex">\\begin{align}f(p_i) &amp;= m_i \\\\ g(p_i) &amp;= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}</annotation>',
        '<annotation encoding="application/x-tex">\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}</annotation>',
        '<annotation encoding="application/x-tex">\\begin{flalign}\\text{source} &amp;&amp; p_i &amp;= m_i &amp;&amp; \\text{review} \\\\ \\text{target} &amp;&amp; x_i &amp;= y_i \\tag{WP-F}\\end{flalign}</annotation>',
        '<annotation encoding="application/x-tex">\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline} + \\left(\\begin{multlined}u+v \\\\ w\\end{multlined}\\right)</annotation>',
        '<annotation encoding="application/x-tex">\\left(\\begin{smallmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i</annotation>',
        '<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\tag{WP-1} \\\\ x_i &amp;= y_i \\label{eq:row-review} \\tag*{review}\\end{align}</annotation>',
        '<annotation encoding="application/x-tex">p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\&gt;z</annotation>',
        '<annotation encoding="application/x-tex">p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b</annotation>',
        '<annotation encoding="application/x-tex">\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/</annotation>',
        '<annotation encoding="application/x-tex">\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle</annotation>',
        '<mo fence="true" stretchy="true">↑</mo><msub><mi>x</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">⇕</mo><msub><mi>y</mi><mi>i</mi></msub><mo fence="true" stretchy="true">↓</mo>',
        '<mo>⟮</mo><msub><mi>p</mi><mi>i</mi></msub><mo>⟯</mo><mo>+</mo><mo fence="true" stretchy="true">⎰</mo><mi>a</mi><mo fence="true" stretchy="true">⎱</mo>',
        '<annotation encoding="application/x-tex">\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow + \\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache</annotation>',
        '<annotation encoding="application/x-tex">\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i</annotation>',
        '<annotation encoding="application/x-tex">p_i + m_i \\label{eq:review-flow} \\tag{WP-2}</annotation>',
        '<annotation encoding="application/x-tex-label">eq:review-flow</annotation>',
        '<mrow id="eq:plain"><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:plain">eq:plain</mtext><mo>)</mo></mrow><mo>+</mo><mtext href="#review-row-2">review row/2</mtext></mrow>',
        '<annotation encoding="application/x-tex">\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}</annotation>',
        '"eq:review-flow"',
        '"reference": "WP-2"',
        '"eq:row-review"',
        '"reference": "review"',
        '"eq:auto-one"',
        '"reference": "1"',
        '"eq:auto-row"',
        '"reference": "2"',
        '<annotation encoding="application/x-tex">\\eqref{eq:review-flow} + \\eqref{eq:row-review}</annotation>',
        '<mrow><mo>(</mo><mtext href="#eq:review-flow">WP-2</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:row-review">review</mtext><mo>)</mo></mrow>',
        '<annotation encoding="application/x-tex">\\eqref{eq:auto-one} + \\eqref{eq:auto-row} + \\eqref{eq:plain}</annotation>',
        '<mrow><mo>(</mo><mtext href="#eq:auto-one">1</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:auto-row">2</mtext><mo>)</mo></mrow><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:plain">eq:plain</mtext><mo>)</mo></mrow>',
        'display="block" alttext="fraction a sub 1 over square root of b superscript 2 plus alpha" intent="row(fraction(subscript(a,1),sqrt(superscript(b,2))),plus,alpha)"',
        '<annotation encoding="application/x-portlibs-math-alttext">fraction a sub 1 over square root of b superscript 2 plus alpha</annotation>',
        '<annotation encoding="application/x-portlibs-math-intent">row(fraction(subscript(a,1),sqrt(superscript(b,2))),plus,alpha)</annotation>',
        '<annotation encoding="application/x-tex">\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha &amp; \\beta &amp; \\omega \\\\ \\hline 1 &amp; 2 &amp; 3\\end{array} + \\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega</annotation>',
        '<annotation encoding="application/x-tex">\\wptuple{post_id,media_id}</annotation>',
        '\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{pmatrix} + \\begin{aligned}x_i &= \\operatorname{score}(p_i) \\\\ y_i &= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha & \\beta & \\omega \\\\ \\hline 1 & 2 & 3\\end{array} + \\begin{cases}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]',
    ] as $needle) {
        if (!str_contains($summaryText, $needle)) {
            throw new RuntimeException('Math TeX handoff self-test missing: ' . $needle);
        }
    }

    echo "math tex handoff self-test ok\n";
    return;
}

echo $summaryJson . "\n";
