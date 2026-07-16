# markerPDF Named Destination Coordinate Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T152800Z`

Accepted base: `8f6707d401e2acd26f35ac32c8f919f501482c3e`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable-PDF text and TOC/navigation metadata through PDF parser backends before model/OCR execution.
- Native no-GPU markerPDF must therefore treat catalog `/Names /Dests` and legacy `/Dests` as review metadata only, and fail closed when a destination view array has malformed required operands.
- This slice stays inside searchable-PDF parser/review behavior. It does not run Surya, Texify, Torch, OCR, PDFium, PIL, JavaScript actions, media playback, or external PDF tools.

## Behavior

Before this patch, named destinations with valid view-mode names but malformed coordinates survived as local destinations:

- `[page /FitH]` was accepted with `top => null` even though the operand was missing.
- `[page /FitV (left)]` was accepted with `left => null` even though the operand was a string.
- `[page /FitR 1 2 (right) 4]` was accepted with `right => null` even though a rectangle coordinate was nonnumeric.

`PdfNamedDestinationExtractor`, `PdfActionReviewExtractor`, `PdfOutlineExtractor`, and `PdfMetadataExtractor` now share the same boundary: required view operands must be present and must resolve to a number or explicit `null`. Accepted null-coordinate behavior is preserved, including existing `/FitBH null` and `/XYZ null null null` outline review rows.

The focused fixture proves malformed named destinations are excluded from:

- standalone document destination rows;
- `document_destinations` metadata;
- outline TOC/navigation review;
- annotation action review;
- WordPress span link promotion.

Valid `/FitH`, `/XYZ null null null`, and legacy `/FitV` destinations remain reviewable.

## Red-First Evidence

Initial focused command before the implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationCoordinateBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 4 assertions, 2 failures
```

Failures showed `Missing FitH Target`, `String FitV Target`, `Bad Rect Target`, and `LegacyBadMissing` surviving in destination metadata, with malformed local-destination actions still promoted for annotations 8, 9, and 10.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationCoordinateBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 47 assertions, 0 failures
```

Adjacent named-destination/link/outline family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationViewBoundaryCurrentBaseTest.php
```

Result:

```text
29 test files, 1216 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-named-destination-coordinate-boundary-currentbase.php >/tmp/markerpdf-named-destination-coordinate-boundary.html
```

Result: exit `0`; emitted `valid_destination_count=3`, `malformed_coordinates_rejected=true`, `null_xyz_coordinates_preserved=true`, and promoted only annotation objects `[7,10]`.

## Non-Overlap

This does not repeat accepted named-destination `/Limits`, byte-limit, duplicate-key, object-stream, trailer-root, xref-offset, page-only, page-operand, action-dictionary, name-key, PDFDocEncoding, indirect view-operand, or unknown view-mode slices. It also does not repeat accepted outline destination view normalization. The bounded behavior is only missing or nonnumeric required coordinate operands inside otherwise valid named-destination view arrays before document metadata, outline, action, and WordPress link review.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF tokenizer, generation-aware object resolver, catalog name-tree walker, named-destination normalizer, action review extractor, outline extractor, metadata extractor, link annotation promotion path, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the current markerPDF no-GPU directive.
