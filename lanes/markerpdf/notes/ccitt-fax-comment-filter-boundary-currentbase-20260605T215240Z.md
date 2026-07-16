# markerPDF CCITT Fax Comment Filter Boundary

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text extraction separate from image rendering: PDF image bytes are rendered through `marker/pdf/images.py`, while text extraction must not ingest image payloads. Under the current no-GPU markerPDF scope, this PHP lane records CCITT Fax image review metadata without rasterizing or invoking PDFium/PIL, Python models, OCR, or external PDF tools.

PDF comments are lexical whitespace. A valid comment inside a `/Filter` array may contain `]`, and a valid comment inside a nested `/DecodeParms` dictionary may contain `>>`; those bytes must not close the array/dictionary while CCITT filter metadata is being prepared for WordPress review.

## Behavior

`PdfImageRenderer` now skips PDF comments while scanning balanced array and dictionary tokens. This preserves CCITT Fax renderer metadata when:

- `/Filter [ /ASCIIHexDecode % ] comment\n /CCF ]` declares a native prefix filter followed by preview-only CCITT Fax;
- `/DecodeParms [ null << ... /EndOfBlock false % >> comment\n >> ]` keeps the CCITT dictionary open until the real delimiter;
- inline image abbreviation expansion still maps `/CCF` to `/CCITTFaxDecode`;
- image payload bytes remain excluded from visible text/review metadata, and native raster decode remains false.

## Red-First Probe

Before the fix:

```bash
php -r 'require "tools/bootstrap.php"; $r=new PortLibs\MarkerPDF\PdfImageRenderer(); $dict="<< /Subtype /Image /Width 16 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter [ /ASCIIHexDecode % ] comment boundary\n /CCF ] /DecodeParms [ null << /K -1 /Columns 16 /Rows 1 /BlackIs1 true /EndOfBlock false % >> comment boundary\n >> ] /Decode [1 0] >>"; $p=$r->imageColorSpaceSoftMaskPlan($dict); var_export([$p["image_filters"]??null,$p["ccitt_fax_decode_boundary"]??null,$p["image_filter_details"]??null]);'
```

The current base reported only `['ASCIIHexDecode']`, with no `ccitt_fax_decode_boundary` and no CCITT filter details.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 667 assertions, 0 failures
```

New focused assertion delta: `642 -> 667` in the CCITT boundary file.

Adjacent renderer/image-filter gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result:

```text
5 test files, 1831 assertions, 0 failures
```

Smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-comment-filter-boundary-currentbase.php
```

The smoke emits `comment_boundaries_ignored=true`, `payload_excluded_from_review=true`, `native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, invalid/unresolved DecodeParms fail-closed metadata, null filter slot alignment, compact DecodeParms alignment, escaped CCITT names, Flate/LZW/RunLength prefix ownership, direct EOFB/RTC ownership, row-count/EOL ownership, ImageMask polarity, DCT/JPX/JBIG2 boundaries, inline image payload exclusion, or OCR/model behavior. The new bounded behavior is specifically renderer balanced-token parsing for PDF comments containing structural delimiter bytes inside CCITT `/Filter` arrays and nested `/DecodeParms` dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF image renderer review path, inline image dictionary expander, CCITT DecodeParms review builder, and WordPress smoke renderer. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend and is intentionally out of scope for this no-GPU slice.
