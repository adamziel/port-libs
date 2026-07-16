# Pandoc Math/TeX Current-Base Href Url Links

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T073203Z`
Base: `df259aa2eedc94083122c4983a2ea922c64e663c`

## Behavior

Implemented one bounded native Math/TeX command cluster:

- `\href{target}{content}` now emits linked MathML by wrapping parsed math
  content in an `<mrow href="...">`.
- `\url{target}` now emits linked visible URL text as
  `<mtext href="...">target</mtext>`.
- Source TeX annotations remain intact for reviewer audit.
- Accessibility `alttext` and `intent` still derive from rendered content.
- Link targets are intentionally bounded to `http`, `https`, `mailto`, local
  anchors, and local relative paths. Empty targets, empty href content,
  unsafe schemes, protocol-relative URLs, and whitespace/control-character
  targets fail before WordPress review packets trust them.

## Red-First Evidence

Before the change, a native PHP probe rendered these commands as literal
identifiers and tokenized link targets as math text:

```text
\href{https://example.test/review}{p_i + m_i} => <mi>\href</mi>...
\url{https://example.test/source} => <mi>\url</mi>...
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
1 test files, 1389 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

## Counter Delta

- `lane-status.json` `phpPass`: `2497 -> 2498`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2875 -> 2876`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 102`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MathTexConverter` token/group parser, MathML serializer, source-TeX
semantics annotation path, accessibility metadata path, focused PHP test
runner, and the existing WordPress math handoff example.

No Pandoc, Cabal/Haskell runner, texmath executable, Word, LibreOffice,
zip/unzip, external converter, TeX/PDF engine, MathJax, KaTeX, browser
renderer, online service, live provider test, live-service provider test, or
root harness was run.

## Non-Overlap

This does not repeat accepted Math/TeX coverage for `\hyperref`, equation
labels/references, accents, arrows, cases, over/under group wrappers, colors,
phantom/cancel/layout wrappers, SI units, AMS environments, matrices, arrays,
or delimiter/fraction handling. A follow-up could cover another bounded
texmath command alias or safe MathML metadata family that is not already
mapped.
