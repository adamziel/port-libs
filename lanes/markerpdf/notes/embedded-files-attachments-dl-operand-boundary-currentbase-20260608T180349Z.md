# markerPDF Embedded Files Attachment DL Operand Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260608T180349Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T180349Z`
Base accepted HEAD: `dd923d65163e791b0e0ab69fe21f3c66d9e1c5ea`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `pdftext.dictionary_output()` and parser/PDFium text APIs before OCR/model stages. EmbeddedFile payloads and FileSpec review dictionaries are not visible text inputs. In the native no-GPU PHP lane, `/DL` decoded-length metadata is review-only attachment metadata; malformed duplicate or tailed `/DL` operands must fail closed before WordPress attachment summaries, rich embedded-file rows, or related-file rows trust stale decoded-length or checksum state.

Reference path: `marker/pdf/extract_text.py` at the pinned upstream commit.

## Behavior

- `PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now include `/DL` in the strict EmbeddedFile stream dictionary boundary key set.
- Primary `/EF` streams with duplicate `/DL` keys or a non-name operand after `/DL` are rejected before attachment summary or rich embedded-file extraction.
- `/RF` related-file streams use the same boundary, so malformed related sidecars are omitted while the primary attachment and valid related-file rows remain reviewable.
- Valid `/DL` decoded-length metadata remains accepted and still reports `decoded_length_matches` without exposing attachment payload bytes in WordPress summaries or visible text.

## Evidence

Red-first probe before the fix:

```text
php -r '... PdfAttachmentExtractor/PdfEmbeddedFileExtractor fixture with /DL 999 extra operand ...'
[["bad.xml","good.xml"],["bad.xml","good.xml"]]
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDecodedLengthOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate and tailed EmbeddedFile DL operands before attachment review

1 test files, 98 assertions, 0 failures
```

Adjacent attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDecodedLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDecodedLengthOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentStreamOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1470 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-dl-operand-boundary-currentbase.php
```

Emits `attachment_count=1`, `bad_attachment_excluded=true`, `bad_related_file_excluded=true`, `decoded_length_matches=true`, `related_decoded_length_matches=true`, `attachment_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentDecodedLengthOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-dl-operand-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted `/DL` decoded-length preservation, stream `/Params` or `/DecodeParms` operand boundaries, duplicate stream `/Filter` handling, unsupported terminal filters, name-tree limits, platform FileSpec filename selection, `/RF` duplicate-key rejection, Mac `/Params`, encrypted EFF preflight, Portfolio/Collection/PieceInfo metadata, xref attachment selection, page `/AF`, or annotation associated-file slices. The bounded behavior is only duplicate and tailed EmbeddedFile stream `/DL` operands before attachment and related-file review.

## Dependency Closure

No new support component is needed. This reuses the native PHP object parser, stream dictionary boundary scanner, FileSpec resolver, EmbeddedFile decoder, checksum/decoded-length review, and WordPress smoke pattern. OCR, Surya/Texify/Torch, PDFium rendering, model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
