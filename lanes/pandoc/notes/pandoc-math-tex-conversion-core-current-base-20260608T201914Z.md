# Pandoc Math/TeX Plain Root Slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T201914Z`

Base accepted HEAD: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Behavior

- `MathTexConverter` now recognizes bounded plain TeX `\root <degree> \of <radicand>` syntax.
- Numeric, expression, and grouped degrees are parsed through the existing TeX fragment parser.
- Grouped radicands keep existing script, fraction, and operator-name handling, then render as MathML `mroot`.
- Source TeX annotations preserve the caller's original `\root...\of` expression.
- Missing degree, missing `\of`, and empty radicand forms fail closed before MathML handoff.

The WordPress Math/TeX handoff example now includes a plain root audit formula and verifies the visible TeX span, native MathML `mroot` output, and source annotation.

## Source Truth

The local upstream cache for this isolated worktree did not include a pinned Pandoc or texmath source checkout, so this slice used the lane's accepted Math/TeX converter contract plus the immediately adjacent native root/indexed-root MathML behavior as the bounded source of truth. No external converter or upstream runner was used as progress.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Evidence

Baseline focused check:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 839 assertions, 0 failures`

Red-first probe:

- `php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\root 3 \\of{x_i + y_i}", true), "\n";'`
- Result before implementation included literal `<mi>\root</mi>` and `<mi>\of</mi>` nodes.

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 850 assertions, 0 failures`
- Focused assertion delta: `+11`

Example smoke:

- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
- Result: `math tex handoff self-test ok`

Syntax checks passed for:

- `lanes/pandoc/src/MathTexConverter.php`
- `lanes/pandoc/tests/MathTexConverterTest.php`
- `lanes/pandoc/examples/wordpress-math-tex-handoff.php`

Lane diff check: `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Added one named PHP PASS case.
- Added `+11` focused Math/TeX assertions in `MathTexConverterTest.php`.
- `lane-status.json` `phpPass`: `1802 -> 1803`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2225 -> 2226`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 96`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `MathTexConverter` command dispatch, fragment parsing, group/atom parsing, MathML serialization, source TeX annotations, focused Math/TeX tests, and the existing WordPress Math/TeX handoff example.

Full upstream Pandoc/texmath runner parity remains out of scope for this implementation slice and still requires a hydrated upstream checkout plus an explicitly authorized non-mutating runner plan.

## Non-Overlap

This avoids recent Math/TeX surfaces for `\sqrt[...]` indexed roots, direct and generalized fractions, scripts, source annotations, fences, sized delimiters, operators, matrices, aligned/cases/array/alignedat/multline environments, TeX comments, color/phantom/cancel/smash/overlap commands, modulo commands, large-operator aliases, and declared operators/delimiters. The patch owns only bounded plain TeX `\root...\of` root syntax.

## Follow-Up

A non-overlapping follow-up could add guarded support for `\buildrel{...}\over` relation placement or another plain-TeX math primitive not already covered by the existing root, fraction, script, array, AMS environment, comment, large-operator, color, phantom, cancel, or modulo slices.
