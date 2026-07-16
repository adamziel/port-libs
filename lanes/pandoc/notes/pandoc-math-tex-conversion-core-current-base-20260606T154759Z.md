# Pandoc Math/TeX Conversion Core: Math Class Wrappers

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T154759Z`
Base accepted HEAD: `fcc419a73630550abf6ce8bf9772fa5c0f06b701`
Date: 2026-06-06 UTC

## Behavior

This slice adds bounded native Math/TeX conversion support for TeX math atom
class wrappers:

- `\mathop`
- `\mathrel`
- `\mathbin`
- `\mathord`
- `\mathopen`
- `\mathclose`
- `\mathpunct`
- `\mathinner`

The converter parses a non-empty braced group or single following math atom and
emits MathML `<mrow data-tex-math-class="...">` metadata so downstream writers
can retain the TeX atom-class handoff. Explicit `\limits` and `\nolimits`
placement after a `\mathop` wrapper is preserved by the existing script
placement path. Missing, empty, or script-marker-only class arguments remain
rejected.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Haskell runner, browser, or
online service was invoked.

## Evidence

Red-first probe before implementation:

```console
$ php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\mathop{\\operatorname{argmax}}\\limits_{p_i \\in P}^{\\text{draft}} f(p_i) + a \\mathrel{\\approx} b + x \\mathbin{\\ast} y", true), "\n";'
```

The baseline emitted literal unsupported command markers such as
`<mi>\mathop</mi>`, `<mi>\mathrel</mi>`, and `<mi>\mathbin</mi>` instead of
classed MathML rows.

Baseline focused test before this slice:

```console
$ php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 513 assertions, 0 failures
```

Final focused verification:

```console
$ php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

$ php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

$ php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

$ php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 523 assertions, 0 failures

$ php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok

$ git diff --check -- lanes/pandoc
(no output)
```

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `lane-status.json` `phpPass`: `1357` -> `1358`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1771` -> `1772`.
- `inventory.mathTexConversionCoreCases`: `13` -> `14`.
- `inventory.mappedMathTexConversionCoreCases`: `13` -> `14`.
- `inventory.mathTexConversionCoreAssertions`: `72` -> `82`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native
`MathTexConverter`, Markdown math handoff, LaTeX writer, and WordPress block
writer paths.

Out of scope for this slice: full texmath/Pandoc parity, arbitrary TeX macro or
package execution, display-style-sensitive movable limit inference, MathJax or
KaTeX rendering, TeX/PDF engines, Haskell/Cabal runners, and browser services.

## Non-Overlap

This does not repeat the already accepted Math/TeX clusters for alignedat,
flalign, eqnarray, multline, equation wrappers, row tags, equation references,
array width columns, array hooks, multicolumn, hlines, clines, operator names,
starred operator names, explicit display limits, or substack handling.

## Follow-Up

Potential next bounded Math/TeX work: display-style-sensitive movable limits,
bounded `\mathchoice`, or additional atom/layout behavior that preserves TeX
semantics without invoking external TeX or Pandoc runners.
