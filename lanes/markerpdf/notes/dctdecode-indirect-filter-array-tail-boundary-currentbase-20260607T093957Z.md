# markerPDF DCTDecode indirect filter-array tail boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260607T093957Z`

## Source Truth

- Upstream `sddai/markerPDF` routes searchable PDF text through native PDF text extraction before model/OCR handoff. Image XObject payload bytes must remain outside WordPress paragraphs when an image stream uses preview-only filters such as `DCTDecode`.
- PDF stream `/Filter` operands may be indirect objects. When an indirect filter object starts with a valid array but carries an extra trailing operand, the valid leading filter chain is useful review metadata, but the trailing operand makes the filter declaration malformed and must fail closed before native raster decode.

## Behavior

`PdfImageRenderer` and `PdfTextExtractor` now parse balanced indirect `/Filter` arrays before checking for trailing non-whitespace operands. A resolved object such as `[ /FlateDecode /DCTDecode ] /Crypt` is reported as:

```text
MalformedFilterOperand, FlateDecode, DCTDecode
```

The malformed sentinel triggers `filter_operand_policy=reject_malformed_filter_operands`, preserves the `DCTDecode` review boundary, keeps `DCTDecode` in `preview_only_filters`, blocks native raster decode, and still aligns the valid `DCTDecode` `/DecodeParms` slot so `/ColorTransform 1` is preserved as review metadata. The searchable text path continues to exclude JPEG/image payload bytes.

## Evidence

Red-first focused run before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeIndirectFilterArrayTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects indirect DCTDecode filter arrays with trailing operands before renderer and WordPress review
Expected: ['MalformedFilterOperand', 'FlateDecode', 'DCTDecode']
Actual: []
1 test files, 8 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeIndirectFilterArrayTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects indirect DCTDecode filter arrays with trailing operands before renderer and WordPress review
1 test files, 36 assertions, 0 failures
```

Adjacent DCT current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecode*CurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 1387 assertions, 0 failures
```

Shared stream filter/DecodeParms stack regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 390 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-indirect-filter-array-tail-boundary-currentbase.php
```

The smoke exits 0 and emits `indirect_filter_array_tail_rejected=true`, `stream_filters=["MalformedFilterOperand","FlateDecode","DCTDecode"]`, `filter_operand_policy=reject_malformed_filter_operands`, `native_raster_decode=false`, `dctdecode_color_transform=1`, `dctdecode_image_payload_excluded_from_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct scalar filter operands with extra top-level tokens, comment-split indirect references, escaped DCTDecode names, duplicate filter declarations, duplicate/malformed DecodeParms operands, missing/extra DecodeParms slot fail-closed handling, null-filter DecodeParms slot preservation, stale or missing stream length recovery, JPEG EOI fake endstream boundary recovery, DCT marker fill handling, alternate image review, or renderer stream preview-only DCT boundaries.

The bounded behavior is specifically indirect `/Filter` array objects with a valid leading DCTDecode chain and a trailing extra operand.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, balanced array reader, indirect object resolver, stream filter metadata parser, DecodeParms parser, image XObject review path, DCTDecode boundary metadata, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, GPU/model runners, PDFium/PIL raster execution, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
