# xref Prev chain damaged root-free current-base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T122914Z`

Source truth:

- Native no-GPU markerPDF scope: searchable-PDF parser behavior only, no OCR,
  Surya, Texify, Torch, Python model workers, or external PDF tools.
- PDF incremental updates merge xref sections through `/Prev`; a latest
  xref-stream free row for the inherited trailer `/Root` must remain
  authoritative even when the `/Prev` operand is damaged and repairable to the
  prior xref section.
- WordPress import boundary: stale previous catalog/page text, XMP metadata,
  language, and EmbeddedFiles attachments must not be revived when the current
  xref-stream section frees the inherited root.

Implementation:

- `PdfTextExtractor::xrefSectionEntriesAndPreviousOffset()` now resolves
  xref-stream section `/Prev` through the same repaired
  `previousXrefOffsetForSectionBody()` path used by the main xref chain,
  instead of the narrower direct integer lookup.
- Added a focused damaged `/Prev` fixture where the latest xref stream has no
  `/Root`, frees object `1`, and points `/Prev` a few bytes into the previous
  classic table. Before the fix, stale page text leaked from the previous
  catalog/page tree.
- Added a WordPress smoke showing the stale page text, catalog XMP/language,
  and EmbeddedFiles payload stay suppressed while carried `/Info` metadata
  remains review metadata.

Focused evidence:

- Red before implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`
  => `1 test files, 395 assertions, 1 failures`; failing case leaked
  `Stale damaged root free Prev page`.
- After implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`
  => `1 test files, 411 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native PDF
  tokenizer, xref stream/table parser, stream filter decoding, metadata
  extraction, and EmbeddedFiles review paths.
- GPU/model parity remains intentionally out of scope for this markerPDF lane.

Next:

- Continue with non-overlapping native searchable-PDF parser behavior around
  xref repair, stream filters, fonts/CMaps, metadata, annotations, forms, page
  geometry, and image/filter review metadata.
