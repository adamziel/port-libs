# markerPDF xref object-stream skipped header index current-base slice

Slice: `markerpdf-object-stream-xref-parser-current-base-20260603T084007Z`

Session: `port-dev-markerpdf-object-xref-20260603T084007Z`

Base accepted HEAD: `72f5cb84857abafdc63cdb83c5e14ce84d9bf3fb`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates PDF text extraction to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` delegates bounded page text to pypdfium/PDFium: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>.
- PDFium object-stream parsing validates `/Type /ObjStm`, `/N`, and `/First`, skips zero object-number header rows, and resolves compressed members through object-stream archive indexes: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>.

## Implemented Behavior

`PdfTextExtractor` now compares xref-stream type-2 member indexes against each parsed object-stream member's original header index, not the filtered PHP list position after skipped zero object-number rows.

Before the fix, a current xref stream with object `4` as a type-2 member index `1` in carrier `6` was dropped when the `/ObjStm` header began with skipped row `0 0`. WordPress extraction emitted only the direct guard page. After the fix, the explicit current member expands, the skipped zero-row decoy stays out of visible text, and review metadata records `xref_member_index=1`, `actual_member_index=1`, and `selection_policy=explicit_member_index`.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps explicit object-stream member indexes aligned after skipped header rows (lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php)
1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStream*Test.php lanes/markerpdf/tests/PdfParserObjectStream*Test.php lanes/markerpdf/tests/PdfParserXrefObjectStream*Test.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php
Focused test run: 21 selected test files (root lock skipped)
21 test files, 356 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-skipped-header-index-currentbase.php
```

Smoke emitted non-empty Gutenberg paragraph output for `Current skipped-header guard page`, `Skipped header explicit index page`, and `Zero object-number header ignored`, with `uses_skipped_header_explicit_index_page=true`, `excluded_skipped_zero_header_decoy=true`, `xref_member_index=1`, `actual_member_index=1`, and `selection_policy=explicit_member_index`.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-skipped-header-index-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All syntax, JSON, and whitespace checks passed.

## Non-Overlap

This does not repeat accepted object-stream nested token parsing, indirect `/Length`/`/Filter`/`/N`/`/First` recovery, explicit type-2 direct `/ObjStm` base preservation, zero-width member-index recovery, duplicate zero-width member fail-closed behavior, object-stream header comment parsing, xref-stream `/Prev` carrier generation repair, hybrid table carrier ownership, current free-entry suppression, or compressed helper filter-chain expansion. The bounded behavior here is specifically explicit type-2 member-index alignment after skipped zero object-number `/ObjStm` header rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, xref-stream parser, object-stream decoder, review metadata path, and WordPress smoke pattern. Full upstream markerPDF runner parity remains intentionally out of scope under the current no-GPU direction and remains gated on live pdftext/pypdfium/PDFium runtime execution, Surya/Torch/OCR model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark workflows, and external PDF/model tooling.

## Next Task

Continue with bounded native searchable-PDF parser behavior: remaining xref repair, stream filters, font/CMap metrics, metadata/action review, annotation/forms, page geometry, image/filter metadata, or supplied-boundary conversion edges that can ship with focused PHP tests and a WordPress smoke.
