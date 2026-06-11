# Pandoc PDF/Typst Dependency Graph Handoff

Slice: `pandoc-pdf-typst-dependency-graph-current-base-20260611T094624Z`

Required base: `592488d646306dddcb4f4ddb49e196583fdbab7a`

## Scope

- Added a bounded Typst dependency graph handoff model to `PdfEngineHandoff::fakeRun()` for runs that provide native dependency sidecars.
- The graph ties dependency artifact hashes, local input nodes, Typst package nodes, output nodes, edge provenance, declared root accounting, source SHA-256, and `--creation-timestamp` provenance into one reviewer-facing structure.
- The slice remains external-tool free: it does not invoke Pandoc, Typst, TeX/PDF engines, browser renderers, validators, online services, or archive tools.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1620 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 62441 assertions, 0 failures.
