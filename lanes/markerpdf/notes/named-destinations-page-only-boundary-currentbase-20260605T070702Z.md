# Named Destinations Page-Only Boundary Current Base

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T070702Z`

Base accepted HEAD: `f5c8efa7a7acc5f8f7506975550909d324c38d52`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates PDF navigation resolution to the PDF engine, and upstream `marker/pdf/extract_text.py::get_text_blocks` keeps navigation metadata separate from page text blocks.
- Native parser boundary for this slice: catalog `/Names /Dests` and legacy `/Dests` rows can be page-only targets. Direct or indirect page references and bounded page indexes become page-only `/Fit` review rows. Missing references, out-of-range indexes, and non-GoTo action dictionaries remain excluded and never execute.

## Implementation

- `PdfNamedDestinationExtractor::normalizeDestination()` now checks the raw destination operand, or the raw `/D` value inside a plain destination dictionary or `/S /GoTo` action dictionary, before resolving it into an object body.
- Added `pageOnlyDestinationDetails()` to preserve direct page-reference identity, follow bounded indirect page-reference/page-index wrappers with a cycle guard, and normalize valid page-only targets to `fit=Fit` with empty coordinates.
- Existing explicit destination arrays, view-name validation, generation checks, name-tree `/Limits`, object-stream/xref selection, and non-GoTo action rejection remain unchanged.
- Added a WordPress smoke for page-only named destinations that emits review metadata while unsafe action payloads and destination labels stay out of visible paragraph text.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPageOnlyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes page-only named destinations to Fit review rows before WordPress import
PASS keeps invalid page-only destinations out of visible WordPress text and review rows
1 test files, 15 assertions, 1 failures
```

The extractor returned no page-only named destinations.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPageOnlyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes page-only named destinations to Fit review rows before WordPress import
PASS keeps invalid page-only destinations out of visible WordPress text and review rows
1 test files, 25 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfNamedDestination*Test.php' | sort)
Focused test run: 14 selected test files (root lock skipped)
14 test files, 327 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-page-only-boundary-currentbase.php
```

The smoke emitted `destination_count=5`, `page_only_fits=true`, `page_only_coordinates_empty=true`, `unsafe_page_only_actions_filtered=true`, `visible_text_excludes_destination_names=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint passed for:

- `lanes/markerpdf/src/PdfNamedDestinationExtractor.php`
- `lanes/markerpdf/tests/PdfNamedDestinationPageOnlyBoundaryCurrentBaseTest.php`
- `lanes/markerpdf/examples/wordpress-pdf-named-destination-page-only-boundary-currentbase.php`

`lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` decoded as valid JSON.

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1555 -> 1557` from the two new focused TestRunner PASS cases.
- WordPress scenarios move `1447 -> 1448`.
- Mapped markerPDF/PDF semantics move `724 -> 725`.

## Non-Overlap

This does not repeat accepted named-destination `/Limits`, generation, indirect arrays, object-stream, trailer-root, xref-offset, page-operand validation, action-dictionary, name-key, PDFDocEncoding, indirect view-operand, or view-mode slices. The bounded new behavior is valid page-only named-destination values, including direct/indirect page references and page indexes, normalized to review-only `/Fit` rows.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, catalog/name-tree resolver, exact-generation reference checks, page-tree index builder, action safety filter, and visible text extractor. Full upstream Python/model/pdftext/pypdfium/Surya/Texify/Torch benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
