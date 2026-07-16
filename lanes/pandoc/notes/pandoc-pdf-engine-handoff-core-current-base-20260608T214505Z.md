# Pandoc PDF Engine Handoff Current-Base Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T214505Z`
- Base accepted HEAD: `d3abbb95081070fb026b5d1f370547d9f7bd861e`
- Behavior: native produced-PDF embedded-file stream filter policy handoff.

## Implementation

`PdfEngineHandoff` now includes filtered embedded-file streams in the central
`pdfStreamFilterPolicy` and `finalPdfStreamFilterPolicy` summaries. The policy
entry preserves the embedded file source path, stream object reference, filter
names, byte count, skipped reason, and review action such as
`requires-decryption` for `/Crypt` filters or `deferred-decode` for bounded
deferred filters.

This extends the accepted stream-filter policy surface beyond xref, object,
page-content, image XObject, form XObject, and annotation-appearance streams so
WordPress PDF review packets can flag filtered attachments and associated-file
payloads without running a PDF renderer.

## Evidence

- Rework-note check: `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print` returned no Pandoc rework note.
- Baseline focused command before the slice: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Baseline result: `1 test files, 975 assertions, 0 failures`.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Final result: `1 test files, 978 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Example result: `pdf engine handoff self-test ok`.
- Manifest/status delta: `benchmarkDenominator.mapped` `2303 -> 2304`; PDF engine handoff core cases `12 -> 13`; PDF engine handoff core assertions `108 -> 111`; lane `phpPass` `1880 -> 1881`.

## Non-Overlap

This slice avoids accepted PDF-engine clusters for XMP/PDF-A/PDF-UA metadata,
output intents, tagged structure, catalog URI base, page display metadata,
annotation appearance extraction, rich media annotations, page resource
ProcSet/Pattern/Shading metadata, and the existing stream-filter policy grouping
for xref/object/page/image/form/annotation surfaces. It only adds embedded-file
stream policy entries from fake-produced PDF bytes already parsed by the native
attachment handoff.

## Dependency Closure

No new support component is needed. This reuses native `PdfEngineHandoff` PDF
object/value/stream inspection helpers and the existing WordPress PDF handoff
example. Pandoc, Cabal solver/build/test commands, Haskell runners, TeX/PDF
engines, Typst, browser renderers, roff renderers, external PDF validators,
JavaScript runtimes, online services, live provider tests, and live-service
provider tests were not run.

## Follow-Up

Potential next PDF-engine gaps remain DecodeParms predictor metadata,
token-delimited stream marker parsing for attachment MIME subtypes containing
`stream`, richer xref/object stream policy handoff, and real renderer parity,
all still bounded away from engine execution unless explicitly authorized.
