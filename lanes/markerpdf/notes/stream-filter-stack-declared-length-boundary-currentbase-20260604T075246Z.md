# markerPDF declared-Length stream filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260604T075246Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` via pypdfium page text extraction: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>
- The native PHP lane owns this no-GPU parser boundary. PDF stream `/Filter` arrays are ordered stacks, so bytes should become WordPress paragraphs only after the declared stack decodes completely.

## Behavior

`PdfTextExtractor` now rejects a declared `/Length` boundary when it lands on a line-start `endstream` token inside encoded stream bytes if that declared payload cannot decode through the complete filter stack and a later candidate can.

The focused fixture uses `/Filter [ /ASCII85Decode /FlateDecode ]`, a stale `/Length` pointing at an ASCII85-encoded fake `endstream`, and a later true terminator after the ASCII85 `~>` EOD marker. The imported text is recovered only from the complete decoded stream:

- `Declared Length Stack Before`
- `Declared Length Stack After`

The fake delimiter bytes and the raw fake marker do not become visible text.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
FAIL uses the complete filter stack when declared Length points at an encoded fake endstream boundary
Values are not identical
Expected: array (
  0 => 'Declared Length Stack Before',
  1 => 'Declared Length Stack After',
)
Actual: array (
)

1 test files, 19 assertions, 1 failures
```

## Verification

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
PASS uses the complete filter stack when declared Length points at an encoded fake endstream boundary

1 test files, 27 assertions, 0 failures
```

Adjacent parser/filter/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
11 test files, 751 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emitted six Gutenberg paragraphs and `declared_length_points_at_encoded_fake_endstream=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Required local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'
manifest json ok

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited with status 0 and no output.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted missing-`/Length` ASCII85 EOD recovery, missing-`/Length` ASCII85-to-Flate stack recovery, stale length recovery where the declared end does not land at an `endstream`, embedded `startxref` stream-owner recovery, indirect filter-name arrays, DecodeParms alignment/fail-closed behavior, object-stream filter ownership, xref-stream filter DecodeParms recovery, image-filter exclusion, inline-image tokenizer boundaries, or encrypted-PDF preflight.

The new boundary is specifically declared `/Length` pointing at an encoded fake `endstream` while the full ordered stream filter stack succeeds at a later terminator.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream dictionary reader, indirect operand resolver, stream filter dispatcher, missing/stale stream-boundary recovery, content-token parser, and WordPress smoke path. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU lane rule and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
