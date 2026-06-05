# markerPDF xref object-stream duplicate-offset current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T013043Z`

Session: `port-dev-markerpdf-object-xref-20260605T013043Z`

Base accepted HEAD: `a4230b52884f2374fef6e49e1e5da092a521b1cf`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to parser-backed `pdftext`/PDFium boundaries before model/OCR fallback. In this native PHP no-GPU lane, PDF 1.5 object-stream member selection and xref-stream type-2 rows are parser dependency behavior before WordPress-visible text is emitted.

PDF object-stream headers identify member object numbers and byte offsets into the object data area. A duplicate member body offset is an ambiguous object boundary: two distinct object numbers cannot safely own the same member bytes. The native parser now fails closed for those duplicate-offset members instead of letting one compressed page dictionary impersonate multiple xref-selected objects.

## Behavior

The focused fixture builds a current xref stream where objects `4` and `12` are both type-2 rows in object stream `6`, and the object-stream header declares both members at offset `0`. Object `4` is in the page tree, so accepting the duplicate-offset member leaks `Duplicate offset compressed page leak` into WordPress paragraphs.

Before this patch, `PdfTextExtractor::objectsFromObjectStreams()` expanded object `4` from the shared member body. After this patch, object-stream expansion rejects members whose selected header offset appears more than once. Review metadata exposes `duplicate_member_offset_rejection_count=2`, `selection_policy=duplicate_object_stream_member_offset`, `matching_header_offset_count=2`, and `duplicate_member_offset_rejected=true`.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate object-stream member offsets before WordPress text extraction (lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current duplicate-offset guard page',
)
Actual: array (
  0 => 'Current duplicate-offset guard page',
  1 => 'Duplicate offset compressed page leak',
  2 => 'Shared member body rejected',
)

1 test files, 1 assertions, 1 failures
```

Focused green run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate object-stream member offsets before WordPress text extraction

1 test files, 20 assertions, 0 failures
```

Adjacent object-stream/xref parser sweep:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'ObjectStream|Xref.*Object|Parser.*ObjectStream' | sort)
Focused test run: 40 selected test files (root lock skipped)
40 test files, 691 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-duplicate-offset-currentbase.php
```

emits one Gutenberg paragraph, `Current duplicate-offset guard page`, plus `duplicate_member_offset_rejection_count=2`, `rejects_duplicate_offset_member=true`, `selection_policy=duplicate_object_stream_member_offset`, `excludes_duplicate_offset_page_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/status checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-duplicate-offset-currentbase.php
php -r '...json_decode lane-status.json and UPSTREAM_TEST_MANIFEST.json...'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream header comment parsing, skipped zero object-number rows, incomplete header fail-closed behavior, offset-order body slicing, explicit type-2 member-index selection, zero-width member-index recovery, duplicate object-number zero-width rejection, direct `/ObjStm` base preservation, stream-object member rejection, carrier generation recovery, compressed helper filter-chain expansion, or stream-owned xref/startxref rejection.

The bounded behavior is specifically duplicate object-stream member body offsets across distinct header rows, where even explicit xref type-2 member indexes are rejected because the same bytes cannot safely own two PDF objects.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, page-tree walker, content stream tokenizer, xref review metadata path, and WordPress smoke renderer. Full upstream model/OCR/runtime parity remains out of scope under the current no-GPU markerPDF directive and dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
