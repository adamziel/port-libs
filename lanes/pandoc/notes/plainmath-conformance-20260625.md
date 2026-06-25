# PlainMath Conformance Harness Lane

## Scope
- Bead: `plib-wj70q.3`.
- Target branch: `plainmath-parity-20260625`.
- Owned artifacts:
  - `lanes/pandoc/tests/PlainMathConformanceTest.php`
  - `lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
  - `lanes/pandoc/notes/plainmath-conformance-20260625.md`

## Harness Shape
- The harness is static and local. It does not shell out to Pandoc, Cabal,
  TexMath, MathJax, KaTeX, or any network service.
- Fixture cases carry:
  - `id`: stable case name for failure output.
  - `family`: parser/writer feature family.
  - `tex`: source TeX consumed by `HtmlWriter`.
  - `display`: inline/display mode.
  - `upstream`: TexMath commit plus reader/writer fixture provenance.
  - `expectedMathML`: expected XHTML-safe MathML including the local
    `<semantics>` wrapper and `application/x-tex` annotation.
- `PlainMathConformanceTest.php` renders each case through
  `HtmlWriter(['writerHTMLMathMethod' => 'mathml'])`, parses both expected and
  actual XML with `DOMDocument`, compares canonical XML, and asserts:
  - MathML namespace is present.
  - display mode is correct.
  - exactly one TeX source annotation is preserved.
  - expected MathML stays XML-parseable.
- The EPUB integration test writes inline and display corpus cases through
  `PandocConverter::write(..., 'epub3')`, verifies the OPF `mathml` property,
  parses generated XHTML, extracts MathML nodes, and reuses the same normalized
  assertions.

## Corpus Selection
- Upstream reference: local TexMath cache at commit `17089967`, matching the
  supervisor brief's `1708996` family. The runnable PHP tests snapshot selected
  expectations rather than reading that cache at runtime.
- Current corpus count after custom environment expansion: 51 passing MathML
  fixtures, 7 fallback fixtures, and 3 known gaps.
- Initial passing corpus:
  - `script-super`: `x^2`, from `test/reader/tex/03.test` and
    `test/writer/mml/03.test` subsets.
  - `implicit-product-identifiers`: adjacent bare letters split into individual
    MathML identifiers, matching TexMath's implicit product tokenization.
  - `direct-unicode-identifiers`: direct UTF-8 Greek identifiers parse as
    Unicode `mi` nodes instead of byte fallback output.
  - `sqrt-subscript`: `\sqrt{x_2}`, from `test/reader/tex/tokens.test` and
    `test/writer/mml/tokens.test` subsets.
  - `display-fraction-root`: `\frac{x^2}{\sqrt{y_1}}`, a small
    fraction/root subset of `01.test` style formulas.
  - `boxed-enclosure`: `\boxed{x^2 + y^2 + z^2}`, from `boxed.test`.
  - `pmatrix-two-by-two`: `\begin{pmatrix}1 & 2 \\ 3 & 4\end{pmatrix}`, from
    the matrix fixture family in `19.test`.
  - `infix-choose`: `n \choose k`, from `choose.test` style infix fractions.
  - `macro-square`: one-argument `\newcommand` expansion.
  - `macro-optional-default`: optional/default macro argument expansion through
    fenced norm notation.
  - custom environment macros: `\newenvironment` and `\renewenvironment`
    expansion for delimiters, optional/default arguments, and array wrappers.
  - `declared-operator`: `\DeclareMathOperator*` expansion to
    `\operatorname*`.
  - `ignorable-label-tag-comment`: TeX comments, labels, tags, `\nonumber`,
    and `\allowbreak` are ignored during parsing while the original annotation
    is preserved.
  - `align-environment`: AMS `align` table parsing.
  - `equation-environment`: `equation` parses as a grouped formula rather than
    a table.
- `plib-wj70q.18` fixture expansion promoted ten additional passing,
  upstream-derived cases from local TexMath reader/writer families:
  - `quadratic-formula`: `test/reader/tex/01.test`.
  - `nested-left-right-fraction`: `test/reader/tex/02.test`.
  - `text-subscript-grouped-power`: `test/reader/tex/04.test`.
  - `integral-thin-negative-spaces`: `test/reader/tex/05.test`.
  - `double-sum-thin-space`: `test/reader/tex/06.test`.
  - `lim-arrow-subscript`: `test/reader/tex/09.test`.
  - `cases-mbox`: `test/reader/tex/12.test`.
  - `fraction-tfrac-control-space`: `test/reader/tex/14.test`.
  - `substack-sum`: `test/reader/tex/substack.test`.
  - `stackrel-arrow`: `test/reader/tex/stackrel.test`.
- Fallback corpus:
  - `empty-source-span`: empty TeX source returns an inline math span instead of
    partial MathML.
  - `recursive-macro-span`: recursive macro expansion stays a plain math span
    rather than emitting partial MathML.
  - malformed structural spans: incomplete fractions, roots, fences, and matrix
    environments, including custom macro environments, preserve the original
    source span rather than emitting partial MathML.

## Gaps And Blockers
- `writer-style-attrs`: TexMath MathML often adds `form`, `columnalign`, and
  text-align style attributes not emitted by the local bounded writer. The
  initial passing corpus snapshots current XHTML-safe output and records
  upstream fixture provenance; future parser lanes can tighten expectations
  when writer parity is implemented.

## Expansion Rules
- Add a fixture only when the TeX source parses through a representative
  formula, not merely because a command name appears in a lookup table.
- Keep upstream provenance in the fixture metadata.
- For known gaps, add either a skipped/metadata entry with the exact upstream
  fixture path and blocker, or promote it to `mathml` once runnable output is
  intentionally supported.
- Keep runtime tests hermetic: no shell-outs, no generated upstream calls, and
  no network access.
