# Math TeX Conversion Core Current Base 20260609T052244Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T052244Z`
- Base accepted HEAD: `aeac7627505caef0c7f45b74c533b70ec36e1807`
- Behavior cluster: bounded plain TeX alignment command groups for `\eqalign` and `\displaylines`, including top-level `\cr` / `\crcr` row separators and optional row-spacing metadata.

## Source Truth And Non-Overlap

The previous accepted math/TeX slice mapped plain TeX matrix command groups (`\matrix`, `\pmatrix`, `\bmatrix`, `\cases`) by reusing the native PHP MathML table renderer. This slice ports the next bounded plain TeX alignment command contract through the same tokenizer, top-level `\cr` normalizer, alignment row splitter, and MathML table renderer.

No hydrated Pandoc upstream checkout was available under `/home/claude/port-libs/.upstream-cache/pandoc` in this isolated worktree, and no Pandoc, Haskell, texmath, MathJax, KaTeX, TeX, browser renderer, or online service was invoked. Source truth for this slice is the existing lane math/TeX contract and the prior accepted follow-up note for bounded TeX alignment command forms.

This does not overlap environment-form matrix/cases support, mathtools starred matrix aliases, compact `smallmatrix` / `subarray`, plain TeX matrix command groups, TeX prime notation, or PDF engine handoff planning.

## Red-First Evidence

After adding the focused alignment-command test and before implementation, the focused math test failed because command groups were still emitted as literal identifiers:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1292 assertions, 1 failures
```

The failing output contained literal `<mi>\eqalign</mi>` and `<mi>\cr</mi>` instead of a MathML table.

## Implemented

- Added bounded `\eqalign` and `\displaylines` command dispatch in `MathTexConverter`.
- Reused the existing top-level plain TeX `\cr` / `\crcr` row normalizer and row-spacing splitter.
- Rendered `\eqalign` as a two-column `mtable` with `columnalign="right left"`.
- Rendered `\displaylines` as a one-column centered `mtable`.
- Preserved source annotations, accessible table-row text, nested matrix command groups inside alignment cells, and `\cr[1ex]` row-spacing review metadata.
- Rejected missing groups, empty groups, wrong column counts, stray `&` in `\displaylines`, and malformed row-spacing brackets.
- Added WordPress math TeX handoff coverage so review packets expose the native MathML table shape instead of literal TeX command identifiers.

## Verification

Focused checks:

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1306 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok

git diff --check -- lanes/pandoc
no whitespace errors
```

Focused delta: `+1` PHP PASS line, `+16` focused assertions, and `+1` mapped math/TeX command case.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `MathTexConverter` tokenizer, plain matrix command row normalizer, alignment row splitter, MathML table renderer, focused PHP test runner, and lane-local WordPress math TeX handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Follow-Up

Potential non-overlapping follow-up: bounded equation-number alignment command forms or additional texmath-compatible delimiter command forms. Do not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, TeX/PDF engines, MathJax, KaTeX, browser renderers, external validators, online services, live provider tests, or live-service provider tests from this lane.
