# markerPDF attachment all-null stream filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T122708Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF conversion through pdftext/PDFium-backed PDF parsing before any OCR/model fallback.
- PDF stream filter arrays are ordered decoder stacks. `null` filter entries are identity placeholders; an array with no real filter names is an empty/identity stack. DecodeParms parameterize real filters and must not make an otherwise raw embedded-file stream disappear.

## Behavior

`PdfAttachmentExtractor` now normalizes attachment `/Filter [ null ]` arrays to an empty decoder stack before DecodeParms parsing. Attachment summaries and embedded-file extraction preserve the raw payload bytes for checksum/size review, omit payload bytes from summaries, and keep stale DecodeParms helper strings out of visible WordPress text.

The focused fixture uses:

```text
/Filter [ null ]
/DecodeParms [ 99 0 R 100 0 R ]
```

Before this patch, the extra DecodeParms operand caused the attachment summary path to fail closed with `attachment_count=0`. After the patch, the all-null stack is treated as identity and the attachment is admitted with empty `filters`.

## Red-First Probe

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
FAIL treats all-null attachment filter arrays as identity stacks before resolving stray DecodeParms
Expected: 1
Actual: 0
1 test files, 132 assertions, 1 failures
```

## Evidence

Focused attachment stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 161 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `all_null_attachment_decoded=true`, `all_null_decodeparms_ignored=true`, `all_null_payload_bytes_omitted_from_summary=true`, `all_null_visible_text_preserved=true`, `all_null_payload_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted text-stream all-null filter behavior, ASCII85/Flate/LZW stack decoding, private Crypt fail-closed behavior, dictionary-valued Filter rejection, extra DecodeParms rejection for real filters, indirect filter operand resolution, xref-stream filter chains, image-filter exclusion, or attachment checksum metadata.

The bounded behavior is attachment embedded-file streams with identity-only filter arrays and stray DecodeParms operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, attachment stream dictionary parser, DecodeParms resolver, embedded-file extractor, checksum metadata path, and WordPress smoke renderer. Full upstream OCR/model parity remains intentionally out of scope under the no-GPU markerPDF direction.
