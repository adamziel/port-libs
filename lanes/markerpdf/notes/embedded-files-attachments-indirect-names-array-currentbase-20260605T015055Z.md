# markerPDF EmbeddedFiles Attachment Indirect Names Array Boundary

Session: `port-dev-markerpdf-attachments-20260605T015055Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T015055Z`
Base accepted HEAD: `58db5d050b17ac9a4faf7ee82d6939836ca4c186`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed pdftext/PDFium extraction. Embedded FileSpec payloads are not visible paragraph text in that path.
- The native no-GPU markerPDF lane owns lightweight attachment preflight for `/Names /EmbeddedFiles`, catalog/page `/AF`, and FileAttachment annotations: summarize review metadata for WordPress imports without running Python, OCR/models, external PDF tools, attachment actions, or payload text promotion.
- PDF name-tree nodes may use indirect array objects. The lightweight attachment preflight already resolved indirect name-tree nodes, `/Limits`, FileSpec dictionaries, and stream operands, but skipped a node when its `/Names` value was an indirect array.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Before the source change, the new regression failed:

```text
FAIL resolves indirect EmbeddedFiles name-tree Names arrays in attachment preflight
Expected: 'embedded-files-name-tree'
Actual: 'catalog-associated-file'
```

The FileSpec was reachable only through catalog `/AF`, so the preflight lost EmbeddedFiles name-tree provenance and did not prove name-tree stale-row pruning for that shape.

## Implementation

`PdfAttachmentExtractor::nameTreeEntries()` now resolves the `/Names` value before treating it as the pair array. This keeps existing direct-array behavior while allowing indirect `/Names 8 0 R` arrays to feed the same FileSpec pairing, limit checks, checksum review, `/AFRelationship` role mapping, and payload-byte exclusion path.

The WordPress smoke `wordpress-pdf-attachments-preflight.php` now stores the attachment name pairs in an indirect array object and emits `indirect_embeddedfiles_names_array_preflight=true`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Passed after the fix:

```text
1 test files, 259 assertions, 0 failures
```

Additional focused attachment-family verification and the smoke command are recorded in the final handoff.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
```

Passed:

```text
5 test files, 793 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Passed: emitted `attachment_count=4`, `indirect_embeddedfiles_names_array_preflight=true`, `pruned_out_of_limits_name_tree_entry=true`, `terminal_eof_bounds_attachment_scan=true`, `related_file_payload_omitted=true`, `page_associated_file_payload_omitted=true`, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted platform filename source selection, `/EF` key selection, `/AFRelationship` role mapping, checksum review, related-file `/RF` summaries, EmbeddedFiles `/Limits` pruning, EOF-bounded object scanning, current xref row selection, xref-stream object-stream FileSpec resolution, catalog/page `/AF` ingestion, FileAttachment annotation extraction, or full `PdfEmbeddedFileExtractor` portfolio/PieceInfo/XMP/OutputIntent review. The bounded new behavior is only resolving an indirect `/Names` array inside a lightweight EmbeddedFiles name-tree node before attachment preflight pairing.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, indirect object resolver, FileSpec parser, stream filter decoder, checksum review, and WordPress smoke pattern. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external rendering/OCR helpers; none were executed for this bounded native PHP slice.
