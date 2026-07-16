# markerPDF classic startxref operand-tail boundary current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T183347Z`

Accepted base: `491fa94b2ad9759bb28ac262b0ad00542377c4c9`

Upstream `sddai/markerPDF` delegates searchable-PDF text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium-backed parsing. The native no-GPU PHP lane owns the parser boundary before WordPress import. At the classic xref repair boundary, a `startxref` numeric operand is only trusted when the integer is followed by PDF whitespace, an end-of-line, or a PDF comment. Private tokens after the integer are not part of the operand and must not select a later review-only table.

## Change

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, `PdfAttachmentExtractor`, and `MarkerAppPreview` now validate the `startxref` operand tail. Nonnumeric damaged operands still rebuild through the existing classic-table recovery path, signed numeric operands remain supported, and `%` comment tails remain valid. Numeric operands followed by non-comment private tokens are skipped so earlier valid `startxref` boundaries remain authoritative.

The focused fixture builds a current classic table with current page text, XMP/Info metadata, and an EmbeddedFiles attachment, then appends a later table guarded by either:

- `startxref <offset> /PrivateTail 20 0 R`, which must be rejected and keep the current WordPress import objects; or
- `startxref <offset> % comment-only startxref operand tail`, which remains valid and selects the latest table.

## Red-first evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects numeric classic startxref operands with private tails before WordPress imports (lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current operand-tail xref page',
  1 => 'Private startxref tail rejected',
)
Actual: array (
  0 => 'Tail operand xref page',
  1 => 'Comment startxref tail accepted',
)
PASS accepts numeric classic startxref operands with PDF comment tails before WordPress imports

1 test files, 31 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects numeric classic startxref operands with private tails before WordPress imports
PASS accepts numeric classic startxref operands with PDF comment tails before WordPress imports

1 test files, 56 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicSignedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewClassicXrefBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
31 PASS cases
5 test files, 761 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-startxref-operand-tail-currentbase.php
```

The smoke exits `0` and emits `private_tail_rejected=true`, `comment_tail_accepted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint passed for:

```text
lanes/markerpdf/src/PdfTextExtractor.php
lanes/markerpdf/src/PdfMetadataExtractor.php
lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
lanes/markerpdf/src/PdfAttachmentExtractor.php
lanes/markerpdf/src/MarkerAppPreview.php
lanes/markerpdf/tests/PdfXrefClassicStartxrefOperandTailBoundaryCurrentBaseTest.php
lanes/markerpdf/examples/wordpress-pdf-classic-xref-startxref-operand-tail-currentbase.php
```

`git diff --check -- lanes/markerpdf` passes with no output.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native direct-object scanner, classic xref rebuild parser, trailer root selection, metadata extraction, embedded-file review, attachment summary, MarkerApp preview root-boundary logic, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium execution, external PDF tools, and exact upstream model benchmark parity remain intentionally outside this no-GPU markerPDF scope.

## Non-overlap

This does not repeat accepted damaged nonnumeric `startxref` rebuild, signed negative `startxref`, stale valid `startxref` repair, commented/composite/name/string `startxref` decoys, comment-delimited xref keywords, EOF-bounded xref rejection, stream-owned trailer rejection, malformed xref row handling, forward `/Prev` repair, xref-stream `/Prev` generation repair, hybrid `/XRefStm` merge policy, or object-stream carrier/member repair. The bounded behavior here is only numeric `startxref` operands with non-comment private tails versus comment-only tails.
