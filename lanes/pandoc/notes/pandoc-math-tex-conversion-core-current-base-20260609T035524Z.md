# Pandoc Math/TeX TexToken Box Arguments

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T035524Z`

Base accepted HEAD: `4cca1c57da8720c140326c22572dbfb45205f318`

## Behavior

- `MathTexConverter` now lets `\colorbox` and `\fcolorbox` consume a single
  TeX token as content, matching the lane's accepted bounded texToken argument
  contract used by fractions, roots, color, boxed, phantom, cancel, accents,
  layout wrappers, and extensible arrows.
- `\cancelto` now also consumes single TeX-token target and content operands,
  so `\cancelto0x_i` and `\cancelto\alpha\frac12` render through the existing
  native MathML cancel-to path.
- Existing braced group handling, empty-content rejection, source TeX
  annotations, accessibility metadata, and WordPress editable math spans are
  preserved.

## Red-First Evidence

Baseline focused test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1187 assertions, 0 failures
```

Red probes before implementation:

```text
\colorbox{yellow}x_i -> InvalidArgumentException: Expected TeX colorbox content group
\fcolorbox{red}{yellow}q_i -> InvalidArgumentException: Expected TeX fcolorbox content group
\cancelto0x_i -> InvalidArgumentException: Expected TeX cancelto target group
```

## Verification

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1200 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

Additional final checks:

```text
php -l lanes/pandoc/src/MathTexConverter.php
php -l lanes/pandoc/tests/MathTexConverterTest.php
php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
git diff --check -- lanes/pandoc
```

All reported clean in the final verification pass.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2260 -> 2261`.
- `benchmarkDenominator.mapped`: `2665 -> 2666`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 98`.
- Focused `MathTexConverterTest.php`: `1187 -> 1200` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP
`MathTexConverter` token parsing, MathML serialization, accessibility
metadata, `MarkdownReader` math spans, `WordPressBlockWriter` source
preservation, and the existing WordPress math/TeX handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath
executable, MathJax, KaTeX, TeX/PDF engine, browser renderer, external
converter, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat accepted slices for the broad texToken command-argument
cluster, color token arguments, colorbox/fcolorbox braced metadata, cancelto
braced metadata, text-mode token arguments, accents, extensible arrows,
matrix/AMS environments, equation labels/tags/references, delimiters, array
metadata, spacing, prime notation, math alphabet variants, or ODF/DOCX/EPUB
reader work.

## Follow-Up

Potential non-overlapping follow-up: additional one-token wrapper consumers,
remaining delimiter/operator aliases, or MathML accessibility refinements.
