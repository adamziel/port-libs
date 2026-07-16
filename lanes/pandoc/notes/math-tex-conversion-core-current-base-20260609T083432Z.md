# Math/TeX Extended siunitx Alias Slice

Session: `port-dev-pandoc-math-tex-20260609T083432Z`
Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T083432Z`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

This slice extends the native PHP `MathTexConverter` siunitx command table with a bounded texmath `siUnitMap` alias cluster:

- `\mHz`, `\hL`, `\hl`, and `\knot`
- `\TeV` and `\mmHg`
- `\becquerel`, `\candela`, `\dalton`, `\tonne`, `\neper`, `\bel`, `\barn`, and `\katal`
- prefix composition for `\yocto\meter` and `\zetta\gram`
- `\astronomicalunit`, `\atomicmassunit`, and `\arcmin`

The aliases now render as semantic MathML `<mtext>` unit tokens, preserve the source TeX annotation, and flow through the WordPress math handoff example without invoking Pandoc, MathJax, KaTeX, TeX engines, browser renderers, or online services.

Source truth was the texmath command map:
`https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs` (`siUnitMap`). The local Pandoc upstream cache was unavailable in this isolated worktree.

## Red-First Evidence

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`

Result before the patch:

`1 test files, 1407 assertions, 0 failures`

Unsupported-unit probe before implementation:

`php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach (["\\si{\\mHz\\per\\hL}", "\\unit{\\TeV\\per\\mmHg}", "\\qty{42}{\\becquerel\\per\\candela}"] as $tex) { try { echo $tex, " => ", $c->texToMathMl($tex), PHP_EOL; } catch (Throwable $e) { echo $tex, " => ", get_class($e), ": ", $e->getMessage(), PHP_EOL; } }'`

Observed before the patch:

- `\si{\mHz\per\hL}` failed on unsupported `\mHz`.
- `\unit{\TeV\per\mmHg}` failed on unsupported `\TeV`.
- `\qty{42}{\becquerel\per\candela}` failed on unsupported `\becquerel`.

## Verification

After implementation:

- `php -l lanes/pandoc/src/MathTexConverter.php` -> no syntax errors
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` -> no syntax errors
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` -> no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` -> `1 test files, 1422 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` -> `math tex handoff self-test ok`
- `git diff --check -- lanes/pandoc` -> passed with no output

Root harness: not run - isolated micro-slice.

## Counter Delta

- `lane-status.json` `phpPass`: `2530 -> 2531`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2898 -> 2899`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 100`
- Focused `MathTexConverterTest.php`: `1407 -> 1422` assertions

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP MathTexConverter token parser, siunitx unit-command table, MathML serializer, source-annotation path, accessibility metadata path, focused test runner, and WordPress math handoff example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, MathJax, KaTeX, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted math/TeX slices for scalar SI aliases, prefixed unit aliases, electric/energy unit aliases, operator spacing, prime notation, equation wrappers, matrices, or accessibility annotations. It maps a distinct texmath `siUnitMap` cluster that was still rejected by the current native converter.

## Follow-Up

Next math/TeX work should choose a non-overlapping texmath parser or MathML handoff gap, such as additional command aliases, bounded environment semantics, or MathML annotation/accessibility parity.
