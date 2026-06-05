# markerPDF XMP UTF-16 Boundary Current Base

Date: 2026-06-05 01:15 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T011534Z`

## Behavior

`PdfMetadataExtractor` now sniffs BOM-less UTF-16BE and UTF-16LE XMP metadata
streams before building XML candidates. The sniffed packet is decoded to UTF-8
and then passes through the existing XML-aware XMP root boundary scanner, so a
current UTF-16 packet can be parsed before UTF-16 null padding or appended
decoy packet bytes.

The same behavior applies to review-only XMP summaries for rejected catalog
metadata streams. Rejected streams still do not promote document XMP, and text
values remain redacted.

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; searchable-PDF text and
  metadata are loaded before OCR/layout/model stages, so this PHP lane owns the
  native parser boundary under the current no-GPU scope.
- PDF XMP metadata streams are XML packet payloads and may be encoded as
  UTF-16 with or without a byte-order mark. Metadata encoding provenance should
  not depend on libxml accepting a raw UTF-16 stream as if it were UTF-8, and
  trailing packet bytes must not replace the current document metadata root.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUtf16BoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds BOM-less UTF-16BE XMP before trailing UTF-16 decoy packets
Expected source: ["xmp","info"]
Actual source: ["info"]
FAIL summarizes rejected BOM-less UTF-16LE XMP streams with redacted packet fields
Expected xmp_summary field names, actual NULL

1 test files, 13 assertions, 2 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpUtf16BoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS bounds BOM-less UTF-16BE XMP before trailing UTF-16 decoy packets
PASS summarizes rejected BOM-less UTF-16LE XMP streams with redacted packet fields

1 test files, 41 assertions, 0 failures
```

Adjacent XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Xmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 1204 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-utf16-boundary-currentbase.php
```

Passed: emitted `packet_encoding="UTF-16BE"`, `decoded_to_utf8=true`,
`packet_boundary_applied=true`, `decoy_xmp_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP behavior tests move `1229 -> 1231` with 2 new PASS cases and 41
  focused assertions.
- WordPress scenarios move `1203 -> 1204` with the new BOM-less UTF-16 XMP
  boundary smoke.
- No dashboard/root publication files were edited.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, stream decoder, XMP XML parser, metadata merger, review summarizer,
and WordPress smoke path. No Python, pdftext, pypdfium/PDFium, Surya, Texify,
Torch, OCR, image raster, online service, or external PDF tool execution was
run.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` type/subtype validation,
XMP packet padding/appended-decoy trimming, XML-aware CDATA/comment root
scanning, undeclared Windows-1252/ISO-8859-1 fallback, XMP generation-exact
FileSpec provenance, encrypted root XMP policy, name-tree XMP review, or
associated-file/PieceInfo XMP review. The bounded behavior is specifically
BOM-less UTF-16BE/LE XMP packet sniffing before existing root-boundary parsing
and redacted review summarization.

## Next Task

Continue with non-overlapping native metadata/parser boundaries such as
annotation/form metadata review, page geometry, image/filter metadata, xref
repair behavior, or remaining catalog/page review metadata under the no-GPU
markerPDF scope.
