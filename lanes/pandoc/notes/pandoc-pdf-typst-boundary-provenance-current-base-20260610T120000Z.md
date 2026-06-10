# Pandoc PDF/Typst Boundary Provenance Slice

## Summary

- Extended `PdfEngineHandoff::plan()` with `engineBoundaryProvenance` for Typst compile handoffs.
- The native plan now records safe relative Typst `--root`, `--font-path`, `--package-path`, `--package-cache-path`, `--input`, and `--ignore-system-fonts` boundary metadata.
- Unsafe absolute, URI, home-relative, empty, traversal, or malformed Typst boundary options are preserved as review issues instead of expanding the permissive engine-option API into execution or filesystem access.
- `fakeRun()` and `fakeRunSequence()` carry the plan provenance as `engineBoundaryProvenance` and `finalEngineBoundaryProvenance` for consumers that inspect runner summaries rather than plans.
- `wordpress-pdf-engine-handoff.php` now exposes a compact Typst boundary plan in its JSON self-test summary.

## Scope Boundary

This is a bounded PDF/Typst handoff provenance slice only. It does not execute Typst, Pandoc, TeX/PDF engines, browser renderers, office suites, package managers, external validators, online services, or live provider tests. It does not implement Typst rendering, Typst syntax parsing, package resolution, font discovery, PDF validation, or filesystem probing.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1453 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - `pdf engine handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 59817 assertions, 0 failures`

## Accounting

- Focused Pandoc lane metric: `phpPass 2956 -> 2957`, `phpFail 0`.
- Direct-format parity is unchanged: this slice adds PDF/Typst fake-runner provenance metadata, not a new direct reader or writer.
