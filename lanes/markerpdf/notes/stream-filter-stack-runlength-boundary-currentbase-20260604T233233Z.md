# markerPDF RunLength stream filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260604T233233Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` via pypdfium page extraction.
- The native no-GPU PHP lane owns the PDF stream parser boundary. PDF `/Filter` arrays are ordered stacks, and `/RunLengthDecode` streams are complete only after the explicit EOD byte `128`.

## Behavior

`PdfTextExtractor::streamFilterInputHasExplicitEndMarker()` now treats `RunLengthDecode` and `RL` as explicit-end filters when validating candidate stream boundaries. During missing or stale `/Length` recovery, a delimiter-looking line-start `endstream` inside RunLength literal bytes is rejected unless the candidate includes the RunLength EOD marker and the full downstream `/FlateDecode` stage succeeds.

The focused fixture covers both:

- missing `/Length` with `/Filter [ /RunLengthDecode /FlateDecode ]`;
- stale declared `/Length` pointing at a raw fake `endstream` inside the encoded RunLength literal bytes.

Both paths recover only the intended WordPress paragraphs:

- `RunLength Flate Stack Before`
- `RunLength Flate Stack After`

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
PASS uses the complete filter stack when declared Length points at an encoded fake endstream boundary
PASS uses a complete Flate then ASCII85 stack before accepting compressed fake endstream boundaries
PASS requires RunLength EOD before accepting missing or stale filter-stack endstream boundaries

1 test files, 55 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `requires_runlength_eod_before_endstream_boundary=true`, `declared_length_points_at_runlength_fake_endstream=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and the RunLength Flate Stack Before/After paragraphs for both missing and stale Length paths.

Adjacent parser/filter/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
11 test files, 779 assertions, 0 failures
```

Required local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85-to-Flate missing-Length stack recovery, stale `/Length` ASCII85-to-Flate recovery, Flate-to-ASCII85 compressed-boundary recovery, length-bounded ASCIIHex/RunLength decoding, DecodeParms alignment/fail-closed behavior, indirect filter-name arrays, object-stream filter ownership, xref-stream filter DecodeParms recovery, image-filter exclusion, inline-image tokenizer boundaries, or encrypted-PDF preflight.

The bounded behavior is specifically RunLength EOD-aware boundary validation for ordered RunLength-to-Flate page-content streams under missing or stale `/Length`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary reader, filter resolver, RunLength decoder, Flate decoder, stale/missing stream-boundary recovery, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
