# markerPDF Named Destinations Page-Operand Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T044339Z`
Session: `port-dev-markerpdf-named-destinations-20260605T044339Z`
Base accepted HEAD: `65530467850a2f179b5e97d0f0d14d580fe10713`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata through the native PDF parsing boundary before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the parser boundary for explicit named-destination arrays: the first operand must identify a valid target page by page reference or in-range page number before WordPress review metadata is emitted.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now rejects explicit destination arrays whose first operand is `null`, a negative page number, an out-of-range page number, a PDF string, a PDF name, or a dictionary.
- Valid page object references still resolve to the page index and page object id.
- In-range integer page numbers remain supported for legacy destination syntax.
- Rejected destination labels, malformed coordinates, and legacy bad rows stay out of WordPress visible text and named-destination review metadata.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects invalid page operands before WordPress named-destination review metadata
Expected: Valid Page Ref, Valid Page Index, LegacyOk
Actual: Valid Page Ref, Valid Page Index, Null Page Operand, Negative Page Index, Out Of Range Page Index, String Page Operand, Name Page Operand, Dictionary Page Operand, LegacyOk, LegacyBad
FAIL keeps invalid page operands out of visible WordPress text and review rows
1 test files, 4 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-page-operand-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-page-operand-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects invalid page operands before WordPress named-destination review metadata
PASS keeps invalid page operands out of visible WordPress text and review rows
1 test files, 32 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
27 PASS cases
10 test files, 220 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-page-operand-boundary-currentbase.php
Emits destination_count=3, destination_names=[Valid Page Ref, Valid Page Index, LegacyOk], valid_page_ref_preserved=true, valid_page_index_preserved=true, legacy_in_range_page_index_preserved=true, invalid_page_operands_rejected=true, visible_text_excludes_destination_metadata=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1421 -> 1423`.
- `wordpressScenarios`: `1351 -> 1352`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php` adds 2 PASS cases and 32 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-exact resolver, name-tree walker, page-tree indexer, destination normalizer, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed leaf `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding string keys, indirect view operands, PDF name-key rejection, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only fail-closed validation of explicit destination page operands before standalone named-destination review rows.

## Next Task

Continue with non-overlapping native searchable-PDF behavior under the no-GPU scope: metadata, annotations, forms, xref repair, page geometry, image/filter review, font/CMap widths, supplied table/equation boundaries, or remaining runtime review behavior.
