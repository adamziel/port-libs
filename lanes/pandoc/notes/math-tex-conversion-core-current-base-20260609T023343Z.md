# Math TeX Conversion Core - Quadruple Prime Handoff

Date: 2026-06-09 UTC

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T023343Z`

Base accepted HEAD: `baf3ce2966b31d81f7576b68e2155b8538ba2649`

## Source Truth

- No `port-pandoc-*.needs-lane-rework.md` note was present in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Local `.upstream-cache` does not contain a hydrated Pandoc or texmath checkout for this slice.
- Upstream texmath source truth was checked at `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`: its TeX reader prime parser canonicalizes four apostrophe primes to U+2057 quadruple prime and then continues parsing remaining primes.

## Implemented Behavior

- `MathTexConverter` now canonicalizes four-prime TeX shorthand such as `r''''` to `<mo>⁗</mo>` instead of four separate `<mo>′</mo>` tokens.
- Prime runs longer than four are emitted as bounded MathML rows of quadruple-prime chunks plus the remaining one, two, or three-prime glyph.
- Repeated `\prime` command runs in `\sideset` post-scripts reuse the same canonical prime renderer.
- Accessibility text and intent now name U+2057 as `quadruple prime` / `quadruple_prime`.
- The WordPress math/TeX handoff smoke now includes `r''''` and `s'''''_j` in an editable source span and checks the rendered MathML.

## Red Probe

Before the change, this local PHP probe produced four separate prime operators and accessibility text `prime prime prime prime`:

```sh
php <<'PHP'
<?php
require 'tools/bootstrap.php';
$c = new PortLibs\Pandoc\MathTexConverter();
echo $c->texToMathMl("r'''' + s'''''_j"), "\n";
echo $c->texToAccessibleMathMl("r''''"), "\n";
PHP
```

After the change, the same probe emits `<msup><mi>r</mi><mo>⁗</mo></msup>` and `alttext="r superscript quadruple prime"`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1107 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - `math tex handoff self-test ok`
- `php -l lanes/pandoc/src/MathTexConverter.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/pandoc`
  - passed

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP `MathTexConverter` prime parser, MathML serializer, accessibility annotation logic, and WordPress math handoff example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, MathJax, KaTeX, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat earlier accepted math slices for token command arguments, color token arguments, arrays, operator names, equation metadata, or 1-3 prime shorthand. The only new mapped behavior is the texmath-compatible four-prime canonical glyph and longer-prime-run composition.

## Follow-Up

- Continue richer bounded TeX parser parity in separate slices, especially additional texmath one-token wrapper cases and remaining MathML handoff aliases.
- Full upstream Pandoc runner parity still requires a hydrated Pandoc checkout and non-mutating Cabal dependency plan.
