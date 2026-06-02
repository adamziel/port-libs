# markerPDF parser stream-length startxref recovery current base

Micro-slice: `parser-stream-length-startxref-recovery-currentbase`

Base accepted HEAD: `78dacbd21ee6b9a83b42fbcf69facc371244266b`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` routes structured text through `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` through `pypdfium2` page text extraction. The native PHP parser therefore owns the PDF parser boundary that decides which file-level `startxref` and xref stream are current before WordPress paragraphs are emitted.
- PDF parser boundary for this slice: a stale stream `/Length` can land on bytes that spell a line-delimited `endstream/endobj/startxref` inside stream payload. Those payload bytes must not close the owning object or expose a fake later `startxref` when a verified later stream terminator exists.

## Behavior

`PdfTextExtractor` now treats a declared `/Length` terminator as recoverable when both conditions hold:

- a later stream terminator is verified by content-stream token scanning or native filter decoding;
- the gap after the declared terminator would expose a `startxref` token.

The same recovery is applied in the direct-object scanner and in the later stream payload reader. This preserves normal declared-length behavior while preventing embedded fake `startxref` bytes from redirecting current-base xref selection.

The focused fixture builds a current xref stream and page, then appends stale page objects and a stale xref stream. A later unreferenced carrier stream declares a short `/Length` that lands on `endstream` inside a literal string, followed by embedded `endobj/startxref` bytes pointing to the stale xref stream. The repaired scanner keeps that fake marker owned by the carrier stream, so the real current `startxref` remains authoritative.

## Red Probe

Before the source change, the focused probe emitted stale page text:

```text
array (
  0 => 'Stale startxref recovery page',
  1 => 'Fake stale trailer leak',
)
```

After the source change, it emits only current page text:

```text
array (
  0 => 'Current recovered startxref page',
  1 => 'Length carrier ignored',
)
```

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers current startxref when stale stream Length lands on embedded terminator bytes

1 test files, 11 assertions, 0 failures
```

Adjacent parser/xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 693 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-stream-length-startxref-recovery-currentbase.php
```

The smoke emitted `uses_current_startxref_page=true`, `recovered_current_trailer=true`, `embedded_startxref_token_ignored=true`, `excluded_stale_length_selected_page=true`, `excluded_fake_trailer_leak=true`, `carrier_payload_excluded=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by two Gutenberg paragraphs for the current page.

Required local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-length-startxref-recovery-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All required local checks passed.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stale stream `/Length` endstream recovery, stream-owned `startxref` token rejection, stream-owned xref table offset rejection, stream-owned xref-stream object rejection, indirect post-stream `/Length` filtered boundary repair, stream dictionary escaped-name parsing, xref-stream `/Prev` generation repair, hybrid xref precedence, object-stream member repair, or xref stream indirect `/Filter`/`Length` owner review.

The new behavior is specifically the combined boundary where a stale declared stream length lands on embedded terminator bytes and would otherwise expose a fake latest `startxref` before current-base xref selection.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, stream dictionary parser, stream filter decoder, content-stream token scanner, xref stream parser, page-tree walker, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and optional OCR/rendering helpers.
