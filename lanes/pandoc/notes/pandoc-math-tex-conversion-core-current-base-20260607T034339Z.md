# Pandoc Math/TeX Optional AMS Placement Handoff

Slice: `pandoc-math-tex-conversion-core-current-base-20260607T034339Z`

Base accepted HEAD: `b6751e8d16a369b3cb6f380d161ef10027ea4635`

## Behavior

Implemented bounded native TeX support for optional compact AMS environment
placement arguments:

- `\begin{aligned}[t]...\end{aligned}`
- `\begin{gathered}[b]...\end{gathered}`
- `\begin{alignedat}[c]{2}...\end{alignedat}`
- `\begin{multlined}[b]...\end{multlined}`
- same parser path for `flaligned` / `flaligned*`

The converter now consumes `[t]`, `[b]`, and `[c]` before environment content
or alignedat pair-count parsing, emits MathML table metadata
`align="top|bottom|center"` plus `data-tex-env-position`, and rejects unsupported
placement values instead of leaking bracket tokens into rendered cells.

The equation-label collection scanner uses the same optional-placement skip, so
document-wide label discovery no longer fails when an inline math node contains
compact `alignedat[c]{...}`.

## Red-First Evidence

Before the patch, direct native conversion showed:

- `aligned[t]`, `gathered[b]`, and `multlined[b]` rendered bracket tokens such as
  `<mo>[</mo><mi>t</mi><mo>]</mo>` into the first table cell.
- `alignedat[c]{2}` failed with `InvalidArgumentException: Expected TeX text group`
  before reading the required pair-count group.

## Focused Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 576 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 588 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.
- Syntax checks passed for:
  `lanes/pandoc/src/MathTexConverter.php`,
  `lanes/pandoc/tests/MathTexConverterTest.php`, and
  `lanes/pandoc/examples/wordpress-math-tex-handoff.php`.
- `git diff --check -- lanes/pandoc` passed with no output.

Assertion delta: `+12` focused assertions.

Expected lane movement:

- `lanes/pandoc/lane-status.json` `phpPass`: `1448 -> 1449`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1864 -> 1865`
- math/TeX conversion core cases: `14 -> 15`
- math/TeX conversion core assertions: `85 -> 97`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MathTexConverter`, the existing focused `MathTexConverterTest.php` harness, and
the WordPress math TeX handoff smoke.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal build/test command,
Haskell runner, external converter, online service, live provider test, or
live-service provider test was executed. Full texmath/Pandoc runner parity
remains a separately scoped upstream/Cabal runner closure task.

## Non-Overlap

This patch does not repeat the accepted math/TeX slices for alignedat base
tables, multline/multlined row spacing, array width columns, TeX comments,
modular commands, or `\bangle`. It is limited to compact-environment optional
placement metadata and the matching label-scanner skip.

## Follow-Up

Keep follow-up math/TeX work bounded to non-overlapping native texmath reader
gaps such as remaining compact-environment layout metadata, MathML diagnostics,
or equation-label scanner parity. External runner parity remains out of scope
for this lane unless explicitly authorized.
