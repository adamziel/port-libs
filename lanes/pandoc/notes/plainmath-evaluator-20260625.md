# PlainMath Parity Evaluator - 2026-06-25

Hook bead: `plib-wj70q.9`.

Scope: independent evaluator audit for the integrated PlainMath/generic TeX
parser parity branch. This note covers the local PHP PlainMath/MathML path in
`HtmlWriter`, the static conformance corpus, and the lane notes under
`lanes/pandoc/notes`. It does not evaluate or request JS rendering, DRM,
crypto authorization, runtime Haskell/Pandoc shell-outs, MathJax, KaTeX, or
network services.

## Corpus Status

| Bucket | Status | Count | Notes |
| --- | --- | ---: | --- |
| `mathml` | pass | 43 | Static upstream-derived cases cover scripts, roots, fractions, enclosures, matrices, infix fractions, command macros, optional macros, declared operators, labels/comments, AMS alignment/equation/gather/multline/eqnarray, delimiters, direct Unicode identifiers/operators, prime shorthand, atom coercion commands, recursive styled text, dimensioned spacing, nested delimiters, operator limits, substack, cases text, and representative spacing. |
| `fallback` | pass | 6 | Empty source, recursive macro expansion, and malformed structural commands remain plain math spans and do not emit partial MathML. |
| `knownGaps` | documented | 4 | The fixture metadata records blocked upstream cases that should not be counted as passing parity. |

The evaluator added one low-risk corpus case,
`unicode-identifiers-prime-shorthand`, because the integrated branch now
supports UTF-8 token reads and TeX prime suffixes. The older
`direct-unicode-token-types` gap was narrowed to
`unicode-symbol-category-parity`: tokenization is no longer byte-oriented, but
TexMath category semantics are still not represented.

The fixture expansion lane `plib-wj70q.18` added ten passing fixtures from the
local TexMath cache at `17089967`: `01.test`, `02.test`, `04.test`, `05.test`,
`06.test`, `09.test`, `12.test`, `14.test`, `substack.test`, and
`stackrel.test`.

## Lane Decisions

| Lane output | Decision | Reason |
| --- | --- | --- |
| Inventory | accepted | It identifies TexMath `readTeX` plus `writeMathML` as the ground truth, names fixture families, and separates parser gaps from bounded EPUB behavior. |
| Architecture | accepted with caveat | It correctly calls out the main blocker: `HtmlWriter` still parses directly to MathML strings instead of a typed expression tree. The migration plan remains necessary for full parity. |
| Conformance harness | accepted | The harness is static, hermetic, XML-normalized, and now tracks 43 passing MathML cases, six fallback cases, and known gaps. |
| Macro/operator preprocessing | accepted as partial parity | `\newcommand`, optional defaults, `\providecommand`, `\renewcommand`, comments, labels/tags, and `\DeclareMathOperator` have representative fixtures. Environment macros and typed operator metadata remain gaps. |
| Symbols/operators/scripts | accepted as partial parity | Direct Unicode tokenization and prime shorthand are covered. Command alias table growth is acceptable only where driven through formulas; category correction remains unimplemented. |
| Environments/arrays | accepted as bounded parser parity | AMS align/gather/matrix/cases families have tests. Layout-only TeX details remain out of scope for current MathML semantics. |
| Style/text/enclosure | accepted as bounded writer parity | Style aliases, enclosures, colors, phantom/padding, text, and named spacing have EPUB evidence. TexMath styled-Unicode rewriting and recursive text-mode parsing remain gaps. |

No current integrated lane is rejected as shallow command-table-only progress:
the accepted symbol/style expansions are exercised through formulas and EPUB or
conformance assertions. Future command-table additions should still be rejected
unless paired with representative formula tests.

## Prioritized Remaining Gaps

### P0 - Blocks Full TexMath Parity

