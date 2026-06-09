# pandoc math TeX conversion current-base slice 2026-06-09

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T045415Z`

Base accepted HEAD: `e3e201377d66d62da0039dedbb153200e0a6e366`

## Behavior

Implemented bounded mathtools cases-environment handoff in native PHP:

- `dcases` renders as a left-braced `mtable` wrapped in `mstyle displaystyle="true"`.
- `rcases` renders as a right-braced `mtable`.
- `drcases` and `drcases*` render as right-braced displaystyle `mtable` output.
- Source TeX remains in MathML semantics annotations, and accessible MathML exposes the right-brace alt text/intent.

Red-first probe before implementation:

```text
dcases  -> InvalidArgumentException: Unsupported TeX environment dcases
rcases  -> InvalidArgumentException: Unsupported TeX environment rcases
drcases -> InvalidArgumentException: Unsupported TeX environment drcases
```

The implementation reuses the existing matrix-environment row splitter and serializer rather than adding a new parser path.

## Evidence

Focused test command:

```bash
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
```

Result:

```text
1 test files, 1262 assertions, 0 failures
```

The same focused file reported `1247` assertions before this slice, so this adds 1 PHP PASS case and 15 focused assertions.

Example smoke:

```bash
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
```

Result:

```text
math tex handoff self-test ok
```

## Dependency Closure

No new support component is needed. This slice reuses `MathTexConverter` bounded environment parsing, MathML table serialization, source annotation generation, accessibility annotation generation, the lane-local focused PHP runner, and the existing WordPress math handoff example.

No Pandoc, Haskell runner, Word, LibreOffice, zip/unzip, TeX/PDF engine, MathJax, KaTeX, browser renderer, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This avoids the accepted math/TeX clusters for ordinary `cases`, starred matrix aliases, AMS row environments, optional row spacing, paired delimiters, color/xcolor/colorbox, siunitx aliases, extensible arrows, prime/accent/operator handling, and equation tags/references. A useful follow-up is starred matrix optional alignment parsing or another bounded mathtools environment not already mapped.
