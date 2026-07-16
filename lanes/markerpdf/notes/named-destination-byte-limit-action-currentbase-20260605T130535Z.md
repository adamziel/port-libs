# markerpdf named-destination byte-limit action boundary current-base

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T130535Z`
Base accepted HEAD: `4f58566bec084638730cc878f5a012cc1b641926`

## Source Truth

- PDF name trees bound child names by the original string-byte order of `/Limits`, not by decoded Unicode display labels.
- Existing native document-destination extraction already used raw PDF string bytes for `/Names /Dests`; this slice applies the same boundary to outline/navigation destination maps used by WordPress TOC and link promotion.
- No GPU/model/OCR, Python, pypdfium, Surya, Texify, or external PDF tools were used.

## Behavior

`PdfOutlineExtractor` now preserves raw string bytes when parsing PDF literal and hex strings. Destination name-tree traversal compares `/Limits` and leaf keys by those bytes while preserving decoded labels for review metadata.

This prevents decoded-but-byte-out-of-range destination names, such as `<80>` decoded through PDFDocEncoding, from becoming:

- outline TOC rows;
- navigation review metadata;
- local-destination annotation action rows;
- promoted WordPress link spans.

Valid byte-in-range names still resolve through outlines and annotation links.

## Evidence

Red run before the production change:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationByteLimitsActionBoundaryCurrentBaseTest.php
```

Result: `1 test files / 29 assertions / 1 failure`, with `Stale Bullet Outline` imported as a TOC row.

Green run after implementation:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationByteLimitsActionBoundaryCurrentBaseTest.php
```

Result: `1 test files / 36 assertions / 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-named-destination-byte-limit-action-currentbase.php
```

Result: emitted `byte_out_of_range_destination_excluded=true`, `stale_outline_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted named-destination extractor byte-limit test, internal node/leaf boundary tests, generation repair tests, page-only destination handling, object-stream destination recovery, or inline-image tokenizer NUL whitespace work. The new behavior is specifically the outline/navigation destination-map boundary that feeds TOC and link promotion.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF parser/tokenizer in `PdfOutlineExtractor` and the existing WordPress link/metadata review path.

## Next Task

Continue non-overlapping native no-GPU markerPDF work around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
