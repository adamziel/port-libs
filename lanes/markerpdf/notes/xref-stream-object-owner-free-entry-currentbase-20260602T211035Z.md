# markerPDF xref-stream object-owner free-entry current base

Micro-slice: `xref-stream-object-owner-free-entry-currentbase`
Session: `port-dev-markerpdf-xref51-20260602T2107Z`
Base accepted HEAD: `c246260033e061f468722755bd7ed5aed0b39863`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes structured PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level parsing to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` delegates page text to pypdfium page text extraction. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDFium parser behavior treats cross-reference entries as object ownership state: free rows are merged into the cross-reference table with `SetFree(...)`, while normal and compressed rows select direct or object-stream storage. Source: https://pdfium.googlesource.com/pdfium/+/master/core/fpdfapi/parser/cpdf_parser.cpp

## Behavior

`PdfTextExtractor::extractXrefObjectStreamIndexReview()` now exposes standalone current xref-stream type-0 free rows as review-only `free_entries`. Each row records the freed object number, free generation, next-free-object field, whether a scanned direct definition was suppressed, whether a previous `/Prev` entry was suppressed, and whether the previous entry was a type-2 object-stream member.

The focused fixture builds:

- a previous xref stream where object `4` is a type-2 compressed page member in object stream `6`;
- a scanned stale direct `4 0 obj` page body that would leak if free entries were ignored;
- a current xref stream whose `/Index` row marks object `4` type 0/free with generation `2`;
- a current page tree that mentions `4 0 R` plus a valid current page, proving the free row skips the stale page owner while preserving current text.

Before this slice the text extraction already skipped the free object, but the current-base review surface could not prove why. After the slice, WordPress smoke metadata reports `xref_stream_free_owner_count=1`, `direct_object_suppressed=true`, `previous_entry_type=2`, and owner policy `xref_stream_free_entry_suppressed_prev_compressed_object`.

## Evidence

Focused single test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps current xref-stream free entries authoritative over stale direct and previous object-stream owners

1 test files, 29 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 134 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-object-owner-free-entry-currentbase.php
```

The smoke emits current Gutenberg paragraphs only: `Current xref stream free owner page` and `Free row owns stale object`. It reports `uses_current_xref_stream_free_owner_page=true`, `reports_xref_stream_free_owner=true`, `suppresses_previous_compressed_owner=true`, `suppresses_scanned_direct_owner=true`, `excluded_stale_direct_page=true`, `excluded_stale_compressed_page=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-object-owner-free-entry-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-stream-object-owner-free-entry-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " json ok\n"; }'
lanes/markerpdf/lane-status.json json ok
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json json ok

git diff --check -- lanes/markerpdf
passed with no output
```

Status delta: behavior tests `818 -> 819`; mapped parser semantics `575 -> 576 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted hybrid xref table free-owner precedence, object-generation free-entry reuse guards, incremental object-stream free repair, previous object-stream carrier generation review, xref-stream owner-cycle rejection, type-2 omitted member-index repair, xref-stream `/Prev` generation repair, malformed `/Index` offset-owner repair, or stream-owned xref/startxref token rejection.

The bounded behavior is specifically a latest current xref stream type-0 free row whose object number has both a scanned stale direct body and a previous `/Prev` compressed-object owner. The free row stays authoritative, and the suppression is now review-visible.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, latest `startxref` chain, xref stream parser, `/Prev` merger, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, live benchmark tooling, model downloads, and external OCR/rendering helpers.
