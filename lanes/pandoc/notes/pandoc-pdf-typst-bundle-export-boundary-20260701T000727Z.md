# PDF/Typst Bundle Export Boundary Provenance

Slice: `plib-h6koh`, PDF/Typst boundary provenance.

This slice adds native `PdfEngineHandoff` provenance for Typst's bundle export
target without executing Typst or any renderer. When a Typst plan selects
`--format=bundle`, the handoff now records a `bundleExport` review packet with
the multi-file output boundary, asset-output possibility, selected feature-gate
source, and an explicit `bundle-feature-gate-missing` issue when the bundle
feature is not enabled.

Behavior:

- Plan diagnostics now expose `typst-bundle-export:*` entries.
- `typstBoundaryProvenance` carries the `bundleExport` policy through planning,
  fake-run artifact review, and fake-run sequence summaries.
- `typstBoundaryMatrix` gets a `bundle-export` case so reviewers can distinguish
  ordinary non-PDF format selection from the bundle target's multi-file/asset
  boundary.
- The focused test also refreshes a stale inherited LaTeX source assertion so
  the existing handoff file remains usable as a clean focused gate.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3480 assertions, 0 failures`

Accounting:

- `phpPass`: `470 -> 471`
- `phpFail`: remains `0`

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, TeX/PDF engine,
browser renderer, office suite, zip/unzip command, external validator, online
service, live provider test, or live-service provider test was invoked.
