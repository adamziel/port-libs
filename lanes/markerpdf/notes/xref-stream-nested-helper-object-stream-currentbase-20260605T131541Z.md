# markerPDF xref stream nested helper object-stream current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T131541Z`
Base: `032d521fe5794ef3313e865db12d4e14587aba5d`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text extraction through parser-backed PDF object loading before OCR/model fallback. Under the current no-GPU lane scope, the PHP port owns native xref stream, object stream, and stream-filter behavior without running Python, models, PDFium, or external PDF tools.

PDF comments are whitespace in indirect-reference operands. Xref-stream dictionaries may place `/W` and `/Index` behind indirect helper objects, and those helpers may themselves be compressed object-stream members before the xref stream is decoded.

## Behavior

`PdfTextExtractor` now resolves xref-stream array operands through nested indirect references when the intermediate helper objects are current compressed object-stream members. The resolver keeps generation/object recursion bounded, reuses the existing comment-aware PDF indirect-reference token reader, and skips comments, literal strings, and hex strings while discovering helper references.

The focused fixture builds a current xref stream where `/W 30 0 R` and `/Index 31 0 R` resolve as:

- object-stream member `30`: `33 % comment\n0 R`
- object-stream member `31`: `34 % comment\n0 R`
- object-stream member `33`: `[1 4 1]`
- object-stream member `34`: `[1 6 9 1 30 2]`

Before the patch the xref stream was rejected before row decode, leaving no selected page text. After the patch, the current object-stream page object is selected and the stale direct page fallback remains excluded.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves xref-stream W and Index arrays through nested compressed helper references
Expected: array (
  0 => 'Current nested xref helper page',
  1 => 'Nested compressed helper references selected',
)
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves xref-stream W and Index arrays through nested compressed helper references
1 test files, 18 assertions, 0 failures
```

Adjacent xref/object-stream parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php
Focused test run: 45 selected test files (root lock skipped)
45 test files, 860 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-nested-helper-currentbase.php
```

The smoke emits:

- `current_text_selected=true`
- `nested_helper_line_selected=true`
- `stale_direct_page_excluded=true`
- `stale_text_excluded=true`
- `fallback_text_excluded=true`
- `compressed_entry_count=3`
- `page_object_stream=6`
- `selection_policy=explicit_member_index`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Root harness status: not run - isolated micro-slice.

Status delta:

- Behavior tests: `1857 -> 1858`.
- WordPress scenarios: `1685 -> 1686`.

## Non-Overlap

This does not repeat accepted direct indirect `/W` and `/Index` helper arrays, malformed `/W` validation, malformed `/Index` validation, compressed `/Prev` helper recovery, object-stream filter/length helper recovery, escaped `/ObjStm` and `/XRef` type names, object-stream header comments, comment offsets, skipped zero object-number header rows, duplicate object-stream offsets, object-stream member boundary checks, inherited carriers, hybrid xref carrier ownership, current-offset repairs, metadata/attachment xref `/Prev` chains, OCR/model execution, or table/equation handoffs.

The bounded behavior here is nested xref-stream array helper resolution through compressed object-stream members with PDF comments inside the intermediate indirect-reference operands.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, object-stream expander, Flate stream decoder, page-tree walker, content-token extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