| Gap | Impact | Evidence |
| --- | --- | --- |
| Typed expression model is absent | Without TexMath-like `Exp` nodes, PHP cannot reliably implement bin-to-ord correction, atom coercion, function application, or source-span diagnostics. | Architecture note, current `HtmlWriter` string-fragment parser, and `knownGaps.unicode-symbol-category-parity`. |
| TeX atom categories and bin-to-ord correction | `Bin`, `Rel`, `Open`, `Close`, `Pun`, `Ord`, and related category correction are not retained. This affects MathML operator semantics even when glyph output is correct. | Inventory and symbols/scripts notes; no corpus pass should be interpreted as category parity. |
| Function application after math operators | TexMath inserts invisible apply-function operators after `EMathOperator`; PHP emits normal-variant `mi` only. | Inventory and symbols/scripts notes; named-operator fixtures intentionally snapshot current bounded output. |
| Parser architecture remains string-fragment based | Identifier splitting is now fixed for representative implicit products, but parser-wide category correction and diagnostics still require a typed intermediate representation rather than direct XML string assembly. | Architecture note, `HtmlWriter` parser helpers, and `knownGaps.unicode-symbol-category-parity`. |

### P1 - Parser Breadth Gaps

| Gap | Impact | Evidence |
| --- | --- | --- |
| `\newenvironment` and `\renewenvironment` | Command macro expansion is covered, but custom environment definitions remain unsupported. | `knownGaps.macro-environments`. |
| Atom coercion beyond explicit commands | `\mathop`, `\mathrel`, `\mathbin`, `\mathord`, `\mathopen`, `\mathclose`, and `\mathpunct` have bounded fixture coverage, but broader bin-to-ord correction and ordinary delimiter categories still need typed categories. | Corpus atom-coercion fixtures and `knownGaps.atom-coercion-bin-context-correction`. |
| `\operatorname*` implicit limits metadata | Starred operator names expand and parse, but the star does not carry a durable limits flag through script parsing without explicit `\limits`. | Symbols/scripts note. |
| Text-mode semantics beyond recursive styles | Representative recursive `\text`/`\mbox`/style nesting is covered; TexMath still has richer text/style behavior and styled Unicode conversion. | Corpus `recursive-text-mode-styles` and style/text note. |
| Spacing semantics beyond bounded dimensions | `\hspace`, `\mspace`, `\kern`, `\mkern`, and named spacing have representative coverage; full TeX glue/layout semantics remain out of scope. | Corpus `dimensioned-spacing` and writer fidelity audit. |
| `\ensuremath`, SIUnitX, and package-like command families | Not represented in the PHP corpus and should remain expected gaps until a fixture owner scopes them. | Inventory note. |

### P2 - Output Fidelity And Layout Gaps

| Gap | Impact | Evidence |
| --- | --- | --- |
| TexMath-specific MathML attributes | Local MathML intentionally omits some `form`, per-cell `columnalign`, and CSS/text-align details. This is not malformed, but it is not byte-for-byte TexMath writer parity. | Conformance and environments notes. |
| Styled Unicode conversion | TexMath may rewrite styled identifiers to mathematical Unicode codepoints; PHP generally preserves children under `mstyle mathvariant`. | Style/text note. |
| Visual/browser rendering differences | Browser MathML layout differences are not parser parity failures. | Supervisor non-goals. |

## In Scope Versus Out Of Scope

In scope for future PlainMath parity:

- Native PHP parsing of TeX math source into a typed expression model.
- Static, hermetic conformance fixtures derived from TexMath behavior.
- MathML generation that preserves XML validity and source annotations.
- Parser diagnostics and predictable fallback for unsupported TeX.

Out of scope for this effort:

- JavaScript rendering support, MathJax, KaTeX, or browser visual parity.
- DRM, crypto authorization, credential handling, or protected-content access.
- Runtime shell-outs to Pandoc, Cabal, Haskell TexMath, or network services.
- EPUBCheck completeness, PDF/DOCX/ODT/CSL parity, and non-PlainMath features.

## Verification

- `php -l lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/pandoc/tests/PlainMathConformanceTest.php lanes/pandoc/tests/HtmlWriterTest.php lanes/pandoc/tests/EpubWriterTest.php`
  - 3 files, 10,916 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 13 files, 22,740 assertions, 0 failures.
