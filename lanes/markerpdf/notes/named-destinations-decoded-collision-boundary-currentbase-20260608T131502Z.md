# markerPDF Named Destinations Decoded Collision Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T131502Z`  
Session: `port-dev-markerpdf-named-destinations-20260608T131502Z`  
Base accepted HEAD: `d6ec1fb5ef671b6ea22e454e765ca0d7b78582a5`

## Source Truth

Pinned upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF navigation metadata to the PDF parser/PDFium boundary before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF name-tree boundary for catalog `/Names /Dests`: name-tree keys are PDF string byte keys, so two distinct raw keys that decode to the same WordPress review label must remain distinct review rows.

## Implementation

- `PdfNamedDestinationExtractor` now de-duplicates name-tree rows by decoded label plus raw source bytes, not by decoded label alone.
- `PdfMetadataExtractor` mirrors the same behavior for `document_destinations`.
- `name_bytes_hex` is emitted only when a decoded-label collision exists, preserving the existing public row shape for ordinary named destinations.
- Legacy catalog `/Dests` rows with the same decoded label remain suppressed behind catalog `/Names /Dests`.

## Focused Evidence

Focused slice:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS preserves distinct raw name-tree byte-string destinations with the same decoded WordPress label
PASS keeps decoded-collision destination operands out of visible WordPress text

1 test files, 26 assertions, 0 failures
```

Full named-destination family:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
```

Result:

```text
62 test files, 2055 assertions, 0 failures
```

Metadata-focused regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionBoundaryCurrentBaseTest.php
```

Result:

```text
3 test files, 944 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-named-destination-decoded-collision-boundary-currentbase.php
```

Result: exits 0 and emits `destination_names=["Collision","Collision","LegacyTail"]`, `destination_name_bytes_hex=["436f6c6c6973696f6e","feff0043006f006c006c006900730069006f006e"]`, `legacy_same_label_suppressed=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

```bash
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-decoded-collision-boundary-currentbase.php
```

Result: no syntax errors detected for all changed PHP files.

## Status Delta

- `phpPass`: `3108 -> 3110`
- `wordpressScenarios`: `2561 -> 2562`
- New focused file: `PdfNamedDestinationDecodedCollisionBoundaryCurrentBaseTest.php` adds 2 PASS cases and 26 assertions.
- Manifest `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.

## Dependency Closure

No new support component is needed. This reuses the existing native PDF tokenizer, object resolver, name-tree collector, named-destination extractor, metadata extractor, and text extractor. No Python, OCR, CUDA, model execution, raster backend, external PDF tool, decryption/password validation, or PDF action execution is required.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, byte-string `/Limits`, malformed leaf/intermediate `/Limits`, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding byte comparisons, UTF-8 BOM decoding, malformed UTF-16 key rejection, duplicate same-byte key replacement, legacy `/Dests` duplicate-key rejection, indirect string aliases, GoTo action aliases/cycles, page-operand validation, destination view-mode/coordinate validation, object-stream/xref recovery, trailer-root selection, outline destination action context, PageLabels number-tree ordering, link annotation destination promotion, xref repair, metadata root selection, attachment, font, image/filter, table, CMap, or Type3 behavior. The bounded behavior is only preserving distinct raw PDF string keys that decode to the same WordPress named-destination review label.
