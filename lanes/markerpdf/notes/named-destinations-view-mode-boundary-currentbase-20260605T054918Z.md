# markerPDF Named Destinations View-Mode Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T054918Z`
Session: `port-dev-markerpdf-named-destinations-20260605T054918Z`
Base accepted HEAD: `59f74ed0eba0c82ff3e4a59978f6d445940ec730`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata through the parser/converter boundary before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF destination syntax boundary: explicit destinations use the standard PDF view names `XYZ`, `Fit`, `FitH`, `FitV`, `FitR`, `FitB`, `FitBH`, and `FitBV`. Action/media-like names such as `/Launch`, `/Movie`, or `/RichMedia` are not valid destination view modes and must not become WordPress named-destination review rows.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now validates the second explicit destination operand against the standard PDF destination view-name set.
- Valid `/Fit`, `/FitB`, `/FitH`, `/FitV`, `/FitR`, `/FitBH`, `/FitBV`, and `/XYZ` destinations remain accepted.
- Unknown direct, indirect, GoTo-action-wrapped, and legacy `/Dests` view names are rejected before metadata is emitted.
- Rejected malformed destination labels, coordinates, and action/media-like view operands stay out of visible WordPress text and named-destination review metadata.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects unknown destination view names before WordPress named-destination review metadata
Expected: Current Fit, Valid Bounding Box Fit, LegacyOk
Actual: Current Fit, Current Unknown View, Indirect Unknown View, Action Unknown View, Valid Bounding Box Fit, LegacyOk, LegacyBad
FAIL keeps unknown destination view operands out of visible WordPress text and review rows
1 test files, 4 assertions, 2 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-view-mode-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-view-mode-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects unknown destination view names before WordPress named-destination review metadata
PASS keeps unknown destination view operands out of visible WordPress text and review rows
1 test files, 30 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php
Focused test run: 12 selected test files (root lock skipped)
31 PASS cases
12 test files, 284 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-view-mode-boundary-currentbase.php
Emits unknown_view_names_rejected=true, valid_fitb_surplus_operands_ignored=true, legacy_valid_destination_preserved=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1489 -> 1491`.
- `wordpressScenarios`: `1396 -> 1397`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php` adds 2 PASS cases and 30 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-exact resolver, name-tree walker, page-tree indexer, destination normalizer, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed leaf `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding string keys, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only fail-closed validation of the explicit destination view-name operand before standalone named-destination review rows.

## Next Task

Continue with non-overlapping native searchable-PDF behavior under the no-GPU scope: metadata, annotations, forms, xref repair, page geometry, image/filter review, font/CMap widths, supplied table/equation boundaries, or remaining runtime review behavior.
