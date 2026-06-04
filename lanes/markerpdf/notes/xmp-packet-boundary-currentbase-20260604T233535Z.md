# markerPDF XMP Packet Boundary Current Base

Date: 2026-06-04 23:35 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260604T233535Z`

## Behavior

`PdfMetadataExtractor` now keeps a bounded XML-root fallback candidate for
catalog `/Metadata` XMP streams. If the decoded stream contains a valid
`x:xmpmeta` or `rdf:RDF` root followed by null padding or appended decoy packet
bytes, the current root packet is parsed and the trailing bytes are ignored.

The same boundary is used for review-only XMP summaries on rejected catalog
metadata streams. Rejected streams still do not promote document XMP, and text
values remain redacted.

The parsed metadata now records `packet_boundary_applied=true` when the bounded
candidate was needed.

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; its PDF text/metadata path
  depends on pdftext/PDFium-style document loading before OCR/layout/model
  stages, so the PHP lane owns this native parser boundary.
- PDF XMP metadata streams are XML packet payloads, commonly wrapped by
  xpacket processing instructions and stream padding. Padding or stale bytes
  after the packet root are not visible text and must not erase the current
  document XMP or promote appended decoys.

## Evidence

Red-first probe before implementation:

`php -r '... PdfMetadataExtractor ... valid root XMP plus null padding ...'`

Result: `array ()` for `metadata["xmp"]`.

`php -r '... PdfMetadataExtractor ... valid root XMP plus appended decoy packet ...'`

Result: `array ()` for `metadata["xmp"]`.

Post-fix focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php`

Result: `1 test files, 40 assertions, 0 failures`.

Adjacent metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadata*Test.php`

Result: `17 test files, 1544 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-packet-boundary-currentbase.php`

Result: emitted `title_from_current_packet=true`,
`packet_boundary_applied=true`, `packet_encoding="UTF-8"`,
`decoy_xmp_excluded=true`, `visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-packet-boundary-currentbase.php`

Result: no syntax errors.

## Status Delta

- Focused PHP behavior tests move `1107 -> 1109` with 2 new PASS cases.
- WordPress scenarios move `1106 -> 1107` with the new XMP packet-boundary
  smoke.
- Manifest mapped coverage moves `709 -> 710`, with
  `mappedPdfXmpMetadataExtractionBehaviors` moving `1 -> 2`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP object
scanner, stream decoder, XMP XML parser, metadata merger, review summarizer,
and WordPress smoke path. No Python, pdftext, pypdfium/PDFium, Surya, Texify,
Torch, OCR, image raster, online service, or external PDF tool execution was
run.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` type/subtype validation,
XMP/Info timezone normalization, undeclared Windows-1252 XMP fallback,
xref-stream trailer metadata precedence, encrypted metadata priority, name-tree
XMP review, associated-file XMP review, PieceInfo XMP review, or PDF/A
OutputIntent association. The bounded behavior is specifically preserving the
current XMP packet root before trailing padding or appended decoy bytes.

## Next Task

Continue with non-overlapping native metadata/parser boundaries such as
annotation/form metadata review, page geometry, image/filter metadata, xref
repair behavior, or remaining catalog/page review metadata under the no-GPU
markerPDF scope.
