# Pandoc Math/TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T060702Z`
Base: `aea0bbc5620fdf1b622909ec6e5a23e6c3713930`

## Behavior

Bounded Math/TeX support now maps combined over/under wrappers into native
MathML:

- `\overunderset{above}{below}{base}` emits `<munderover>` with child order
  `base`, `below`, `above`.
- `\underoverset{below}{above}{base}` emits the same MathML child order.
- Both forms reuse the existing bounded `texToken` argument path used by
  `\overset` and `\underset`, so unbraced token arguments remain supported and
  later scripts bind to the whole stacked node.
- Source TeX annotations and accessibility `alttext`/`intent` annotations are
  preserved through the existing MathML handoff helpers.

This is a narrow, non-overlapping follow-up to the accepted `\overset`,
`\underset`, `\buildrel`, brace/bracket wrapper, extensible arrow, and explicit
operator limit slices. It does not add a TeX engine, MathJax, KaTeX, Pandoc,
or Haskell runner dependency.

## Red-First Evidence

Before the change, a native PHP probe emitted literal fallback identifiers:

```text
php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach(["\\overunderset{\\text{publish}}{\\operatorname{draft}}{p_i} + \\underoverset{0}{\\infty}{\\lim}_{n \\to \\infty} a_n","\\overunderset\\alpha0x_i + \\underoverset\\beta\\gamma y^2"] as $tex){echo $c->texToMathMl($tex, true), PHP_EOL;}'
... <mi>\overunderset</mi> ...
... <mi>\underoverset</mi> ...
```

## Verification

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1340 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

The focused math test baseline in this worktree was:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1321 assertions, 0 failures
```

## Counter Delta

- `lane-status.json` `phpPass`: `2420 -> 2421`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2809 -> 2810`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 104`

## Dependency Closure

No new support component is needed. The slice reuses the native PHP TeX parser,
bounded token argument reader, MathML emitter, source TeX semantics annotation
path, and accessibility annotation path. Full upstream Pandoc/texmath runner
parity remains a separate upstream-runner dependency task requiring a hydrated
Pandoc checkout and Haskell test executables.

## Exclusions

Did not run Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip,
tar, gzip, LZ4, TeX/PDF engines, browser renderers, external validators, online
services, live provider tests, or the root harness.
