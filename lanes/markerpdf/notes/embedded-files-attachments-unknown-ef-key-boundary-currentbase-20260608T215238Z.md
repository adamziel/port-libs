# Embedded Files Unknown EF-Key Boundary

- Session: `port-dev-markerpdf-attachments-20260608T215238Z`
- Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T215238Z`
- Accepted base: `6f8463809fe932bed047f1bc503ab1bca68687f8`

## Source Truth

Upstream markerPDF treats PDF attachments as document artifacts beside page text rather than visible text content. This no-GPU PHP port keeps that native boundary: searchable PDF text extraction, attachment metadata review, and WordPress file-block handoff run without OCR/model execution or external PDF tooling.

For FileSpec embedded files, the PDF `/EF` dictionary uses standard platform keys such as `/F`, `/UF`, `/DOS`, `/Unix`, and `/Mac` to point to EmbeddedFile streams. This slice keeps unknown/private `/EF` keys out of lightweight attachment summaries, matching the full `PdfEmbeddedFileExtractor` key selection boundary.

## Implementation

`PdfAttachmentExtractor::embeddedFileStreamReference()` no longer falls back to accepting any object-valued `/EF` dictionary key after standard keys fail. A FileSpec like `/EF << /Private 11 0 R >>` is now ignored by attachment summaries instead of being promoted as a WordPress attachment row.

The existing standard-key behavior remains intact. A neighboring FileSpec with `/EF << /F 21 0 R >>` is still summarized, checksum-checked, mirrored through `/AF`, and available to the full embedded-file extractor.

## Verification

Red-first focused check before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentUnknownEfKeyBoundaryCurrentBaseTest.php
FAIL rejects unknown FileSpec EF dictionary keys before WordPress attachment summaries
Expected: 1
Actual: 2
1 test files, 1 assertions, 1 failures
```

Focused check after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentUnknownEfKeyBoundaryCurrentBaseTest.php
1 test files, 55 assertions, 0 failures
```

Adjacent attachment/extractor subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPlatformEmbeddedFileKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentIndirectEfDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEmbeddedFileStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataKeyOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
6 test files, 1134 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-unknown-ef-key-currentbase.php
exits 0 with unknown_ef_key_excluded=true, valid_payload_omitted_from_summary=true, valid_payload_returned_by_full_extractor=true, visible_text_excludes_attachment_payloads=true, executes_python_or_models=false, and executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted attachment work for platform EF key order, indirect EF dictionary operands, non-EmbeddedFile stream type boundaries, metadata operand parsing, duplicate FileSpec names, related files, Mac params, PieceInfo, associated-file mirrors, generation/xref boundaries, or encrypted EFF preflight. The only behavior change is fail-closed handling for unknown/private `/EF` dictionary keys in lightweight attachment summaries.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, FileSpec dictionary decoding, stream decoding, attachment metadata summarizer, full embedded-file extractor, text extractor, and local WordPress smoke path. GPU/model execution, OCR, Python workers, and external PDF tools remain intentionally out of scope.
