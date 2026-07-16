# markerpdf xref object-stream omitted graph indirect-wrapper current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T155026Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF obtains searchable PDF text through the PDF parser path before OCR/model fallback. Under the current no-GPU PHP lane, native object-stream and xref-stream boundaries must be enforced before page-tree repair can feed WordPress paragraph output.
- PDF object-stream members contain object bodies, not full indirect object wrappers. A member whose body starts with `n g obj ... endobj` is malformed in an `/ObjStm`; it must not repair an omitted compressed catalog/page-tree graph from a current xref stream.

## Behavior

`PdfTextExtractor::repairOmittedCurrentUpdateGraphRows()` now uses the same object-stream member boundary guards as normal compressed-object expansion before it repairs omitted current-update graph rows.

The new `objectStreamMemberBodyForGraphRepair()` helper rejects:

- empty object-stream member bodies;
- indirect-object wrapper members;
- top-level stream-object members;
- multi-value member tails that cannot be safely recovered from a later invalid offset.

Valid single top-level member bodies, and recoverable first top-level values for damaged later offsets, remain available to the existing omitted-graph repair path.

## Red-First Evidence

Before the source edit, the focused fixture allowed an indirect-object wrapper catalog member to repair the current page graph:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphIndirectWrapperCurrentBaseTest.php
FAIL rejects indirect-object wrapper members before omitted graph repair expands page trees
Expected: array (
  0 => 'Current wrapper guard page',
)
Actual: array (
  0 => 'Wrapped omitted graph leak',
  1 => 'Indirect wrapper graph ignored',
  2 => 'Current wrapper guard page',
)
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphIndirectWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects indirect-object wrapper members before omitted graph repair expands page trees

1 test files, 21 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCompressedOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedMetadataGraphCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndirectWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 201 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-graph-indirect-wrapper-currentbase.php --self-test
exits 0 with guard_page_selected=true, wrapped_omitted_graph_suppressed=true, compressed_page_tree_not_repaired_from_wrapper=true, compressed_entry_count=1, indirect_member_wrapper_rejection_count=1, object_1_selection_policy=indirect_object_wrapper_member, object_1_wrapper_rejected=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphIndirectWrapperCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-graph-indirect-wrapper-currentbase.php
No syntax errors detected
```

```text
git diff --check -- lanes/markerpdf
exits 0
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream header comments, incomplete headers, `/First` repair, duplicate offsets, out-of-range type-2 indexes, stream member rejection in normal expansion, ordinary indirect-wrapper expansion review, valid omitted graph repair, compressed `/N` and `/First` helper repair, attachment/metadata object-stream boundaries, xref `/Prev` generation repair, or current carrier repair. This slice only closes the current-update omitted graph repair path that previously reused compressed member bodies without the indirect-wrapper/single-value guards.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-stream parser, object-stream decoder, page-tree repair logic, content extractor, xref review metadata, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium runtime parity, PIL, Streamlit/FastAPI workers, JavaScript/PDF action execution, decryption/password validation, signing/signature validation, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
