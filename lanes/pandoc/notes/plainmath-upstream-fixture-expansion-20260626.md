# PlainMath Upstream Fixture Expansion

Date: 2026-06-26 UTC
Bead: `plib-wj70q.18`

## Scope

Expanded the static PlainMath conformance corpus with passing TexMath reader
fixtures. This is fixture promotion only: no Pandoc, TexMath, Haskell, Cabal,
TeX engine, MathJax, KaTeX, browser renderer, external converter, online
service, or runtime shell-out was used to derive expected output.

## Source Truth

- Local fixture cache inspected read-only:
  `/home/claude/port-libs/polecats/flint/port_libs/.upstream-cache/texmath`
- TexMath cache commit: `170899673ee31de9096e178605e8da31a36e4185`
- Source family: `test/reader/tex/*.test`

## Promoted Passing Fixtures

Added seven fixture-backed cases to `PlainMathStaticTexmathFixtureTest.php`:

- `test/reader/tex/quadratic_formula.test`
- `test/reader/tex/simple_sum_formula.test`
- `test/reader/tex/binomial_coefficient.test`
- `test/reader/tex/boxed.test`
- `test/reader/tex/phantom.test`
- `test/reader/tex/stackrel.test`
- `test/reader/tex/substack.test`

Each case asserts native PHP MathML structure plus the exact source TeX
annotation preserved by the PlainMath handoff.

## Counts

- Static upstream denominator remains `2,276` inspected upstream Pandoc
  test/data/benchmark files.
- Focused mapped behavior checks: `2,289 -> 2,296`.
- PlainMath static TexMath fixture cases: `0 -> 7` in this lane slice.

## Verification

Focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
```

Expected result: the focused PlainMath conformance test file passes without
external converter execution.
