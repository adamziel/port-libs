# Pandoc Math/TeX DeclarePairedDelimiter Slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T143709Z`

Base accepted HEAD: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`

## Behavior

- `MarkdownReader` now recognizes bounded `\DeclarePairedDelimiter` lines before paragraph parsing.
- Both `\DeclarePairedDelimiter{\wpabs}{\lvert}{\rvert}` and `\DeclarePairedDelimiter\wpangle{\langle}{\rangle}` are preserved as `raw_tex` blocks.
- `MathTexConverter::macroDefinitionsFromDocument()` maps those declarations into one-argument `\left...\right...` templates, so declared delimiter macros render as native MathML fence nodes.
- Unsupported delimiter commands and empty delimiters fail closed before MathML handoff.
- Source TeX annotations preserve the caller's original math expression while WordPress review packets retain the raw declaration line.

## Source Truth

The local upstream cache for this isolated worktree did not include pinned Pandoc or texmath sources, so this slice used the lane's accepted Math/TeX declaration and delimiter-handoff contract plus the immediately preceding lane note that named `\DeclarePairedDelimiter` as a non-overlapping follow-up. No external converter or upstream runner was used as progress.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Evidence

- Focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 783 assertions, 0 failures`.
- Markdown reader regression: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - Result: `1 test files, 3791 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- Syntax checks passed for:
  - `lanes/pandoc/src/MathTexConverter.php`
  - `lanes/pandoc/src/MarkdownReader.php`
  - `lanes/pandoc/tests/MathTexConverterTest.php`
  - `lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one named PHP PASS case.
- Added `+16` focused Math/TeX assertions in `MathTexConverterTest.php`.
- `lane-status.json` `phpPass`: `1689 -> 1690`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2109 -> 2110`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 101`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `MarkdownReader` raw TeX declaration capture, `MathTexConverter` macro extraction and expansion, delimiter parsing, MathML semantics annotations, accessibility metadata, `WordPressBlockWriter`, and the existing WordPress Math/TeX handoff example.

Full upstream Pandoc/texmath runner parity remains out of scope for this implementation slice and still requires a hydrated upstream checkout plus an explicitly authorized non-mutating runner plan.

## Non-Overlap

This avoids recent Math/TeX surfaces for `\mathchoice`, prescripts, declared math operators, Markdown capture of `\DeclareMathOperator*`, TeX comments, environment row comments, alignedat, multline/multlined, eqnarray, equation wrappers, array width columns/hooks/multicolumns/rules, bangle infix fractions, modulo commands, hyperref wrappers, siunitx commands, and color/phantom/cancel/smash/overlap commands. The patch owns only bounded `\DeclarePairedDelimiter` declaration capture and one-argument delimiter macro MathML handoff.

## Follow-Up

A non-overlapping follow-up could add guarded support for `\DeclarePairedDelimiterX`, starred paired-delimiter invocation syntax, or additional delimiter diagnostics, still using native PHP and focused lane tests only.
