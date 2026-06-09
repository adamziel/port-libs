# Pandoc Math/TeX Current-Base Over/Under Group Accents

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T070732Z`
Base: `030e94cf137586963da96dca64555cebe2ff01ee`

## Behavior

Implemented one bounded native Math/TeX command-family cluster:

- `\overparen{...}` emits a MathML `<mover>` with U+23DC top parenthesis.
- `\underparen{...}` emits a MathML `<munder>` with U+23DD bottom parenthesis.
- `\overgroup{...}` emits a MathML `<mover>` with U+23E0 top grouping bracket.
- `\undergroup{...}` emits a MathML `<munder>` with U+23E1 bottom grouping bracket.
- Grouped and unbraced token arguments are both accepted through the existing
  `parseRequiredTexToken()` path, so scripts bind to the whole accent node.
- Source TeX annotations and accessibility `alttext`/`intent` metadata are
  preserved for WordPress review handoff.

## Red-First Evidence

Before the change, a bounded native PHP probe rendered these commands as
literal identifiers:

```text
\overparen{x+y} => <mi>\overparen</mi>
\underparen{x+y} => <mi>\underparen</mi>
\overgroup{x+y} => <mi>\overgroup</mi>
\undergroup{x+y} => <mi>\undergroup</mi>
```

## Verification

Baseline focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1358 assertions, 0 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1372 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

## Counter Delta

- `lane-status.json` `phpPass`: `2470 -> 2471`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2851 -> 2852`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 99`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MathTexConverter` parser, MathML serializer, source-TeX semantics annotation
path, accessibility metadata path, focused PHP test runner, and the existing
WordPress math handoff example.

No Pandoc, Cabal/Haskell runner, texmath executable, Word, LibreOffice,
zip/unzip, external converter, TeX/PDF engine, MathJax, KaTeX, browser
renderer, online service, live provider test, live-service provider test, or
root harness was run.

## Non-Overlap

This does not repeat accepted Math/TeX coverage for over/under braces,
over/under brackets, combined `\overunderset`/`\underoverset`, buildrel,
extensible arrows, AMS layout environments, color/xcolor/colorbox, cancel,
phantom, math alphabet, SI units, equation tags/references, or syntax
highlighting. A follow-up could cover another bounded texmath command alias or
safe layout metadata family that is not already mapped.
