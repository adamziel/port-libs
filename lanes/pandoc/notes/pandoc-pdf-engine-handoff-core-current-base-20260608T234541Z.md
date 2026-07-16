# Pandoc PDF Engine Handoff Current-Base Filter Abbreviations

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T234541Z`
Base accepted HEAD: `04878c2d5c57d16172dcae66b4ced2d6a4447658`

## Behavior

- Added bounded native produced-PDF filter-name normalization in `PdfEngineHandoff`.
- Standard abbreviated stream filter names now normalize before review handoff:
  `/AHx`, `/A85`, `/LZW`, `/Fl`, `/RL`, `/CCF`, and `/DCT` map to
  `ASCIIHexDecode`, `ASCII85Decode`, `LZWDecode`, `FlateDecode`,
  `RunLengthDecode`, `CCITTFaxDecode`, and `DCTDecode`.
- The canonical names feed xref/object stream filter summaries, page-content
  stream metadata, image stream metadata, stream filter policy actions,
  DecodeParms attribution, diagnostics, and fake-runner multipass final
  summaries.
- The WordPress PDF handoff example now includes abbreviated `/Fl` xref streams
  while asserting canonical `FlateDecode` reviewer metadata.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this
  lane before work began.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1034 assertions, 0 failures`.
- Red-first: same focused command failed after adding the new case with
  `1 test files, 1036 assertions, 1 failures` because abbreviated `/Fl` was
  reported as raw `Fl`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1051 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2401 -> 2402`.
- PDF engine handoff core cases: `12 -> 13`.
- PDF engine handoff focused assertion counter: `108 -> 125`.
- Added `mappedPdfEngineFilterAbbreviationCases: 1`.
- `lane-status.json` `phpPass`: `1982 -> 1983`.

## Dependency Closure

No new support component is needed. The patch reuses native PHP PDF value
parsing, stream filter extraction, stream policy summaries, DecodeParms
summaries, and fake-runner multipass handoff in `PdfEngineHandoff`. No Pandoc,
Cabal/Haskell runner, TeX/PDF engine, Typst, browser renderer, roff, external
PDF validator, JavaScript runtime, online service, live provider test, or
live-service provider test was run.

## Non-Overlap

This slice does not repeat accepted PDF engine work for stream filter policy
classification, DecodeParms metadata extraction, XMP/PDF-A, output intents,
tagged structure, catalog URI base, page display/timing/viewports, page
resources, page-content operator metadata, annotations, AcroForm, signatures,
DSS, optional content, collections, embedded files, encryption, or active
actions. It only normalizes standard PDF stream filter abbreviations before
those existing summaries consume the names.

## Follow-Up

Next PDF engine work should target a separate native fake-runner handoff gap,
such as filter-chain validation policy, viewer-review diagnostics, richer
destination/action validation, or incremental revision provenance.
