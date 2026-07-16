# Pandoc PDF Engine Handoff Core: Structure User Properties

Date: 2026-06-09 UTC
Base accepted HEAD: ae05f994f04ccc78db62e7bd6dd42669f76246b1
Micro-slice: pandoc-pdf-engine-handoff-core-current-base-20260609T020811Z

## Behavior

This slice adds bounded native PHP extraction for tagged-PDF structure user
properties in fake-produced PDF bytes. `PdfEngineHandoff` now recognizes
`StructElem` `/A` attribute dictionaries whose `/O` owner is `/UserProperties`,
walks bounded inline/reference `/P` property lists, and reports:

- structure object and structure type;
- user-property attribute object or inline source;
- property `/N` name;
- typed `/V` value plus `valueType`;
- optional `/F` formatted label;
- optional `/H` hidden flag.

The handoff emits `pdf-byte-structure-user-properties:*`,
`pdf-byte-structure-user-property-type:*`, and hidden-count diagnostics, keeps
these `/UserProperties` dictionaries out of the existing layout/table
`pdfStructureAttributes` surface, and propagates the extracted list through
`fakeRunSequence()` as `finalPdfStructureUserProperties`.

The WordPress PDF review-packet smoke now includes a structure user-property
attribute dictionary for the H1 structure element and exposes the current/final
metadata arrays plus diagnostics without invoking Pandoc, TeX/PDF engines, or
browser renderers.

## Evidence

Baseline from the prior accepted PDF slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `1 test files, 1118 assertions, 0 failures`

Focused verification after this patch:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `No syntax errors detected in lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `php -r 'foreach (["lanes/pandoc/UPSTREAM_TEST_MANIFEST.json", "lanes/pandoc/lane-status.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } } echo "json ok\n";'`
- `json ok`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `1 test files, 1130 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- `pdf engine handoff self-test ok`
- `git diff --check -- lanes/pandoc`
- passed with no output

Focused delta:

- `phpPass`: 2124 -> 2125
- focused PDF assertions: 1118 -> 1130
- `benchmarkDenominator.mapped`: 2551 -> 2552
- `pdfEngineHandoffCoreCases`: 12 -> 13
- `pdfEngineHandoffCoreAssertions`: 108 -> 120

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing bounded native PDF
object parser and fake-runner byte inspection surfaces. No Pandoc, Cabal,
Haskell runner, TeX/PDF engine, Word, LibreOffice, zip/unzip, browser renderer,
external converter, online service, live provider test, or live-service provider
test was executed.

## Non-Overlap

This slice does not repeat recent PDF/XMP Media Management, signature seed value,
PDF/A/PDF/UA conformance, output-intent, catalog URI/base, tagging root,
structure parent-tree, structure element, layout/table structure attribute,
class-map/class-usage, ID-tree, marked-content, annotation, form, signature, DSS,
active-action, encryption, or renderer-parity surfaces. It only adds the
`/O /UserProperties` attribute-list handoff and its WordPress smoke coverage.

## Follow-Up

Future PDF handoff slices should choose non-overlapping fake-runner diagnostics
such as deeper parent-tree/ID-tree resolution edges, artifact/annotation
metadata gaps, XMP provenance gaps, form/signature/security preflight, or
PDF-output planning fields.
