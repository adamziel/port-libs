# markerpdf named destinations metadata kid generation current-base

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260607T004501Z`  
Session: `port-dev-markerpdf-named-destinations-20260607T004501Z`  
Base accepted HEAD: `07bc98135d31956e36bc5df88c443bd479b2ac20`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this native PHP slice owns the same PDF object-reference boundary for catalog `/Names /Dests`: no-xref fallback scanning can encounter same-object, different-generation name-tree nodes, and valid `9 0 R -> 9 1 R` child traversal must not be collapsed as a cycle when building WordPress `document_destinations` or outline navigation review rows.

## Change

- `PdfMetadataExtractor` now carries indirect dictionary generations and keys destination-name-tree cycle checks by `object:generation`.
- `PdfMetadataExtractor` keeps xref-selected generation ownership strict, but in no-xref direct-object scan mode it can resolve a referenced direct object generation that is present in the file.
- `PdfOutlineExtractor` mirrors that no-xref generation fallback for parsed object values and keys destination name-tree child traversal by `object:generation`.
- Added focused coverage and a WordPress smoke proving native named destinations, document metadata, outline navigation, and link promotion agree while destination labels stay review-only.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationMetadataKidGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL mirrors generation-distinct name-tree kids into document destination metadata
Expected: [Current Review, Summary Review, LegacyFallback]
Actual: [Summary Review, LegacyFallback]
PASS keeps generation-distinct destination labels review-only for WordPress import
1 test files, 18 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationMetadataKidGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS mirrors generation-distinct name-tree kids into document destination metadata
PASS keeps generation-distinct destination labels review-only for WordPress import
1 test files, 29 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
Focused test run: 42 selected test files (root lock skipped)
42 test files, 1215 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNameTreeLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNameTreeActionStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationActionThreadReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNamedDestinationTransitionThreadSecurityCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationMetadataKidGenerationBoundaryCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 1332 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionTransitionCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationPageContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 608 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-metadata-kid-generation-currentbase.php
```

The smoke reports `metadata_generation_kid_preserved=true`, `outline_generation_kid_preserved=true`, `link_generation_kid_preserved=true`, `destination_labels_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `2722 -> 2724`.
- `wordpressScenarios`: `2294 -> 2295`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationMetadataKidGenerationBoundaryCurrentBaseTest.php` adds 2 PASS cases and 29 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, no-xref direct-object fallback parser, generation-exact reference resolver, name-tree `/Kids` and `/Limits` traversal, named-destination normalizer, metadata extractor, outline extractor, link annotation extractor, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed leaf/intermediate `/Limits` fallback, indirect arrays, PDFDocEncoding byte comparisons, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, duplicate key behavior, leaf ordering, kid `/Limits` ordering, partial kid ordering, outline destination action context, PageLabels, xref repair, metadata root selection, attachment, font, image/filter, table, or CMap behavior. The bounded behavior is only no-xref same-object generation-distinct destination name-tree `/Kids` traversal in document metadata and outline navigation mirrors.
