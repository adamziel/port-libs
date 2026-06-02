# markerPDF parser inline stream length/filter repair

Micro-slice: `parser-inline-stream-length-filter-repair-currentbase-20260602T171314Z`

Base accepted HEAD: `49180e79432b8b918699ff28f84476d5fe362bc7`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes visible PDF text through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates structured page text to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` delegates bounded page text to pypdfium. The native PHP parser must therefore preserve PDF parser stream ownership boundaries before WordPress paragraphs are produced.

For this slice, the parser boundary is a filtered direct page `/Contents` stream whose `/Length` is an indirect object declared after the stream object. If the compressed bytes contain line-delimited `endstream`, `endobj`, or fake `obj` tokens, those bytes are still stream payload; they cannot terminate the owner object or seed fake indirect objects before filter decoding.

## Native Behavior Added

`PdfTextExtractor::pdfObjectEndOffset()` now uses the stream dictionary filter chain as a verifier when a direct stream boundary lacks a currently resolvable declared length. It skips early `endstream` candidates whose filtered payload cannot decode and accepts the first line-delimited candidate whose payload decodes through supported filters.

`PdfTextExtractor::streamPayloadAt()` uses the same filtered boundary verifier for no-length payload recovery. This keeps ordinary stale-length recovery intact while repairing post-stream indirect `/Length` cases where the object scanner must not split inside compressed payload bytes.

## Evidence

Red-first focused test on accepted source:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs filtered stream boundaries when indirect Length follows inline payload bytes
Expected: [Inline stream length repair, Filter payload stays current]
Actual: []
1 test files, 1 assertions, 1 failures
```

Focused test after repair:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS repairs filtered stream boundaries when indirect Length follows inline payload bytes
1 test files, 8 assertions, 0 failures
```

Adjacent parser/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php
11 test files, 700 assertions, 0 failures
```

Example smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-inline-stream-length-filter-repair-currentbase.php
visible_text_imported=true
stream_owned_fake_object_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required local checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-parser-inline-stream-length-filter-repair-currentbase.php` passed.
- `php -r` JSON validation for `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted stale direct `/Length` recovery, simple indirect `/Length` owner scanning before fake DecodeParms objects, stream dictionary escaped-name parsing, direct fallback stream object-boundary selection, inline-image `/F` array abbreviation/null-entry handling, object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, xref-stream filter DecodeParms repair, or xref/object-stream generation repair. The bounded behavior is specifically filtered direct stream boundary selection when the `/Length` object appears after the owner stream and the compressed payload contains fake file-level tokens.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary parser, stream filter dispatcher, Flate decoder, content-token extractor, WordPress smoke path, and existing parser/xref tests. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and optional OCR/rendering helpers.
