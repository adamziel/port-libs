# markerPDF Embedded Files Attachment Trailing Operand Current Base

Session: `port-dev-markerpdf-attachments-20260607T154020Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T154020Z`
Base accepted HEAD: `ae851dc273eeed6158fd120747071605c45efcaa`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through parser/PDFium-backed page text paths before OCR/model handoff. Embedded-file payloads and FileSpec related files are attachment review metadata, not visible text.

At the native PHP boundary, FileSpec `/EF` and `/RF` dictionaries are PDF dictionaries: each selected entry must be one key/value pair. A malformed value such as `/EF << /F 11 0 R 12 0 R >>` has a valid-looking first reference plus an unkeyed trailing operand. Trusting the first reference can import an attachment stream the PDF did not declare cleanly.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now apply the same raw dictionary boundary:

- FileSpec filename and `/EF` attachment keys with non-key trailing operands fail closed before attachment import.
- `/EF` dictionaries with `/F`, `/UF`, `/DOS`, `/Unix`, or `/Mac` entries followed by unkeyed operands are rejected before stream decoding.
- FileSpec `/RF` values or related-file dictionaries with trailing operands suppress related-file sidecar rows while preserving the primary attachment, matching the accepted duplicate-`/RF` behavior.
- `attachmentSummary()` still omits raw payload bytes, and `PdfTextExtractor` keeps attachment payload text out of visible WordPress paragraphs.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecTrailingOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on FileSpec EF and RF trailing operands before WordPress attachment review (lanes/markerpdf/tests/PdfAttachmentFileSpecTrailingOperandBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 2
Actual: 3

1 test files, 1 assertions, 1 failures
```

Focused green after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecTrailingOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on FileSpec EF and RF trailing operands before WordPress attachment review

1 test files, 68 assertions, 0 failures
```

Attachment/embedded-file family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile).*Test\.php$' | sort)
Focused test run: 50 selected test files (root lock skipped)
50 test files, 3664 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-filespec-trailing-operand-boundary-currentbase.php
```

Result: exits `0` and emits `attachment_count=2`, `malformed_ef_attachment_excluded=true`, `malformed_rf_related_files_suppressed=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted duplicate FileSpec filename or `/EF` key rejection, direct name-tree FileSpec duplicate-key rejection, direct `/AF` FileSpec duplicate-key rejection, duplicate `/RF` entries, duplicate `/RF` platform keys, related-file filename/path review, platform EF key selection, stream-filter decoding, encrypted EFF redaction, Mac Params, portfolio collection metadata, PieceInfo metadata, object-stream/xref repair, EOF-bounded object scanning, or outline/navigation work. The bounded behavior is only unkeyed trailing operands after FileSpec attachment/related-file dictionary values.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object/value parser, raw dictionary scanner, FileSpec parsing, embedded-file stream review, checksum review, text extractor payload exclusion, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, decryption, media playback, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
