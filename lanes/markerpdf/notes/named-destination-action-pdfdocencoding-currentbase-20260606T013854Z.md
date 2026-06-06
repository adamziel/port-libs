# markerPDF named-destination action PDFDocEncoding boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260606T013854Z`
Base accepted HEAD: `dc418bfd2db14b95c71aee17525326c77a378a5b`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF navigation and action review to the PDF parser boundary before OCR/model handoff. Under the no-GPU markerPDF scope, native PHP review paths must preserve PDF name-tree byte semantics while presenting decoded destination labels for WordPress review.

PDF destination name-tree keys are PDF strings. `/Limits` comparisons use original string bytes, but review labels should decode text-string encodings such as PDFDocEncoding. The standalone named-destination extractor and outline extractor already had this split; `PdfActionReviewExtractor` kept only raw string values, so annotation action rows and promoted Link spans could expose raw byte-string names like `<18>` instead of the PDFDocEncoding label.

No OCR, Surya, Texify, Torch, pypdfium/PDFium, Python model worker, JavaScript execution, or external PDF tools were used.

## Behavior

`PdfActionReviewExtractor` now:

- stores both decoded string text and raw source bytes while parsing literal and hex strings;
- compares destination name-tree `/Limits` and leaf keys by raw bytes;
- decodes non-UTF PDFDocEncoding string names before action review rows, Link annotation promotion, and supplied WordPress span metadata.

The focused fixture keeps destination names `<18>` and `<80>` inside one `/Names /Dests` leaf. Standalone metadata, document metadata, outline rows, annotation action review, page link extraction, and supplied span promotion all expose `\u02d8` and `\u2022`, while raw `0x18` and `0x80` bytes remain absent from review JSON and visible page text.

## Evidence

Baseline before this slice:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*Test.php
```

Result: `33 test files / 916 assertions / 0 failures`.

Focused new test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionPdfDocEncodingBoundaryCurrentBaseTest.php
```

Result: `1 test files / 28 assertions / 0 failures`.

Adjacent regression:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationByteLimitsActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameTreeKeyActionBoundaryCurrentBaseTest.php
```

Result: `3 test files / 93 assertions / 0 failures`.

Named-destination family after this slice:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*Test.php
```

Result: `34 test files / 944 assertions / 0 failures`.

Broader annotation/link regression:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotation*Test.php lanes/markerpdf/tests/PdfLinkAnnotation*Test.php
```

Result: `37 test files / 1488 assertions / 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-named-destination-pdfdoc-action-currentbase.php
```

Result: emitted `destination_names=["\u02d8","\u2022"]`, `annotation_destinations=["\u02d8","\u2022",null]`, `link_destinations=["\u02d8","\u2022",null]`, `raw_control_destination_hidden=true`, `raw_high_byte_destination_hidden=true`, `visible_text_excludes_destination_labels=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted standalone named-destination PDFDocEncoding key decoding, byte-string `/Limits` pruning, byte-limit action pruning, PDF name-key rejection, sparse `/Names` recovery, duplicate key handling, alias/action-dictionary cycles, view-mode/coordinate validation, page operands, object-stream/xref repair, outline destination action context, PageLabels, font/CMap, image/filter, xref, form, security, or supplied table/equation behavior. The bounded behavior is only PDFDocEncoding display-label decoding in the action-review map that feeds annotation actions and Link annotation span promotion, while preserving raw-byte name-tree comparisons.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF action reviewer, name-tree parser, raw string-byte tokenizer, existing standalone destination extractor, outline extractor, link annotation promotion path, supplied pdftext span merger, and WordPress smoke renderer. Full upstream model/runtime parity remains outside this no-GPU markerPDF scope.

## Next Task

Continue non-overlapping native no-GPU markerPDF work around searchable-PDF fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
