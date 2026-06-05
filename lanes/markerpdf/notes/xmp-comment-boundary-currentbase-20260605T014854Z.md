# markerPDF XMP Comment Boundary Current Base

- Lane: `markerpdf`
- Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T014854Z`
- Accepted base: `3842384ec9077570f8cd975f93c72a182627f7ed`
- Scope: native no-GPU searchable-PDF metadata parsing.

## Source Truth

The manifest-pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible page text extraction separate from metadata and review artifacts. For this native PHP lane, Catalog `/Metadata` is promoted only from a PDF metadata stream (`/Type /Metadata`, `/Subtype /XML`), while rejected XML streams can produce redacted review summaries and must not leak XMP packet text into WordPress paragraphs.

This slice maps the XML packet boundary around XMP root selection: packet comments, processing instructions, CDATA, and DOCTYPE/internal declarations are not XML roots. A fallback parser may bound the current `xmpmeta` or `rdf:RDF` root after packet padding, but it must not select XMP-looking tags embedded inside comments or declarations before that root.

## Red-First Evidence

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php
```

Initial result before the source change:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores comment and doctype decoy XMP roots before the current root packet
Expected: 'Current Comment Boundary XMP Title'
Actual: 'Comment Decoy XMP Title'
FAIL summarizes rejected XML metadata streams without using comment decoy roots
Expected: '2026-06-05T02:13:24Z'
Actual: '2026-06-05T02:59:59Z'
1 test files, 18 assertions, 2 failures
```

## Patch

`PdfMetadataExtractor::boundedXmlRootCandidate()` now asks a token-aware XML scanner for the next `xmpmeta` or `RDF` root by local name. The scanner skips comments, CDATA sections, processing instructions, closing tags, and markup declarations before returning a candidate root offset. Markup declaration skipping respects quoted values and internal-subset brackets, so a DOCTYPE entity containing `<x:xmpmeta>` cannot become the selected XMP root.

The same root selection feeds accepted Catalog `/Metadata` promotion and rejected stream `xmp_summary` review rows.

## Verification

Focused test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php
```

Passed:

```text
1 test files, 42 assertions, 0 failures
```

XMP regression family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCdataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUtf16BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpGenerationBoundaryCurrentBaseTest.php
```

Passed:

```text
5 test files, 188 assertions, 0 failures
```

Syntax:

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xmp-comment-boundary-currentbase.php
```

All reported no syntax errors.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-xmp-comment-boundary-currentbase.php
```

Passed. The smoke emits `comment_decoy_excluded=true`, `doctype_decoy_excluded=true`, `trailing_decoy_excluded=true`, `packet_boundary_applied=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Diff check:

```sh
git diff --check -- lanes/markerpdf
```

Run after this note/status update as final verification.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream filter decoder, Catalog `/Metadata` extractor, DOMDocument with `LIBXML_NONET`, XMP packet parser, redacted XMP review summary path, and WordPress smoke path. Full upstream model parity remains out of scope under the current no-GPU markerPDF direction.

## Non-Overlap

This does not repeat accepted direct root XMP extraction, XMP packet padding/trailing-decoy bounding, CDATA false-closing-marker handling, BOM-less UTF-16 XMP decoding, FileSpec XMP generation-exact provenance, encrypted metadata source policy, OutputIntent/PieceInfo metadata review, xref `/Prev` metadata selection, or visible text extraction. The new behavior is specifically token-aware XMP root fallback that ignores XMP-looking decoy roots inside XML comments and markup declarations.
