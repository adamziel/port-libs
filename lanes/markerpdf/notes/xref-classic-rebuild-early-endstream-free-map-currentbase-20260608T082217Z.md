# Classic Xref Rebuild Early-Endstream Free Map Current Base

Date: 2026-06-08 UTC
Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T082217Z`
Accepted base: `6c29be4bda70f43b52fe8fb02b6dc807643e8db3`

## Source Truth

Classic xref rebuild scans must ignore table-looking bytes that are owned by a
direct stream object. A stream with a direct `/Length` can legally contain the
byte sequence `endstream`, `endobj`, and `xref` before its real terminator, so
the free-object-map scanner must use the declared stream extent before falling
back to a raw `endstream` marker search.

## Behavior

- Added a damaged-final-startxref fixture where the current classic xref table
  frees stale annotation object 7, then a later stream object contains an early
  fake `endstream/endobj/xref` sequence before the declared stream length ends.
- Updated `PdfXrefFreeObjectMap` direct-object scanning to carry the stream
  dictionary into stream-boundary detection and honor a direct non-negative
  `/Length` when it points to a valid `endstream` terminator.
- Kept the existing first-`endstream` fallback for indirect, malformed, or
  unusable stream lengths.
- Added a WordPress smoke proving the stale URI is not promoted and current
  searchable page text remains importable without Python models or external PDF
  tools.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildEarlyEndstreamFreeMapCurrentBaseTest.php`

Result before the source fix: `1 test files, 5 assertions, 1 failures`.
Failure: object 7 was not marked free because the fake stream-owned xref was
treated as a rebuild candidate.

After repair:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildEarlyEndstreamFreeMapCurrentBaseTest.php`

Result: `1 test files, 10 assertions, 0 failures`.

Adjacent family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildStreamPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildEarlyEndstreamFreeMapCurrentBaseTest.php`

Result: `4 test files, 139 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-early-endstream-free-map-currentbase.php`

Result: exits 0 and reports `current_xref_frees_annotation_object=true`,
`early_endstream_decoy_present=true`, `stale_link_promoted=false`,
`visible_text_imported=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted classic stream-payload test with a deliberately
short `/Length`, free-object-map literal/name/comment/whitespace decoy handling,
comment-only startxref rebuilding, private-tail EOF bounds, plus-signed headers,
or missing/malformed final startxref repair. It covers the narrower declared
stream-length boundary where an early fake `endstream` appears before a fake
classic xref table in stream payload bytes.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP classic xref
table, stream dictionary, direct-object scanner, free-object-map, link
annotation, and searchable text paths. GPU/OCR/model execution, raster
rendering, external PDF tools, and live services remain intentionally out of
scope for this markerPDF lane.

Root harness: not run - isolated micro-slice.
