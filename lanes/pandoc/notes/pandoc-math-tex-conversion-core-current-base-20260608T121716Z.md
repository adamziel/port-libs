# Pandoc Math/TeX Current-Base Environment Comments

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T121716Z`

Base: `a00e14f093dc188f213b61df223920efd39f90c6`

## Behavior

- `MathTexConverter` now skips raw TeX `%` line comments before top-level environment row scanning.
- Commented `&` no longer creates extra alignment cells.
- Commented `\\` no longer creates extra rows.
- Full-line comments between environment rows remain annotation-only instead of rendered table content.
- Source TeX comments remain preserved in MathML `<annotation encoding="application/x-tex">`.

## Source Truth

- This is bounded native PHP support for the Pandoc Math/TeX/texmath contract that percent comments are parser input comments, not rendered math tokens.
- The local upstream Pandoc/texmath checkout was unavailable in this isolated worktree, so this slice used current lane Math/TeX manifest history and the accepted native `MathTexConverter` comment behavior as the source-truth boundary.
- No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Red-First Evidence

Before the patch, local probes showed:

- `\begin{aligned}p_i &= m_i % hidden & ignored ...` rendered comment payload after `&` as an extra alignment cell.
- `\begin{array}{cc}p_i & m_i % hidden \\ no row sep ...` treated the commented `\\` as a row break.
- `\begin{array}{cc}p_i & m_i \\ % full row comment & hidden ...` rendered the full-line comment payload as table content.

The focused test `ignores bounded tex comments while splitting environment rows` now covers those three cases plus strict trailing-row validation with a commented final row separator in `smallmatrix`.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` -> `1 test files, 734 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` -> `1 test files, 745 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` -> `math tex handoff self-test ok`.
- Required final lint, JSON validation, and `git diff --check -- lanes/pandoc` were run in the final verification pass for this handoff.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one named PHP PASS case.
- Added `+11` focused Math/TeX assertions.
- `lane-status.json` `phpPass`: `1638 -> 1639`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2058 -> 2059`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 96`.

## Dependency Closure

No new support component is needed. This reuses the existing bounded native PHP `MathTexConverter` and WordPress math handoff example. Full upstream Pandoc/texmath runner parity remains out of scope for this implementation slice.

## Non-Overlap

This avoids recently accepted Math/TeX surfaces including `\mathchoice`, modular commands, bangle infix fractions, raw expression comments, array width columns, multline/multlined, alignedat, equation wrappers, row tags, and notag/nonumber behavior. The patch is scoped to comment handling inside environment row scanning.

## Follow-Up

Potential follow-up: handle comments that hide `\end{...}` environment terminators in `readEnvironmentContent` if needed by a future bounded texmath parity slice.
