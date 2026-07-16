# Metadata Stream Unsupported Filter Stack Boundary

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T072235Z`
Base: `3b2384d9eed3a89fec07a417134e6fbeab3bfd4a`
Date: 2026-06-08 UTC

## Source Truth

PDF metadata streams use the same `/Filter` stack contract as other PDF streams:
all filters in the stack must be decoded in order before the bytes are
trustworthy as XMP. A searchable-PDF import must not accept a partially decoded
catalog `/Metadata` stream when a supported native filter is followed by an
unsupported image/private filter. This keeps unsupported stream stacks out of
document metadata and falls back to Info/catalog review metadata.

## Red-First Evidence

Before the patch, an in-memory fixture with:

`/Filter [ /FlateDecode /DCTDecode ]`

accepted the Flate-decoded XMP packet as document metadata even though the final
`DCTDecode` stage was unsupported. The probe returned source `["xmp","info"]`
and promoted title `Unsupported Stack XMP Leak` instead of falling back to the
Info dictionary title.

## Implementation

`PdfMetadataExtractor::decodeStream()` now returns `null` for unsupported
metadata stream filters instead of treating them as identity filters. Supported
metadata filters remain unchanged: ASCIIHexDecode, ASCII85Decode, RunLengthDecode,
and FlateDecode.

## Verification

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`:
  no syntax errors detected.
- `php -l lanes/markerpdf/tests/PdfMetadataStreamUnsupportedFilterStackBoundaryCurrentBaseTest.php`:
  no syntax errors detected.
- `php -l lanes/markerpdf/examples/wordpress-pdf-metadata-unsupported-filter-stack-currentbase.php`:
  no syntax errors detected.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataStreamUnsupportedFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpDecodeParmsOperandBoundaryCurrentBaseTest.php`:
  3 test files, 114 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-metadata-unsupported-filter-stack-currentbase.php`:
  exits 0 and reports `unsupported_filter_stack_failed_closed=true`,
  `info_fallback_used=true`, `xmp_payload_excluded=true`, and
  `visible_text_preserved=true`.
- `git diff --check -- lanes/markerpdf`:
  clean.

Root harness was not run - isolated micro-slice.

## Non-Overlap

This patch does not repeat page-content stream stack recovery, attachment stream
filter stacks, metadata explicit EOD trailing-payload rejection, XMP DecodeParms
operand validation, image DCTDecode review, page labels, xref repair, OCR,
Surya/Texify/Torch, Streamlit/FastAPI workers, or upstream model benchmark
parity. The behavior is limited to catalog XMP metadata stream stacks where an
unsupported filter follows a supported native stream filter.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
metadata stream filter helpers and keeps unsupported filters fail-closed under
the current no-GPU/no-model markerPDF scope.

## Next Task

Continue native no-GPU markerPDF stream/filter and metadata work with a
non-overlapping behavior cluster, such as image/filter metadata review,
font/CMap stream boundary handling, xref repair, outlines, annotations, forms,
or supplied-boundary table/equation handoffs.
