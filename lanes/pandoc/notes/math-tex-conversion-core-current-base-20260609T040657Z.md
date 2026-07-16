# Math TeX Conversion Core Current Base 20260609T040657Z

## Behavior

This slice extends the native `MathTexConverter` siunitx unit handoff to map bounded prefixed unit aliases from texmath's `siUnitMap` into MathML text units. Covered aliases include `\mg`, `\mL`, `\nm`, `\MHz`, `\kPa`, `\us`, `\uJ`, `\umol`, `\kWh`, and `\kmol`.

The WordPress math handoff example now includes a review-packet smoke for:

```tex
\si{\mg\per\mL} + \qty{532}{\nm} + \SI{20}{\MHz} + \unit{\kPa} + \si{\us}
```

## Source Truth

Upstream behavior source: texmath `siUnitMap` in `src/Text/TeXMath/Readers/TeX/Commands.hs`:

https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs

This is a bounded support-library port of command-to-unit-token handoff behavior, not a TeX engine implementation.

## Red-First Evidence

Before the change, the local native converter rejected representative prefixed units:

```text
ERR \si{\mg\per\mL} :: Unsupported TeX siunitx unit \mg
ERR \qty{532}{\nm} :: Unsupported TeX siunitx unit \nm
ERR \SI{20}{\MHz} :: Unsupported TeX siunitx unit \MHz
ERR \unit{\kPa} :: Unsupported TeX siunitx unit \kPa
ERR \si{\us} :: Unsupported TeX siunitx unit \us
```

## Verification

Baseline focused test before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1211 assertions, 0 failures
```

Final focused test after the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1223 assertions, 0 failures
```

Final WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

## Mapping Delta

- `phpPass`: 2275 -> 2276
- `benchmarkDenominator.mapped`: 2677 -> 2678
- `mathTexConversionCoreCases`: 14 -> 15
- `mappedMathTexConversionCoreCases`: 14 -> 15
- `mathTexConversionCoreAssertions`: 85 -> 97

## Non-Overlap

This slice does not repeat the prior common siunitx alias work for named units such as `\km`, `\newton`, `\joule`, and `\kelvin`. It targets prefixed texmath unit aliases that were still rejected on the current accepted base.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `MathTexConverter` siunitx parsing, focused `MathTexConverterTest` coverage, and the existing WordPress math handoff example. No Pandoc, Cabal, Haskell runner, TeX/PDF engine, Typst, browser renderer, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.
