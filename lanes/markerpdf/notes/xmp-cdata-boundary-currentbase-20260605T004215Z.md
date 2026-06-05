# markerPDF XMP CDATA Boundary Current Base

Date: 2026-06-05 00:42 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T004215Z`

## Behavior

`PdfMetadataExtractor` now bounds catalog XMP packet roots with XML-aware
CDATA, comment, processing-instruction, and quoted-attribute handling. A
literal `</x:xmpmeta>` or `</rdf:RDF>` string inside CDATA/comment text no
longer truncates the current packet before trailing padding or appended decoy
XMP bytes.

The same root-boundary scanner is reused for review-only XMP summaries on
rejected catalog metadata streams. Rejected XML streams still do not promote
document XMP, and text values remain redacted.

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; searchable-PDF text and
  metadata are loaded before OCR/layout/model stages, so this PHP lane owns the
  native parser boundary under the current no-GPU scope.
- XMP metadata streams are XML packet payloads. XML CDATA and comments may
  contain text that looks like a closing tag, but that text is not an XML root
  terminator and must not let stale trailing packet bytes replace current
  document metadata.

## Evidence

Red-first probe before implementation:

`php -r '... valid XMP root with CDATA containing </x:xmpmeta> plus trailing null padding ...'`

Result: `array ()` for `metadata["xmp"]`.

Post-fix focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCdataBoundaryCurrentBaseTest.php`

Result: `1 test files, 39 assertions, 0 failures`.

Adjacent metadata regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCdataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `3 test files, 944 assertions, 0 failures`.

Broader XMP current-base regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Xmp*CurrentBaseTest.php`

Result: `8 test files, 298 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-cdata-boundary-currentbase.php`

Result: emitted `title_from_current_packet=true`,
`description_preserves_cdata_text=true`, `packet_boundary_applied=true`,
`decoy_xmp_excluded=true`, `visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpCdataBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-cdata-boundary-currentbase.php`

Result: no syntax errors.

## Status Delta

- Focused PHP behavior tests move `1197 -> 1199` with 2 new PASS cases.
- WordPress scenarios move `1179 -> 1180` with the XMP CDATA boundary smoke.
- Manifest mapped semantics move `714 -> 715` for the XML-aware XMP packet
  boundary behavior.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object
scanner, stream decoder, XMP XML parser, metadata merger, review summarizer,
and WordPress smoke path. No Python, pdftext, pypdfium/PDFium, Surya, Texify,
Torch, OCR, image raster, online service, or external PDF tool execution was
run.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` type/subtype validation,
XMP/Info timezone normalization, undeclared Windows-1252 XMP fallback,
xref-stream trailer metadata precedence, encrypted metadata priority, XMP
generation-exact FileSpec provenance, or the prior packet padding/appended
decoy boundary. The new boundary is specifically XML-aware root scanning when
CDATA/comment text contains closing-tag-looking strings.

## Next Task

Continue with non-overlapping native metadata/parser boundaries such as
annotation/form metadata review, page geometry, image/filter metadata, xref
repair behavior, or remaining catalog/page review metadata under the no-GPU
markerPDF scope.
