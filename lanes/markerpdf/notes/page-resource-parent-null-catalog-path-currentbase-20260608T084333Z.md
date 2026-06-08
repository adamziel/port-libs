# markerpdf-page-resource-inheritance-current-base-20260608T084333Z

## Slice

- Lane: `markerpdf`
- Accepted base: `efe757fea34410e917212cb2f88d964760b187d1`
- Scope: native no-GPU searchable-PDF page-resource inheritance only.

## Source Truth

The selected catalog `/Pages` to `/Kids` path is the authoritative page-tree
lineage for a selected page. A page `/Parent null`, or an indirect wrapper that
resolves to `null`, does not identify a detached `/Pages` node whose
`/Resources` may be inherited. This slice treats that value like an omitted
`/Parent` for the selected catalog path while preserving the accepted
fail-closed behavior for mismatched or detached non-null `/Parent` references.

Red-first scratch probe before the source fix returned only raw text for the
direct `/Parent null` fixture and no page-boundary resource metadata. The page
was present under the catalog `/Kids` path, but the inherited catalog-branch
font and Form XObject resources were not selected.

## Implemented Behavior

- `PdfTextExtractor` now falls back to the selected catalog `/Kids` path when a
  page `/Parent` value is direct `null` or resolves through an indirect wrapper
  to `null`.
- `PdfPagePropertyExtractor` uses the same null-parent catalog-path lineage for
  page-boundary resource metadata.
- Detached parent resources remain excluded. Existing parent `/Kids`
  membership checks and mismatched-parent fail-closed behavior are unchanged.

## Focused Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentNullCatalogPathCurrentBaseTest.php`
  - `1 test files, 28 assertions, 0 failures`
  - Adds 2 focused PASS cases.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCatalogParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTreeWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`
  - `5 test files, 353 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-null-catalog-path-currentbase.php`
  - Exits 0.
  - Smoke flags include `catalog_path_resources_selected=true`,
    `parent_null_font_decoded=true`, `parent_null_form_expanded=true`,
    `detached_parent_resources_excluded=true`,
    `executes_python_or_models=false`, and
    `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `2999` -> `3001`
- `suiteProgress`: `2176` -> `2178` tracked PHP behavior tests
- `wordpressScenarios`: `2484` -> `2485`

## Non-Overlap

This does not repeat accepted page-resource wrapper, parent `/Kids` boundary,
catalog-parent boundary, direct resource, CMap/font, Form XObject, or xref
repair slices. It only covers null-valued page `/Parent` lineage when the page
is selected by the catalog `/Kids` path.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
object parsing, page-tree reference resolution, resource inheritance, CMap text
decode, and Form XObject expansion paths. No Python, OCR, models, GPU,
external PDF tools, raster rendering, or live services are required.

## Root Harness

Not run - isolated micro-slice.
