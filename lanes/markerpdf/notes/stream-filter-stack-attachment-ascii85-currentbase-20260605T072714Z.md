# markerPDF Attachment ASCII85 Stream-Filter Stack Boundary

Session: `port-dev-markerpdf-stream-filter-stack-20260605T072714Z`
Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T072714Z`
Base accepted HEAD: `3b9767ce8c21142a751d7d2fe37577b7e692da2d`

## Source Truth

- Upstream `sddai/markerPDF` at manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF and document-asset extraction through native PDF parser boundaries before OCR/layout/model stages.
- PDF stream `/Filter` arrays are ordered stacks. EmbeddedFile streams used for WordPress import preflight need the same stack decoding boundary as page content streams before `/Params /Size` and `/Params /CheckSum` review metadata can be trusted.

## Behavior

`PdfAttachmentExtractor` now supports `ASCII85Decode`/`A85` as an attachment stream filter stage. This lets the lightweight WordPress attachment preflight decode valid EmbeddedFile payloads such as:

```text
/Filter [ null /ASCII85Decode /FlateDecode ]
```

The decoded payload is used only for byte length, SHA-256, declared-size match state, and MD5 checksum match state. `attachmentSummary()` still strips raw `bytes`, so payload CSV/XML text is not exposed in WordPress summary output.

## Evidence

Red probe before the source change dropped the valid stacked attachment:

```text
array (
  0 => 0,
  1 => 0,
  2 =>
  array (
  ),
  3 => NULL,
)
```

Focused attachment test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
1 test files, 414 assertions, 0 failures
```

Adjacent stream-filter guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
2 test files, 580 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `attachment_count=1`, `total_bytes=38`, `filters=["ASCII85Decode","FlateDecode"]`, `declared_size_matches=true`, `checksum_matches=true`, `payload_bytes_omitted_from_summary=true`, `payload_content_exposed=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-content stream stack boundary recovery, stale `/Length` handling, LZW/RunLength/Crypt text stream stages, DecodeParms null-slot alignment, attachment xref selection, attachment platform filename selection, `/AFRelationship` mapping, checksum review itself, EmbeddedFiles `/Limits` pruning, associated-file mirror dedupe, encrypted EFF redaction, or attachment payload omission.

The bounded behavior is specifically ASCII85-to-Flate ordered stream-stack decoding in the lightweight `PdfAttachmentExtractor` preflight path before attachment size/checksum metadata review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, stream filter resolver, attachment FileSpec parser, checksum review path, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
