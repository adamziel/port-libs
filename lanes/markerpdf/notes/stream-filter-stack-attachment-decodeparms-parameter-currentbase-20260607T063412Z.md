# Attachment Stream Filter DecodeParms Parameter Boundary - 2026-06-07

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260607T063412Z`
Session: `port-dev-markerpdf-stream-filter-stack-20260607T063412Z`
Base accepted HEAD: `5676fbf8a969a7c59e6fe2b0891edf6d0b0a69e1`

## Source Truth

Pinned upstream markerPDF source is `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream searchable-PDF handling reaches stream decoding before any OCR/model handoff. Under the current no-GPU scope, the PHP attachment path follows the same conservative boundary used by text extraction: duplicate top-level keys inside a single `/DecodeParms` dictionary are malformed. This includes predictor parameters such as `/Predictor` and Crypt filter `/Name` parameters where `/Identity` and a private crypt filter would otherwise conflict.

## Implementation

- `PdfAttachmentExtractor` now inspects raw stream `/DecodeParms` operands before parsed dictionaries collapse repeated keys. Direct arrays, direct dictionaries, indirect arrays, and indirect dictionaries are resolved through the existing token-aware PDF value scanners, while parameters aligned only to null filter slots remain ignored.
- `PdfEmbeddedFileExtractor` now rejects duplicate nested DecodeParms parameters before Flate/LZW predictor validation or Crypt `/Name` resolution.
- Valid singleton DecodeParms dictionaries, null filter slots, compact DecodeParms arrays, Identity Crypt parameters, short-length recovery, and sibling valid attachments continue to decode.

## Evidence

Red-first probe before the source edit:

```text
php -r '...duplicate /Predictor 99 /Predictor 1 attachment fixture...'
{"summary_count":1,"files_count":1,"filenames":["dup-predictor.csv"],"content":"Title,Status\nDuplicate Predictor Attachment Leak,Blocked\n"}
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 319 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emitted `duplicate_decodeparms_parameter_attachments_rejected=true`, `duplicate_predictor_parameter_rejected=true`, `duplicate_crypt_name_parameter_rejected=true`, `duplicate_decodeparms_parameter_payload_excluded=true`, `duplicate_decodeparms_parameter_visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted text-stream duplicate DecodeParms parameter slice, duplicate stream-owned `/Filter` or `/DecodeParms` key rejection, extra DecodeParms array entries, all-null filter slots, LZW/EOD boundaries, short declared stream length recovery, indirect operand cycles, attachment predictor handling, image metadata, CMaps, annotations, forms, encryption preflight, xref repair, metadata, outlines, or OCR/model work. The patch is attachment/embedded-file payload specific.

## Dependency Closure

No new support component is required. The patch reuses the native PHP PDF object scanner, raw dictionary/array operand readers, stream filter resolver, DecodeParms alignment logic, Flate/LZW/Crypt decoders, attachment summary path, embedded-file extractor, and WordPress smoke renderer. No Python, pdftext, PDFium, OCR, Surya, Texify, Torch, GPU/model execution, external PDF tooling, or online service was executed.

Root harness: not run - isolated micro-slice.
