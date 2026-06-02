# markerPDF xref linearized Prev hint startxref current-base

Micro-slice: `xref-linearized-prev-hint-startxref-currentbase`
Session: `port-dev-markerpdf-xref57-20260602T211803Z`
Base accepted HEAD: `21d06ebd1b6613951a7951bffd383999ec33281d`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF parsing to `pdftext`/PDFium before model execution. Source: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.

PDF linearization dictionaries use `/H` byte ranges for hint-table data, not authoritative xref sections. A tolerant native current-base importer should follow the latest valid `startxref`, but it must not let `/Prev` values or embedded `startxref` tokens inside `/H` hint bytes select stale catalog/page/content rows.

## Behavior

`PdfTextExtractor` now treats linearized `/H` byte ranges as non-xref territory for:

- latest `startxref` token selection;
- xref table/stream `/Prev` chain merging;
- trailer `/Root` and `/Encrypt` fallback traversal;
- xref object-stream generation review traversal.

The focused fixture builds a linearized PDF whose latest `startxref` selects a current xref stream. That xref stream intentionally omits the current content stream row so native current-base fallback should use the latest direct content object. Its `/Prev` points into the linearized `/H` byte range, where a fake xref table selects an older same-object content stream. Before the fix, WordPress extraction emitted `Linearized Prev hint stale page` and `Hint xref leak`. After the fix, extraction emits only `Current startxref linearized page` and `Prev hint range skipped`.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefLinearizedPrevHintStartxrefCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps latest startxref current objects when Prev points into a linearized hint range
Expected: array (
  0 => 'Current startxref linearized page',
  1 => 'Prev hint range skipped',
)
Actual: array (
  0 => 'Linearized Prev hint stale page',
  1 => 'Hint xref leak',
)
1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefLinearizedPrevHintStartxrefCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps latest startxref current objects when Prev points into a linearized hint range
1 test files, 9 assertions, 0 failures
```

Adjacent xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*.php lanes/markerpdf/tests/PdfParserXref*.php lanes/markerpdf/tests/PdfObjectStream*.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 40 selected test files (root lock skipped)
40 test files, 1087 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-linearized-prev-hint-startxref-currentbase.php
uses_current_startxref_linearized_page=true
skips_prev_hint_range=true
excludes_linearized_prev_hint_stale_page=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefLinearizedPrevHintStartxrefCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-linearized-prev-hint-startxref-currentbase.php
No syntax errors detected.
```

JSON and whitespace:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " valid\n"; }'
lanes/markerpdf/lane-status.json valid
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json valid

git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted basic linearized `/H` stream exclusion, indirect linearized hint range resolution, object-stream hinted member exclusion, latest startxref stale object-stream rebuild suppression, xref-stream `/Prev` generation repair, hybrid `/Prev` underdeclared `/Size` repair, previous object-stream carrier generation guards, or stream-owned fake xref rejection.

The bounded behavior is specifically `/Prev` and embedded `startxref` candidates whose offsets are inside linearized `/H` hint byte ranges while the latest startxref-selected current xref stream remains authoritative.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, linearization dictionary hint-range parser, xref table/stream parser, `/Prev` chain walker, trailer metadata walkers, page-tree walker, stream decoder, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, OCR/rendering helpers, and external model/runtime dependencies.
