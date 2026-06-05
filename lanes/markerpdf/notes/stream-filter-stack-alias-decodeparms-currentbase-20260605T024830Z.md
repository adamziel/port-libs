# markerPDF abbreviated stream-filter stack DecodeParms boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T024830Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level stream filter decoding before OCR/layout/model stages.
- PDF stream filters can use abbreviated names such as `/A85` and `/Fl`, and filter arrays can include `null` identity entries. A compact `/DecodeParms` array must bind only to real filters even when a null placeholder sits between abbreviated filters.

## Behavior

The existing native PHP decoder already supports this boundary. This slice locks the current-base behavior with a focused fixture:

```text
/Filter [ /A85 null /Fl ]
/DecodeParms [ null << /Predictor 12 /Columns N >> ]
```

The raw ASCII85 bytes contain a line-start fake `endstream` before the ASCII85 EOD marker. The Flate output is a PNG predictor row, so the predictor dictionary must be applied to the abbreviated `/Fl` stage to strip the row filter byte before WordPress paragraph extraction.

WordPress-visible paragraphs:

```text
Alias Params Stack Before
Alias Params Stack After
```

The encoded fake `endstream`, abbreviated filter names, predictor metadata, row-filter NUL byte, and raw binary marker bytes remain excluded from Gutenberg paragraphs.

## Evidence

Baseline focused stack test before this coverage addition:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 93 assertions, 0 failures
```

Focused test after this patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS applies singleton DecodeParms dictionaries to the only real filter after null stack entries
PASS aligns compact DecodeParms arrays to real filters after null placeholders in stream stacks
PASS aligns compact DecodeParms arrays to abbreviated filters around middle null placeholders
PASS ignores stray DecodeParms when no stream filters are declared before WordPress text extraction
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
PASS uses the complete filter stack when declared Length points at an encoded fake endstream boundary
PASS uses the complete filter stack when stale Length lands before an encoded fake endstream boundary
PASS uses a complete Flate then ASCII85 stack before accepting compressed fake endstream boundaries
PASS requires RunLength EOD before accepting missing or stale filter-stack endstream boundaries

1 test files, 105 assertions, 0 failures
```

Focused assertion delta for this file: `93 -> 105`.

Adjacent stream/filter boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php
4 test files, 565 assertions, 0 failures
```

Syntax and metadata checks:

```text
php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php

jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
passed

git diff --check -- lanes/markerpdf
passed
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke metadata emits `abbreviated_filter_compact_decodeparms_middle_null=true`, `requires_complete_filter_stack_before_boundary=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with the alias-stack paragraphs included in the generated block output.

Root harness status: not run - isolated micro-slice.

Optional broad check note: `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php` was not used as acceptance evidence because existing xref coverage in `PdfTextExtractorTest.php` raises an unrelated `Undefined variable $previousOffset` warning in `PdfTextExtractor.php` and fails `honors hybrid xref Prev free entries and encrypted PDF preflight before WordPress text extraction`. The focused and adjacent stream/filter boundary checks above pass.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, canonical compact DecodeParms alignment after a leading null placeholder, singleton DecodeParms after a null filter, stale declared-Length stack recovery, Flate-to-ASCII85 recovery, RunLength EOD-aware recovery, indirect filter-name arrays, inline-image abbreviation handling, malformed CMap filter operands, xref-stream DecodeParms recovery, object-stream filter ownership, or image-filter exclusion.

The bounded behavior is specifically abbreviated `/A85` plus `/Fl` stream filters around a middle `null` placeholder, where a compact `/DecodeParms` array must still bind the predictor dictionary to the real Flate stage while stream-boundary recovery requires the complete encoded stack.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary reader, filter resolver, DecodeParms parser, ASCII85 decoder, Flate decoder, missing/stale stream-boundary recovery, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
