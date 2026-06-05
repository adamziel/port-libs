# Classic XRef Generation Offset Boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T122311Z`

Accepted base: `593ac039c7e8ea27f13e4df6b87caa113ca3d76a`

## Source Truth

Classic xref rows identify a direct object by object number, generation, and byte offset. During no-GPU markerPDF import, rebuilt classic tables must not let a row for `1 0` select a direct `1 1 obj` simply because the explicit byte offset points there. The selected direct object must either match the row generation or the row must be repaired to the latest exact-generation direct object before the rebuilt xref table.

## Implemented Boundary

- `PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now require explicit xref offset owners to match the row generation before selecting them as live direct objects.
- Classic xref table parsing repairs same-object wrong-generation explicit rows to the latest direct object with the row generation before the rebuilt xref table.
- The repair is bounded before the xref table offset, so post-table garbage is not promoted.

## Focused Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php`
  - `1 test files, 31 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicGenerationOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php`
  - `3 test files, 576 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamGenerationIndexRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamHybridGenerationOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php`
  - `6 test files, 114 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-xref-classic-generation-offset-currentbase.php`
  - emits `wrong_generation_offsets_repaired=true`, `current_classic_xref_import_kept=true`, `decoy_import_excluded=true`, and no model/external-tool execution flags.

## Dependency Closure

No new support component is needed. This reuses the native PHP classic xref parser/rebuild path and stays inside the current no-GPU searchable-PDF parser scope.

## Non-Overlap

This does not repeat accepted `/Prev` stale-offset repair, xref-stream generation repair, zero-count classic subsection rejection, post-EOF classic rebuild bounds, linearized hint skipping, or runtime metadata preflight work. The slice is limited to same-object wrong-generation explicit offsets in rebuilt classic xref tables.
