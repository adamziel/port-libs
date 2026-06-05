# markerPDF inline image tokenizer slash delimiter boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T010428Z`

Base accepted HEAD: `0ea8dd0772ccf1520f53c121288a94ef07992eca`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text extraction through `marker/pdf/extract_text.py` into the pdftext/PDF parser boundary. Inline image payload bytes are image data, not visible WordPress paragraph text.

Relevant PDF parser behavior: `/` is a PDF delimiter. A compact content stream token sequence such as `BI/W 16/H 1/CS/G/BPC 8 ID` is lexically equivalent to `BI /W 16 /H 1 /CS /G /BPC 8 ID`, so the native tokenizer must start an inline-image boundary before scanning payload bytes for `EI`.

## Red-First Probe

Before the source fix, the focused test fixture leaked inline image payload text from a slash-delimited preamble:

```text
FAIL treats slash-delimited compact BI dictionaries as inline images before WordPress text extraction
Expected: Before Compact Delimiter Boundary, After Compact Delimiter Boundary
Actual: Before Compact Delimiter Boundary, Compact Delimiter Inline Payload Noise, After Compact Delimiter Boundary
```

The failing command was:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

It reported `1 test files, 73 assertions, 1 failures` after adding the red fixture.

## Implementation

`PdfTextExtractor` now splits bare content tokens on `/` while keeping the existing PDF name tokenizer and inline-image `EI` marker boundary unchanged. This lets `contentTokens()`, content-stream endstream scanning, inline-image preamble detection, inline-image dictionary parsing, and generic PDF array item parsing see compact slash-delimited operator/name boundaries.

The inline image dictionary reader also now requires whitespace after the `ID` operator before image data begins, matching the PDF inline-image separator boundary instead of treating an adjacent bare token as image data.

## Evidence

Focused verification after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

Result: `1 test files, 81 assertions, 0 failures`.

The WordPress smoke `wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` now emits `compact_slash_delimited_inline_image_excluded=true`, keeps `After Compact Delimiter Boundary`, and excludes `Compact Delimiter Inline Payload Noise`.

Additional non-required broad lane check:

```bash
php tools/run-tests.php lanes/markerpdf/tests
```

Result: `357 test files, 20135 assertions, 2 failures`. The failing files were `PdfEncryptedPermissionRevisionBitCurrentBaseTest.php` and `PdfSecurityPublicKeyPermissionCurrentBaseTest.php`, both in security preflight review-reason expectations outside this tokenizer slice. The focused inline-image/text extractor families above passed after the source patch.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior inline image Flate/DecodeParms validation, null filter-array alignment, DCT/JPX/JBIG2/CCITT preview-only payload framing, malformed `BI` preamble recovery, early `EI` sample-floor behavior, object-stream filter helpers, image XObject filtering, OCR/model execution, PDFium rendering, or external PDF tools.

The bounded behavior is specifically PDF lexical delimiter handling for compact slash-delimited inline image dictionaries before WordPress text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, stream boundary skipper, and WordPress smoke path. Full live upstream OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Next Task

Continue with a non-overlapping native searchable-PDF parser gap such as page-resource token boundaries, font/CMap width behavior, xref repair, annotation/form review, image/filter metadata, supplied table/equation handoffs, or runtime preflight behavior.
