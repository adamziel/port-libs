# markerPDF EmbeddedFiles Attachment EF Stream Generation Boundary

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T113747Z`
Base accepted HEAD: `295de7be43d755bd0e5a2a0b4b78f621b5c55f17`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF import at the searchable-PDF parser boundary before OCR/model stages; embedded payload bytes are not page text.
- PDF indirect references are generation-qualified. A FileSpec `/EF << /F 5 0 R >>` must not resolve to a newer `5 1 obj` stream just because both streams share object number 5.
- This slice stays in the native no-GPU parser scope: EmbeddedFiles/FileSpec attachment review, no OCR, PDFium rendering, model execution, decryption, or external PDF tools.

## Implementation

- `PdfAttachmentExtractor::embeddedFileStreamReference()` now carries the exact stream object returned by the generation-aware resolver.
- `attachmentFromFileSpecValue()` decodes that resolved stream object instead of reselecting `$objects[$objectId]`, which previously lost fallback generation information in no-xref recovery.
- Added `PdfAttachmentEfStreamGenerationBoundaryCurrentBaseTest.php` with a no-xref PDF where the current FileSpec references `5 0 R` while a decoy `5 1 obj` stream exists.
- Added `wordpress-pdf-attachment-ef-stream-generation-boundary-currentbase.php` to prove WordPress attachment summaries select the referenced generation, omit payload bytes, exclude the newer-generation decoy, and preserve visible page text.

## Evidence

Red probe before the source edit:

```text
PdfAttachmentExtractor attachment byte_length => 18
PdfAttachmentExtractor attachment sha256 => generation-1 decoy payload
PdfEmbeddedFileExtractor file size => 16
```

Passing focused checks after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentEfStreamGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps fallback EmbeddedFile EF stream references generation-exact before attachment summaries

1 test files, 40 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php lanes/markerpdf/tests/PdfEmbeddedFile*Test.php lanes/markerpdf/tests/PdfEmbeddedFiles*Test.php
Focused test run: 63 selected test files (root lock skipped)
63 test files, 4640 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-ef-stream-generation-boundary-currentbase.php
```

The smoke exits 0 and emits review metadata with `referenced_generation_payload_selected=true`, `decoy_newer_generation_excluded=true`, `payload_bytes_omitted_from_summary=true`, and all execution flags false.

## Status Delta

- PHP behavior tests: `3070 -> 3071`.
- WordPress scenarios: `2536 -> 2537`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, fallback generation map, FileSpec/EF dictionary parser, stream decoder, attachment summary sanitizer, embedded-file review extractor, and visible text boundary. GPU/model/OCR/PDFium parity remains intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted FileSpec generation repair, xref-stream object-stream attachments, same-object current xref row selection, name-tree kid generation ordering, duplicate FileSpec/EF key fail-closed behavior, stream filter/DecodeParms boundaries, encrypted EFF review, page/catalog/annotation associated-file extraction, or attachment payload omission. The bounded new behavior is only preserving the exact resolved EmbeddedFile stream generation inside fallback/no-xref attachment summaries.
