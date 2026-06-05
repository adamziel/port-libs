# markerPDF classic xref rebuild unterminated literal boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T160319Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T160319Z`

Base accepted HEAD: `1ec299d70fc84b468f2f246042c3fd21c99bd4eb`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium. In this native PHP no-GPU lane, the parser owns the same PDF syntax boundary before WordPress import: tokens that occur inside a PDF literal string are not top-level `xref`, `trailer`, or `startxref` markers. If a damaged import leaves a literal string unterminated, later xref-looking bytes remain inside that literal and must not replace the current trailer root, Info dictionary, metadata stream, EmbeddedFiles name tree, or attachment summary.

## Behavior

`PdfTextExtractor` and `PdfEmbeddedFileExtractor` now treat an unterminated literal string as owning later xref-like tokens during top-level composite-token scans, instead of letting a later `startxref` or `xref` candidate escape the string boundary.

`PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` now apply the same token/comment/direct-object guards to textual trailer fallback scans that already protect xref section scans.

`PdfAttachmentExtractor` now uses the guarded selected trailer dictionary for catalog selection. That keeps attachment enumeration scoped to the current catalog when all candidate `startxref` tokens are rejected as non-top-level.

## Red-First

Before the source patch, the new focused fixture selected the decoy page text from the xref-looking bytes embedded in an unterminated literal string:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedLiteralBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips unterminated literal-string xref decoys before WordPress imports (lanes/markerpdf/tests/PdfXrefClassicUnterminatedLiteralBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current unterminated literal xref page',
  1 => 'Unclosed string xref skipped',
)
Actual: array (
  0 => 'Unterminated literal xref decoy page',
  1 => 'Unclosed string xref leak',
)

1 test files, 3 assertions, 1 failures
```

## Verification

Focused check after the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicUnterminatedLiteralBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips unterminated literal-string xref decoys before WordPress imports

1 test files, 29 assertions, 0 failures
```

Adjacent classic xref rebuild family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
25 PASS cases

1 test files, 605 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-unterminated-literal-currentbase.php
```

Result: emitted two Gutenberg paragraphs for `Current unterminated literal xref page` and `Unclosed string xref skipped`, with smoke booleans `uses_current_classic_trailer_root=true`, `keeps_current_metadata_root=true`, `keeps_current_info_root=true`, `imports_current_attachment=true`, `attachment_summary_current_only=true`, `current_attachment_checksum_matches=true`, `excludes_unterminated_literal_page=true`, `excludes_unterminated_literal_metadata=true`, `excludes_unterminated_literal_attachment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefClassicUnterminatedLiteralBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-unterminated-literal-currentbase.php
```

All passed.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted damaged-`startxref` rebuild, stale valid `startxref` repair, EOF-bounded post-EOF xref rejection, commented `xref` or commented `startxref` rejection, array/composite-contained xref rejection, name-token xref rejection, linearized hint-range exclusion, malformed-row rejection, stream-owned trailer rejection, stream-owned composite token scanning, xref-stream `/Prev` generation repair, or hybrid/object-stream current-base xref repair.

The bounded behavior here is specifically xref/trailer/startxref byte sequences inside an unterminated PDF literal string and the attachment-summary catalog fallback that previously lost the current-root boundary when no valid top-level `startxref` survived.

## Dependency Closure

No new support component is needed. This slice reuses native direct-object scanning, literal/composite token scanning, classic xref table parsing, trailer dictionary parsing, page-tree text extraction, metadata extraction, EmbeddedFiles extraction, attachment summary extraction, and the WordPress smoke renderer. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table-cell models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
