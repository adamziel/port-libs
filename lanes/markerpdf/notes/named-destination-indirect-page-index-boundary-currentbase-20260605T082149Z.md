# Named Destination Indirect Page Index Boundary Current Base

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T082149Z`

Base accepted HEAD: `047062ffae599f2aed5868dc8e085f869923184a`

## Source Truth

- PDF explicit destination arrays may use a page object reference or a page number as their first operand; operands can also be indirect PDF objects.
- MarkerPDF's no-GPU scope still needs these native named-destination review rows before WordPress import navigation is emitted.

## Red-First Probe

Before the source change, an in-memory PDF with:

- `/Names /Dests` row `(Indirect Page Index) [20 0 R /XYZ 72 640 0]`
- `/Names /Dests` row `(Indirect Page Ref) [23 0 R /FitH 700]`
- legacy `/Dests << /LegacyIndirect [20 0 R /FitV 90] >>`
- `20 0 obj 1 endobj`
- `23 0 obj 3 0 R endobj`

returned no named-destination rows from `PdfNamedDestinationExtractor::extractNamedDestinations()`.

## Patch

- `PdfNamedDestinationExtractor::normalizeDestination()` now resolves the first operand of explicit destination arrays through the existing guarded page-only destination resolver.
- Valid indirect page-number operands resolve to page indexes with `page_object_id: null`.
- Valid indirect page-reference operands resolve to the target page object and page index.
- Negative, out-of-range, string, and null indirect page operands remain excluded from review metadata and visible WordPress text.
- Added focused current-base tests and a WordPress smoke for this native parser boundary.

## Verification

- `php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php`
- `php -l lanes/markerpdf/tests/PdfNamedDestinationIndirectPageIndexBoundaryCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationIndirectPageIndexBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-page-index-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-page-index-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectPageIndexBoundaryCurrentBaseTest.php`
  - `1 test files, 28 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOnlyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectPageIndexBoundaryCurrentBaseTest.php`
  - `4 test files, 138 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php`
  - `16 test files, 377 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-page-index-currentbase.php`
  - emitted `indirect_page_index_resolved=true`, `indirect_page_ref_resolved=true`, `legacy_indirect_page_index_resolved=true`, `malformed_indirect_page_operands_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted named-destination generation, name-key, view-mode, page-only, direct page-operand, xref/object-stream, trailer-root, action-dictionary, intermediate limit, internal-node, or limits-fallback slices. The bounded behavior is explicit named-destination arrays whose page operand is itself an indirect page number or indirect page reference.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, generation-aware reference resolver, page tree indexer, named-destination extractor, text extractor, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
