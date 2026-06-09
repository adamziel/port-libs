# Math TeX Conversion Core Current Base 20260609T051011Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T051011Z`
- Base accepted HEAD: `516b4c2368ab923eeb7c71f762618468a7a4d437`
- Behavior cluster: bounded plain TeX matrix command groups for `\matrix`, `\pmatrix`, `\bmatrix`, and `\cases` with top-level `\cr` / `\crcr` row separators.

## Source Truth And Non-Overlap

The accepted math/TeX converter already mapped LaTeX environment forms such as `\begin{matrix}`, `\begin{pmatrix}`, `\begin{bmatrix}`, and `\begin{cases}` into native MathML tables and fences. This slice ports the matching plain TeX command-form contract by reusing the existing native PHP table/fence renderer instead of shelling out to Pandoc, texmath, MathJax, KaTeX, TeX, or browser renderers.

This does not overlap the latest accepted YAML metadata slice, TeX prime notation, environment-form matrix/cases support, mathtools starred matrix aliases, compact `smallmatrix` / `subarray`, or PDF engine handoff planning.

## Red-First Evidence

Before the implementation, a local probe against `MathTexConverter` emitted literal identifiers:

```text
\matrix{p_1 & m_1 \cr p_2 & m_2} -> <mi>\matrix</mi> ... <mi>\cr</mi>
\pmatrix{a & b \cr c & d} -> <mi>\pmatrix</mi> ... <mi>\cr</mi>
\cases{p_i & p_i \in P \cr 0 & \text{otherwise}} -> <mi>\cases</mi> ... <mi>\cr</mi>
```

## Implemented

- Added a bounded command-to-matrix-environment map in `MathTexConverter`.
- Added plain TeX matrix command parsing for required brace groups.
- Normalized only top-level `\cr` and `\crcr` into the existing row-splitting path, preserving nested group text and comments.
- Reused the existing MathML `mtable`, fence, row-spacing, and source-annotation behavior.
- Added focused tests covering display `\matrix`, fenced `\pmatrix`, bracketed `\bmatrix`, `\cases`, `\cr[2pt]` row spacing, trailing `\cr`, `\crcr`, literal-command suppression, and malformed/missing groups.
- Added a WordPress math TeX handoff summary smoke for command-form matrix conversion.

## Verification

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1272 assertions, 0 failures
```

Final focused checks:

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1290 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

Focused delta: `+1` PHP PASS line and `+18` focused assertions in `MathTexConverterTest.php`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP TeX tokenizer, matrix row splitter, MathML table/fence renderer, and WordPress math handoff example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

## Follow-Up

Potential non-overlapping follow-up: bounded TeX alignment command forms outside matrix groups, macro-expanded table-cell annotation provenance, or additional texmath-compatible delimiter command forms. Do not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests from this lane.
