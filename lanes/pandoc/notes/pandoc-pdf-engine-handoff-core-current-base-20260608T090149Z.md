# Pandoc PDF Engine Handoff Current-Base Active Action Chain

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T090149Z`

Accepted base: `ca61108bdd827e1c12cda271a01da7d8c060a0f3`

## Scope

This slice extends the bounded PDF fake-runner diagnostics for produced-PDF active actions. `PdfEngineHandoff` now follows `/Next` action chains from catalog, page, and annotation active-action dictionaries across direct dictionaries, referenced dictionaries, and arrays. Traversal is bounded by a depth cap and reference-cycle guard so cyclic action graphs are surfaced without looping.

## Evidence

- Rework notes: none found for `port-pandoc-*.needs-lane-rework.md`.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 820 assertions, 0 failures`.
- Red-first focused test: the new `/Next` action-chain case failed before implementation with `1 test files, 822 assertions, 1 failures` because only top-level active actions were reported.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 829 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `PdfEngineHandoff` PDF byte/object parsing, the fake-runner diagnostic path, focused PDF engine tests, and the lane-local WordPress PDF handoff example.

Full upstream runner parity remains out of scope for this isolated micro-slice. No Pandoc, TeX/PDF engine, Typst, browser renderer, roff renderer, external PDF validator, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Next

Choose a non-overlapping PDF fake-runner handoff gap such as outline/page-transition/form-action diagnostics or produced-PDF catalog/page metadata that can be inspected from bytes without invoking external renderers.
