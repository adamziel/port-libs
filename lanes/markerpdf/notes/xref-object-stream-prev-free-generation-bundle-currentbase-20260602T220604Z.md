# markerPDF xref object-stream Prev free generation bundle current-base

Micro-slice: `xref-object-stream-prev-free-generation-bundle-currentbase`
Session: `port-dev-markerpdf-xref71-20260602T220604Z`
Base accepted HEAD: `f7360ce6eb81b8c1919c66db722cb7028bf7e306`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level xref/object parsing to `pdftext` and pypdfium/PDFium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

Relevant dependency behavior is PDFium parser behavior: start from `startxref`, follow `/Prev`, keep the current xref section authoritative for object numbers, and parse type-2 compressed objects through the selected object-stream carrier. Sources: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_parser.cpp> and <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

The bundled fixture builds an incremental PDF where:

- the previous xref stream maps page object `4` as a type-2 member of object stream `6 0`;
- the latest xref stream marks object `4` free at generation `1`;
- the latest xref stream maps page object `10` as a type-2 member of object stream `6`;
- the current revision contains a newer direct `6 1 /ObjStm` carrier, but the latest xref stream omits a direct row for carrier `6`.

Text extraction already selected the current object-stream page and excluded stale compressed member text. The missing boundary was review metadata: `extractXrefPrevObjectStreamGenerationReview()` skipped the previous type-2 row silently because the current section had an entry for object `4`.

`PdfTextExtractor` now records that decision as a review-only skipped inherited type-2 entry with `owner_policy=skipped_current_free_object_generation`, `current_free_object_suppressed=true`, `current_object_generation=1`, and top-level `skipped_current_free_object_count`.

WordPress paragraph extraction emits only:

- `Current bundled object stream page`
- `Free generation member suppressed`

The stale previous compressed page, object-stream member dictionaries, Python workers, pdftext, pypdfium, model execution, raster execution, and external PDF tools remain excluded.

## Evidence

Red-first run before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reviews current free generation rows that suppress stale Prev object-stream members while rebuilding the current carrier
Values are not identical
Expected: 1
Actual: 0

1 test files, 12 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reviews current free generation rows that suppress stale Prev object-stream members while rebuilding the current carrier

1 test files, 28 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 138 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-prev-free-generation-bundle-currentbase.php
```

The smoke emitted `uses_current_bundled_object_stream_page=true`, `suppresses_stale_prev_member=true`, `excludes_object_stream_payload_text=true`, `skipped_current_free_object_count=1`, `owner_policy=skipped_current_free_object_generation`, `current_object_generation=1`, `object_stream=6`, and `page_count=1`, followed by Gutenberg paragraphs for only the current text.

Status delta: `phpPass` / `wordpressScenarios` move `892 -> 893`; mapped markerPDF semantics move `629 -> 630 / 78` with `pdfXrefObjectStreamPrevFreeGenerationBundleCurrentBase`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat current type-2 rows preserving a direct carrier when `/Prev` marks that carrier free, rebuilt current object-stream carrier precedence over stale previous direct carrier rows, current free-entry suppression without review metadata, previous type-2 rows whose carrier was absent or compressed, same-storage carrier replay, hybrid table free-entry precedence over companion `/XRefStm`, zero-width member-index recovery, or malformed current carrier generation recovery.

The bounded behavior here is specifically a previous type-2 object-stream member row skipped by a current free generation row while a different current type-2 row still needs a rebuilt current object-stream carrier.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, startxref `/Prev` chain parser, xref-stream decoder, object-stream expander, review metadata path, page-tree walker, stream decoder, text-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
