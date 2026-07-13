<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MathTexConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Math Import Review

\newcommand{\wptuple}[1]{\langle #1 \rangle}
\newcommand{\wpreview}[2][draft]{#2 + #1}
\newcommand{\wpnestedscore}[1]{\operatorname{nested\,review}_{#1}}
\DeclareMathOperator{\wpreviewscore}{review\,score}
\DeclareMathOperator*{\wpargreview}{arg\,review}
\DeclarePairedDelimiter{\wpabs}{\lvert}{\rvert}
\DeclarePairedDelimiterX{\wpbetween}[2]{\langle}{\rangle}{#1 , #2}
\DeclarePairedDelimiterX{\wpfilter}[2]{\langle}{\rangle}{#1 , \operatorname{media}_{#2}}
\DeclarePairedDelimiterXPP{\wprelated}[2]{\alpha\,}{\lbrack}{\rbrack}{\,\omega}{#1 \mid #2}

Reviewer equation $\wptuple{post_id,media_id}$ stays editable.

Optional macro audit $\wpreview{p_i} + \wpreview[final]{m_i}$ stays editable.

Nested macro declaration audit $\wpnestedscore{p_i} + \wpfilter{p_i}{m_i}$ keeps balanced templates editable.

Declared operator audit $\wpreviewscore_i(p_i)$ stays semantic.

Starred declared operator audit $\wpargreview_{p_i \in P}^{\text{draft}} f(p_i)$ stays semantic.

Paired delimiter audit $\wpabs{p_i + m_i}$ stays semantic.

Starred and sized paired delimiter audit $\wpabs*{p_i + m_i} + \wpabs[\Big]{q_i} + \wpabs[\bigg]{r_i}$ stays semantic.

Paired delimiter template audit $\wpbetween{p_i}{m_i} + \wpbetween[\Big]{q_i}{r_i}$ stays semantic.

Paired delimiter prefix-suffix audit $\wprelated{p_i}{m_i} + \wprelated[\Big]{q_i}{r_i}$ stays semantic.

Text alias audit $\mbox{review mode} + \textrm{media label} + \textbf{draft} + \textit{review} + \texttt{code_1} + \textsf{sans group}$ stays semantic.

Unbraced text token audit $\textbf x_i + \textit\% + \mbox~ + \texttt\& + \textnormal\TeX + \textsf\ldots$ stays semantic.

Escaped special symbol audit $\{p_i\} + a\#b + c\&d + e\$f + g\%h + i\_j + \textbackslash$ stays semantic.

Dot and named symbol alias audit $\ldots + \cdots + \ddots + \aleph + \ell + \Re + \Im + \wp + a \cong b + c \simeq d + x \propto y + u \parallel v + r \perp s + \angle x + \nabla f + \top + \bot$ stays semantic.

Operator relation alias audit $a \oplus b + c \ominus d + x \asymp y + p \vdash q + u \bowtie v$ stays semantic.

Generated symbol alias audit $a \dotplus b + c \boxplus d + e \boxminus f + A \sqsubset B + C \sqsupseteq D + x \lesssim y + r \gtrapprox s + p \Bumpeq q + x \rightsquigarrow y + m \nleq n$ stays semantic.

Relation and harpoon alias audit $A \prec B + C \succ D + E \ll F + G \gg H + x \nearrow y + a \searrow b + L \leftharpoonup M + N \rightharpoondown O + P \rightleftharpoons Q + p \because q + f \multimap g$ stays semantic.

Symbol override alias audit $\arg z + \hbar\omega + \digamma + \varnothing + a \dag b + c \ddag d + A \lhd B + C \unrhd D + M \longmapsto N + \blacklozenge$ stays semantic.

Extended relation alias audit $\beth + \gimel + \daleth + a \leqq b + c \geqq d + x \doteq y + P \nsubseteq Q + u \nparallel v$ stays semantic.

Generated symbol-map relation audit $a \lneq b + c \gneq d + p \precapprox q + r \succapprox s + P \nvdash Q + R \nvDash S + x \varpropto y + A \smallsetminus B + \blacktriangle$ stays semantic.

Negative approximate relation alias audit $x \approxeq y + a \napprox b + c \ncong d$ stays semantic.

Comparison relation alias audit $x \nless y + a \ngtr b + c \leqgtr d + e \geqless f$ stays semantic.

Unicode symbol-map alias audit $\AC + \twoheadleftarrow + \hookleftarrow + A \nleftarrow B + C \nrightarrow D + E \nleftrightarrow F + P \nsubset Q + R \nsupset S$ stays semantic.

Variant Greek and underbar audit $\varGamma + \varDelta + \varrho_i + \varsigma + \upUpsilon + \overbar{x_i + y_i} + \underbar{\operatorname{draft}}$ stays semantic.

Display audit:
$$\sum_{i=1}^{n} \operatorname{migrate}(p_i) + \frac{a_1}{\sqrt{b^2}} + \sqrt[3]{x_i + y_i} + \binom{n}{k} + \tbinom{p_i}{2} + \dbinom{a+b}{c} + \dfrac{q_i}{r_i} + \genfrac{\langle}{\rangle}{0pt}{0}{n}{k} + \widehat{\operatorname{quality}} + \vec{v}_i + \begin{pmatrix}p_1 & m_1 \\ p_2 & m_2\end{pmatrix} + \begin{aligned}x_i &= \operatorname{score}(p_i) \\ y_i &= \frac{a_i}{b_i}\end{aligned} + \begin{array}{l|c|r}\alpha & \beta & \omega \\ \hline 1 & 2 & 3\end{array} + \begin{cases}p_i & p_i \in P \\ 0 & \text{otherwise}\end{cases} + \forall p_i \in P \Rightarrow p_i \notin \emptyset + \alpha \times \omega$$

Plain root audit $\root 3 \of{x_i + y_i} + \root n+1 \of{\frac{a}{b}}$ stays semantic.

TeX token argument audit $\sqrt x_i + \sqrt[3]y_j + \frac12 + \dfrac a b + \binom n k + \overset\alpha q_i + \underset 0 r_i + \boxed s_i + \phantom t_i + \hphantom u_i + \vphantom v_i + \cancel w_i + \bcancel x + \xcancel y$ stays semantic.

Plain alignment command audit $\eqalign{p_i &= m_i \cr q_i &= n_i} + \displaylines{p_i + m_i \cr q_i + n_i}$ keeps command rows semantic.

Partial array rule audit $\begin{array}{l|c|r}p_i & m_i & 1 \\ \cline{2-3} q_i & n_i & 2 \\ \cline{1-1}\cline{3-3} r_i & s_i & 3\end{array}$ stays semantic.

Repeated array preamble audit $\begin{array}{*{2}{c|}r}p_1 & m_1 & 1 \\ p_2 & m_2 & 2\end{array}$ stays semantic.

Width array audit $\begin{array}{p{2cm}|m{1.5em}|b{8pt}}p_i & \text{middle review} & 1 \\ q_i & n_i & 2\end{array}$ stays semantic.

Array hook audit $\begin{array}{>{\text{src}}l<{\hspace{.25em}}@{\,}c}p_i & m_i \\ q_i & n_i\end{array}$ keeps preamble metadata.

Multicolumn array audit $\begin{array}{lcr}p_i & \multicolumn{2}{|c|}{m_i + q_i} \\ a & b & c\end{array}$ keeps span metadata.

Negated relation audit $p_i \not\in P + a \not= b + x \not\leq y + A \not\subseteq B + \not\alpha_i$ stays semantic.

Braced negated relation audit $x \not{\in} S + y \not{=} z + q \not{\leqslant} r + u \not\geqslant v$ stays canonical.

Prime audit $f'(x) + g''_i + h_i''' + r'''' + s'''''_j + \partial^\prime f + y^\backprime$ stays semantic.

Accent alias audit $\acute{x} + \grave{y} + \breve{z} + \check{a} + \mathring{A}_0 + \widetilde{mn}$ stays semantic.

Extended accent alias audit $\dddot{x_i} + \ddddot{y} + \DDDot z + \utilde{x_i} + \wideutilde{mn}$ stays semantic.

Above and below audit $\overset{\text{new}}{p_i} + \underset{0}{\lim}_{n \to \infty} a_n + \overbrace{x + y}^{\text{sum}} + \underbrace{m_i}_{\text{media}} + \displaystyle \frac{q}{r}$ stays semantic.

Combined over-under audit $\overunderset{\text{publish}}{\operatorname{draft}}{p_i} + \underoverset{0}{\infty}{\lim}_{n \to \infty} a_n$ keeps review labels semantic.

Unbraced brace audit $\overbrace x_i^n + \underbrace y_j + \overbracket x^2 + \underbracket y_0$ keeps texToken bases semantic.

Over-under group audit $\overparen{p_i + m_i}^{\text{review}} + \underparen{q_i}_{0} + \overgroup{x+y} + \undergroup{z}$ stays semantic.

Buildrel relation audit $\buildrel{\text{def}}\over= + A \buildrel{\operatorname{iso}}\over\longrightarrow B$ stays semantic.

Infix audit ${a+b \over c+d} + {n \choose k} + {n \atop k} + {p_i \brack m_i} + {x+y \brace z} + {n \bangle k}$ stays semantic.

With-delims audit ${a+b \overwithdelims() c+d} + {n \atopwithdelims\langle\rangle k} + {p_i \abovewithdelims[]1pt m_i}$ stays semantic.

Review controls $\color{red}{p_i} + \textcolor{#336699}{\operatorname{media}} + \phantom{p_i + m_i} + \hphantom{draft} + \vphantom{\frac{a}{b}} + \cancel{x_i} + \bcancel{y_i} + \xcancel{z_i} + \cancelto{0}{\operatorname{draft}_i}$ stay explicit.

Color declaration audit $\color{reviewblue} p_i + m_i + \frac{a}{b}$ scopes review color to the remaining math expression.

Xcolor model audit $\textcolor[HTML]{336699}{\operatorname{media}} + \textcolor[rgb]{0.2,0.4,0.6}{p_i} + \color[gray]{.5} m_i + q_i$ keeps bounded color models semantic.

Color box audit $\colorbox{yellow}{p_i + m_i} + \colorbox[HTML]{fff9cc}{\operatorname{media}} + \fcolorbox{red}{yellow}{q_i} + \fcolorbox[RGB]{51,102,153}{255,249,204}{\frac{a}{b}}$ keeps review box metadata.

Token color and cancel box audit $\colorbox{yellow}x_i + \fcolorbox{red}{yellow}q_i + \cancelto0r_i + \cancelto\alpha\frac12$ keeps texToken boxes semantic.

Boxed equation audit $\boxed{p_i + m_i} + \boxed{\frac{a}{b}}_j$ stays semantic.

Overlap layout audit $\smash{\frac{a}{b}} + \smash[t]{p_i} + \smash[b]{m_i} + \mathllap{L_i} + \mathrlap{R_i} + \mathclap{x+y}$ stays semantic.

Unbraced layout audit $\smash x_i + \smash[t] y^2 + \mathllap L_i + \mathrlap R + \clap C$ keeps texToken boxes semantic.

Math alphabet audit $\mathrm{d}x + \mathbf{v_i} + \mathit{n} + \mathsf{S} + \mathtt{code} + \mathcal{F}_n + \mathbb{R} + \mathfrak{g} + \mathscr{L} + \boldsymbol{\alpha}_i$ stays semantic.

Math alphanumeric audit $\mathbb{AZ09} + \mathcal{FLO} + \mathfrak{gR} + \mathtt{code42}$ keeps Unicode MathML review glyphs.

Math alphabet alias audit $\mathup{x} + \symbf{A1} + \bm{\alpha_i} + \mathds{R2} + \mathbfit{Az} + \mathbfsfup{R2} + \mathbfsfit{Az} + \mathbfscr{F} + \mathbfcal{L} + \mathbffrak{g} + \mathsfit{n}$ keeps texmath aliases semantic.

Math alphabet Greek alias audit $\bm{\alpha_i} + \mathbfit{\Gamma\alpha} + \mathbfsfup{\Theta\beta} + \mathbfsfit{\Omega\omega}$ keeps Unicode Greek variants semantic.

Stacked limits audit $\sum_{\substack{i=1 \\ i\ne j}}^{n} a_i + \lim_{\substack{x \to 0 \\ x > 0}} f(x)$ stays semantic.

Prescript isotope audit $\prescript{14}{6}{C} + \prescript{\text{review}}{}{p_i} + \prescript{}{L}{\operatorname{score}}_j$ stays semantic.

Operator limits audit $\sum\limits_{i=1}^{n} p_i + \lim\limits_{x \to 0} f(x) + \int\nolimits_{0}^{1} g(x) dx$ stays semantic.

Large operator alias audit $\bigcup_{i=1}^{n} A_i + \bigcap_{j} B_j + \coprod\limits_{k=0}^{m} C_k + \iint_D f(x,y) dx dy + \bigoplus_i G_i$ stays semantic.

Starred operator limits audit $\operatorname*{argmax}_{p_i \in P}^{\text{draft}} f(p_i) + \operatorname{median}\displaylimits_{i=1}^{n} p_i + \operatorname*{rank}\nolimits_{j} q_j$ stays semantic.

Unbraced operatorname audit $\operatorname\alpha_i + \operatorname*\max_{j}^{n} p_j$ keeps texmath single-token operators semantic.

Modulo audit $a \mod n + b \bmod m_i + x \pmod {n+1} + y \pod m_i$ stays semantic.

Math class audit $\mathop{\operatorname{argmax}}\limits_{p_i \in P}^{\text{draft}} f(p_i) + a \mathrel{\approx} b + x \mathbin{\cdot} y + \mathopen{[}q_i\mathclose{]} + f\mathpunct{,}g$ stays semantic.

AMS layout audit $\begin{align}f(p_i) &= m_i \\ g(p_i) &= \frac{a_i}{b_i}\end{align} + \begin{gathered}x+y \\ z\end{gathered} + \begin{split}S &= \sum_{i=1}^{n} p_i \\ &= \frac{a}{b}\end{split}$ stays semantic.

Alignedat audit $\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\ x &= y & u &= v\end{alignedat}$ stays semantic.

Intertext audit $\begin{align}p_i &= m_i \\ \intertext{review \& media} x_i &= y_i \\ \shortintertext{compact review} u_i &= v_i\end{align}$ stays semantic.

Optional AMS placement audit $\begin{aligned}[t]p_i &= m_i \\ x &= y\end{aligned} + \begin{gathered}[b]u+v \\ w\end{gathered} + \begin{alignedat}[c]{2}a &= b & c &= d\end{alignedat}$ stays semantic.

Flush alignment audit $\begin{flalign}\text{source} && p_i &= m_i && \text{review} \\ \text{target} && x_i &= y_i \tag{WP-F}\end{flalign}$ stays semantic.

Eqnarray audit $\begin{eqnarray}p_i &=& m_i \\ x_i &=& y_i \tag{WP-E}\end{eqnarray}$ stays semantic.

Row spacing audit $\begin{aligned}a_i &= b_i \\[.5em] c_i &= d_i\end{aligned}$ preserves review spacing metadata.

Multline audit $\begin{multline}p_i + m_i \\[.5em] = a_i + b_i \\ + \frac{x}{y}\end{multline} + \left(\begin{multlined}u+v \\ w\end{multlined}\right)$ stays semantic.

Compact environment audit $\left(\begin{smallmatrix}p_1 & m_1 \\ p_2 & m_2\end{smallmatrix}\right) + \sum_{\begin{subarray}{c}i=1 \\ i\ne j\end{subarray}}^{n} a_i$ stays semantic.

Mathtools cases audit $\begin{dcases}p_i & p_i \in P \\ 0 & \text{otherwise}\end{dcases} + \begin{rcases}q_i & q_i \in Q \\ 0 & \text{otherwise}\end{rcases} + \begin{drcases*}r_i & r_i \in R \\ 0 & \text{otherwise}\end{drcases*}$ keeps display and right-brace cases semantic.

Starred matrix alias audit $\begin{pmatrix*}p_i & m_i \\ q_i & n_i\end{pmatrix*} + \begin{cases*}p_i & p_i \in P \\ 0 & \text{otherwise}\end{cases*}$ stays semantic.

Texmath command wrapper audit $\stackrel{\text{audit}}{p_i} + \ensuremath{q_i + r_i} + \surd{s_i}$ stays semantic.

Math choice audit $\mathchoice{\text{display branch}}{\text{text branch}}{\text{script branch}}{\text{tiny branch}} + q_i$ stays style-aware.

SI unit alias audit $\SI{9.81}{\metre\per\second\squared} + \si{\km\per\hour} + \unit{\joule\per\mole\per\kelvin}$ stays semantic.

Prefixed SI unit alias audit $\si{\mg\per\mL} + \qty{532}{\nm} + \SI{20}{\MHz} + \unit{\kPa} + \si{\us}$ stays semantic.

Extended SI unit alias audit $\si{\mHz\per\hL} + \unit{\TeV\per\mmHg} + \qty{42}{\becquerel\per\candela} + \si{\dalton\per\tonne} + \si{\yocto\meter\per\zetta\gram}$ stays semantic.

Equation wrapper audit:
$$\begin{equation}r_i + s_i \label{eq:wrapped-env} \tag{WP-3}\end{equation}$$

Starred equation wrapper audit $\begin{equation*}\operatorname{review}(p_i) + \eqref{eq:wrapped-env}\end{equation*}$ stays semantic.

Row-tag audit $\begin{align}p_i &= m_i \tag{WP-1} \\ x_i &= y_i \label{eq:row-review} \tag*{review}\end{align}$ stays semantic.

No-number row audit $\begin{align}p_i &= m_i \notag \\ x_i &= y_i \nonumber \\ u_i &= v_i\end{align}$ keeps suppressed rows unnumbered.

Spacing audit $p_i\,m_i\;n_i\!q_i + a\quad b\qquad c + \operatorname{post}\thinspace\operatorname{media}\negthinspace\operatorname{review} + x\:y\>z$ stays semantic.

Allowbreak audit $p_i\allowbreak + m_i + \operatorname{slug}\allowbreak$ stays semantic.

Comment audit $p_i % reviewer hidden$ keeps source notes out of rendered MathML.

Environment terminator comment audit:
$$\begin{aligned}a_i &= b_i % \end{aligned} hidden terminator
\\ c_i &= d_i\end{aligned}$$

Explicit spacing audit $p_i\hspace{1.5em}m_i\mspace{-2mu}q_i + a\hspace*{.25in}b$ stays semantic.

Sized delimiters audit $\bigl( p_i \bigr) + \Bigl\langle m_i \Bigr\rangle + \bigm| x \in S + \Bigg/ y \Bigg/$ stays semantic.

Delimiter alias audit $\left\lVert p_i + m_i \right\rVert + \left\lceil \frac{x}{y} \right\rfloor + \lbrack q_i \rbrack$ stays semantic.

Tortoise shell delimiter audit $\left\lbrbrak p_i + m_i \right\rbrbrak + \Bigl\Lbrbrak q_i \Bigr\Rbrbrak$ stays semantic.

Arrow/group delimiter audit $\left\uparrow x_i \middle\Updownarrow y_i \right\downarrow + \Bigl\Uparrow z \Bigr\Downarrow + \lgroup p_i \rgroup + \left\lmoustache a \right\rmoustache$ stays semantic.

Middle delimiter audit $\left\{p_i \middle| p_i \in P\right\} + \left\langle x \middle/ y \right\rangle$ stays semantic.

Extensible arrow audit $\xrightarrow[\text{review}]{\operatorname{publish}} p_i + \xleftarrow{draft} m_i + \overrightarrow{AB}_i$ stays semantic.

Extensible arrow alias audit $\xlongequal{\text{same}} + \xhookrightarrow[\text{map}]{f} + \xtwoheadleftarrow{g} + \xleftharpoonup{\text{pull}} + \xrightharpoondown[low]{high}$ stays semantic.

Reciprocal harpoon arrow audit $\xrightleftharpoons[\text{review}]{\operatorname{publish}} p_i + \xleftrightharpoons{draft} m_i$ stays semantic.

Unbraced extensible arrow audit $\xrightarrow\alpha p_i + \xleftarrow[\text{low}]\beta m_i + \xhookrightarrow[map] f q + \overrightarrow A_i + \underrightarrow\operatorname{media}$ keeps texToken arrow labels semantic.

Tagged equation audit:
$$p_i + m_i \label{eq:review-flow} \tag{WP-2}$$

Equation reference audit $\label{eq:plain}x_i + \eqref{eq:plain} + \ref{review row/2}$ stays linked.

Resolved equation reference audit $\eqref{eq:review-flow} + \eqref{eq:row-review}$ keeps known tags.

Hyperref wrapper audit $\hyperref[eq:review-flow]{p_i + m_i} + \hyperref{q_i}$ stays semantic.

Math href/url audit $\href{https://example.test/review}{p_i + m_i} + \url{mailto:reviewer@example.test}$ stays linked.

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
    'inlineMathml' => $converter->mathMlFor($inlineMath),
    'mathml' => $converter->mathMlFor($displayMath),
    'macroExpandedMathml' => $converter->texToMathMl('\\wptuple{post_id,media_id}', false, $converter->macroDefinitionsFromDocument($document)),
    'optionalMacroMathml' => $converter->texToMathMl('\\wpreview{p_i} + \\wpreview[final]{m_i}', false, $converter->macroDefinitionsFromDocument($document)),
    'nestedMacroMathml' => $converter->texToMathMl('\\wpnestedscore{p_i} + \\wpfilter{p_i}{m_i}', false, $converter->macroDefinitionsFromDocument($document)),
    'declaredOperatorMathml' => $converter->texToMathMl('\\wpreviewscore_i(p_i)', false, $converter->macroDefinitionsFromDocument($document)),
    'starredDeclaredOperatorMathml' => $converter->texToMathMl('\\wpargreview_{p_i \\in P}^{\\text{draft}} f(p_i)', true, $converter->macroDefinitionsFromDocument($document)),
    'declaredPairedDelimiterMathml' => $converter->texToMathMl('\\wpabs{p_i + m_i}', false, $converter->macroDefinitionsFromDocument($document)),
    'declaredPairedDelimiterSizedMathml' => $converter->texToMathMl('\\wpabs*{p_i + m_i} + \\wpabs[\\Big]{q_i} + \\wpabs[\\bigg]{r_i}', false, $converter->macroDefinitionsFromDocument($document)),
    'declaredPairedDelimiterXMathml' => $converter->texToMathMl('\\wpbetween{p_i}{m_i} + \\wpbetween[\\Big]{q_i}{r_i}', false, $converter->macroDefinitionsFromDocument($document)),
    'declaredPairedDelimiterXppMathml' => $converter->texToMathMl('\\wprelated{p_i}{m_i} + \\wprelated[\\Big]{q_i}{r_i}', false, $converter->macroDefinitionsFromDocument($document)),
    'textAliasMathml' => $converter->texToMathMl('\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}'),
    'textTokenAliasMathml' => $converter->texToMathMl('\\textbf x_i + \\textit\\% + \\mbox~ + \\texttt\\& + \\textnormal\\TeX + \\textsf\\ldots'),
    'escapedSpecialSymbolMathml' => $converter->texToMathMl('\\{p_i\\} + a\\#b + c\\&d + e\\$f + g\\%h + i\\_j + \\textbackslash'),
    'dotRelationSymbolAliasMathml' => $converter->texToMathMl('\\ldots + \\cdots + \\ddots + \\aleph + \\ell + \\Re + \\Im + \\wp + a \\cong b + c \\simeq d + x \\propto y + u \\parallel v + r \\perp s + \\angle x + \\nabla f + \\top + \\bot'),
    'operatorRelationAliasMathml' => $converter->texToMathMl('a \\oplus b + c \\ominus d + x \\asymp y + p \\vdash q + u \\bowtie v'),
    'generatedSymbolAliasMathml' => $converter->texToMathMl('a \\dotplus b + c \\boxplus d + e \\boxminus f + A \\sqsubset B + C \\sqsupseteq D + x \\lesssim y + r \\gtrapprox s + p \\Bumpeq q + x \\rightsquigarrow y + m \\nleq n'),
    'relationHarpoonAliasMathml' => $converter->texToMathMl('A \\prec B + C \\succ D + E \\ll F + G \\gg H + x \\nearrow y + a \\searrow b + L \\leftharpoonup M + N \\rightharpoondown O + P \\rightleftharpoons Q + p \\because q + f \\multimap g'),
    'symbolOverrideAliasMathml' => $converter->texToMathMl('\\arg z + \\hbar\\omega + \\digamma + \\varnothing + a \\dag b + c \\ddag d + A \\lhd B + C \\unrhd D + M \\longmapsto N + \\blacklozenge'),
    'extendedRelationAliasMathml' => $converter->texToMathMl('\\beth + \\gimel + \\daleth + a \\leqq b + c \\geqq d + x \\doteq y + P \\nsubseteq Q + u \\nparallel v'),
    'generatedSymbolMapRelationAliasMathml' => $converter->texToMathMl('a \\lneq b + c \\gneq d + p \\precapprox q + r \\succapprox s + P \\nvdash Q + R \\nvDash S + x \\varpropto y + A \\smallsetminus B + \\blacktriangle'),
    'negativeApproxRelationAliasMathml' => $converter->texToMathMl('x \\approxeq y + a \\napprox b + c \\ncong d'),
    'comparisonRelationAliasMathml' => $converter->texToMathMl('x \\nless y + a \\ngtr b + c \\leqgtr d + e \\geqless f'),
    'unicodeSymbolMapAliasMathml' => $converter->texToMathMl('\\AC + \\twoheadleftarrow + \\hookleftarrow + A \\nleftarrow B + C \\nrightarrow D + E \\nleftrightarrow F + P \\nsubset Q + R \\nsupset S'),
    'variantGreekUnderbarAliasMathml' => $converter->texToMathMl('\\varGamma + \\varDelta + \\varrho_i + \\varsigma + \\upUpsilon + \\overbar{x_i + y_i} + \\underbar{\\operatorname{draft}}'),
    'plainRootMathml' => $converter->texToMathMl('\\root 3 \\of{x_i + y_i} + \\root n+1 \\of{\\frac{a}{b}}'),
    'texTokenArgumentMathml' => $converter->texToMathMl('\\sqrt x_i + \\sqrt[3]y_j + \\frac12 + \\dfrac a b + \\binom n k + \\overset\\alpha q_i + \\underset 0 r_i + \\boxed s_i + \\phantom t_i + \\hphantom u_i + \\vphantom v_i + \\cancel w_i + \\bcancel x + \\xcancel y'),
    'arrayClineMathml' => $converter->texToMathMl('\\begin{array}{l|c|r}p_i & m_i & 1 \\\\ \\cline{2-3} q_i & n_i & 2 \\\\ \\cline{1-1}\\cline{3-3} r_i & s_i & 3\\end{array}'),
    'arrayRepeatedPreambleMathml' => $converter->texToMathMl('\\begin{array}{*{2}{c|}r}p_1 & m_1 & 1 \\\\ p_2 & m_2 & 2\\end{array}'),
    'arrayWidthColumnMathml' => $converter->texToMathMl('\\begin{array}{p{2cm}|m{1.5em}|b{8pt}}p_i & \\text{middle review} & 1 \\\\ q_i & n_i & 2\\end{array}'),
    'arrayHookMathml' => $converter->texToMathMl('\\begin{array}{>{\\text{src}}l<{\\hspace{.25em}}@{\\,}c}p_i & m_i \\\\ q_i & n_i\\end{array}'),
    'arrayMulticolumnMathml' => $converter->texToMathMl('\\begin{array}{lcr}p_i & \\multicolumn{2}{|c|}{m_i + q_i} \\\\ a & b & c\\end{array}'),
    'notRelationMathml' => $converter->texToMathMl('p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + \\not\\alpha_i'),
    'bracedNotRelationMathml' => $converter->texToMathMl('x \\not{\\in} S + y \\not{=} z + q \\not{\\leqslant} r + u \\not\\geqslant v'),
    'primeMathml' => $converter->texToMathMl("f'(x) + g''_i + h_i''' + r'''' + s'''''_j + \\partial^\\prime f + y^\\backprime"),
    'accentAliasMathml' => $converter->texToMathMl('\\acute{x} + \\grave{y} + \\breve{z} + \\check{a} + \\mathring{A}_0 + \\widetilde{mn}'),
    'extendedAccentAliasMathml' => $converter->texToMathMl('\\dddot{x_i} + \\ddddot{y} + \\DDDot z + \\utilde{x_i} + \\wideutilde{mn}'),
    'aboveBelowMathml' => $converter->texToMathMl('\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}'),
    'combinedOverUnderMathml' => $converter->texToMathMl('\\overunderset{\\text{publish}}{\\operatorname{draft}}{p_i} + \\underoverset{0}{\\infty}{\\lim}_{n \\to \\infty} a_n'),
    'tokenBraceWrapperMathml' => $converter->texToMathMl('\\overbrace x_i^n + \\underbrace y_j + \\overbracket x^2 + \\underbracket y_0'),
    'parenGroupWrapperMathml' => $converter->texToMathMl('\\overparen{p_i + m_i}^{\\text{review}} + \\underparen{q_i}_{0} + \\overgroup{x+y} + \\undergroup{z}'),
    'buildrelMathml' => $converter->texToMathMl('\\buildrel{\\text{def}}\\over= + A \\buildrel{\\operatorname{iso}}\\over\\longrightarrow B'),
    'infixFractionMathml' => $converter->texToMathMl('{a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z} + {n \\bangle k}'),
    'withDelimsFractionMathml' => $converter->texToMathMl('{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}'),
    'colorPhantomCancelMathml' => $converter->texToMathMl('\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}'),
    'colorDeclarationMathml' => $converter->texToMathMl('\\color{reviewblue} p_i + m_i + \\frac{a}{b}'),
    'colorTexTokenMathml' => $converter->texToMathMl('\\textcolor{red}x_i + \\textcolor[HTML]{336699}\\operatorname{media} + \\textcolor[rgb]{0.2,0.4,0.6}p_i + \\color[RGB]{51,102,153}\\frac12'),
    'xcolorModelMathml' => $converter->texToMathMl('\\textcolor[HTML]{336699}{\\operatorname{media}} + \\textcolor[rgb]{0.2,0.4,0.6}{p_i} + \\color[gray]{.5} m_i + q_i'),
    'colorBoxMathml' => $converter->texToMathMl('\\colorbox{yellow}{p_i + m_i} + \\colorbox[HTML]{fff9cc}{\\operatorname{media}} + \\fcolorbox{red}{yellow}{q_i} + \\fcolorbox[RGB]{51,102,153}{255,249,204}{\\frac{a}{b}}'),
    'tokenColorBoxCancelMathml' => $converter->texToMathMl('\\colorbox{yellow}x_i + \\fcolorbox{red}{yellow}q_i + \\cancelto0r_i + \\cancelto\\alpha\\frac12'),
    'boxedMathml' => $converter->texToMathMl('\\boxed{p_i + m_i} + \\boxed{\\frac{a}{b}}_j'),
    'smashOverlapMathml' => $converter->texToMathMl('\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i} + \\mathllap{L_i} + \\mathrlap{R_i} + \\mathclap{x+y}'),
    'tokenLayoutWrapperMathml' => $converter->texToMathMl('\\smash x_i + \\smash[t] y^2 + \\mathllap L_i + \\mathrlap R + \\clap C'),
    'mathVariantMathml' => $converter->texToMathMl('\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i'),
    'mathAlphanumericMathml' => $converter->texToMathMl('\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}'),
    'mathAlphabetAliasMathml' => $converter->texToMathMl('\\mathup{x} + \\symbf{A1} + \\bm{\\alpha_i} + \\mathds{R2} + \\mathbfit{Az} + \\mathbfsfup{R2} + \\mathbfsfit{Az} + \\mathbfscr{F} + \\mathbfcal{L} + \\mathbffrak{g} + \\mathsfit{n}'),
    'mathAlphabetGreekAliasMathml' => $converter->texToMathMl('\\bm{\\alpha_i} + \\mathbfit{\\Gamma\\alpha} + \\mathbfsfup{\\Theta\\beta} + \\mathbfsfit{\\Omega\\omega}'),
    'substackMathml' => $converter->texToMathMl('\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x > 0}} f(x)'),
    'prescriptMathml' => $converter->texToMathMl('\\prescript{14}{6}{C} + \\prescript{\\text{review}}{}{p_i} + \\prescript{}{L}{\\operatorname{score}}_j'),
    'operatorLimitsMathml' => $converter->texToMathMl('\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\int\\nolimits_{0}^{1} g(x) dx'),
    'largeOperatorAliasMathml' => $converter->texToMathMl('\\bigcup_{i=1}^{n} A_i + \\bigcap_{j} B_j + \\coprod\\limits_{k=0}^{m} C_k + \\iint_D f(x,y) dx dy + \\bigoplus_i G_i'),
    'starredOperatorLimitsMathml' => $converter->texToMathMl('\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j'),
    'unbracedOperatorNameMathml' => $converter->texToMathMl('\\operatorname\\alpha_i + \\operatorname*\\max_{j}^{n} p_j', true),
    'moduloMathml' => $converter->texToMathMl('a \\mod n + b \\bmod m_i + x \\pmod {n+1} + y \\pod m_i'),
    'mathClassMathml' => $converter->texToMathMl('\\mathop{\\operatorname{argmax}}\\limits_{p_i \\in P}^{\\text{draft}} f(p_i) + a \\mathrel{\\approx} b + x \\mathbin{\\cdot} y + \\mathopen{[}q_i\\mathclose{]} + f\\mathpunct{,}g'),
    'amsEnvironmentMathml' => $converter->texToMathMl('\\begin{align}f(p_i) &= m_i \\\\ g(p_i) &= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &= \\sum_{i=1}^{n} p_i \\\\ &= \\frac{a}{b}\\end{split}'),
    'alignedAtMathml' => $converter->texToMathMl('\\begin{alignedat}{2}p_i &= m_i & a_i &= b_i \\\\ x &= y & u &= v\\end{alignedat}'),
    'intertextMathml' => $converter->texToMathMl('\\begin{align}p_i &= m_i \\\\ \\intertext{review \\& media} x_i &= y_i \\\\ \\shortintertext{compact review} u_i &= v_i\\end{align}', true),
    'optionalAmsPlacementMathml' => $converter->texToMathMl('\\begin{aligned}[t]p_i &= m_i \\\\ x &= y\\end{aligned} + \\begin{gathered}[b]u+v \\\\ w\\end{gathered} + \\begin{alignedat}[c]{2}a &= b & c &= d\\end{alignedat}'),
    'flalignMathml' => $converter->texToMathMl('\\begin{flalign}\\text{source} && p_i &= m_i && \\text{review} \\\\ \\text{target} && x_i &= y_i \\tag{WP-F}\\end{flalign}', true),
    'eqnarrayMathml' => $converter->texToMathMl('\\begin{eqnarray}p_i &=& m_i \\\\ x_i &=& y_i \\tag{WP-E}\\end{eqnarray}', true),
    'rowSpacingMathml' => $converter->texToMathMl('\\begin{aligned}a_i &= b_i \\\\[.5em] c_i &= d_i\\end{aligned}', true),
    'multlineMathml' => $converter->texToMathMl('\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline} + \\left(\\begin{multlined}u+v \\\\ w\\end{multlined}\\right)'),
    'compactEnvironmentMathml' => $converter->texToMathMl('\\left(\\begin{smallmatrix}p_1 & m_1 \\\\ p_2 & m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i'),
    'mathtoolsCasesMathml' => $converter->texToMathMl('\\begin{dcases}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{dcases} + \\begin{rcases}q_i & q_i \\in Q \\\\ 0 & \\text{otherwise}\\end{rcases} + \\begin{drcases*}r_i & r_i \\in R \\\\ 0 & \\text{otherwise}\\end{drcases*}', true),
    'starredMatrixAliasMathml' => $converter->texToMathMl('\\begin{pmatrix*}p_i & m_i \\\\ q_i & n_i\\end{pmatrix*} + \\begin{cases*}p_i & p_i \\in P \\\\ 0 & \\text{otherwise}\\end{cases*}', true),
    'texMatrixCommandMathml' => $converter->texToMathMl('\\matrix{p_1 & m_1 \\cr p_2 & m_2} + \\pmatrix{a & b \\cr c & d} + \\cases{p_i & p_i \\in P \\cr 0 & \\text{otherwise}}', true),
    'plainAlignmentCommandMathml' => $converter->texToMathMl('\\eqalign{p_i &= m_i \\cr q_i &= n_i} + \\displaylines{p_i + m_i \\cr q_i + n_i}', true),
    'texmathCommandWrapperMathml' => $converter->texToMathMl('\\stackrel{\\text{audit}}{p_i} + \\ensuremath{q_i + r_i} + \\surd{s_i}'),
    'mathChoiceMathml' => $converter->texToMathMl('\\mathchoice{\\text{display branch}}{\\text{text branch}}{\\text{script branch}}{\\text{tiny branch}} + q_i'),
    'siunitxUnitAliasMathml' => $converter->texToMathMl('\\SI{9.81}{\\metre\\per\\second\\squared} + \\si{\\km\\per\\hour} + \\unit{\\joule\\per\\mole\\per\\kelvin}'),
    'siunitxPrefixedUnitAliasMathml' => $converter->texToMathMl('\\si{\\mg\\per\\mL} + \\qty{532}{\\nm} + \\SI{20}{\\MHz} + \\unit{\\kPa} + \\si{\\us}'),
    'siunitxElectricEnergyUnitAliasMathml' => $converter->texToMathMl('\\si{\\mohm\\per\\kohm} + \\qty{12}{\\pV\\per\\uV} + \\SI{3}{\\MN} + \\si{\\meV\\per\\GeV} + \\unit{\\fF\\per\\pF} + \\qty{5}{\\gray\\per\\sievert}'),
    'siunitxExtendedUnitAliasMathml' => $converter->texToMathMl('\\si{\\mHz\\per\\hL} + \\unit{\\TeV\\per\\mmHg} + \\qty{42}{\\becquerel\\per\\candela} + \\si{\\dalton\\per\\tonne} + \\si{\\yocto\\meter\\per\\zetta\\gram}'),
    'equationWrapperMathml' => $converter->texToMathMl('\\begin{equation}r_i + s_i \\label{eq:wrapped-env} \\tag{WP-3}\\end{equation}', true),
    'starredEquationWrapperMathml' => $converter->texToMathMl('\\begin{equation*}\\operatorname{review}(p_i) + \\eqref{eq:wrapped-env}\\end{equation*}', false, [], $equationReferenceLabels),
    'rowTaggedEnvironmentMathml' => $converter->texToMathMl('\\begin{align}p_i &= m_i \\tag{WP-1} \\\\ x_i &= y_i \\label{eq:row-review} \\tag*{review}\\end{align}', true),
    'notagNonumberMathml' => $converter->texToMathMl('\\begin{align}p_i &= m_i \\notag \\\\ x_i &= y_i \\nonumber \\\\ u_i &= v_i\\end{align}', true),
    'spacingMathml' => $converter->texToMathMl('p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\>z'),
    'allowBreakMathml' => $converter->texToMathMl('p_i\\allowbreak + m_i + \\operatorname{slug}\\allowbreak'),
    'commentMathml' => $converter->texToMathMl("p_i % reviewer note with \\badcommand\n+ m_i + \\operatorname{slug}% trailing reviewer note\n"),
    'environmentCommentMathml' => $converter->texToMathMl("\\begin{aligned}p_i &= m_i % hidden & ignored\n\\\\ x_i &= y_i\\end{aligned} + \\begin{array}{cc}a & b % hidden \\\\ no row sep\n\\\\ c & d\\end{array}", true),
    'environmentEndCommentMathml' => $converter->texToMathMl("\\begin{aligned}a_i &= b_i % \\end{aligned} hidden terminator\n\\\\ c_i &= d_i\\end{aligned}", true),
    'explicitSpacingMathml' => $converter->texToMathMl('p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b'),
    'sizedDelimiterMathml' => $converter->texToMathMl('\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/'),
    'delimiterAliasMathml' => $converter->texToMathMl('\\left\\lVert p_i + m_i \\right\\rVert + \\left\\lceil \\frac{x}{y} \\right\\rfloor + \\lbrack q_i \\rbrack'),
    'tortoiseShellDelimiterMathml' => $converter->texToMathMl('\\left\\lbrbrak p_i + m_i \\right\\rbrbrak + \\Bigl\\Lbrbrak q_i \\Bigr\\Rbrbrak'),
    'arrowGroupDelimiterMathml' => $converter->texToMathMl('\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow + \\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache'),
    'middleDelimiterMathml' => $converter->texToMathMl('\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle'),
    'extensibleArrowMathml' => $converter->texToMathMl('\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i'),
    'extensibleArrowAliasMathml' => $converter->texToMathMl('\\xlongequal{\\text{same}} + \\xhookrightarrow[\\text{map}]{f} + \\xtwoheadleftarrow{g} + \\xleftharpoonup{\\text{pull}} + \\xrightharpoondown[low]{high}'),
    'reciprocalHarpoonArrowMathml' => $converter->texToMathMl('\\xrightleftharpoons[\\text{review}]{\\operatorname{publish}} p_i + \\xleftrightharpoons{draft} m_i'),
    'unbracedExtensibleArrowMathml' => $converter->texToMathMl('\\xrightarrow\\alpha p_i + \\xleftarrow[\\text{low}]\\beta m_i + \\xhookrightarrow[map] f q + \\overrightarrow A_i + \\underrightarrow\\operatorname{media}'),
    'taggedEquationMathml' => $converter->texToMathMl('p_i + m_i \\label{eq:review-flow} \\tag{WP-2}', true),
    'equationReferenceMathml' => $converter->texToMathMl('\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}', true),
    'equationReferenceLabels' => $equationReferenceLabels,
    'resolvedEquationReferenceMathml' => $converter->texToMathMl('\\eqref{eq:review-flow} + \\eqref{eq:row-review}', false, [], $equationReferenceLabels),
    'hyperrefMathml' => $converter->texToMathMl('\\hyperref[eq:review-flow]{p_i + m_i} + \\hyperref{q_i}'),
    'hrefUrlMathml' => $converter->texToMathMl('\\href{https://example.test/review}{p_i + m_i} + \\url{mailto:reviewer@example.test}'),
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

    if (
        str_contains($summary['nestedMacroMathml'], '<mi>\\wpnestedscore</mi>')
        || str_contains($summary['nestedMacroMathml'], '<mi>\\wpfilter</mi>')
        || !str_contains($summary['nestedMacroMathml'], '<msub><mi>nested review</mi><msub><mi>p</mi><mi>i</mi></msub></msub>')
        || !str_contains($summary['nestedMacroMathml'], '<mo fence="true" stretchy="true">⟨</mo><msub><mi>p</mi><mi>i</mi></msub><mo>,</mo><msub><mi>media</mi><msub><mi>m</mi><mi>i</mi></msub></msub><mo fence="true" stretchy="true">⟩</mo>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test left nested balanced macro declaration unexpanded');
    }

    if (str_contains($summary['declaredOperatorMathml'], '<mi>\\wpreviewscore</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test left declared operator macro unexpanded');
    }

    if (str_contains($summary['starredDeclaredOperatorMathml'], '<mi>\\wpargreview</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test left starred declared operator macro unexpanded');
    }

    if (str_contains($summary['declaredPairedDelimiterMathml'], '<mi>\\wpabs</mi>') || !str_contains($summary['declaredPairedDelimiterMathml'], '<mo fence="true" stretchy="true">|</mo>')) {
        throw new RuntimeException('Math TeX handoff self-test left declared paired delimiter macro unexpanded');
    }

    if (
        str_contains($summary['declaredPairedDelimiterSizedMathml'], '<mi>\\wpabs</mi>')
        || str_contains($summary['declaredPairedDelimiterSizedMathml'], '<mo>*</mo>')
        || str_contains($summary['declaredPairedDelimiterSizedMathml'], '<mo>[</mo>')
        || !str_contains($summary['declaredPairedDelimiterSizedMathml'], 'minsize="1.8em" maxsize="1.8em"')
        || !str_contains($summary['declaredPairedDelimiterSizedMathml'], 'minsize="2.4em" maxsize="2.4em"')
    ) {
        throw new RuntimeException('Math TeX handoff self-test left declared paired delimiter star or size syntax unexpanded');
    }

    if (
        str_contains($summary['declaredPairedDelimiterXMathml'], '<mi>\\wpbetween</mi>')
        || str_contains($summary['declaredPairedDelimiterXMathml'], '<mo>[</mo>')
        || !str_contains($summary['declaredPairedDelimiterXMathml'], '<mo fence="true" stretchy="true">⟨</mo>')
        || !str_contains($summary['declaredPairedDelimiterXMathml'], 'minsize="1.8em" maxsize="1.8em"')
    ) {
        throw new RuntimeException('Math TeX handoff self-test left declared paired delimiter X syntax unexpanded');
    }

    if (
        str_contains($summary['declaredPairedDelimiterXppMathml'], '<mi>\\wprelated</mi>')
        || str_contains($summary['declaredPairedDelimiterXppMathml'], '<mo>[</mo>')
        || !str_contains($summary['declaredPairedDelimiterXppMathml'], '<mi>α</mi><mspace width="0.1667em"></mspace><mo fence="true" stretchy="true">[</mo>')
        || !str_contains($summary['declaredPairedDelimiterXppMathml'], '<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">]</mo><mspace width="0.1667em"></mspace><mi>ω</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test left declared paired delimiter XPP prefix/suffix syntax unexpanded');
    }

    if (
        str_contains($summary['textTokenAliasMathml'], '<mi>\\textbf</mi>')
        || str_contains($summary['textTokenAliasMathml'], '<mi>\\mbox</mi>')
        || !str_contains($summary['textTokenAliasMathml'], '<msub><mstyle mathvariant="bold"><mtext>x</mtext></mstyle><mi>i</mi></msub>')
        || !str_contains($summary['textTokenAliasMathml'], '<mstyle mathvariant="italic"><mtext>%</mtext></mstyle><mo>+</mo><mtext>~</mtext><mo>+</mo><mstyle mathvariant="monospace"><mtext>&amp;</mtext></mstyle>')
        || !str_contains($summary['textTokenAliasMathml'], '<mstyle mathvariant="normal"><mtext>TeX</mtext></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mtext>…</mtext></mstyle>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map unbraced text-mode token arguments');
    }

    if (
        str_contains($summary['escapedSpecialSymbolMathml'], '<mi>\\#</mi>')
        || str_contains($summary['escapedSpecialSymbolMathml'], '<mi>\\&amp;</mi>')
        || str_contains($summary['escapedSpecialSymbolMathml'], '<mi>\\textbackslash</mi>')
        || !str_contains($summary['escapedSpecialSymbolMathml'], '<mo>{</mo><msub><mi>p</mi><mi>i</mi></msub><mo>}</mo><mo>+</mo><mi>a</mi><mo>#</mo><mi>b</mi>')
        || !str_contains($summary['escapedSpecialSymbolMathml'], '<mi>c</mi><mo>&amp;</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>$</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>%</mo><mi>h</mi><mo>+</mo><mi>i</mi><mo>_</mo><mi>j</mi><mo>+</mo><mo>\\</mo>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map escaped special symbols');
    }

    if (str_contains($summary['allowBreakMathml'], '<mi>\\allowbreak</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test emitted allowbreak as a literal identifier');
    }

    if (str_contains($summary['commentMathml'], '<mo>%</mo>') || str_contains($summary['commentMathml'], '<mi>\\badcommand</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test emitted TeX comment content as rendered MathML');
    }

    if (
        str_contains($summary['environmentCommentMathml'], '<mi>h</mi><mi>i</mi><mi>d</mi><mi>d</mi><mi>e</mi><mi>n</mi>')
        || str_contains($summary['environmentCommentMathml'], '<mi>i</mi><mi>g</mi><mi>n</mi><mi>o</mi><mi>r</mi><mi>e</mi><mi>d</mi>')
        || str_contains($summary['environmentCommentMathml'], '<mi>n</mi><mi>o</mi><mi>r</mi><mi>o</mi><mi>w</mi><mi>s</mi><mi>e</mi><mi>p</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test emitted environment comment content as rendered MathML');
    }

    if (
        !str_contains($summary['environmentEndCommentMathml'], '<mtable columnalign="right left"><mtr><mtd><msub><mi>a</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>c</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>d</mi><mi>i</mi></msub></mtd></mtr></mtable>')
        || str_contains((string) strstr($summary['environmentEndCommentMathml'], '<annotation', true), '<mi>h</mi><mi>i</mi><mi>d</mi><mi>d</mi><mi>e</mi><mi>n</mi>')
        || str_contains((string) strstr($summary['environmentEndCommentMathml'], '<annotation', true), '<mi>t</mi><mi>e</mi><mi>r</mi><mi>m</mi><mi>i</mi><mi>n</mi><mi>a</mi><mi>t</mi><mi>o</mi><mi>r</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test let a commented environment terminator close the environment');
    }

    if (
        str_contains($summary['mathAlphabetAliasMathml'], '<mi>\\bm</mi>')
        || str_contains($summary['mathAlphabetAliasMathml'], '<mi>\\mathbfit</mi>')
        || str_contains($summary['mathAlphabetGreekAliasMathml'], '<mi>\\mathbfsfit</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test emitted texmath math alphabet aliases as literal identifiers');
    }

    if (str_contains($summary['hyperrefMathml'], '<mi>\\hyperref</mi>') || str_contains($summary['hyperrefMathml'], '<mo>[</mo><mi>e</mi><mi>q</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test emitted hyperref target syntax as rendered MathML');
    }

    if (
        str_contains($summary['hrefUrlMathml'], '<mi>\\href</mi>')
        || str_contains($summary['hrefUrlMathml'], '<mi>\\url</mi>')
        || !str_contains($summary['hrefUrlMathml'], '<mrow href="https://example.test/review"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mrow>')
        || !str_contains($summary['hrefUrlMathml'], '<mtext href="mailto:reviewer@example.test">mailto:reviewer@example.test</mtext>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map href/url commands to linked MathML');
    }

    if (str_contains($summary['mathChoiceMathml'], '<mi>\\mathchoice</mi>') || !str_contains($summary['mathChoiceMathml'], '<mtext>text branch</mtext>')) {
        throw new RuntimeException('Math TeX handoff self-test did not select the inline mathchoice branch');
    }

    if (
        str_contains($summary['siunitxUnitAliasMathml'], '<mi>\\km</mi>')
        || str_contains($summary['siunitxUnitAliasMathml'], '<mi>\\joule</mi>')
        || str_contains($summary['siunitxUnitAliasMathml'], '<mi>\\kelvin</mi>')
        || !str_contains($summary['siunitxUnitAliasMathml'], '<mrow><mn>9.81</mn><mspace width="0.2222em"></mspace><mrow><mtext>m</mtext><mtext>/</mtext><msup><mtext>s</mtext><mn>2</mn></msup></mrow></mrow>')
        || !str_contains($summary['siunitxUnitAliasMathml'], '<mrow><mtext>km</mtext><mtext>/</mtext><mtext>h</mtext></mrow>')
        || !str_contains($summary['siunitxUnitAliasMathml'], '<mrow><mtext>J</mtext><mtext>/</mtext><mtext>mol</mtext><mtext>/</mtext><mtext>K</mtext></mrow>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map siunitx unit aliases');
    }

    if (
        str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mi>\\mg</mi>')
        || str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mi>\\MHz</mi>')
        || str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mi>\\us</mi>')
        || !str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mrow><mtext>mg</mtext><mtext>/</mtext><mtext>mL</mtext></mrow>')
        || !str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mrow><mn>532</mn><mspace width="0.2222em"></mspace><mtext>nm</mtext></mrow>')
        || !str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mrow><mn>20</mn><mspace width="0.2222em"></mspace><mtext>MHz</mtext></mrow>')
        || !str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mtext>kPa</mtext>')
        || !str_contains($summary['siunitxPrefixedUnitAliasMathml'], '<mtext>μs</mtext>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map prefixed siunitx unit aliases');
    }

    if (
        str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mi>\\mohm</mi>')
        || str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mi>\\pV</mi>')
        || str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mi>\\gray</mi>')
        || !str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mrow><mtext>mΩ</mtext><mtext>/</mtext><mtext>kΩ</mtext></mrow>')
        || !str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mrow><mn>12</mn><mspace width="0.2222em"></mspace><mrow><mtext>pV</mtext><mtext>/</mtext><mtext>μV</mtext></mrow></mrow>')
        || !str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mrow><mn>3</mn><mspace width="0.2222em"></mspace><mtext>MN</mtext></mrow>')
        || !str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mrow><mtext>meV</mtext><mtext>/</mtext><mtext>GeV</mtext></mrow>')
        || !str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mrow><mtext>fF</mtext><mtext>/</mtext><mtext>pF</mtext></mrow>')
        || !str_contains($summary['siunitxElectricEnergyUnitAliasMathml'], '<mrow><mn>5</mn><mspace width="0.2222em"></mspace><mrow><mtext>Gy</mtext><mtext>/</mtext><mtext>Sv</mtext></mrow></mrow>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map electric and energy siunitx unit aliases');
    }

    if (
        str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mi>\\mHz</mi>')
        || str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mi>\\TeV</mi>')
        || str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mi>\\becquerel</mi>')
        || str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mi>\\dalton</mi>')
        || str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mi>\\yocto</mi>')
        || !str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mrow><mtext>mHz</mtext><mtext>/</mtext><mtext>hL</mtext></mrow>')
        || !str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mrow><mtext>TeV</mtext><mtext>/</mtext><mtext>mmHg</mtext></mrow>')
        || !str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mrow><mn>42</mn><mspace width="0.2222em"></mspace><mrow><mtext>Bq</mtext><mtext>/</mtext><mtext>cd</mtext></mrow></mrow>')
        || !str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mrow><mtext>Da</mtext><mtext>/</mtext><mtext>t</mtext></mrow>')
        || !str_contains($summary['siunitxExtendedUnitAliasMathml'], '<mrow><mtext>y</mtext><mtext>m</mtext><mtext>/</mtext><mtext>Z</mtext><mtext>g</mtext></mrow>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map extended siunitx unit aliases');
    }

    if (
        str_contains($summary['buildrelMathml'], '<mi>\\buildrel</mi>')
        || str_contains($summary['buildrelMathml'], '<mfrac><mrow><mi>\\buildrel</mi>')
        || !str_contains($summary['buildrelMathml'], '<mover><mo>=</mo><mtext>def</mtext></mover><mo>+</mo><mi>A</mi><mover><mo>→</mo><mi>iso</mi></mover><mi>B</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map plain TeX buildrel relations');
    }

    if (
        str_contains($summary['combinedOverUnderMathml'], '<mi>\\overunderset</mi>')
        || str_contains($summary['combinedOverUnderMathml'], '<mi>\\underoverset</mi>')
        || !str_contains($summary['combinedOverUnderMathml'], '<munderover><msub><mi>p</mi><mi>i</mi></msub><mi>draft</mi><mtext>publish</mtext></munderover>')
        || !str_contains($summary['combinedOverUnderMathml'], '<msub><munderover><mo>lim</mo><mn>0</mn><mi>∞</mi></munderover><mrow><mi>n</mi><mo>→</mo><mi>∞</mi></mrow></msub>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map combined over-under wrappers');
    }

    if (
        str_contains($summary['texTokenArgumentMathml'], '<mi>\\boxed</mi>')
        || str_contains($summary['texTokenArgumentMathml'], '<mi>\\cancel</mi>')
        || str_contains($summary['texTokenArgumentMathml'], '<mi>\\overset</mi>')
        || !str_contains($summary['texTokenArgumentMathml'], '<mfrac><mn>1</mn><mn>2</mn></mfrac>')
        || !str_contains($summary['texTokenArgumentMathml'], '<msub><menclose notation="box"><mi>s</mi></menclose><mi>i</mi></msub>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map unbraced TeX token arguments');
    }

    if (
        str_contains($summary['colorDeclarationMathml'], '<mi>\\color</mi>')
        || !str_contains($summary['colorDeclarationMathml'], '<mstyle mathcolor="reviewblue"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mrow></mstyle>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not scope color declaration content');
    }

    if (
        str_contains($summary['xcolorModelMathml'], '<mo>[</mo>')
        || !str_contains($summary['xcolorModelMathml'], '<mstyle mathcolor="#336699"><mi>media</mi></mstyle>')
        || !str_contains($summary['xcolorModelMathml'], '<mstyle mathcolor="#336699"><msub><mi>p</mi><mi>i</mi></msub></mstyle>')
        || !str_contains($summary['xcolorModelMathml'], '<mstyle mathcolor="#808080"><mrow><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><msub><mi>q</mi><mi>i</mi></msub></mrow></mstyle>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map bounded xcolor model arguments');
    }

    if (
        str_contains($summary['colorTexTokenMathml'], '<mi>\\textcolor</mi>')
        || str_contains($summary['colorTexTokenMathml'], '<mo>[</mo>')
        || !str_contains($summary['colorTexTokenMathml'], '<msub><mstyle mathcolor="red"><mi>x</mi></mstyle><mi>i</mi></msub>')
        || !str_contains($summary['colorTexTokenMathml'], '<mstyle mathcolor="#336699"><mi>media</mi></mstyle>')
        || !str_contains($summary['colorTexTokenMathml'], '<msub><mstyle mathcolor="#336699"><mi>p</mi></mstyle><mi>i</mi></msub>')
        || !str_contains($summary['colorTexTokenMathml'], '<mstyle mathcolor="#336699"><mfrac><mn>1</mn><mn>2</mn></mfrac></mstyle>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map bounded color TeX-token arguments');
    }

    if (
        str_contains($summary['colorBoxMathml'], '<mi>\\colorbox</mi>')
        || str_contains($summary['colorBoxMathml'], '<mi>\\fcolorbox</mi>')
        || !str_contains($summary['colorBoxMathml'], '<mstyle mathbackground="yellow"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mstyle>')
        || !str_contains($summary['colorBoxMathml'], '<mstyle mathbackground="#fff9cc"><mi>media</mi></mstyle>')
        || !str_contains($summary['colorBoxMathml'], '<menclose notation="box" mathbackground="yellow" data-tex-framecolor="red"><msub><mi>q</mi><mi>i</mi></msub></menclose>')
        || !str_contains($summary['colorBoxMathml'], '<menclose notation="box" mathbackground="#fff9cc" data-tex-framecolor="#336699"><mfrac><mi>a</mi><mi>b</mi></mfrac></menclose>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map bounded colorbox/fcolorbox metadata');
    }

    if (
        str_contains($summary['tokenColorBoxCancelMathml'], '<mi>\\colorbox</mi>')
        || str_contains($summary['tokenColorBoxCancelMathml'], '<mi>\\fcolorbox</mi>')
        || str_contains($summary['tokenColorBoxCancelMathml'], '<mi>\\cancelto</mi>')
        || !str_contains($summary['tokenColorBoxCancelMathml'], '<msub><mstyle mathbackground="yellow"><mi>x</mi></mstyle><mi>i</mi></msub>')
        || !str_contains($summary['tokenColorBoxCancelMathml'], '<msub><menclose notation="box" mathbackground="yellow" data-tex-framecolor="red"><mi>q</mi></menclose><mi>i</mi></msub>')
        || !str_contains($summary['tokenColorBoxCancelMathml'], '<msub><mover><menclose notation="updiagonalstrike"><mi>r</mi></menclose><mn>0</mn></mover><mi>i</mi></msub>')
        || !str_contains($summary['tokenColorBoxCancelMathml'], '<mover><menclose notation="updiagonalstrike"><mfrac><mn>1</mn><mn>2</mn></mfrac></menclose><mi>α</mi></mover>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map bounded color and cancel box TeX-token arguments');
    }

    if (
        !str_contains($summary['rowSpacingMathml'], 'rowspacing=".5em"')
        || !str_contains($summary['rowSpacingMathml'], 'data-tex-rowspacing="after-row-1:.5em"')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not preserve optional row-spacing metadata');
    }

    if (
        str_contains($summary['intertextMathml'], '<mi>\\intertext</mi>')
        || str_contains($summary['intertextMathml'], '<mi>\\shortintertext</mi>')
        || !str_contains($summary['intertextMathml'], '<mtr data-tex-intertext="normal"><mtd columnspan="2"><mtext>review &amp; media</mtext></mtd></mtr>')
        || !str_contains($summary['intertextMathml'], '<mtr data-tex-intertext="short"><mtd columnspan="2"><mtext>compact review</mtext></mtd></mtr>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not preserve AMS intertext rows');
    }

    if (
        str_contains($summary['mathtoolsCasesMathml'], '<mi>\\dcases</mi>')
        || str_contains($summary['mathtoolsCasesMathml'], '<mi>\\rcases</mi>')
        || str_contains($summary['mathtoolsCasesMathml'], '<mi>\\drcases</mi>')
        || str_contains($summary['mathtoolsCasesMathml'], '<mo>*</mo>')
        || !str_contains($summary['mathtoolsCasesMathml'], '<mo fence="true" stretchy="true">{</mo><mstyle displaystyle="true"><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable></mstyle>')
        || !str_contains($summary['mathtoolsCasesMathml'], '<mtable columnalign="left left"><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>q</mi><mi>i</mi></msub><mo>∈</mo><mi>Q</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable><mo fence="true" stretchy="true">}</mo>')
        || !str_contains($summary['mathtoolsCasesMathml'], '<mstyle displaystyle="true"><mtable columnalign="left left"><mtr><mtd><msub><mi>r</mi><mi>i</mi></msub></mtd><mtd><msub><mi>r</mi><mi>i</mi></msub><mo>∈</mo><mi>R</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable></mstyle><mo fence="true" stretchy="true">}</mo>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map mathtools cases environments');
    }

    if (
        str_contains($summary['starredMatrixAliasMathml'], '<mo>*</mo>')
        || str_contains($summary['starredMatrixAliasMathml'], '<mi>*</mi>')
        || !str_contains($summary['starredMatrixAliasMathml'], '<annotation encoding="application/x-tex">\\begin{pmatrix*}p_i &amp; m_i \\\\ q_i &amp; n_i\\end{pmatrix*} + \\begin{cases*}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases*}</annotation>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not keep starred matrix aliases metadata-only');
    }

    if (
        str_contains($summary['texMatrixCommandMathml'], '<mi>\\matrix</mi>')
        || str_contains($summary['texMatrixCommandMathml'], '<mi>\\pmatrix</mi>')
        || str_contains($summary['texMatrixCommandMathml'], '<mi>\\cases</mi>')
        || str_contains($summary['texMatrixCommandMathml'], '<mi>\\cr</mi>')
        || !str_contains($summary['texMatrixCommandMathml'], '<mtable><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable>')
        || !str_contains($summary['texMatrixCommandMathml'], '<mo fence="true" stretchy="true">(</mo><mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>')
        || !str_contains($summary['texMatrixCommandMathml'], '<mo fence="true" stretchy="true">{</mo><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable>')
        || !str_contains($summary['texMatrixCommandMathml'], '<annotation encoding="application/x-tex">\\matrix{p_1 &amp; m_1 \\cr p_2 &amp; m_2} + \\pmatrix{a &amp; b \\cr c &amp; d} + \\cases{p_i &amp; p_i \\in P \\cr 0 &amp; \\text{otherwise}}</annotation>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map plain TeX matrix commands');
    }

    if (
        str_contains($summary['plainAlignmentCommandMathml'], '<mi>\\eqalign</mi>')
        || str_contains($summary['plainAlignmentCommandMathml'], '<mi>\\displaylines</mi>')
        || str_contains($summary['plainAlignmentCommandMathml'], '<mi>\\cr</mi>')
        || !str_contains($summary['plainAlignmentCommandMathml'], '<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>n</mi><mi>i</mi></msub></mtd></mtr></mtable>')
        || !str_contains($summary['plainAlignmentCommandMathml'], '<mtable columnalign="center"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub><mo>+</mo><msub><mi>n</mi><mi>i</mi></msub></mtd></mtr></mtable>')
        || !str_contains($summary['plainAlignmentCommandMathml'], '<annotation encoding="application/x-tex">\\eqalign{p_i &amp;= m_i \\cr q_i &amp;= n_i} + \\displaylines{p_i + m_i \\cr q_i + n_i}</annotation>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map plain TeX alignment commands');
    }

    if (str_contains($summary['dotRelationSymbolAliasMathml'], '<mi>\\ldots</mi>') || str_contains($summary['dotRelationSymbolAliasMathml'], '<mi>\\cong</mi>')) {
        throw new RuntimeException('Math TeX handoff self-test emitted dot/relation symbol aliases as literal identifiers');
    }

    if (
        str_contains($summary['operatorRelationAliasMathml'], '<mi>\\oplus</mi>')
        || str_contains($summary['operatorRelationAliasMathml'], '<mi>\\asymp</mi>')
        || !str_contains($summary['operatorRelationAliasMathml'], '<mo>⊕</mo>')
        || !str_contains($summary['operatorRelationAliasMathml'], '<mo>≍</mo>')
        || !str_contains($summary['operatorRelationAliasMathml'], '<mo>⊢</mo>')
        || !str_contains($summary['operatorRelationAliasMathml'], '<mo>⋈</mo>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map operator/relation aliases');
    }

    if (
        str_contains($summary['relationHarpoonAliasMathml'], '<mi>\\prec</mi>')
        || str_contains($summary['relationHarpoonAliasMathml'], '<mi>\\nearrow</mi>')
        || str_contains($summary['relationHarpoonAliasMathml'], '<mi>\\because</mi>')
        || !str_contains($summary['relationHarpoonAliasMathml'], '<mi>A</mi><mo>≺</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>≻</mo><mi>D</mi><mo>+</mo><mi>E</mi><mo>≪</mo><mi>F</mi><mo>+</mo><mi>G</mi><mo>≫</mo><mi>H</mi>')
        || !str_contains($summary['relationHarpoonAliasMathml'], '<mi>x</mi><mo>↗</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>↘</mo><mi>b</mi><mo>+</mo><mi>L</mi><mo>↼</mo><mi>M</mi><mo>+</mo><mi>N</mi><mo>⇁</mo><mi>O</mi>')
        || !str_contains($summary['relationHarpoonAliasMathml'], '<mi>P</mi><mo>⇌</mo><mi>Q</mi><mo>+</mo><mi>p</mi><mo>∵</mo><mi>q</mi><mo>+</mo><mi>f</mi><mo>⊸</mo><mi>g</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map relation/harpoon aliases');
    }

    if (
        str_contains($summary['symbolOverrideAliasMathml'], '<mi>\\hbar</mi>')
        || str_contains($summary['symbolOverrideAliasMathml'], '<mi>\\dag</mi>')
        || str_contains($summary['symbolOverrideAliasMathml'], '<mi>\\longmapsto</mi>')
        || str_contains($summary['symbolOverrideAliasMathml'], '<mi>\\blacklozenge</mi>')
        || !str_contains($summary['symbolOverrideAliasMathml'], '<mi>arg</mi><mi>z</mi><mo>+</mo><mi>ℏ</mi><mi>ω</mi><mo>+</mo><mi>ϝ</mi><mo>+</mo><mo>⌀</mo>')
        || !str_contains($summary['symbolOverrideAliasMathml'], '<mi>a</mi><mo>†</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>‡</mo><mi>d</mi>')
        || !str_contains($summary['symbolOverrideAliasMathml'], '<mi>A</mi><mo>⊲</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⊵</mo><mi>D</mi><mo>+</mo><mi>M</mi><mo>⟼</mo><mi>N</mi><mo>+</mo><mo>⬧</mo>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map symbol override aliases');
    }

    if (
        str_contains($summary['extendedRelationAliasMathml'], '<mi>\\beth</mi>')
        || str_contains($summary['extendedRelationAliasMathml'], '<mi>\\leqq</mi>')
        || str_contains($summary['extendedRelationAliasMathml'], '<mi>\\doteq</mi>')
        || str_contains($summary['extendedRelationAliasMathml'], '<mi>\\nsubseteq</mi>')
        || !str_contains($summary['extendedRelationAliasMathml'], '<mi>ℶ</mi><mo>+</mo><mi>ℷ</mi><mo>+</mo><mi>ℸ</mi><mo>+</mo><mi>a</mi><mo>≦</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>≧</mo><mi>d</mi><mo>+</mo><mi>x</mi><mo>≐</mo><mi>y</mi>')
        || !str_contains($summary['extendedRelationAliasMathml'], '<mi>P</mi><mo>⊈</mo><mi>Q</mi><mo>+</mo><mi>u</mi><mo>∦</mo><mi>v</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map extended relation aliases');
    }

    if (
        str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>\\lneq</mi>')
        || str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>\\precapprox</mi>')
        || str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>\\nvdash</mi>')
        || str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>\\varpropto</mi>')
        || str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>\\blacktriangle</mi>')
        || !str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>a</mi><mo>⪇</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⪈</mo><mi>d</mi>')
        || !str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>p</mi><mo>⪷</mo><mi>q</mi><mo>+</mo><mi>r</mi><mo>⪸</mo><mi>s</mi>')
        || !str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>P</mi><mo>⊬</mo><mi>Q</mi><mo>+</mo><mi>R</mi><mo>⊭</mo><mi>S</mi>')
        || !str_contains($summary['generatedSymbolMapRelationAliasMathml'], '<mi>x</mi><mo>∝</mo><mi>y</mi><mo>+</mo><mi>A</mi><mo>∖</mo><mi>B</mi><mo>+</mo><mo>▴</mo>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map generated symbol-map relation aliases');
    }

    if (
        str_contains($summary['negativeApproxRelationAliasMathml'], '<mi>\\approxeq</mi>')
        || str_contains($summary['negativeApproxRelationAliasMathml'], '<mi>\\napprox</mi>')
        || str_contains($summary['negativeApproxRelationAliasMathml'], '<mi>\\ncong</mi>')
        || !str_contains($summary['negativeApproxRelationAliasMathml'], '<mi>x</mi><mo>≊</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>≉</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>≇</mo><mi>d</mi>')
        || !str_contains($summary['negativeApproxRelationAliasMathml'], '<annotation encoding="application/x-tex">x \\approxeq y + a \\napprox b + c \\ncong d</annotation>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map negative approximate relation aliases');
    }

    if (
        str_contains($summary['comparisonRelationAliasMathml'], '<mi>\\nless</mi>')
        || str_contains($summary['comparisonRelationAliasMathml'], '<mi>\\ngtr</mi>')
        || str_contains($summary['comparisonRelationAliasMathml'], '<mi>\\leqgtr</mi>')
        || str_contains($summary['comparisonRelationAliasMathml'], '<mi>\\geqless</mi>')
        || !str_contains($summary['comparisonRelationAliasMathml'], '<mi>x</mi><mo>≮</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>≯</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⋚</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>⋛</mo><mi>f</mi>')
        || !str_contains($summary['comparisonRelationAliasMathml'], '<annotation encoding="application/x-tex">x \\nless y + a \\ngtr b + c \\leqgtr d + e \\geqless f</annotation>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map comparison relation aliases');
    }

    if (
        str_contains($summary['unicodeSymbolMapAliasMathml'], '<mi>\\AC</mi>')
        || str_contains($summary['unicodeSymbolMapAliasMathml'], '<mi>\\twoheadleftarrow</mi>')
        || str_contains($summary['unicodeSymbolMapAliasMathml'], '<mi>\\hookleftarrow</mi>')
        || str_contains($summary['unicodeSymbolMapAliasMathml'], '<mi>\\nleftarrow</mi>')
        || str_contains($summary['unicodeSymbolMapAliasMathml'], '<mi>\\nsubset</mi>')
        || !str_contains($summary['unicodeSymbolMapAliasMathml'], '<mo>⏦</mo><mo>+</mo><mo>↞</mo><mo>+</mo><mo>↩</mo>')
        || !str_contains($summary['unicodeSymbolMapAliasMathml'], '<mi>A</mi><mo>↚</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>↛</mo><mi>D</mi><mo>+</mo><mi>E</mi><mo>↮</mo><mi>F</mi>')
        || !str_contains($summary['unicodeSymbolMapAliasMathml'], '<mi>P</mi><mo>⊄</mo><mi>Q</mi><mo>+</mo><mi>R</mi><mo>⊅</mo><mi>S</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map unicode symbol-map aliases');
    }

    if (
        str_contains($summary['variantGreekUnderbarAliasMathml'], '<mi>\\varGamma</mi>')
        || str_contains($summary['variantGreekUnderbarAliasMathml'], '<mi>\\varrho</mi>')
        || str_contains($summary['variantGreekUnderbarAliasMathml'], '<mi>\\overbar</mi>')
        || str_contains($summary['variantGreekUnderbarAliasMathml'], '<mi>\\underbar</mi>')
        || !str_contains($summary['variantGreekUnderbarAliasMathml'], '<mi>𝛤</mi><mo>+</mo><mi>𝛥</mi><mo>+</mo><msub><mi>𝜚</mi><mi>i</mi></msub><mo>+</mo><mi>𝜍</mi><mo>+</mo><mi>ϒ</mi>')
        || !str_contains($summary['variantGreekUnderbarAliasMathml'], '<mover accent="true"><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mo>¯</mo></mover>')
        || !str_contains($summary['variantGreekUnderbarAliasMathml'], '<munder accentunder="true"><mi>draft</mi><mo>̱</mo></munder>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map variant Greek and underbar aliases');
    }

    if (
        str_contains($summary['extendedAccentAliasMathml'], '<mi>\\dddot</mi>')
        || str_contains($summary['extendedAccentAliasMathml'], '<mi>\\ddddot</mi>')
        || str_contains($summary['extendedAccentAliasMathml'], '<mi>\\DDDot</mi>')
        || str_contains($summary['extendedAccentAliasMathml'], '<mi>\\utilde</mi>')
        || str_contains($summary['extendedAccentAliasMathml'], '<mi>\\wideutilde</mi>')
        || !str_contains($summary['extendedAccentAliasMathml'], '<mover accent="true"><msub><mi>x</mi><mi>i</mi></msub><mo>⃛</mo></mover>')
        || !str_contains($summary['extendedAccentAliasMathml'], '<mover accent="true"><mi>y</mi><mo>⃜</mo></mover>')
        || !str_contains($summary['extendedAccentAliasMathml'], '<munder accentunder="true"><msub><mi>x</mi><mi>i</mi></msub><mo>̰</mo></munder>')
        || !str_contains($summary['extendedAccentAliasMathml'], '<munder accentunder="true"><mrow><mi>m</mi><mi>n</mi></mrow><mo>̰</mo></munder>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map extended dot and undertilde accent aliases');
    }

    if (
        str_contains($summary['largeOperatorAliasMathml'], '<mi>\\bigcup</mi>')
        || str_contains($summary['largeOperatorAliasMathml'], '<mi>\\iint</mi>')
        || !str_contains($summary['largeOperatorAliasMathml'], '<msubsup><mo>⋃</mo>')
        || !str_contains($summary['largeOperatorAliasMathml'], '<msub><mo>∬</mo><mi>D</mi></msub>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map large operator aliases');
    }

    if (
        str_contains($summary['plainRootMathml'], '<mi>\\root</mi>')
        || str_contains($summary['plainRootMathml'], '<mi>\\of</mi>')
        || !str_contains($summary['plainRootMathml'], '<mroot><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mn>3</mn></mroot>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map plain TeX root syntax');
    }

    if (
        str_contains($summary['tokenBraceWrapperMathml'], '<mi>\\overbrace</mi>')
        || str_contains($summary['tokenBraceWrapperMathml'], '<mi>\\underbrace</mi>')
        || str_contains($summary['tokenBraceWrapperMathml'], '<mi>\\overbracket</mi>')
        || str_contains($summary['tokenBraceWrapperMathml'], '<mi>\\underbracket</mi>')
        || !str_contains($summary['tokenBraceWrapperMathml'], '<msubsup><mover><mi>x</mi><mo>⏞</mo></mover><mi>i</mi><mi>n</mi></msubsup>')
        || !str_contains($summary['tokenBraceWrapperMathml'], '<msub><munder><mi>y</mi><mo>⏟</mo></munder><mi>j</mi></msub>')
        || !str_contains($summary['tokenBraceWrapperMathml'], '<msup><mover><mi>x</mi><mo>⎴</mo></mover><mn>2</mn></msup>')
        || !str_contains($summary['tokenBraceWrapperMathml'], '<msub><munder><mi>y</mi><mo>⎵</mo></munder><mn>0</mn></msub>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map unbraced brace and bracket wrappers');
    }

    if (
        str_contains($summary['parenGroupWrapperMathml'], '<mi>\\overparen</mi>')
        || str_contains($summary['parenGroupWrapperMathml'], '<mi>\\underparen</mi>')
        || str_contains($summary['parenGroupWrapperMathml'], '<mi>\\overgroup</mi>')
        || str_contains($summary['parenGroupWrapperMathml'], '<mi>\\undergroup</mi>')
        || !str_contains($summary['parenGroupWrapperMathml'], '<msup><mover><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow><mo>⏜</mo></mover><mtext>review</mtext></msup>')
        || !str_contains($summary['parenGroupWrapperMathml'], '<msub><munder><msub><mi>q</mi><mi>i</mi></msub><mo>⏝</mo></munder><mn>0</mn></msub>')
        || !str_contains($summary['parenGroupWrapperMathml'], '<mover><mrow><mi>x</mi><mo>+</mo><mi>y</mi></mrow><mo>⏠</mo></mover>')
        || !str_contains($summary['parenGroupWrapperMathml'], '<munder><mi>z</mi><mo>⏡</mo></munder>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map over/under parenthesis and group wrappers');
    }

    if (
        str_contains($summary['tokenLayoutWrapperMathml'], '<mi>\\smash</mi>')
        || str_contains($summary['tokenLayoutWrapperMathml'], '<mi>\\mathllap</mi>')
        || str_contains($summary['tokenLayoutWrapperMathml'], '<mi>\\mathrlap</mi>')
        || str_contains($summary['tokenLayoutWrapperMathml'], '<mi>\\clap</mi>')
        || !str_contains($summary['tokenLayoutWrapperMathml'], '<msub><mpadded height="0" depth="0"><mi>x</mi></mpadded><mi>i</mi></msub>')
        || !str_contains($summary['tokenLayoutWrapperMathml'], '<msup><mpadded height="0"><mi>y</mi></mpadded><mn>2</mn></msup>')
        || !str_contains($summary['tokenLayoutWrapperMathml'], '<msub><mpadded width="0" lspace="-1width"><mi>L</mi></mpadded><mi>i</mi></msub>')
        || !str_contains($summary['tokenLayoutWrapperMathml'], '<mpadded width="0"><mi>R</mi></mpadded>')
        || !str_contains($summary['tokenLayoutWrapperMathml'], '<mpadded width="0" lspace="-0.5width"><mi>C</mi></mpadded>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not map unbraced smash and overlap wrappers');
    }

    if (
        str_contains($summary['bracedNotRelationMathml'], '<menclose notation="updiagonalstrike"><mo>')
        || !str_contains($summary['bracedNotRelationMathml'], '<mi>x</mi><mo>∉</mo><mi>S</mi><mo>+</mo><mi>y</mi><mo>≠</mo><mi>z</mi><mo>+</mo><mi>q</mi><mo>≰</mo><mi>r</mi><mo>+</mo><mi>u</mi><mo>≱</mo><mi>v</mi>')
    ) {
        throw new RuntimeException('Math TeX handoff self-test did not canonicalize braced negated relations');
    }

    $mathSymbol = static fn (int $codepoint): string => html_entity_decode('&#x' . strtoupper(dechex($codepoint)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');

    foreach ([
        '<span class="math inline">\\(\\langle post_id,media_id \\rangle\\)</span>',
        '<span class="math inline">\\(\\wpreview{p_i} + \\wpreview[final]{m_i}\\)</span>',
        '<span class="math inline">\\(\\operatorname{review\\,score}_i(p_i)\\)</span>',
        '<span class="math inline">\\(\\operatorname*{arg\\,review}_{p_i \\in P}^{\\text{draft}} f(p_i)\\)</span>',
        '<span class="math inline">\\(\\left\\lvert p_i + m_i \\right\\rvert\\)</span>',
        '<span class="math inline">\\(\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}\\)</span>',
        '<span class="math inline">\\(\\ldots + \\cdots + \\ddots + \\aleph + \\ell + \\Re + \\Im + \\wp + a \\cong b + c \\simeq d + x \\propto y + u \\parallel v + r \\perp s + \\angle x + \\nabla f + \\top + \\bot\\)</span>',
        '<span class="math inline">\\(a \\oplus b + c \\ominus d + x \\asymp y + p \\vdash q + u \\bowtie v\\)</span>',
        '<span class="math inline">\\(a \\dotplus b + c \\boxplus d + e \\boxminus f + A \\sqsubset B + C \\sqsupseteq D + x \\lesssim y + r \\gtrapprox s + p \\Bumpeq q + x \\rightsquigarrow y + m \\nleq n\\)</span>',
        '<span class="math inline">\\(A \\prec B + C \\succ D + E \\ll F + G \\gg H + x \\nearrow y + a \\searrow b + L \\leftharpoonup M + N \\rightharpoondown O + P \\rightleftharpoons Q + p \\because q + f \\multimap g\\)</span>',
        '<span class="math inline">\\(\\arg z + \\hbar\\omega + \\digamma + \\varnothing + a \\dag b + c \\ddag d + A \\lhd B + C \\unrhd D + M \\longmapsto N + \\blacklozenge\\)</span>',
        '<span class="math inline">\\(\\beth + \\gimel + \\daleth + a \\leqq b + c \\geqq d + x \\doteq y + P \\nsubseteq Q + u \\nparallel v\\)</span>',
        '<span class="math inline">\\(x \\approxeq y + a \\napprox b + c \\ncong d\\)</span>',
        '<span class="math inline">\\(\\AC + \\twoheadleftarrow + \\hookleftarrow + A \\nleftarrow B + C \\nrightarrow D + E \\nleftrightarrow F + P \\nsubset Q + R \\nsupset S\\)</span>',
        '<span class="math display">\\[\\sum_{i=1}^{n} \\operatorname{migrate}(p_i) + \\frac{a_1}{\\sqrt{b^2}} + \\sqrt[3]{x_i + y_i} + \\binom{n}{k} + \\tbinom{p_i}{2} + \\dbinom{a+b}{c} + \\dfrac{q_i}{r_i} + \\genfrac{\\langle}{\\rangle}{0pt}{0}{n}{k} + \\widehat{\\operatorname{quality}} + \\vec{v}_i + \\begin{pmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{pmatrix} + \\begin{aligned}x_i &amp;= \\operatorname{score}(p_i) \\\\ y_i &amp;= \\frac{a_i}{b_i}\\end{aligned} + \\begin{array}{l|c|r}\\alpha &amp; \\beta &amp; \\omega \\\\ \\hline 1 &amp; 2 &amp; 3\\end{array} + \\begin{cases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases} + \\forall p_i \\in P \\Rightarrow p_i \\notin \\emptyset + \\alpha \\times \\omega\\]</span>',
        '<span class="math inline">\\(\\root 3 \\of{x_i + y_i} + \\root n+1 \\of{\\frac{a}{b}}\\)</span>',
        '<mroot><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mn>3</mn></mroot><mo>+</mo><mroot><mfrac><mi>a</mi><mi>b</mi></mfrac><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></mroot>',
        '<annotation encoding="application/x-tex">\\root 3 \\of{x_i + y_i} + \\root n+1 \\of{\\frac{a}{b}}</annotation>',
        '<span class="math inline">\\(\\sqrt x_i + \\sqrt[3]y_j + \\frac12 + \\dfrac a b + \\binom n k + \\overset\\alpha q_i + \\underset 0 r_i + \\boxed s_i + \\phantom t_i + \\hphantom u_i + \\vphantom v_i + \\cancel w_i + \\bcancel x + \\xcancel y\\)</span>',
        '<msub><msqrt><mi>x</mi></msqrt><mi>i</mi></msub><mo>+</mo><msub><mroot><mi>y</mi><mn>3</mn></mroot><mi>j</mi></msub><mo>+</mo><mfrac><mn>1</mn><mn>2</mn></mfrac>',
        '<msub><mover><mi>q</mi><mi>α</mi></mover><mi>i</mi></msub><mo>+</mo><msub><munder><mi>r</mi><mn>0</mn></munder><mi>i</mi></msub>',
        '<msub><menclose notation="box"><mi>s</mi></menclose><mi>i</mi></msub><mo>+</mo><msub><mphantom><mi>t</mi></mphantom><mi>i</mi></msub>',
        '<annotation encoding="application/x-tex">\\sqrt x_i + \\sqrt[3]y_j + \\frac12 + \\dfrac a b + \\binom n k + \\overset\\alpha q_i + \\underset 0 r_i + \\boxed s_i + \\phantom t_i + \\hphantom u_i + \\vphantom v_i + \\cancel w_i + \\bcancel x + \\xcancel y</annotation>',
        '<span class="math inline">\\(\\begin{array}{l|c|r}p_i &amp; m_i &amp; 1 \\\\ \\cline{2-3} q_i &amp; n_i &amp; 2 \\\\ \\cline{1-1}\\cline{3-3} r_i &amp; s_i &amp; 3\\end{array}\\)</span>',
        '<span class="math inline">\\(\\begin{array}{*{2}{c|}r}p_1 &amp; m_1 &amp; 1 \\\\ p_2 &amp; m_2 &amp; 2\\end{array}\\)</span>',
        '<span class="math inline">\\(\\begin{array}{p{2cm}|m{1.5em}|b{8pt}}p_i &amp; \\text{middle review} &amp; 1 \\\\ q_i &amp; n_i &amp; 2\\end{array}\\)</span>',
        '<span class="math inline">\\(\\begin{array}{&gt;{\\text{src}}l&lt;{\\hspace{.25em}}@{\\,}c}p_i &amp; m_i \\\\ q_i &amp; n_i\\end{array}\\)</span>',
        '<span class="math inline">\\(\\begin{array}{lcr}p_i &amp; \\multicolumn{2}{|c|}{m_i + q_i} \\\\ a &amp; b &amp; c\\end{array}\\)</span>',
        '<span class="math inline">\\(p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + \\not\\alpha_i\\)</span>',
        '<span class="math inline">\\(f&#039;(x) + g&#039;&#039;_i + h_i&#039;&#039;&#039; + r&#039;&#039;&#039;&#039; + s&#039;&#039;&#039;&#039;&#039;_j + \\partial^\\prime f + y^\\backprime\\)</span>',
        '<msup><mi>r</mi><mo>⁗</mo></msup>',
        '<msubsup><mi>s</mi><mi>j</mi><mrow><mo>⁗</mo><mo>′</mo></mrow></msubsup>',
        '<span class="math inline">\\(\\acute{x} + \\grave{y} + \\breve{z} + \\check{a} + \\mathring{A}_0 + \\widetilde{mn}\\)</span>',
        '<span class="math inline">\\(\\dddot{x_i} + \\ddddot{y} + \\DDDot z + \\utilde{x_i} + \\wideutilde{mn}\\)</span>',
        '<mover accent="true"><msub><mi>x</mi><mi>i</mi></msub><mo>⃛</mo></mover><mo>+</mo><mover accent="true"><mi>y</mi><mo>⃜</mo></mover><mo>+</mo><mover accent="true"><mi>z</mi><mo>⃛</mo></mover>',
        '<munder accentunder="true"><msub><mi>x</mi><mi>i</mi></msub><mo>̰</mo></munder><mo>+</mo><munder accentunder="true"><mrow><mi>m</mi><mi>n</mi></mrow><mo>̰</mo></munder>',
        '<annotation encoding="application/x-tex">\\dddot{x_i} + \\ddddot{y} + \\DDDot z + \\utilde{x_i} + \\wideutilde{mn}</annotation>',
        '<span class="math inline">\\(\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}\\)</span>',
        '<span class="math inline">\\(\\overbrace x_i^n + \\underbrace y_j + \\overbracket x^2 + \\underbracket y_0\\)</span>',
        '<annotation encoding="application/x-tex">\\overbrace x_i^n + \\underbrace y_j + \\overbracket x^2 + \\underbracket y_0</annotation>',
        '<span class="math inline">\\(\\overparen{p_i + m_i}^{\\text{review}} + \\underparen{q_i}_{0} + \\overgroup{x+y} + \\undergroup{z}\\)</span>',
        '<annotation encoding="application/x-tex">\\overparen{p_i + m_i}^{\\text{review}} + \\underparen{q_i}_{0} + \\overgroup{x+y} + \\undergroup{z}</annotation>',
        '<span class="math inline">\\(\\buildrel{\\text{def}}\\over= + A \\buildrel{\\operatorname{iso}}\\over\\longrightarrow B\\)</span>',
        '<span class="math inline">\\({a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z} + {n \\bangle k}\\)</span>',
        '<span class="math inline">\\({a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}\\)</span>',
        '<span class="math inline">\\(\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}\\)</span>',
        '<span class="math inline">\\(\\textcolor[HTML]{336699}{\\operatorname{media}} + \\textcolor[rgb]{0.2,0.4,0.6}{p_i} + \\color[gray]{.5} m_i + q_i\\)</span>',
        '<span class="math inline">\\(\\colorbox{yellow}{p_i + m_i} + \\colorbox[HTML]{fff9cc}{\\operatorname{media}} + \\fcolorbox{red}{yellow}{q_i} + \\fcolorbox[RGB]{51,102,153}{255,249,204}{\\frac{a}{b}}\\)</span>',
        '<span class="math inline">\\(\\colorbox{yellow}x_i + \\fcolorbox{red}{yellow}q_i + \\cancelto0r_i + \\cancelto\\alpha\\frac12\\)</span>',
        '<span class="math inline">\\(\\boxed{p_i + m_i} + \\boxed{\\frac{a}{b}}_j\\)</span>',
        '<span class="math inline">\\(\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i} + \\mathllap{L_i} + \\mathrlap{R_i} + \\mathclap{x+y}\\)</span>',
        '<span class="math inline">\\(\\smash x_i + \\smash[t] y^2 + \\mathllap L_i + \\mathrlap R + \\clap C\\)</span>',
        '<annotation encoding="application/x-tex">\\smash x_i + \\smash[t] y^2 + \\mathllap L_i + \\mathrlap R + \\clap C</annotation>',
        '<span class="math inline">\\(\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i\\)</span>',
        '<span class="math inline">\\(\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}\\)</span>',
        '<span class="math inline">\\(\\mathup{x} + \\symbf{A1} + \\bm{\\alpha_i} + \\mathds{R2} + \\mathbfit{Az} + \\mathbfsfup{R2} + \\mathbfsfit{Az} + \\mathbfscr{F} + \\mathbfcal{L} + \\mathbffrak{g} + \\mathsfit{n}\\)</span>',
        '<span class="math inline">\\(\\bm{\\alpha_i} + \\mathbfit{\\Gamma\\alpha} + \\mathbfsfup{\\Theta\\beta} + \\mathbfsfit{\\Omega\\omega}\\)</span>',
        '<span class="math inline">\\(\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)\\)</span>',
        '<span class="math inline">\\(\\prescript{14}{6}{C} + \\prescript{\\text{review}}{}{p_i} + \\prescript{}{L}{\\operatorname{score}}_j\\)</span>',
        '<span class="math inline">\\(\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\int\\nolimits_{0}^{1} g(x) dx\\)</span>',
        '<span class="math inline">\\(\\bigcup_{i=1}^{n} A_i + \\bigcap_{j} B_j + \\coprod\\limits_{k=0}^{m} C_k + \\iint_D f(x,y) dx dy + \\bigoplus_i G_i\\)</span>',
        '<span class="math inline">\\(\\operatorname*{argmax}_{p_i \\in P}^{\\text{draft}} f(p_i) + \\operatorname{median}\\displaylimits_{i=1}^{n} p_i + \\operatorname*{rank}\\nolimits_{j} q_j\\)</span>',
        '<span class="math inline">\\(\\operatorname\\alpha_i + \\operatorname*\\max_{j}^{n} p_j\\)</span>',
        '<span class="math inline">\\(a \\mod n + b \\bmod m_i + x \\pmod {n+1} + y \\pod m_i\\)</span>',
        '<span class="math inline">\\(\\mathop{\\operatorname{argmax}}\\limits_{p_i \\in P}^{\\text{draft}} f(p_i) + a \\mathrel{\\approx} b + x \\mathbin{\\cdot} y + \\mathopen{[}q_i\\mathclose{]} + f\\mathpunct{,}g\\)</span>',
        '<span class="math inline">\\(\\begin{align}f(p_i) &amp;= m_i \\\\ g(p_i) &amp;= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}\\)</span>',
        '<span class="math inline">\\(\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}\\)</span>',
        '<span class="math inline">\\(\\begin{align}p_i &amp;= m_i \\\\ \\intertext{review \\&amp; media} x_i &amp;= y_i \\\\ \\shortintertext{compact review} u_i &amp;= v_i\\end{align}\\)</span>',
        '<span class="math inline">\\(\\begin{aligned}[t]p_i &amp;= m_i \\\\ x &amp;= y\\end{aligned} + \\begin{gathered}[b]u+v \\\\ w\\end{gathered} + \\begin{alignedat}[c]{2}a &amp;= b &amp; c &amp;= d\\end{alignedat}\\)</span>',
        '<span class="math inline">\\(\\begin{flalign}\\text{source} &amp;&amp; p_i &amp;= m_i &amp;&amp; \\text{review} \\\\ \\text{target} &amp;&amp; x_i &amp;= y_i \\tag{WP-F}\\end{flalign}\\)</span>',
        '<span class="math inline">\\(\\begin{eqnarray}p_i &amp;=&amp; m_i \\\\ x_i &amp;=&amp; y_i \\tag{WP-E}\\end{eqnarray}\\)</span>',
        '<span class="math inline">\\(\\begin{aligned}a_i &amp;= b_i \\\\[.5em] c_i &amp;= d_i\\end{aligned}\\)</span>',
        '<span class="math inline">\\(\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline} + \\left(\\begin{multlined}u+v \\\\ w\\end{multlined}\\right)\\)</span>',
        '<span class="math inline">\\(\\left(\\begin{smallmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i\\)</span>',
        '<span class="math inline">\\(\\begin{dcases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{dcases} + \\begin{rcases}q_i &amp; q_i \\in Q \\\\ 0 &amp; \\text{otherwise}\\end{rcases} + \\begin{drcases*}r_i &amp; r_i \\in R \\\\ 0 &amp; \\text{otherwise}\\end{drcases*}\\)</span>',
        '<span class="math inline">\\(\\begin{pmatrix*}p_i &amp; m_i \\\\ q_i &amp; n_i\\end{pmatrix*} + \\begin{cases*}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases*}\\)</span>',
        '<span class="math inline">\\(\\stackrel{\\text{audit}}{p_i} + \\ensuremath{q_i + r_i} + \\surd{s_i}\\)</span>',
        '<span class="math inline">\\(\\mathchoice{\\text{display branch}}{\\text{text branch}}{\\text{script branch}}{\\text{tiny branch}} + q_i\\)</span>',
        '<span class="math inline">\\(\\SI{9.81}{\\metre\\per\\second\\squared} + \\si{\\km\\per\\hour} + \\unit{\\joule\\per\\mole\\per\\kelvin}\\)</span>',
        '<annotation encoding="application/x-tex">\\SI{9.81}{\\metre\\per\\second\\squared} + \\si{\\km\\per\\hour} + \\unit{\\joule\\per\\mole\\per\\kelvin}</annotation>',
        '<span class="math inline">\\(\\si{\\mg\\per\\mL} + \\qty{532}{\\nm} + \\SI{20}{\\MHz} + \\unit{\\kPa} + \\si{\\us}\\)</span>',
        '<annotation encoding="application/x-tex">\\si{\\mg\\per\\mL} + \\qty{532}{\\nm} + \\SI{20}{\\MHz} + \\unit{\\kPa} + \\si{\\us}</annotation>',
        '<span class="math display">\\[\\begin{equation}r_i + s_i \\label{eq:wrapped-env} \\tag{WP-3}\\end{equation}\\]</span>',
        '<span class="math inline">\\(\\begin{equation*}\\operatorname{review}(p_i) + \\eqref{eq:wrapped-env}\\end{equation*}\\)</span>',
        '<span class="math inline">\\(\\begin{align}p_i &amp;= m_i \\tag{WP-1} \\\\ x_i &amp;= y_i \\label{eq:row-review} \\tag*{review}\\end{align}\\)</span>',
        '<span class="math inline">\\(\\begin{align}p_i &amp;= m_i \\notag \\\\ x_i &amp;= y_i \\nonumber \\\\ u_i &amp;= v_i\\end{align}\\)</span>',
        '<span class="math inline">\\(p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\&gt;z\\)</span>',
        '<span class="math inline">\\(p_i\\allowbreak + m_i + \\operatorname{slug}\\allowbreak\\)</span>',
        '<span class="math inline">\\(p_i % reviewer hidden\\)</span>',
        "<span class=\"math display\">\\[\\begin{aligned}a_i &amp;= b_i % \\end{aligned} hidden terminator\n\\\\ c_i &amp;= d_i\\end{aligned}\\]</span>",
        '<span class="math inline">\\(p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b\\)</span>',
        '<span class="math inline">\\(\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/\\)</span>',
        '<span class="math inline">\\(\\left\\lVert p_i + m_i \\right\\rVert + \\left\\lceil \\frac{x}{y} \\right\\rfloor + \\lbrack q_i \\rbrack\\)</span>',
        '<span class="math inline">\\(\\left\\lbrbrak p_i + m_i \\right\\rbrbrak + \\Bigl\\Lbrbrak q_i \\Bigr\\Rbrbrak\\)</span>',
        '<span class="math inline">\\(\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow + \\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache\\)</span>',
        '<span class="math inline">\\(\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle\\)</span>',
        '<span class="math inline">\\(\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i\\)</span>',
        '<span class="math inline">\\(\\xlongequal{\\text{same}} + \\xhookrightarrow[\\text{map}]{f} + \\xtwoheadleftarrow{g} + \\xleftharpoonup{\\text{pull}} + \\xrightharpoondown[low]{high}\\)</span>',
        '<span class="math inline">\\(\\xrightleftharpoons[\\text{review}]{\\operatorname{publish}} p_i + \\xleftrightharpoons{draft} m_i\\)</span>',
        '<span class="math inline">\\(\\xrightarrow\\alpha p_i + \\xleftarrow[\\text{low}]\\beta m_i + \\xhookrightarrow[map] f q + \\overrightarrow A_i + \\underrightarrow\\operatorname{media}\\)</span>',
        '<span class="math display">\\[p_i + m_i \\label{eq:review-flow} \\tag{WP-2}\\]</span>',
        '<span class="math inline">\\(\\label{eq:plain}x_i + \\eqref{eq:plain} + \\ref{review row/2}\\)</span>',
        '<span class="math inline">\\(\\eqref{eq:review-flow} + \\eqref{eq:row-review}\\)</span>',
        '<span class="math display">\\[p_i + m_i \\label{eq:auto-one}\\]</span>',
        '<span class="math display">\\[\\begin{align}x_i &amp;= y_i \\label{eq:auto-row} \\\\ u_i &amp;= v_i \\tag{manual}\\end{align}\\]</span>',
        '<span class="math inline">\\(\\eqref{eq:auto-one} + \\eqref{eq:auto-row} + \\eqref{eq:plain}\\)</span>',
        '<span class="math inline">\\(\\hyperref[eq:review-flow]{p_i + m_i} + \\hyperref{q_i}\\)</span>',
        '<span class="math inline">\\(\\href{https://example.test/review}{p_i + m_i} + \\url{mailto:reviewer@example.test}\\)</span>',
        '<span class="math inline">\\(\\frac{a_1}{\\sqrt{b^2}} + \\alpha\\)</span>',
        '<mtable columnalign="right left" rowspacing=".5em" data-tex-rowspacing="after-row-1:.5em"><mtr><mtd><msub><mi>a</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr>',
        '<annotation encoding="application/x-tex">\\begin{aligned}a_i &amp;= b_i \\\\[.5em] c_i &amp;= d_i\\end{aligned}</annotation>',
        '<mo>⟨</mo>',
        '<mo>⟩</mo>',
        '<annotation encoding="application/x-tex">\\langle post_id,media_id \\rangle</annotation>',
        '<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mi>f</mi><mi>i</mi><mi>n</mi><mi>a</mi><mi>l</mi>',
        '<annotation encoding="application/x-tex">\\wpreview{p_i} + \\wpreview[final]{m_i}</annotation>',
        '<msub><mi>review score</mi><mi>i</mi></msub><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>',
        '<annotation encoding="application/x-tex">\\wpreviewscore_i(p_i)</annotation>',
        '<munderover><mi>arg review</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo>',
        '<annotation encoding="application/x-tex">\\wpargreview_{p_i \\in P}^{\\text{draft}} f(p_i)</annotation>',
        '<mtext>review mode</mtext><mo>+</mo><mstyle mathvariant="normal"><mtext>media label</mtext></mstyle>',
        '<mstyle mathvariant="bold"><mtext>draft</mtext></mstyle><mo>+</mo><mstyle mathvariant="italic"><mtext>review</mtext></mstyle>',
        '<mstyle mathvariant="monospace"><mtext>code_1</mtext></mstyle><mo>+</mo><mstyle mathvariant="sans-serif"><mtext>sans group</mtext></mstyle>',
        '<annotation encoding="application/x-tex">\\mbox{review mode} + \\textrm{media label} + \\textbf{draft} + \\textit{review} + \\texttt{code_1} + \\textsf{sans group}</annotation>',
        '<mo>…</mo><mo>+</mo><mo>⋯</mo><mo>+</mo><mo>⋱</mo><mo>+</mo><mi>ℵ</mi><mo>+</mo><mi>ℓ</mi><mo>+</mo><mi>ℜ</mi><mo>+</mo><mi>ℑ</mi><mo>+</mo><mi>℘</mi>',
        '<mi>a</mi><mo>≅</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>≃</mo><mi>d</mi><mo>+</mo><mi>x</mi><mo>∝</mo><mi>y</mi><mo>+</mo><mi>u</mi><mo>∥</mo><mi>v</mi><mo>+</mo><mi>r</mi><mo>⊥</mo><mi>s</mi><mo>+</mo><mo>∠</mo><mi>x</mi><mo>+</mo><mo>∇</mo><mi>f</mi><mo>+</mo><mo>⊤</mo><mo>+</mo><mo>⊥</mo>',
        '<annotation encoding="application/x-tex">\\ldots + \\cdots + \\ddots + \\aleph + \\ell + \\Re + \\Im + \\wp + a \\cong b + c \\simeq d + x \\propto y + u \\parallel v + r \\perp s + \\angle x + \\nabla f + \\top + \\bot</annotation>',
        '<mi>a</mi><mo>⊕</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⊖</mo><mi>d</mi><mo>+</mo><mi>x</mi><mo>≍</mo><mi>y</mi><mo>+</mo><mi>p</mi><mo>⊢</mo><mi>q</mi><mo>+</mo><mi>u</mi><mo>⋈</mo><mi>v</mi>',
        '<annotation encoding="application/x-tex">a \\oplus b + c \\ominus d + x \\asymp y + p \\vdash q + u \\bowtie v</annotation>',
        '<mtable columnalign="left center right" columnlines="solid solid" data-tex-clines="after-row-1:2-3 after-row-2:1-1,3-3"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd><mtd><mn>2</mn></mtd></mtr><mtr><mtd><msub><mi>r</mi><mi>i</mi></msub></mtd><mtd><msub><mi>s</mi><mi>i</mi></msub></mtd><mtd><mn>3</mn></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{array}{l|c|r}p_i &amp; m_i &amp; 1 \\\\ \\cline{2-3} q_i &amp; n_i &amp; 2 \\\\ \\cline{1-1}\\cline{3-3} r_i &amp; s_i &amp; 3\\end{array}</annotation>',
        '<mtable columnalign="center center right" columnlines="solid solid"><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd><mtd><mn>2</mn></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{array}{*{2}{c|}r}p_1 &amp; m_1 &amp; 1 \\\\ p_2 &amp; m_2 &amp; 2\\end{array}</annotation>',
        '<mtable columnalign="left left left" columnwidth="2cm 1.5em 8pt" data-tex-column-valign="top middle bottom" columnlines="solid solid"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mtext>middle review</mtext></mtd><mtd><mn>1</mn></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd><mtd><mn>2</mn></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{array}{p{2cm}|m{1.5em}|b{8pt}}p_i &amp; \\text{middle review} &amp; 1 \\\\ q_i &amp; n_i &amp; 2\\end{array}</annotation>',
        '<mtable columnalign="left center" data-tex-column-hooks="pre-1:\\text{src} | post-1:\\hspace{.25em} | gap-after-1:\\,"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>n</mi><mi>i</mi></msub></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{array}{&gt;{\\text{src}}l&lt;{\\hspace{.25em}}@{\\,}c}p_i &amp; m_i \\\\ q_i &amp; n_i\\end{array}</annotation>',
        '<mtable columnalign="left center right"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd columnspan="2" columnalign="center" data-tex-column-lines="left right"><mrow><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><msub><mi>q</mi><mi>i</mi></msub></mrow></mtd></mtr><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd><mtd><mi>c</mi></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{array}{lcr}p_i &amp; \\multicolumn{2}{|c|}{m_i + q_i} \\\\ a &amp; b &amp; c\\end{array}</annotation>',
        '<msub><mi>p</mi><mi>i</mi></msub><mo>∉</mo><mi>P</mi><mo>+</mo><mi>a</mi><mo>≠</mo><mi>b</mi><mo>+</mo><mi>x</mi><mo>≰</mo><mi>y</mi><mo>+</mo><mi>A</mi><mo>⊈</mo><mi>B</mi><mo>+</mo><msub><menclose notation="updiagonalstrike"><mi>α</mi></menclose><mi>i</mi></msub>',
        '<annotation encoding="application/x-tex">p_i \\not\\in P + a \\not= b + x \\not\\leq y + A \\not\\subseteq B + \\not\\alpha_i</annotation>',
        '<span class="math inline">\\(x \\not{\\in} S + y \\not{=} z + q \\not{\\leqslant} r + u \\not\\geqslant v\\)</span>',
        '<annotation encoding="application/x-tex">x \\not{\\in} S + y \\not{=} z + q \\not{\\leqslant} r + u \\not\\geqslant v</annotation>',
        '<msup><mi>f</mi><mo>′</mo></msup><mo>(</mo><mi>x</mi><mo>)</mo><mo>+</mo><msubsup><mi>g</mi><mi>i</mi><mo>″</mo></msubsup>',
        '<msup><mo>∂</mo><mo>′</mo></msup><mi>f</mi><mo>+</mo><msup><mi>y</mi><mo>‵</mo></msup>',
        '<mover accent="true"><mi>x</mi><mo>´</mo></mover><mo>+</mo><mover accent="true"><mi>y</mi><mo>`</mo></mover>',
        '<mover accent="true"><mi>z</mi><mo>˘</mo></mover><mo>+</mo><mover accent="true"><mi>a</mi><mo>ˇ</mo></mover>',
        '<msub><mover accent="true"><mi>A</mi><mo>˚</mo></mover><mn>0</mn></msub><mo>+</mo><mover accent="true"><mrow><mi>m</mi><mi>n</mi></mrow><mo>~</mo></mover>',
        '<annotation encoding="application/x-tex">\\acute{x} + \\grave{y} + \\breve{z} + \\check{a} + \\mathring{A}_0 + \\widetilde{mn}</annotation>',
        '<mover><mo>=</mo><mtext>def</mtext></mover><mo>+</mo><mi>A</mi><mover><mo>→</mo><mi>iso</mi></mover><mi>B</mi>',
        '<annotation encoding="application/x-tex">\\buildrel{\\text{def}}\\over= + A \\buildrel{\\operatorname{iso}}\\over\\longrightarrow B</annotation>',
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
        '<span class="math inline">\\(\\overunderset{\\text{publish}}{\\operatorname{draft}}{p_i} + \\underoverset{0}{\\infty}{\\lim}_{n \\to \\infty} a_n\\)</span>',
        '<munderover><msub><mi>p</mi><mi>i</mi></msub><mi>draft</mi><mtext>publish</mtext></munderover>',
        '<msub><munderover><mo>lim</mo><mn>0</mn><mi>∞</mi></munderover><mrow><mi>n</mi><mo>→</mo><mi>∞</mi></mrow></msub>',
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
        '<mstyle mathcolor="reviewblue"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mrow></mstyle>',
        '<mstyle mathcolor="#808080"><mrow><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><msub><mi>q</mi><mi>i</mi></msub></mrow></mstyle>',
        '<mstyle mathbackground="yellow"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mstyle>',
        '<mstyle mathbackground="#fff9cc"><mi>media</mi></mstyle>',
        '<menclose notation="box" mathbackground="yellow" data-tex-framecolor="red"><msub><mi>q</mi><mi>i</mi></msub></menclose>',
        '<menclose notation="box" mathbackground="#fff9cc" data-tex-framecolor="#336699"><mfrac><mi>a</mi><mi>b</mi></mfrac></menclose>',
        '<mphantom><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mphantom>',
        '<mpadded height="0" depth="0"><mphantom><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mphantom></mpadded>',
        '<mpadded width="0"><mphantom><mfrac><mi>a</mi><mi>b</mi></mfrac></mphantom></mpadded>',
        '<menclose notation="updiagonalstrike"><msub><mi>x</mi><mi>i</mi></msub></menclose>',
        '<menclose notation="downdiagonalstrike"><msub><mi>y</mi><mi>i</mi></msub></menclose>',
        '<menclose notation="updiagonalstrike downdiagonalstrike"><msub><mi>z</mi><mi>i</mi></msub></menclose>',
        '<mover><menclose notation="updiagonalstrike"><msub><mi>draft</mi><mi>i</mi></msub></menclose><mn>0</mn></mover>',
        '<menclose notation="box"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></menclose><mo>+</mo><msub><menclose notation="box"><mfrac><mi>a</mi><mi>b</mi></mfrac></menclose><mi>j</mi></msub>',
        '<annotation encoding="application/x-tex">\\boxed{p_i + m_i} + \\boxed{\\frac{a}{b}}_j</annotation>',
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
        '<msub><mstyle mathvariant="bold"><mi>' . $mathSymbol(0x1D6C2) . '</mi></mstyle><mi>i</mi></msub>',
        '<mstyle mathvariant="double-struck"><mrow><mi>' . $mathSymbol(0x1D538) . '</mi><mi>' . $mathSymbol(0x2124) . '</mi><mn>' . $mathSymbol(0x1D7D8) . $mathSymbol(0x1D7E1) . '</mn></mrow></mstyle>',
        '<mstyle mathvariant="script"><mrow><mi>' . $mathSymbol(0x2131) . '</mi><mi>' . $mathSymbol(0x2112) . '</mi><mi>' . $mathSymbol(0x1D4AA) . '</mi></mrow></mstyle>',
        '<mstyle mathvariant="fraktur"><mrow><mi>' . $mathSymbol(0x1D524) . '</mi><mi>' . $mathSymbol(0x211C) . '</mi></mrow></mstyle>',
        '<mstyle mathvariant="monospace"><mrow><mi>' . $mathSymbol(0x1D68C) . '</mi><mi>' . $mathSymbol(0x1D698) . '</mi><mi>' . $mathSymbol(0x1D68D) . '</mi><mi>' . $mathSymbol(0x1D68E) . '</mi><mn>' . $mathSymbol(0x1D7FA) . $mathSymbol(0x1D7F8) . '</mn></mrow></mstyle>',
        '<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>',
        '<msub><mo>lim</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>x</mi><mo>→</mo><mn>0</mn></mtd></mtr><mtr><mtd><mi>x</mi><mo>&gt;</mo><mn>0</mn></mtd></mtr></mtable></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>',
        '<mmultiscripts><mi>C</mi><mprescripts/><mn>6</mn><mn>14</mn></mmultiscripts><mo>+</mo><mmultiscripts><msub><mi>p</mi><mi>i</mi></msub><mprescripts/><none/><mtext>review</mtext></mmultiscripts><mo>+</mo><msub><mmultiscripts><mi>score</mi><mprescripts/><mi>L</mi><none/></mmultiscripts><mi>j</mi></msub>',
        '<annotation encoding="application/x-tex">\\prescript{14}{6}{C} + \\prescript{\\text{review}}{}{p_i} + \\prescript{}{L}{\\operatorname{score}}_j</annotation>',
        '<munderover><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover><msub><mi>p</mi><mi>i</mi></msub>',
        '<munder><mo>lim</mo><mrow><mi>x</mi><mo>→</mo><mn>0</mn></mrow></munder><mi>f</mi><mo>(</mo><mi>x</mi><mo>)</mo>',
        '<msubsup><mo>∫</mo><mn>0</mn><mn>1</mn></msubsup><mi>g</mi><mo>(</mo><mi>x</mi><mo>)</mo><mi>d</mi><mi>x</mi>',
        '<msubsup><mo>⋃</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>A</mi><mi>i</mi></msub>',
        '<msub><mo>∬</mo><mi>D</mi></msub><mi>f</mi><mo>(</mo><mi>x</mi><mo>,</mo><mi>y</mi><mo>)</mo><mi>d</mi><mi>x</mi><mi>d</mi><mi>y</mi>',
        '<annotation encoding="application/x-tex">\\bigcup_{i=1}^{n} A_i + \\bigcap_{j} B_j + \\coprod\\limits_{k=0}^{m} C_k + \\iint_D f(x,y) dx dy + \\bigoplus_i G_i</annotation>',
        '<munderover><mi>argmax</mi><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover>',
        '<munderover><mi>median</mi><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover>',
        '<msub><mi>rank</mi><mi>j</mi></msub><msub><mi>q</mi><mi>j</mi></msub>',
        '<msub><mi>α</mi><mi>i</mi></msub><mo>+</mo><munderover><mi>max</mi><mi>j</mi><mi>n</mi></munderover><msub><mi>p</mi><mi>j</mi></msub>',
        '<annotation encoding="application/x-tex">\\operatorname\\alpha_i + \\operatorname*\\max_{j}^{n} p_j</annotation>',
        '<mi>a</mi><mspace width="0.4444em"></mspace><mi>mod</mi><mspace width="0.2222em"></mspace><mi>n</mi><mo>+</mo><mi>b</mi><mspace width="0.2222em"></mspace><mi>mod</mi><mspace width="0.2222em"></mspace><msub><mi>m</mi><mi>i</mi></msub>',
        '<mi>x</mi><mspace width="0.2222em"></mspace><mo>(</mo><mi>mod</mi><mspace width="0.2222em"></mspace><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow><mo>)</mo><mo>+</mo><mi>y</mi><mspace width="0.2222em"></mspace><mo>(</mo><msub><mi>m</mi><mi>i</mi></msub><mo>)</mo>',
        '<annotation encoding="application/x-tex">a \\mod n + b \\bmod m_i + x \\pmod {n+1} + y \\pod m_i</annotation>',
        '<munderover><mrow data-tex-math-class="operator"><mi>argmax</mi></mrow><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mrow><mtext>draft</mtext></munderover>',
        '<mrow data-tex-math-class="relation"><mo>≈</mo></mrow>',
        '<mrow data-tex-math-class="binary"><mo>⋅</mo></mrow>',
        '<mrow data-tex-math-class="open"><mo>[</mo></mrow><msub><mi>q</mi><mi>i</mi></msub><mrow data-tex-math-class="close"><mo>]</mo></mrow>',
        '<mrow data-tex-math-class="punctuation"><mo>,</mo></mrow>',
        '<annotation encoding="application/x-tex">\\mathop{\\operatorname{argmax}}\\limits_{p_i \\in P}^{\\text{draft}} f(p_i) + a \\mathrel{\\approx} b + x \\mathbin{\\cdot} y + \\mathopen{[}q_i\\mathclose{]} + f\\mathpunct{,}g</annotation>',
        '<mtable columnalign="right left"><mtr><mtd><mi>f</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>g</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo></mtd><mtd><mo>=</mo><mfrac><msub><mi>a</mi><mi>i</mi></msub><msub><mi>b</mi><mi>i</mi></msub></mfrac></mtd></mtr></mtable>',
        '<mtable columnalign="center"><mtr><mtd><mi>x</mi><mo>+</mo><mi>y</mi></mtd></mtr><mtr><mtd><mi>z</mi></mtd></mtr></mtable>',
        '<mtable columnalign="right left"><mtr><mtd><mi>S</mi></mtd><mtd><mo>=</mo><msubsup><mo>∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></msubsup><msub><mi>p</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd></mtd><mtd><mo>=</mo><mfrac><mi>a</mi><mi>b</mi></mfrac></mtd></mtr></mtable>',
        '<mtable columnalign="right left right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd><msub><mi>a</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd><mtd><mi>u</mi></mtd><mtd><mo>=</mo><mi>v</mi></mtd></mtr></mtable>',
        '<mtable columnalign="left right left right left right"><mtr><mtd><mtext>source</mtext></mtd><mtd></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd><mtd></mtd><mtd><mtext>review</mtext></mtd></mtr><mlabeledtr><mtd><mtext>(WP-F)</mtext></mtd><mtd><mtext>target</mtext></mtd><mtd></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>',
        '<mtable columnalign="center" rowspacing=".5em normal" data-tex-rowspacing="after-row-1:.5em"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>=</mo><msub><mi>a</mi><mi>i</mi></msub><mo>+</mo><msub><mi>b</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mo>+</mo><mfrac><mi>x</mi><mi>y</mi></mfrac></mtd></mtr></mtable>',
        '<mo fence="true" stretchy="true">(</mo><mtable columnalign="center"><mtr><mtd><mi>u</mi><mo>+</mo><mi>v</mi></mtd></mtr><mtr><mtd><mi>w</mi></mtd></mtr></mtable><mo fence="true" stretchy="true">)</mo>',
        '<mstyle scriptlevel="1"><mtable rowspacing="0.1em" columnspacing="0.2778em"><mtr><mtd><msub><mi>p</mi><mn>1</mn></msub></mtd><mtd><msub><mi>m</mi><mn>1</mn></msub></mtd></mtr><mtr><mtd><msub><mi>p</mi><mn>2</mn></msub></mtd><mtd><msub><mi>m</mi><mn>2</mn></msub></mtd></mtr></mtable></mstyle>',
        '<msubsup><mo>∑</mo><mtable columnalign="center" rowspacing="0.1em"><mtr><mtd><mi>i</mi><mo>=</mo><mn>1</mn></mtd></mtr><mtr><mtd><mi>i</mi><mo>≠</mo><mi>j</mi></mtd></mtr></mtable><mi>n</mi></msubsup><msub><mi>a</mi><mi>i</mi></msub>',
        '<mtable><mlabeledtr><mtd><mtext>(WP-3)</mtext></mtd><mtd id="eq:wrapped-env"><mrow><msub><mi>r</mi><mi>i</mi></msub><mo>+</mo><msub><mi>s</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{equation}r_i + s_i \\label{eq:wrapped-env} \\tag{WP-3}\\end{equation}</annotation>',
        '<mi>review</mi><mo>(</mo><msub><mi>p</mi><mi>i</mi></msub><mo>)</mo><mo>+</mo><mrow><mo>(</mo><mtext href="#eq:wrapped-env">WP-3</mtext><mo>)</mo></mrow>',
        '<mtable columnalign="right left"><mlabeledtr><mtd><mtext>(WP-1)</mtext></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mlabeledtr><mlabeledtr id="eq:row-review"><mtd><mtext>review</mtext></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>',
        '<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>u</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>v</mi><mi>i</mi></msub></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\notag \\\\ x_i &amp;= y_i \\nonumber \\\\ u_i &amp;= v_i\\end{align}</annotation>',
        '<mrow href="https://example.test/review"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mrow><mo>+</mo><mtext href="mailto:reviewer@example.test">mailto:reviewer@example.test</mtext>',
        '<annotation encoding="application/x-tex">\\href{https://example.test/review}{p_i + m_i} + \\url{mailto:reviewer@example.test}</annotation>',
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
        '<mo fence="true" stretchy="true">〔</mo><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo fence="true" stretchy="true">〕</mo>',
        '<mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">〘</mo><msub><mi>q</mi><mi>i</mi></msub><mo fence="true" stretchy="true" minsize="1.8em" maxsize="1.8em">〙</mo>',
        '<annotation encoding="application/x-tex">\\left\\lbrbrak p_i + m_i \\right\\rbrbrak + \\Bigl\\Lbrbrak q_i \\Bigr\\Rbrbrak</annotation>',
        '<mo fence="true" stretchy="true">{</mo><msub><mi>p</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">|</mo><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi><mo fence="true" stretchy="true">}</mo>',
        '<mo fence="true" stretchy="true">⟨</mo><mi>x</mi><mo fence="true" stretchy="true" separator="true">/</mo><mi>y</mi><mo fence="true" stretchy="true">⟩</mo>',
        '<munderover><mo stretchy="true">→</mo><mtext>review</mtext><mi>publish</mi></munderover><msub><mi>p</mi><mi>i</mi></msub>',
        '<mover><mo stretchy="true">←</mo><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mover><msub><mi>m</mi><mi>i</mi></msub>',
        '<msub><mover accent="true"><mrow><mi>A</mi><mi>B</mi></mrow><mo stretchy="true">→</mo></mover><mi>i</mi></msub>',
        '<mtable><mlabeledtr><mtd><mtext>(WP-2)</mtext></mtd><mtd id="eq:review-flow"><mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow></mtd></mlabeledtr></mtable>',
        '<annotation encoding="application/x-tex">\\overset{\\text{new}}{p_i} + \\underset{0}{\\lim}_{n \\to \\infty} a_n + \\overbrace{x + y}^{\\text{sum}} + \\underbrace{m_i}_{\\text{media}} + \\displaystyle \\frac{q}{r}</annotation>',
        '<annotation encoding="application/x-tex">\\overunderset{\\text{publish}}{\\operatorname{draft}}{p_i} + \\underoverset{0}{\\infty}{\\lim}_{n \\to \\infty} a_n</annotation>',
        '<annotation encoding="application/x-tex">{a+b \\over c+d} + {n \\choose k} + {n \\atop k} + {p_i \\brack m_i} + {x+y \\brace z} + {n \\bangle k}</annotation>',
        '<annotation encoding="application/x-tex">{a+b \\overwithdelims() c+d} + {n \\atopwithdelims\\langle\\rangle k} + {p_i \\abovewithdelims[]1pt m_i}</annotation>',
        '<mi>a</mi><mo>∔</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>⊞</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>⊟</mo><mi>f</mi>',
        '<mi>A</mi><mo>⊏</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>⊒</mo><mi>D</mi><mo>+</mo><mi>x</mi><mo>≲</mo><mi>y</mi><mo>+</mo><mi>r</mi><mo>⪆</mo><mi>s</mi>',
        '<mi>p</mi><mo>≎</mo><mi>q</mi><mo>+</mo><mi>x</mi><mo>⇝</mo><mi>y</mi><mo>+</mo><mi>m</mi><mo>≰</mo><mi>n</mi>',
        '<annotation encoding="application/x-tex">a \\dotplus b + c \\boxplus d + e \\boxminus f + A \\sqsubset B + C \\sqsupseteq D + x \\lesssim y + r \\gtrapprox s + p \\Bumpeq q + x \\rightsquigarrow y + m \\nleq n</annotation>',
        '<mi>A</mi><mo>≺</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>≻</mo><mi>D</mi><mo>+</mo><mi>E</mi><mo>≪</mo><mi>F</mi><mo>+</mo><mi>G</mi><mo>≫</mo><mi>H</mi>',
        '<mi>x</mi><mo>↗</mo><mi>y</mi><mo>+</mo><mi>a</mi><mo>↘</mo><mi>b</mi><mo>+</mo><mi>L</mi><mo>↼</mo><mi>M</mi><mo>+</mo><mi>N</mi><mo>⇁</mo><mi>O</mi>',
        '<mi>P</mi><mo>⇌</mo><mi>Q</mi><mo>+</mo><mi>p</mi><mo>∵</mo><mi>q</mi><mo>+</mo><mi>f</mi><mo>⊸</mo><mi>g</mi>',
        '<annotation encoding="application/x-tex">A \\prec B + C \\succ D + E \\ll F + G \\gg H + x \\nearrow y + a \\searrow b + L \\leftharpoonup M + N \\rightharpoondown O + P \\rightleftharpoons Q + p \\because q + f \\multimap g</annotation>',
        '<mi>ℶ</mi><mo>+</mo><mi>ℷ</mi><mo>+</mo><mi>ℸ</mi><mo>+</mo><mi>a</mi><mo>≦</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>≧</mo><mi>d</mi><mo>+</mo><mi>x</mi><mo>≐</mo><mi>y</mi>',
        '<mi>P</mi><mo>⊈</mo><mi>Q</mi><mo>+</mo><mi>u</mi><mo>∦</mo><mi>v</mi>',
        '<annotation encoding="application/x-tex">\\beth + \\gimel + \\daleth + a \\leqq b + c \\geqq d + x \\doteq y + P \\nsubseteq Q + u \\nparallel v</annotation>',
        '<mo>⏦</mo><mo>+</mo><mo>↞</mo><mo>+</mo><mo>↩</mo>',
        '<mi>A</mi><mo>↚</mo><mi>B</mi><mo>+</mo><mi>C</mi><mo>↛</mo><mi>D</mi><mo>+</mo><mi>E</mi><mo>↮</mo><mi>F</mi>',
        '<mi>P</mi><mo>⊄</mo><mi>Q</mi><mo>+</mo><mi>R</mi><mo>⊅</mo><mi>S</mi>',
        '<annotation encoding="application/x-tex">\\AC + \\twoheadleftarrow + \\hookleftarrow + A \\nleftarrow B + C \\nrightarrow D + E \\nleftrightarrow F + P \\nsubset Q + R \\nsupset S</annotation>',
        '<mi>𝛤</mi><mo>+</mo><mi>𝛥</mi><mo>+</mo><msub><mi>𝜚</mi><mi>i</mi></msub><mo>+</mo><mi>𝜍</mi><mo>+</mo><mi>ϒ</mi>',
        '<mover accent="true"><mrow><msub><mi>x</mi><mi>i</mi></msub><mo>+</mo><msub><mi>y</mi><mi>i</mi></msub></mrow><mo>¯</mo></mover><mo>+</mo><munder accentunder="true"><mi>draft</mi><mo>̱</mo></munder>',
        '<annotation encoding="application/x-tex">\\varGamma + \\varDelta + \\varrho_i + \\varsigma + \\upUpsilon + \\overbar{x_i + y_i} + \\underbar{\\operatorname{draft}}</annotation>',
        '<annotation encoding="application/x-tex">\\color{red}{p_i} + \\textcolor{#336699}{\\operatorname{media}} + \\phantom{p_i + m_i} + \\hphantom{draft} + \\vphantom{\\frac{a}{b}} + \\cancel{x_i} + \\bcancel{y_i} + \\xcancel{z_i} + \\cancelto{0}{\\operatorname{draft}_i}</annotation>',
        '<annotation encoding="application/x-tex">\\textcolor[HTML]{336699}{\\operatorname{media}} + \\textcolor[rgb]{0.2,0.4,0.6}{p_i} + \\color[gray]{.5} m_i + q_i</annotation>',
        '<annotation encoding="application/x-tex">\\colorbox{yellow}{p_i + m_i} + \\colorbox[HTML]{fff9cc}{\\operatorname{media}} + \\fcolorbox{red}{yellow}{q_i} + \\fcolorbox[RGB]{51,102,153}{255,249,204}{\\frac{a}{b}}</annotation>',
        '<annotation encoding="application/x-tex">\\colorbox{yellow}x_i + \\fcolorbox{red}{yellow}q_i + \\cancelto0r_i + \\cancelto\\alpha\\frac12</annotation>',
        '<annotation encoding="application/x-tex">\\smash{\\frac{a}{b}} + \\smash[t]{p_i} + \\smash[b]{m_i} + \\mathllap{L_i} + \\mathrlap{R_i} + \\mathclap{x+y}</annotation>',
        '<annotation encoding="application/x-tex">\\mathrm{d}x + \\mathbf{v_i} + \\mathit{n} + \\mathsf{S} + \\mathtt{code} + \\mathcal{F}_n + \\mathbb{R} + \\mathfrak{g} + \\mathscr{L} + \\boldsymbol{\\alpha}_i</annotation>',
        '<annotation encoding="application/x-tex">\\mathbb{AZ09} + \\mathcal{FLO} + \\mathfrak{gR} + \\mathtt{code42}</annotation>',
        '<mstyle mathvariant="normal"><mi>x</mi></mstyle><mo>+</mo><mstyle mathvariant="bold"><mrow><mi>' . $mathSymbol(0x1D400) . '</mi><mn>' . $mathSymbol(0x1D7CF) . '</mn></mrow></mstyle>',
        '<mstyle mathvariant="bold"><msub><mi>' . $mathSymbol(0x1D6C2) . '</mi><mi>' . $mathSymbol(0x1D422) . '</mi></msub></mstyle><mo>+</mo><mstyle mathvariant="double-struck"><mrow><mi>' . $mathSymbol(0x211D) . '</mi><mn>' . $mathSymbol(0x1D7DA) . '</mn></mrow></mstyle>',
        '<mstyle mathvariant="bold-italic"><mrow><mi>' . $mathSymbol(0x1D468) . '</mi><mi>' . $mathSymbol(0x1D49B) . '</mi></mrow></mstyle><mo>+</mo><mstyle mathvariant="bold-sans-serif"><mrow><mi>' . $mathSymbol(0x1D5E5) . '</mi><mn>' . $mathSymbol(0x1D7EE) . '</mn></mrow></mstyle>',
        '<mstyle mathvariant="sans-serif-bold-italic"><mrow><mi>' . $mathSymbol(0x1D63C) . '</mi><mi>' . $mathSymbol(0x1D66F) . '</mi></mrow></mstyle><mo>+</mo><mstyle mathvariant="bold-script"><mi>' . $mathSymbol(0x1D4D5) . '</mi></mstyle>',
        '<mstyle mathvariant="bold-script"><mi>' . $mathSymbol(0x1D4DB) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="bold-fraktur"><mi>' . $mathSymbol(0x1D58C) . '</mi></mstyle><mo>+</mo><mstyle mathvariant="sans-serif-italic"><mi>' . $mathSymbol(0x1D62F) . '</mi></mstyle>',
        '<annotation encoding="application/x-tex">\\mathup{x} + \\symbf{A1} + \\bm{\\alpha_i} + \\mathds{R2} + \\mathbfit{Az} + \\mathbfsfup{R2} + \\mathbfsfit{Az} + \\mathbfscr{F} + \\mathbfcal{L} + \\mathbffrak{g} + \\mathsfit{n}</annotation>',
        '<mstyle mathvariant="bold"><msub><mi>' . $mathSymbol(0x1D6C2) . '</mi><mi>' . $mathSymbol(0x1D422) . '</mi></msub></mstyle><mo>+</mo><mstyle mathvariant="bold-italic"><mrow><mi>' . $mathSymbol(0x1D71E) . '</mi><mi>' . $mathSymbol(0x1D736) . '</mi></mrow></mstyle>',
        '<mstyle mathvariant="bold-sans-serif"><mrow><mi>' . $mathSymbol(0x1D75D) . '</mi><mi>' . $mathSymbol(0x1D771) . '</mi></mrow></mstyle><mo>+</mo><mstyle mathvariant="sans-serif-bold-italic"><mrow><mi>' . $mathSymbol(0x1D7A8) . '</mi><mi>' . $mathSymbol(0x1D7C2) . '</mi></mrow></mstyle>',
        '<annotation encoding="application/x-tex">\\bm{\\alpha_i} + \\mathbfit{\\Gamma\\alpha} + \\mathbfsfup{\\Theta\\beta} + \\mathbfsfit{\\Omega\\omega}</annotation>',
        '<annotation encoding="application/x-tex">\\sum_{\\substack{i=1 \\\\ i\\ne j}}^{n} a_i + \\lim_{\\substack{x \\to 0 \\\\ x &gt; 0}} f(x)</annotation>',
        '<annotation encoding="application/x-tex">\\sum\\limits_{i=1}^{n} p_i + \\lim\\limits_{x \\to 0} f(x) + \\int\\nolimits_{0}^{1} g(x) dx</annotation>',
        '<annotation encoding="application/x-tex">\\begin{align}f(p_i) &amp;= m_i \\\\ g(p_i) &amp;= \\frac{a_i}{b_i}\\end{align} + \\begin{gathered}x+y \\\\ z\\end{gathered} + \\begin{split}S &amp;= \\sum_{i=1}^{n} p_i \\\\ &amp;= \\frac{a}{b}\\end{split}</annotation>',
        '<annotation encoding="application/x-tex">\\begin{alignedat}{2}p_i &amp;= m_i &amp; a_i &amp;= b_i \\\\ x &amp;= y &amp; u &amp;= v\\end{alignedat}</annotation>',
        '<mtr data-tex-intertext="normal"><mtd columnspan="2"><mtext>review &amp; media</mtext></mtd></mtr>',
        '<mtr data-tex-intertext="short"><mtd columnspan="2"><mtext>compact review</mtext></mtd></mtr>',
        '<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\\\ \\intertext{review \\&amp; media} x_i &amp;= y_i \\\\ \\shortintertext{compact review} u_i &amp;= v_i\\end{align}</annotation>',
        '<mtable columnalign="right left" align="top" data-tex-env-position="top"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><mi>x</mi></mtd><mtd><mo>=</mo><mi>y</mi></mtd></mtr></mtable>',
        '<mtable columnalign="center" align="bottom" data-tex-env-position="bottom"><mtr><mtd><mi>u</mi><mo>+</mo><mi>v</mi></mtd></mtr><mtr><mtd><mi>w</mi></mtd></mtr></mtable>',
        '<mtable columnalign="right left right left" align="center" data-tex-env-position="center"><mtr><mtd><mi>a</mi></mtd><mtd><mo>=</mo><mi>b</mi></mtd><mtd><mi>c</mi></mtd><mtd><mo>=</mo><mi>d</mi></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{aligned}[t]p_i &amp;= m_i \\\\ x &amp;= y\\end{aligned} + \\begin{gathered}[b]u+v \\\\ w\\end{gathered} + \\begin{alignedat}[c]{2}a &amp;= b &amp; c &amp;= d\\end{alignedat}</annotation>',
        '<annotation encoding="application/x-tex">\\begin{flalign}\\text{source} &amp;&amp; p_i &amp;= m_i &amp;&amp; \\text{review} \\\\ \\text{target} &amp;&amp; x_i &amp;= y_i \\tag{WP-F}\\end{flalign}</annotation>',
        '<mtable columnalign="right center left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo></mtd><mtd><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mlabeledtr><mtd><mtext>(WP-E)</mtext></mtd><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo></mtd><mtd><msub><mi>y</mi><mi>i</mi></msub></mtd></mlabeledtr></mtable>',
        '<annotation encoding="application/x-tex">\\begin{eqnarray}p_i &amp;=&amp; m_i \\\\ x_i &amp;=&amp; y_i \\tag{WP-E}\\end{eqnarray}</annotation>',
        '<annotation encoding="application/x-tex">\\begin{multline}p_i + m_i \\\\[.5em] = a_i + b_i \\\\ + \\frac{x}{y}\\end{multline} + \\left(\\begin{multlined}u+v \\\\ w\\end{multlined}\\right)</annotation>',
        '<annotation encoding="application/x-tex">\\left(\\begin{smallmatrix}p_1 &amp; m_1 \\\\ p_2 &amp; m_2\\end{smallmatrix}\\right) + \\sum_{\\begin{subarray}{c}i=1 \\\\ i\\ne j\\end{subarray}}^{n} a_i</annotation>',
        '<annotation encoding="application/x-tex">\\begin{dcases}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{dcases} + \\begin{rcases}q_i &amp; q_i \\in Q \\\\ 0 &amp; \\text{otherwise}\\end{rcases} + \\begin{drcases*}r_i &amp; r_i \\in R \\\\ 0 &amp; \\text{otherwise}\\end{drcases*}</annotation>',
        '<mo fence="true" stretchy="true">{</mo><mstyle displaystyle="true"><mtable columnalign="left left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><msub><mi>p</mi><mi>i</mi></msub><mo>∈</mo><mi>P</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable></mstyle>',
        '<mtable columnalign="left left"><mtr><mtd><msub><mi>q</mi><mi>i</mi></msub></mtd><mtd><msub><mi>q</mi><mi>i</mi></msub><mo>∈</mo><mi>Q</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable><mo fence="true" stretchy="true">}</mo>',
        '<mstyle displaystyle="true"><mtable columnalign="left left"><mtr><mtd><msub><mi>r</mi><mi>i</mi></msub></mtd><mtd><msub><mi>r</mi><mi>i</mi></msub><mo>∈</mo><mi>R</mi></mtd></mtr><mtr><mtd><mn>0</mn></mtd><mtd><mtext>otherwise</mtext></mtd></mtr></mtable></mstyle><mo fence="true" stretchy="true">}</mo>',
        '<annotation encoding="application/x-tex">\\begin{pmatrix*}p_i &amp; m_i \\\\ q_i &amp; n_i\\end{pmatrix*} + \\begin{cases*}p_i &amp; p_i \\in P \\\\ 0 &amp; \\text{otherwise}\\end{cases*}</annotation>',
        '<mover><msub><mi>p</mi><mi>i</mi></msub><mtext>audit</mtext></mover><mo>+</mo><mrow><msub><mi>q</mi><mi>i</mi></msub><mo>+</mo><msub><mi>r</mi><mi>i</mi></msub></mrow><mo>+</mo><msqrt><msub><mi>s</mi><mi>i</mi></msub></msqrt>',
        '<annotation encoding="application/x-tex">\\stackrel{\\text{audit}}{p_i} + \\ensuremath{q_i + r_i} + \\surd{s_i}</annotation>',
        '<mtext>text branch</mtext><mo>+</mo><msub><mi>q</mi><mi>i</mi></msub>',
        '<annotation encoding="application/x-tex">\\mathchoice{\\text{display branch}}{\\text{text branch}}{\\text{script branch}}{\\text{tiny branch}} + q_i</annotation>',
        '<mrow><mtext>mg</mtext><mtext>/</mtext><mtext>mL</mtext></mrow><mo>+</mo><mrow><mn>532</mn><mspace width="0.2222em"></mspace><mtext>nm</mtext></mrow><mo>+</mo><mrow><mn>20</mn><mspace width="0.2222em"></mspace><mtext>MHz</mtext></mrow><mo>+</mo><mtext>kPa</mtext><mo>+</mo><mtext>μs</mtext>',
        '<annotation encoding="application/x-tex">\\begin{align}p_i &amp;= m_i \\tag{WP-1} \\\\ x_i &amp;= y_i \\label{eq:row-review} \\tag*{review}\\end{align}</annotation>',
        '<annotation encoding="application/x-tex">p_i\\,m_i\\;n_i\\!q_i + a\\quad b\\qquad c + \\operatorname{post}\\thinspace\\operatorname{media}\\negthinspace\\operatorname{review} + x\\:y\\&gt;z</annotation>',
        '<msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub><mo>+</mo><mi>slug</mi>',
        '<annotation encoding="application/x-tex">p_i\\allowbreak + m_i + \\operatorname{slug}\\allowbreak</annotation>',
        "<annotation encoding=\"application/x-tex\">p_i % reviewer note with \\badcommand\n+ m_i + \\operatorname{slug}% trailing reviewer note\n</annotation>",
        '<mtable columnalign="right left"><mtr><mtd><msub><mi>p</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>m</mi><mi>i</mi></msub></mtd></mtr><mtr><mtd><msub><mi>x</mi><mi>i</mi></msub></mtd><mtd><mo>=</mo><msub><mi>y</mi><mi>i</mi></msub></mtd></mtr></mtable>',
        '<mtable columnalign="center center"><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>',
        '<annotation encoding="application/x-tex">p_i\\hspace{1.5em}m_i\\mspace{-2mu}q_i + a\\hspace*{.25in}b</annotation>',
        '<annotation encoding="application/x-tex">\\bigl( p_i \\bigr) + \\Bigl\\langle m_i \\Bigr\\rangle + \\bigm| x \\in S + \\Bigg/ y \\Bigg/</annotation>',
        '<annotation encoding="application/x-tex">\\left\\{p_i \\middle| p_i \\in P\\right\\} + \\left\\langle x \\middle/ y \\right\\rangle</annotation>',
        '<mo fence="true" stretchy="true">↑</mo><msub><mi>x</mi><mi>i</mi></msub><mo fence="true" stretchy="true" separator="true">⇕</mo><msub><mi>y</mi><mi>i</mi></msub><mo fence="true" stretchy="true">↓</mo>',
        '<mo>⟮</mo><msub><mi>p</mi><mi>i</mi></msub><mo>⟯</mo><mo>+</mo><mo fence="true" stretchy="true">⎰</mo><mi>a</mi><mo fence="true" stretchy="true">⎱</mo>',
        '<annotation encoding="application/x-tex">\\left\\uparrow x_i \\middle\\Updownarrow y_i \\right\\downarrow + \\Bigl\\Uparrow z \\Bigr\\Downarrow + \\lgroup p_i \\rgroup + \\left\\lmoustache a \\right\\rmoustache</annotation>',
        '<annotation encoding="application/x-tex">\\xrightarrow[\\text{review}]{\\operatorname{publish}} p_i + \\xleftarrow{draft} m_i + \\overrightarrow{AB}_i</annotation>',
        '<mover><mo stretchy="true">=</mo><mtext>same</mtext></mover><mo>+</mo><munderover><mo stretchy="true">↪</mo><mtext>map</mtext><mi>f</mi></munderover><mo>+</mo><mover><mo stretchy="true">↞</mo><mi>g</mi></mover>',
        '<mover><mo stretchy="true">↼</mo><mtext>pull</mtext></mover><mo>+</mo><munderover><mo stretchy="true">⇁</mo><mrow><mi>l</mi><mi>o</mi><mi>w</mi></mrow><mrow><mi>h</mi><mi>i</mi><mi>g</mi><mi>h</mi></mrow></munderover>',
        '<annotation encoding="application/x-tex">\\xlongequal{\\text{same}} + \\xhookrightarrow[\\text{map}]{f} + \\xtwoheadleftarrow{g} + \\xleftharpoonup{\\text{pull}} + \\xrightharpoondown[low]{high}</annotation>',
        '<munderover><mo stretchy="true">⇌</mo><mtext>review</mtext><mi>publish</mi></munderover><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><mover><mo stretchy="true">⇋</mo><mrow><mi>d</mi><mi>r</mi><mi>a</mi><mi>f</mi><mi>t</mi></mrow></mover><msub><mi>m</mi><mi>i</mi></msub>',
        '<annotation encoding="application/x-tex">\\xrightleftharpoons[\\text{review}]{\\operatorname{publish}} p_i + \\xleftrightharpoons{draft} m_i</annotation>',
        '<mover><mo stretchy="true">→</mo><mi>α</mi></mover><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><munderover><mo stretchy="true">←</mo><mtext>low</mtext><mi>β</mi></munderover><msub><mi>m</mi><mi>i</mi></msub>',
        '<munderover><mo stretchy="true">↪</mo><mrow><mi>m</mi><mi>a</mi><mi>p</mi></mrow><mi>f</mi></munderover><mi>q</mi><mo>+</mo><msub><mover accent="true"><mi>A</mi><mo stretchy="true">→</mo></mover><mi>i</mi></msub>',
        '<munder accentunder="true"><mi>media</mi><mo stretchy="true">→</mo></munder>',
        '<annotation encoding="application/x-tex">\\xrightarrow\\alpha p_i + \\xleftarrow[\\text{low}]\\beta m_i + \\xhookrightarrow[map] f q + \\overrightarrow A_i + \\underrightarrow\\operatorname{media}</annotation>',
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
        '<annotation encoding="application/x-tex">\\hyperref[eq:review-flow]{p_i + m_i} + \\hyperref{q_i}</annotation>',
        '<mrow><msub><mi>p</mi><mi>i</mi></msub><mo>+</mo><msub><mi>m</mi><mi>i</mi></msub></mrow><mo>+</mo><msub><mi>q</mi><mi>i</mi></msub>',
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
