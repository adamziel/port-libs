# markerPDF linearized object-stream referenced stream-member repair

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T075231Z`
Session: `port-dev-markerpdf-object-xref-20260605T075231Z`
Base accepted HEAD: `f4a714cee9206bb53c7a3560db2ebdeb5e7daf8e`

## Source truth

Upstream markerPDF delegates searchable PDF text extraction to pdftext and pypdfium/PDFium page parsing. For the native PHP port, xref stream traversal, object-stream member ownership, linearized hint-table exclusion, page-tree recovery, and page `/Contents` stream resolution are therefore parser dependency boundaries.

Object streams normally reject top-level stream members in the PHP port, matching the existing fail-closed guard tests. The bounded repair here is narrower: when a linearized `/H` hint range points inside an object-stream payload, the parser may need to keep the selected object-stream carrier and expand the current page tree before it can tell which compressed content stream members are live. In that repair path only, a top-level stream member is admitted only after an expanded `/Page` dictionary references it through `/Contents`; hinted stale compressed members are still removed.

## Behavior

`PdfTextExtractor::withObjectStreamObjects()` now accepts an internal `allowReferencedStreamMembers` flag. The normal review and extraction paths keep rejecting top-level stream members in object streams. `pdfObjects()` enables the flag only when linearized hint ranges exist, and `objectsFromObjectStreams()` then admits a stream member only if the currently expanded page objects reference that object number as page content.

This fixes two current-base failures:

- a linearized xref stream whose `/H` range points to stale compressed content member `9`, while current page text comes from compressed content member `7`;
- a hybrid linearized file where `/H` points to stale compressed generation-zero page member `4`, while the current direct generation `4 1` references compressed content member `9`.

## Red-first evidence

Before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefLinearizedObjectStreamHintRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php
Focused test run: 3 selected test files (root lock skipped)
FAIL preserves repaired direct generation page when a linearized hint range covers the stale compressed member
FAIL preserves linearized object-stream carrier while skipping hinted member before current-base text extraction
PASS keeps current trailer page tree before unselected object-stream repair fallback
3 test files, 11 assertions, 2 failures
```

After the repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefLinearizedObjectStreamHintRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS preserves repaired direct generation page when a linearized hint range covers the stale compressed member
PASS preserves linearized object-stream carrier while skipping hinted member before current-base text extraction
PASS keeps current trailer page tree before unselected object-stream repair fallback
3 test files, 26 assertions, 0 failures
```

Adjacent object-stream guard run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamUnfilteredStreamMemberCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 139 assertions, 0 failures
```

## WordPress smokes

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-linearized-object-stream-hint-repair-currentbase.php
```

The smoke emits `uses_current_object_stream_page=true`, `skips_linearized_hint_member=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with only these paragraphs:

- `Current object stream page`
- `Linearized hint member skipped`

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-linearized-object-stream-generation-currentbase.php
```

The smoke emits `uses_current_linearized_hybrid_page=true`, `preserves_repaired_direct_generation_page=true`, `excludes_hinted_stale_generation_zero_page=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with only these paragraphs:

- `Current linearized hybrid page`
- `Hinted compressed generation skipped`

## Non-overlap

This does not repeat accepted direct top-level stream-member rejection, unfiltered stream-member rejection, object-stream member offset ordering, skipped header rows, incomplete object-stream header fail-closed behavior, linearized hint object-number exclusion, or hybrid direct-generation repair by itself. The new bounded behavior is specifically the composition of linearized hint repair with page-referenced selected compressed content-stream members.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref table/xref-stream parser, object-stream decoder, linearized hint-range detector, page-tree walker, content stream decoder, WordPress smoke path, and TestRunner. Full upstream markerPDF model/OCR parity remains out of scope under the current no-GPU direction.

## Final verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

jq empty lanes/markerpdf/lane-status.json
passed

git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.
