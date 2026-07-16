# ODF OpenDocument Named List Continuation

Slice: `pandoc-odf-open-document-core-current-base-20260608T225207Z`
Base accepted HEAD: `c992bb947324f7207d596c6abc6496ba6a35dd32`

## Behavior

- Preserves ODF source list identifiers from `text:id` and `xml:id` on list AST metadata.
- Uses `text:continue-list` to resume numbering from the named earlier list instead of whichever same-level list happened to appear most recently.
- Exposes inert WordPress review attributes for source list ids and named continuation links:
  `data-odf-list-id`, `data-odf-list-id-attribute`, `data-odf-list-continue-list`, and `data-odf-list-continued`.
- Keeps ordinary `text:continue-numbering` output stable unless a named `text:continue-list` is present.

## Source Truth

The pinned upstream ODT reader at `jgm/pandoc` commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` tracks list-continuation counters
per list level in `ContentReader.hs`. This native PHP support slice keeps that
bounded continuation behavior and adds ODF source-list linkage metadata needed
for WordPress import review of split ODT checklists.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external converter, online service, live provider test,
or live-service provider test was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2479 assertions, 0 failures`
- Intermediate focused run caught two exact-output regressions after adding
  `data-odf-list-continued` to ordinary `text:continue-numbering` lists; the
  implementation was narrowed to named `text:continue-list` metadata.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2503 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native `OdfReader` DOM
package/content parsing, existing list-style state, `MarkdownWriter` list
numbering, `WordPressBlockWriter` list output, `ZipPackage` fixtures, and the
existing WordPress ODF handoff example.

## Non-Overlap

This does not repeat accepted ODF text:tab normalization, heading ids,
blockquote styles, table captions, style maps, data-pilot metadata, typed
fields, drop-down fields, settings.xml, draw-layer metadata, form controls,
chart metadata, table row/column visibility, linked/protected sections,
tracked changes, manifest media, encrypted media reporting, or ordinary
same-level `text:continue-numbering` behavior.

## Next

Choose a non-overlapping ODF package/content gap such as additional list-style
image metadata, manifest-entry provenance, tracked-change edge metadata, or
style-driven table/list semantics.
