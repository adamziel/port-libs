# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260605T111532Z`
- Base accepted HEAD: `c7cfb94227debaaf0d478d9165f4e827450475a2`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Extended the native `OdfReader` OpenDocument Text handoff for non-Math
`draw:object` package containers, especially ODF chart objects:

- Keeps `draw:object` frames that do not contain MathML as inert embedded-object
  review placeholders instead of dropping them.
- Classifies chart objects from the manifest media type
  `application/vnd.oasis.opendocument.chart`.
- Preserves object href, package path, manifest source part, media type,
  existence, contained part names/count, contained byte length, encryption state,
  and non-exposure policy as AST and rendered review attributes.
- Reports present and missing chart/object containers through the existing
  `importReport.content.embeddedObjectCount` and
  `missingEmbeddedObjectCount` counters.
- Keeps opaque chart XML out of Markdown and WordPress rendered output.
- Updates the WordPress ODF handoff example to include a chart object placeholder
  alongside existing MathML and OLE object handoffs.

## Source Truth And Non-Overlap

The local upstream Pandoc checkout path recorded in the manifest was unavailable
in this isolated worktree. This slice therefore used accepted Pandoc lane source
truth: the existing ODF notes record that Pandoc's ODT reader follows
`draw:object` package links for embedded object content, and earlier accepted
ODF notes left chart objects as a separate follow-up after MathML objects and
OLE placeholders.

This patch does not overlap accepted ODF mimetype, manifest, metadata, styles,
page-layout/master-page, table, list, section, link, annotation, tracked-change,
field, bibliography-mark, soft-page-break, image, MathML object, form-control, or
object-ole behavior.

## Red-First Evidence

Before the implementation, the new chart-object test failed as expected:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 653 assertions, 1 failures`.

The failure showed the non-Math `draw:object` block was dropped, leaving only one
content block in the focused fixture.

## Focused Verification

Final focused test run:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`

Result: `2 test files, 778 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

Result: `odf open document handoff self-test ok`.

Syntax checks:

- `php -l lanes/pandoc/src/OdfReader.php` passed.
- `php -l lanes/pandoc/tests/OdfReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` passed.

Metadata and diff hygiene:

- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " valid\n"; }'` passed.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `856 -> 857`.
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `1314 -> 1315`.
- ODF OpenDocument core cases: `10 -> 11`.
- Mapped ODF OpenDocument core cases: `10 -> 11`.
- ODF OpenDocument core assertions: `217 -> 247`.
- Focused `OdfReaderTest.php` coverage now includes `29` PASS cases and `683`
  assertions.

## Dependency Closure

No new support component is required. This slice reuses the existing native PHP
ODF DOM/XML reader, `ZipPackage`, `AstNode`, `MarkdownWriter`, and
`WordPressBlockWriter`.

No Pandoc, Word, LibreOffice, office automation, chart renderer, zip/unzip,
Cabal build, Haskell runner, browser renderer, online sanitizer, or online
conversion service was executed.

## Follow-Up

Keep chart data-series extraction, chart-to-image rendering, form action and
submission semantics, live widgets, validation, scripting, database bindings,
richer style cascades, export-side ODT writing, and full Pandoc Haskell runner
parity as separate bounded slices.

Root harness status: not run - isolated micro-slice.
