# Math/TeX Starred Environment Alias Slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T215401Z`
Base accepted HEAD: `d291953d10cb3a81d9c31878d6d7b3226cc33af0`
Date: 2026-06-08 UTC

## Source Truth

Upstream texmath's TeX reader accepts an optional trailing `*` after registered
environment names and then expects the same optional star on the closing
environment. This slice ports the bounded native PHP behavior needed for
matrix-like environments already supported by `MathTexConverter`: `pmatrix*`,
`cases*`, and `smallmatrix*`.

Source reference inspected: `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`

## Implementation

- Added native normalization for supported starred matrix-like environment names
  while preserving the exact raw begin/end names for delimiter validation and
  source annotations.
- Reused the existing matrix/cases/smallmatrix row parsing and MathML rendering
  paths.
- Kept unsupported starred environment names fail-closed.
- Kept mismatched starred/non-starred delimiters rejected.
- Updated the WordPress math TeX handoff smoke so reviewer-editable source
  keeps the `*` while rendered MathML does not emit a literal star operator or
  identifier.

## Verification

Red-first probe before the implementation:

```text
php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); try { echo $c->texToMathMl("\\begin{pmatrix*}p_i & m_i \\\\ q_i & n_i\\end{pmatrix*}", true), "\n"; } catch (Throwable $e) { fwrite(STDERR, get_class($e) . ": " . $e->getMessage() . "\n"); exit(1); }'
InvalidArgumentException: Unsupported TeX environment pmatrix* at offset 16
```

Focused baseline before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 901 assertions, 0 failures
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
1 test files, 912 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

## Dependency Closure

No new support component is needed. This reuses native PHP MathTexConverter
environment parsing, row splitting, MathML rendering, and WordPress math handoff
paths. No Pandoc, texmath executable, MathJax, KaTeX, TeX/PDF engine, Cabal
solver/build/test command, Haskell runner, external converter, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted Math/TeX slices for alignedat, multline,
intertext, array width columns, comments, modular commands, bangle, binary or
relation operator aliases, large operator aliases, or equation wrapper tags.
Follow-up can handle any remaining optional-star aliases outside this bounded
matrix-like environment family.
