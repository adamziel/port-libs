# pandoc math TeX conversion current-base slice 2026-06-09

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T054801Z`

Base accepted HEAD: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`

## Behavior

Implemented a bounded math/TeX command-table relation alias cluster in native PHP:

- `\approxeq` now emits `≊` as a MathML operator.
- `\napprox` now emits `≉` as a MathML operator.
- `\ncong` now emits `≇` as a MathML operator.
- Source TeX remains in MathML semantics annotations, and accessible MathML exposes deterministic alt text and intent tokens.

Red-first probe before implementation:

```text
x \approxeq y -> <mi>\approxeq</mi>
a \napprox b -> <mi>\napprox</mi>
c \ncong d -> <mi>\ncong</mi>
```

This follows the existing bounded texmath-style command-table contract in `MathTexConverter`: recognized TeX relation command names are converted directly to Unicode MathML operator tokens, with no TeX engine or renderer involved.

## Evidence

Focused test command:

```bash
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
```

Result:

```text
1 test files, 1321 assertions, 0 failures
```

The same focused file reported `1315` assertions before this slice, so this adds 1 PHP PASS case and 6 focused assertions.

Example smoke:

```bash
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
```

Result:

```text
math tex handoff self-test ok
```

## Dependency Closure

No new support component is needed. This slice reuses `MathTexConverter` command-table parsing, MathML operator serialization, source annotation generation, accessibility annotation generation, the lane-local focused PHP runner, and the existing WordPress math handoff example.

No Pandoc, Haskell runner, Word, LibreOffice, zip/unzip, TeX/PDF engine, MathJax, KaTeX, browser renderer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This avoids the accepted math/TeX clusters for `\not` overlays, braced negated relations, ordinary `\approx` and `\cong`, named symbol aliases, siunitx aliases, paired delimiters, color/xcolor/colorbox, extensible arrows, prime/accent/operator handling, matrix/cases environments, equation tags/references, and PDF engine handoff behavior. A useful follow-up is another bounded non-overlapping TeX relation command-table gap or MathML accessibility metadata case.
