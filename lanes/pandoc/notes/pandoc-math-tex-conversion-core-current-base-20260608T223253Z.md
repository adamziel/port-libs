# Pandoc Math/TeX Xcolor Model Slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T223253Z`
Base accepted HEAD: `a93e698ac06f7885c2a47509237e09731628d097`
Date: 2026-06-08 UTC

## Source Truth

This slice targets a bounded texmath-style color parsing gap in the native PHP
Math/TeX support library. The accepted converter already handled grouped
`\color{...}{...}`, declaration-style `\color{...}`, and `\textcolor{...}{...}`;
this patch adds the common xcolor optional model forms that Pandoc/texmath
sources can preserve in inline math: `[HTML]`, `[RGB]`, `[rgb]`, `[gray]`, and
`[named]`.

The local isolated worktree does not contain a hydrated Pandoc or texmath
checkout, so the executable source truth is the lane's accepted Math/TeX
contract plus the red-first PHP probe. No Pandoc, texmath executable, MathJax,
KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner,
external converter, online service, live provider test, or live-service
provider test was executed.

## Implementation

- Reused the converter's existing TeX optional-bracket and brace readers to
  parse color models before the color value group.
- Normalized `[HTML]{336699}` and `[HTML]{#336699}` to `#336699`.
- Normalized `[RGB]{51,102,153}` integer components and `[rgb]{0.2,0.4,0.6}`
  unit components to the same MathML `mathcolor="#336699"` value.
- Normalized `[gray]{.5}` to `#808080`.
- Preserved `[named]{reviewblue}` through the existing bounded CSS-name guard.
- Kept malformed models fail-closed for short HTML hex values, out-of-range
  RGB/rgb/gray values, empty model names, and unsupported models such as cmyk.
- Updated the WordPress Math/TeX handoff example so reviewer-visible TeX source
  remains unchanged while emitted MathML carries normalized semantic colors.

## Verification

Red-first probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\textcolor[HTML]{336699}{x}");'
PHP Fatal error:  Uncaught InvalidArgumentException: Expected TeX text group at offset 10
```

Final focused verification:

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS converts bounded tex xcolor model arguments to mathml colors
1 test files, 955 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

## Status Delta

- `lane-status.json`: `phpPass` moves `1928 -> 1929`.
- `UPSTREAM_TEST_MANIFEST.json`: mapped denominator moves `2350 -> 2351`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 101`.

## Dependency Closure

No new support component is needed. This reuses native PHP `MathTexConverter`
parsing, MathML source annotations, accessibility alt/intent metadata,
MarkdownReader math spans, and WordPressBlockWriter handoff. Full upstream
Pandoc/texmath runner parity remains out of scope until a hydrated upstream
checkout and an explicitly authorized non-mutating runner plan are available.

## Non-Overlap

This does not repeat accepted Math/TeX work for alignedat, multline/multlined,
starred matrix aliases, array width/preamble hooks, bangle infix fractions,
modular commands, TeX comments, hyperref wrappers, siunitx, mathchoice,
prescript, sideset, buildrel relation placement, large/operator/relation
aliases, overbracket/underbracket, or color declaration scoping. The new mapped
case is only bounded xcolor optional model normalization for native MathML and
WordPress handoff.

## Follow-Up

A non-overlapping follow-up can handle `\colorbox`/`\fcolorbox` metadata
handoff, additional delimiter/operator metadata, or another guarded equation
environment gap, still without invoking Pandoc, texmath, TeX engines, MathJax,
KaTeX, Cabal/Haskell runners, online services, live provider tests, or
live-service provider tests.
