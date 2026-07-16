# Pandoc Legacy DOC CFB Core Slice 2026-06-05 10:15 UTC

## Scope

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T101557Z`.

Accepted base: `a63d0e111d11d4cbd43704afd1c4614546f1110e`.

This slice adds bounded native legacy Word DOC formatting-table provenance. It
does not invoke Pandoc, Word, LibreOffice, OLE handlers, macro engines,
Haskell runners, online services, or full Word formatting expansion.

## Source Truth

Microsoft MS-DOC records the FibRgFcLcb97 `PlcBteChpx` and `PlcBtePapx`
offset/length pairs in the selected Word table stream. The bounded PHP reader
now treats those as piece-location tables containing FC boundaries followed by
`PnFkp` entries and exposes the corresponding FKP page/range metadata for
review.

## Behavior

- Added bounded parsing for `PlcBtePapx` and `PlcBteChpx` from the selected
  `0Table`/`1Table` stream.
- Exposed paragraph and character formatting range metadata under
  `formattingRuns`, including FC start/end offsets, byte length, FKP page,
  FKP byte offset, FKP byte count, and FKP page run count.
- Added metadata counters for total, paragraph, and character formatting runs.
- Rejected malformed formatting table lengths, unsorted or duplicate FC
  boundaries, FCs outside the WordDocument stream, table-stream ranges outside
  the selected table stream, and FKP pages outside the WordDocument stream.
- Kept `canApplyFormatting` false so import reviewers get provenance without
  claiming full PAPX/CHPX SPRM style application.
- Updated the WordPress legacy DOC handoff smoke to surface the formatting-run
  audit while preserving rendered block output.

## Evidence

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 380 assertions, 0 failures`.
- Focused after edit:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  passed with `1 test files, 414 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  passed with `legacy doc handoff self-test ok`.
- PASS growth: `+2` focused PHP PASS cases.
- Assertion growth: `+34` focused assertions.
- Manifest movement: `+1` mapped legacy DOC/CFB native case, legacy DOC/CFB
  assertions `38 -> 72`, total mapped static inventory `1287 -> 1288`.

## Dependency Closure

No new support component is needed. This reuses the existing native
`pandoc-legacy-doc-cfb-core` reader and existing CFB/FIB/table-stream helpers.
Full upstream Pandoc runner parity remains outside this slice and still needs a
hydrated upstream Haskell checkout plus Cabal dependency plan before any
bounded runner execution can be claimed.

## Non-Overlap

This patch avoids accepted legacy DOC/CFB FIB encryption, fExtChar Unicode text
range decoding, CLX PCD flag validation, CFB header/root preflight, and
ObjectPool Ole10Native metadata extraction. It also avoids DOCX/OpenXML, EPUB3,
ODT, archive compression, YAML, CSL/BibTeX, table geometry, math/TeX, PDF
handoff, XML/HTML5 DOM, charset/Unicode, and upstream-runner audit surfaces.

## Follow-Up

Keep full PAPX/CHPX SPRM expansion, FKP property-byte interpretation, style-id
application to paragraph/character ranges, latent style data, list tables,
revision marks, picture extraction, VBA trust policy, and encrypted DOC
password/decryption policy as separate bounded slices.

## Verification

Final verification for this handoff is recorded in `lane-status.json` and the
worker final report. Root harness: not run - isolated micro-slice.
