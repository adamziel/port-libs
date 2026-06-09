# Math TeX Conversion Core Current Base 2026-06-09T04:31:40Z

## Scope

Mapped one bounded texmath/siunitx alias cluster into the native PHP MathML
handoff: electric, energy, capacitance, force, resistance, and dose unit
commands used by `\si`, `\unit`, `\SI`, and `\qty`.

Added aliases include `\mohm`, `\kohm`, `\Mohm`, `\pV`, `\uV`, `\nV`, `\MN`,
`\meV`, `\keV`, `\MeV`, `\GeV`, `\fF`, `\pF`, `\gray`, and `\sievert`.

## Source Truth

- Upstream behavior source: texmath's bounded TeX command table in
  `Text.TeXMath.Readers.TeX.Commands`, especially the `siUnitMap` entries:
  https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs
- Local contract: preserve native MathML source annotations and accessibility
  text without invoking Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, or
  Haskell runners.

## Red-First Evidence

Before the patch, the selected aliases were unsupported by the native converter:

```text
ERR \si{\mohm\per\kohm} :: Unsupported TeX siunitx unit \mohm
ERR \qty{12}{\pV\per\uV} :: Unsupported TeX siunitx unit \pV
ERR \SI{3}{\MN} :: Unsupported TeX siunitx unit \MN
ERR \si{\meV\per\GeV} :: Unsupported TeX siunitx unit \meV
ERR \unit{\fF\per\pF} :: Unsupported TeX siunitx unit \fF
ERR \qty{5}{\gray\per\sievert} :: Unsupported TeX siunitx unit \gray
```

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 1233 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 1247 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.
- Syntax checks passed:
  - `php -l lanes/pandoc/src/MathTexConverter.php`
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Diff whitespace check: `git diff --check -- lanes/pandoc` passed with no
  output.
- Root harness: not run - isolated micro-slice.

## Delta

- `phpPass`: `2305 -> 2306`
- `benchmarkDenominator.mapped`: `2705 -> 2706`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 99`
- Focused assertions: `1233 -> 1247` in `MathTexConverterTest.php`.

## Non-Overlap

This slice does not repeat the accepted common siunitx alias slice
(`\km`, `\newton`, `\joule`, `\kelvin`, etc.) or the accepted prefixed
alias slice (`\mg`, `\mL`, `\nm`, `\MHz`, `\kPa`, `\us`, `\uJ`, `\umol`,
`\kWh`, `\kmol`). It also avoids the accepted color/xcolor, colorbox,
cancel/cancelto, matrix/AMS environment, macro declaration, prime, arrow,
and legacy DOC/CFB slices.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
`MathTexConverter`, `MathTexConverterTest`, and WordPress math TeX handoff
example. Full upstream Pandoc/texmath runner parity remains a separate
upstream-runner dependency task because it requires hydrated upstream checkouts
and Haskell test executables.
