# XRef Stream Filter Stack Boundary Current Base

- Date: 2026-06-08 UTC
- Lane: markerpdf
- Slice: markerpdf-stream-filter-stack-boundary-current-base-20260608T203930Z
- Accepted base: ae5f6fd385045c5bd4eaa3669e2cb41d0fecb36c

## Source Truth

PDF xref streams define object-selection state. Native row decoding must not
silently ignore bytes after the terminal compressed filter member, because a
concatenated Flate member can otherwise be hidden behind the first member while
the review still counts current xref rows.

The red-first probe on this base built a `/Type /XRef` stream with
`/Filter /FlateDecode` and two concatenated Flate members. Before the source
edit, `extractXrefStreamFilterLengthOwnerReview()` reported
`decoded_entry_count=1` and `decoded_with_current_operands=true`, admitting the
first compressed member as current xref data.

## Patch

- Added `decodeXrefStreamObject()` so xref stream row decoding reuses the
  existing bounded native stream decoder with null-filter DecodeParms recovery.
- Routed xref-stream row admission and xref-stream problem checks through
  bounded filter-end validation.
- Added focused tests for direct Flate concat rejection, ASCII85 + Flate stack
  concat rejection, and the control case where a single Flate member followed
  only by PDF whitespace still decodes.
- Added a WordPress-facing preflight smoke for the same xref stream filter
  stack boundary.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamFilterStackBoundaryCurrentBaseTest.php`
  - `1 test files, 41 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-xref-stream-filter-stack-boundary-currentbase.php --self-test`
  - exits 0 with `concat_flate_xref_rejected=true`,
    `stacked_concat_flate_xref_rejected=true`, and
    `single_flate_member_preserved=true`

## Dependency Closure

No new support component is required. This reuses the existing native PHP
stream-filter boundary helpers (`streamFilterInputHasBoundedEndMarker()` and
Flate consumed-byte detection) for xref streams. No Python, OCR/model worker,
external PDF tool, or live service is invoked.

## Non-Overlap

This does not repeat the accepted metadata Flate concat boundary, object-stream
carrier boundary, page content filter-stack boundary, malformed CMap literal
target, DecodeParms applicability, or GPU/OCR/model parity work. The ownership
surface is xref stream row admission and xref stream preflight review only.
