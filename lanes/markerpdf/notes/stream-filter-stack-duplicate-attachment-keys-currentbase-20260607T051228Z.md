# markerPDF Attachment Stream Filter Duplicate-Key Boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260607T051228Z`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit routes searchable PDF and attachment extraction through low-level PDF parsers before OCR, layout, and model stages. PDF stream `/Filter` and `/DecodeParms` declarations define the byte decoder stack; duplicate top-level declarations are ambiguous and must fail closed before WordPress attachment review promotes payload metadata.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now treat duplicate top-level EmbeddedFile stream `/Filter` and `/DecodeParms` declarations as guarded stream-boundary keys, alongside the already guarded `/Params` key. A stream whose last duplicate declaration would otherwise decode successfully is rejected before attachment summary rows or embedded-file payload extraction, while a valid attachment in the same EmbeddedFiles name tree is preserved.

The focused fixture covers:

- duplicate `/Filter` where the last declaration is a valid ASCII85 + Flate stack;
- duplicate `/DecodeParms` where the last declaration is a valid null/null parameter array;
- a valid subsequent attachment proving the name tree is still traversed;
- visible page text preservation and attachment payload exclusion from Gutenberg text.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
FAIL rejects duplicate attachment stream Filter and DecodeParms declarations before payload extraction
Expected: 1
Actual: 3
1 test files, 254 assertions, 1 failures
```

Focused run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 286 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `duplicate_stream_key_attachments_rejected=true`, `duplicate_filter_stream_rejected=true`, `duplicate_decodeparms_stream_rejected=true`, `duplicate_stream_key_payload_excluded=true`, `duplicate_stream_key_visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-content duplicate stream key rejection, dictionary-valued attachment `/Filter` rejection, all-null stacks, LZW/RunLength/ASCII85/Flate EOD boundaries, short-Length stack recovery, extra non-null DecodeParms rejection, indirect filter operand cycles, attachment `/Params` duplicate-key rejection, or annotation/FileSpec duplicate-key boundaries. The new behavior is specifically duplicate top-level `/Filter` and `/DecodeParms` declarations on EmbeddedFile streams before attachment summary or payload extraction.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, raw stream dictionary duplicate-key scanner, EmbeddedFiles name-tree traversal, attachment summary builder, embedded-file payload extractor, stream filter stack decoder, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
