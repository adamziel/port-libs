# Math TeX Conversion Core Current Base 20260609T055907Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T055907Z`
- Base accepted HEAD: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`
- Behavior cluster: bounded TeX comparison relation aliases in MathML handoff.

## Source Truth And Non-Overlap

This slice extends the existing native `MathTexConverter` command-table contract
for safe TeX relation aliases. The converter already maps neighboring
texmath-style comparison relations such as `\lessgtr`, `\gtrless`,
`\lesseqgtr`, `\gtreqless`, `\nleq`, and `\ngeq`; this adds the missing direct
aliases `\nless`, `\ngtr`, `\leqgtr`, and `\geqless` so they render as MathML
operator tokens instead of literal command identifiers.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external
template engine, external converter, TeX/PDF engine, MathJax, KaTeX, browser
renderer, online service, live provider test, or live-service provider test was
invoked.

This does not overlap prior accepted Math/TeX slices for `\not` overlays,
braced negated relations, negative approximate relations, extended named
relations, Unicode symbol-map aliases, roots, fractions, matrices, arrays,
AMS row environments, equation references, comments, spacing commands, color,
phantom/cancel, math variants, accents, extensible arrows, or TeX prime
notation.

## Red-First Evidence

Baseline focused test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1321 assertions, 0 failures
```

Direct native probe before implementation:

```text
x \nless y + a \ngtr b + c \leqgtr d
=> <mi>\nless</mi>, <mi>\ngtr</mi>, and <mi>\leqgtr</mi>

x \nless y + a \ngtr b + c \leqgtr d + e \geqless f
=> accessibility alttext used literal command names instead of relation names
```

## Implemented

- Added `\nless` and `\ngtr` command-table mappings to the existing MathML
  relation operator path.
- Added `\leqgtr` and `\geqless` aliases to the existing less-equal-greater
  and greater-equal-less MathML operator output.
- Added a focused test case covering MathML output, source TeX annotations,
  accessibility alttext, accessibility intent, and no literal command fallback.
- Extended the WordPress math TeX handoff example and self-test with the same
  comparison relation alias review packet.

## Verification

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1327 assertions, 0 failures
```

Focused delta: `+1` PHP PASS line and `+6` focused assertions.

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses the native PHP
`MathTexConverter` tokenizer, command tables, MathML semantics annotations,
accessibility metadata extraction, focused PHP test runner, and the lane-local
WordPress math TeX handoff example. Full upstream Pandoc/texmath runner parity
remains a separate upstream-runner dependency task requiring a hydrated Pandoc
checkout and explicitly authorized Haskell/Cabal runner work.

## Status Delta

- `phpPass`: `2411 -> 2412`
- `benchmarkDenominator.mapped`: `2800 -> 2801`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 91`
- Focused `MathTexConverterTest.php`: `1321 -> 1327` assertions

## Follow-Up

Potential non-overlapping follow-up: another bounded TeX command-table gap,
MathML accessibility metadata refinement, or equation-layout parser edge with
red-first native assertions. Keep external renderers, Pandoc/Cabal/Haskell
runners, and online services out of this lane unless explicitly authorized.
