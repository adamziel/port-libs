# Pandoc Math/TeX Accent Alias Slice

Base accepted HEAD: `1f5e5a83d969498573a313dba1838afefd977f4f`

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260606T133848Z`

## Behavior

This slice extends the native bounded `MathTexConverter` over-accent mapping for common TeX accent aliases:

- `\acute`
- `\grave`
- `\breve`
- `\check`
- `\mathring`
- `\widetilde`

Before this slice, those commands fell through as literal identifiers. They now produce semantic MathML `<mover accent="true">` nodes, preserve source TeX annotations, support scripts on the accented expression, and expose readable accessibility token text and intent metadata.

The WordPress math handoff smoke now includes an inline accent-alias formula so reviewer packets keep the editable source span and native MathML without MathJax, KaTeX, TeX engines, or browser renderers.

## Source Truth And Scope

This uses the existing native Pandoc-like MathML handoff contract in `MathTexConverter`. A focused local upstream texmath fixture for these exact aliases was not available in the static cache, so this is a bounded support-library behavior slice rather than upstream Haskell runner parity.

Out of scope: arbitrary TeX macro expansion, full accent package parity, renderer layout fidelity, TeX/PDF engine output, MathJax/KaTeX rendering, and full upstream Pandoc/texmath runner execution.

## Evidence

Red-first evidence:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result before implementation: `1 test file, 483 assertions, 0 failures`.
- Direct conversion probe for `\acute{x} + \grave{y} + \breve{z}` produced literal command identifiers such as `<mi>\acute</mi>` before implementation.

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 490 assertions, 0 failures`.
- New focused test: `PASS converts bounded tex accent aliases to mathml`.

Example smoke:

- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
- Result: `math tex handoff self-test ok`.

Syntax and lane guards:

- `php -l lanes/pandoc/src/MathTexConverter.php`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- Result: no syntax errors detected for all changed PHP files.

Final JSON and whitespace verification are recorded in the worker final response.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1335` to `1336`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1749` to `1750`.
- Math/TeX core cases: `13` to `14`.
- Mapped Math/TeX core cases: `13` to `14`.
- Math/TeX focused assertions: `72` to `79`.
- Focused `MathTexConverterTest.php`: `483` to `490` assertions.

## Dependency Closure

No new support component is needed. The slice reuses:

- `MathTexConverter`
- `MathTexConverterTest`
- `wordpress-math-tex-handoff.php`

Remaining dependency blockers are intentionally out of scope for this micro-slice: hydrated upstream Pandoc/texmath checkout, non-mutating Cabal solver/build/test planning, Haskell runner parity, TeX/PDF engine output, MathJax/KaTeX/browser rendering, and full upstream test-suite execution.

## Non-Overlap

This does not repeat accepted Math/TeX coverage for fractions, roots, scripts, binomial/genfrac/infix fractions, delimiters, arrays, array column metadata, AMS alignedat/multline/multlined environments, labels/refs, prime notation, existing hat/bar/dot/ddot/tilde/vec/overline/underline mappings, arrow accents, or renderer handoff diagnostics.

The new behavior is limited to common over-accent alias commands listed above.

## Next Task

Useful follow-up work would be a separate bounded Math/TeX slice for broader symbol tables, matched delimiter/fence preflight, or a real upstream texmath/Pandoc runner audit after the pinned upstream checkout and runner dependency closure are available.
