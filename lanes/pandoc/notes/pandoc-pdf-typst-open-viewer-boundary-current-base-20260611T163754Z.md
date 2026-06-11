# pandoc-pdf-typst-open-viewer-boundary-current-base-20260611T163754Z

Slice: `plib-gqges`, PDF/Typst boundary provenance.
Required base: `6995e705a4f862a907c7deb350f9f5636ed25f5c`.

## Scope

`PdfEngineHandoff` now preserves Typst `--open` viewer-launch provenance as
inert review metadata. Plans record default-viewer launches, explicit program
viewer selections, side-effect review issues, and repeated `--open` override
history without invoking the viewer.

The same `typstBoundaryProvenance` is carried into fake-run artifact review
metadata and fake-run sequence summaries without invoking Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test files, 1687 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64101 assertions, 0 failures.

## Accounting

- `lanes/pandoc/lane-status.json` `phpPass`: `3075 -> 3076`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `3197 -> 3198`.
- Added `mappedTypstOpenViewerBoundaryProvenanceCases = 1`.
- Added `typstOpenViewerBoundaryProvenanceAssertions = 12`.

## Non-Overlap

This slice stays in PDF/Typst boundary provenance. It does not alter Pandoc,
Typst, TeX/PDF engine execution, DOCX/OpenXML, ODF/ODT, EPUB, ZIP/OPC,
XML/HTML5 DOM, JSON/native AST, or CSL/BibTeX behavior.
