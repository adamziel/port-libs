# DCTDecode Escaped Filter Boundary Current Base

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260607T032932Z`
- Accepted base: `4f2a40d7ce52644b619c40dcb9423d278952be79`
- Upstream source truth: pinned `sddai/markerPDF` image conversion treats DCT/JPEG image payloads as image inputs, not searchable text. Under the current no-GPU PHP lane scope, this patch keeps DCT image bytes review-only while normalizing PDF name escapes in native parser metadata.

## Behavior

PDF names can escape bytes with `#hh`, so these spellings are equivalent to the canonical stream names:

- `/Fil#74er` -> `/Filter`
- `/DCT#44ecode` -> `/DCTDecode`
- `/Decode#50arms` -> `/DecodeParms`
- `/Color#54ransform` -> `/ColorTransform`
- `/Len#67th` -> `/Length`

This slice adds a DCT-specific current-base regression for escaped image XObject filter dictionaries where the declared `/Length` stops at a fake early `endstream` inside JPEG bytes. The native parser and renderer now have focused coverage proving that escaped DCT filter names are normalized before:

- searchable text extraction excludes JPEG payload bytes;
- Image XObject review recovers the full raw JPEG payload through the real EOI marker;
- DCTDecode remains preview-only with `native_raster_decode=false`;
- DCT DecodeParms preserve `ColorTransform 1` for CMYK/YCCK review;
- the renderer ICCBased stream-preview path reports the same recovered DCT stream boundary.

## Evidence

Focused DCT escaped-filter test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeEscapedFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes escaped DCTDecode image filter names before stream-boundary review
1 test files, 43 assertions, 0 failures
```

DCT plus escaped-dictionary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeEscapedFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodePreviewPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserMalformedCMapEscapedKeyFilterBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 877 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-escaped-filter-boundary-currentbase.php
```

The smoke exits 0 and reports `escaped_filter_key_decoded=true`, `escaped_dct_filter_name_decoded=true`, `escaped_decodeparms_key_decoded=true`, `xobject_raw_length_recovered=true`, `renderer_raw_length_recovered=true`, `dctdecode_payload_excluded_from_text=true`, `preview_only_filters=["DCTDecode"]`, `native_raster_decode=false`, `uses_ycck_transform=true`, and no Python/model/OCR/PDFium/PIL/external PDF execution.

## Non-Overlap

This does not repeat accepted DCTDecode direct terminal review, DCT DecodeParms declaration/operand handling, null filter slots, malformed operands, indirect/comment-split filter references, post-EOI surplus clipping, EOI line-boundary checks, NUL/comment/SOS marker boundaries, native prefix filters, post-DCT filter reachability, renderer stream preview, CCITT escaped filters, malformed CMap escaped filter names, generic stream dictionary escaped-key parsing, OCR/model execution, or raster execution. The owned surface is DCT image XObject and renderer review metadata when the DCT filter key/value and DCT DecodeParms names use PDF name escapes.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF name decoder, stream dictionary parser, DCT stream-boundary scanner, Image XObject review rows, and renderer review plan. Full JPEG raster decoding, PDFium/PIL execution, OCR, and upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
