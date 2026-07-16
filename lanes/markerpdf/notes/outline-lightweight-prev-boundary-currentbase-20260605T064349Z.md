# markerPDF lightweight outline Prev boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T064349Z`

Base accepted HEAD: `66d1418133da4443c2300fa93a01793691d07e92`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark metadata separate from page text through `marker/cleaners/toc.py::get_pdf_toc` and `marker/pdf/extract_text.py::get_text_blocks`.
- PDF outline sibling lists may include `/Next`, `/Prev`, and root/item `/Last` pointers. Native WordPress TOC metadata should tolerate missing `/Prev` in lightweight producer fixtures, but an explicit contradictory backlink is a malformed sibling boundary and must stop traversal before stale outline/action metadata is imported.
- This slice covers the upstream-style lightweight `PdfTextExtractor::extractOutlineMetadata()['pdf_toc']` path, which feeds converter metadata separately from the richer document-outline review payload.

## Red Baseline

After adding `PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php`, the accepted base failed before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds lightweight pdf_toc traversal by explicit outline Prev backlinks
Expected: Lightweight Boundary Current Chapter
Actual: Lightweight Boundary Current Chapter, Stale Lightweight Boundary Remote Review, Untrusted Lightweight Tail After Bad Prev
1 test files, 3 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::pdfTocFromObjects()` now reads top-level `/Outlines`, `/First`, `/Last`, `/Parent`, `/Prev`, and `/Next` references through the token-aware PDF value parser instead of regexing nested dictionaries.
- Lightweight `pdf_toc` traversal now:
  - validates that the catalog `/Outlines` target is an outline root, not a spoofed page/object dictionary;
  - stops when a sibling has an explicit `/Prev` that does not match the previous current-chain sibling;
  - honors declared `/Last` objects for root and child lists;
  - skips child traversal under untitled outline placeholders while still allowing later titled siblings when their `/Prev` backlink is coherent.

## Verification

Red-to-green focused check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds lightweight pdf_toc traversal by explicit outline Prev backlinks
1 test files, 13 assertions, 0 failures
```

Adjacent outline/converter sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataPrevBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/CorePdfConverterTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 602 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-lightweight-prev-boundary-currentbase.php
```

Smoke emitted `pdf_toc_titles=["WordPress Lightweight Current Chapter"]`, `document_outline_titles=["WordPress Lightweight Current Chapter"]`, `stale_outline_excluded=true`, `stale_remote_action_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataLightweightBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-lightweight-prev-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-lightweight-prev-boundary-currentbase.php

git diff --check -- lanes/markerpdf
clean
```

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1538 -> 1539` from the one new focused PASS case.
- Focused assertion coverage for the new lightweight boundary test is 13 assertions.
- WordPress scenario count moves `1432 -> 1433` from the added smoke.

## Non-Overlap

This does not repeat accepted document-outline `/Prev`, `/Last`, parent/missing-parent, title, root-count/type, generation, EOF, xref-owner, xref-stream-root, named-destination, page-operand, action-chain, PageLabels, transition/thread, metadata, attachment, form, image, font, stream-filter, or encrypted-permission slices. The bounded behavior is only the upstream-style lightweight `pdf_toc` traversal boundary inside `PdfTextExtractor::extractOutlineMetadata()`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, current object selection, page-tree ordering, outline metadata parser, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
