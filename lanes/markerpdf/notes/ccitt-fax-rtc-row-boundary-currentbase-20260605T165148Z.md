# markerPDF CCITT Fax RTC Row Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T165148Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T165148Z`
Base accepted HEAD: `8a97d1d6b1d7db8e2da4dd55d72688539c8f700f`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text on the structured PDF text path and leaves raster image bytes to image/PDFium/model paths. Under the current no-GPU native PHP scope, CCITTFaxDecode image bytes remain review-only, but the native parser must still keep image stream ownership correct so fake stream owners cannot leak into WordPress paragraphs.

PDF CCITT `/EndOfBlock` defaults to true. When it is true, a row EOL marker is not the terminal stream boundary even if `/Rows` is declared. Group 3 one-dimensional data must reach the RTC marker; Group 4 must reach EOFB. Row-count EOL ownership is only safe when `/EndOfBlock false` is explicitly in effect.

## Behavior

`PdfTextExtractor` now gates row-EOL ownership behind an explicit `/EndOfBlock false` DecodeParms value. True/default `/EndOfBlock` CCITT streams and inline images continue scanning until the RTC/EOFB marker before accepting the `endstream` or `EI` boundary.

The focused fixture covers:

- an XObject stream with `/Filter [/Crypt /CCITTFaxDecode]`, identity Crypt DecodeParms, `/Rows 1`, `/EndOfLine true`, and `/EndOfBlock true`;
- a fake `endstream/endobj` and page `/Contents 9 0 R` immediately after the first row EOL;
- the real RTC marker later in the same image payload;
- an inline `/CCF` image with the same row-EOL decoy before the final RTC and real `EI`.

## Red-First Evidence

Before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
FAIL requires CCITT RTC end-of-block markers when row EOL ownership is not terminal
1 test files, 483 assertions, 1 failures
```

The failure leaked `Fake RTC row CCITT leak` into extracted text.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 505 assertions, 0 failures
```

Adjacent image/filter/text extractor family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
7 test files, 3761 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-rtc-row-boundary-currentbase.php
```

The smoke exits 0 and emits `row_eol_not_terminal_when_end_of_block_true=true`, `inline_row_eol_not_terminal_when_end_of_block_true=true`, `raw_length_preserved_until_rtc=true`, `payload_excluded_from_review=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT image-only payload exclusion, malformed/unresolved DecodeParms fail-closed metadata, null-filter DecodeParms alignment, CCF alias metadata, post-CCITT filter reachability, direct EOFB/RTC ownership for zero-row streams, `/EndOfBlock false` row-count ownership, height-derived row-count ownership, identity Crypt CCITT EOFB ownership, RunLength prefix ownership, nested mask/alternate review, DCT/JPX/JBIG2 image boundaries, or live OCR/model work.

The bounded behavior is specifically that row EOL markers cannot close CCITT ownership while `/EndOfBlock` is true or defaulted true; RTC/EOFB remains required for XObject streams and inline images.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, stream dictionary reader, filter-stack resolver, DecodeParms boolean/int parsing, CCITT owner-boundary detector, image XObject review path, inline image tokenizer, and WordPress smoke renderer. Full CCITT raster decoding remains intentionally out of scope without a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
