# PlainMath Macros And Lexing Lane

Issue: `plib-wj70q.6`

Target: `plainmath-parity-20260625`

## Scope

This lane owns the current `HtmlWriter` PlainMath front end for TexMath-style
macro definitions, comments, ignorable constructs, command tokenization, and
fallback behavior. It does not introduce runtime shell-outs or a new parser
package.

Controlling inputs:

- `plainmath-supervisor-20260625.md`
- `plainmath-texmath-inventory-20260625.md`
- `plainmath-conformance-20260625.md`
- Shared TexMath cache at `170899673ee31de9096e178605e8da31a36e4185`

## Upstream Behavior Audited

TexMath handles this family in two front-end passes:

- `Readers/TeX/Macros.hs` parses leading `\newcommand`, `\renewcommand`,
  `\providecommand`, `\newenvironment`, and `\DeclareMathOperator` definitions,
  then applies macros with a bounded fixed-point expansion limit.
- `Readers/TeX.hs` treats comments, `\label{...}`, `\tag{...}`,
  `\tag*{...}`, `\nonumber`, whitespace, and `\allowbreak` as ignorable during
  expression parsing.
- `Commands.hs` maps control-space `\ `, escaped newline, `\,`, `\:`, `\;`,
  `\>`, and `\!` to spacing expressions.

## Implemented Slice

`HtmlWriter` now performs a bounded TeX front-end normalization before the
existing string-to-MathML parser:

| Area | Local behavior |
| --- | --- |
| Command macros | Parses `\newcommand`, `\renewcommand`, and `\providecommand`, including optional `*`, braced or direct control-sequence names, one-digit argument counts, optional default arguments, and braced bodies. |
| Macro application | Expands user macros in a fixed-point loop capped at `2 * macro_count + 1`, with later definitions shadowing earlier definitions. |
| Macro arguments | Supports braced arguments, control-sequence arguments, single-character arguments, and optional macro arguments with defaults. |
| User environments | Parses `\newenvironment` and `\renewenvironment` definitions and expands matching `\begin{...}` / `\end{...}` invocations, including optional/default arguments for the bounded corpus cases. |
| `\DeclareMathOperator` | Parses starred and unstarred definitions and expands use sites to existing `\operatorname` handling. |
| Comments | Skips `%` comments to newline or end of source before parsing. |
| Ignorable commands | Skips `\label{...}`, `\tag{...}`, `\tag*{...}`, `\notag`, `\nonumber`, and `\allowbreak`. |
| Command tokenization | Adds TexMath-compatible spacing for control-space `\ `, escaped newline, and `\>`. Existing spacing commands remain covered. |
| Fallback | Non-terminating macro expansion declines generated MathML so the existing escaped math span fallback preserves the original TeX instead of emitting partial MathML. |
| Annotation | Generated MathML still annotates the original unexpanded TeX source. |

The rebased conformance corpus now covers these macro/lexing cases:

- `macro-square`
- `macro-optional-default`
- `newenvironment-delimiters`
- `renewenvironment-delimiters`
- `newenvironment-optional-default`
- `newenvironment-array-wrapper`
- `declared-operator`
- `ignorable-label-tag-comment`
- `control-space-token`
- `align-environment`
- `equation-environment`
- `recursive-macro-span`

## Accepted Gaps

| Gap | Reason |
| --- | --- |
| Structured diagnostics | Recursive macros now fall back predictably, but there is no parser diagnostic object to expose the fixed-point failure. |
| Full TeX macro grammar | The local prepass intentionally supports a bounded subset. It does not implement package loading, category-code changes, arbitrary delimited parameters, or TeX engine conditionals. |
| AST-grade token model | This patch improves front-end behavior in the existing private parser. It does not replace the offset/string parser with the AST/token-stream architecture proposed by the architecture lane. |

## Verification

Focused checks run during this slice:

- `php -l lanes/pandoc/src/HtmlWriter.php`
- `php -l lanes/pandoc/tests/fixtures/plainmath-conformance-corpus.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainMathConformanceTest.php`

Full lane verification should also include `EpubWriterTest.php` and the full
`lanes/pandoc/tests` suite before merge-queue submission because `HtmlWriter`
changes affect EPUB MathML output.
