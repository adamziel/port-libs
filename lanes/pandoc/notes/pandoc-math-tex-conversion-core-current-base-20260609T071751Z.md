# Pandoc Math/TeX Current-Base Generated SymbolMap Aliases

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T071751Z`
Base: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Behavior

Implemented one bounded native Math/TeX command-family cluster backed by
texmath's generated `Text.TeXMath.Unicode.ToTeX.symbolMap` table:

- `\nexists`, `\lneq`, `\gneq`, `\lnsim`, `\gnsim`, `\precapprox`,
  `\succapprox`, `\subsetneqq`, and `\supsetneqq` now emit MathML operator
  glyphs instead of literal TeX identifiers.
- `\nvdash`, `\nvDash`, `\nVdash`, `\nVDash`, and `\Vvdash` now emit bounded
  turnstile relation glyphs.
- `\varpropto`, `\smallsetminus`, `\backcong`, and `\blacktriangle` now map
  through the native operator command table.
- Source TeX annotations and accessibility metadata are preserved for
  WordPress review handoff.

Primary source truth:

- `https://github.com/jgm/texmath/blob/master/src/Text/TeXMath/Readers/TeX/Commands.hs`
- `https://github.com/jgm/texmath/blob/master/src/Text/TeXMath/Unicode/ToTeX.hs`

## Red-First Evidence

Before the change, a native PHP probe rendered the selected texmath
`symbolMap` commands as literal identifiers:

```text
\nexists literal
\lneq literal
\gneq literal
\precapprox literal
\succapprox literal
\subsetneqq literal
\supsetneqq literal
\lnsim literal
\gnsim literal
\varpropto literal
\smallsetminus literal
\backcong literal
\blacktriangle literal
\nvdash literal
\nvDash literal
\nVdash literal
\nVDash literal
\Vvdash literal
```

## Verification

Baseline focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1372 assertions, 0 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1390 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

## Counter Delta

- `lane-status.json` `phpPass`: `2482 -> 2483`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2861 -> 2862`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 103`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MathTexConverter` command table, MathML serializer, source-TeX semantics
annotation path, accessibility metadata path, focused PHP test runner, and the
existing WordPress math handoff example.

No Pandoc, Cabal/Haskell runner, texmath executable, Word, LibreOffice,
zip/unzip, external converter, TeX/PDF engine, MathJax, KaTeX, browser
renderer, online service, live provider test, live-service provider test, or
root harness was run.

## Non-Overlap

This does not repeat accepted Math/TeX coverage for text-mode aliases,
escaped symbols, generated symbol override aliases, extended relation aliases,
variant Greek/underbar aliases, over/under group accents, extensible arrows,
AMS layout environments, color/xcolor/colorbox, cancel, phantom, math
alphabet, SI units, equation tags/references, or syntax highlighting. A
follow-up can cover another bounded generated `symbolMap` alias cluster or a
parser behavior that still falls through as literal TeX.
