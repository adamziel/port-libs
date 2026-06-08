# ODF OpenDocument Image List Style Metadata

Slice: `pandoc-odf-open-document-core-current-base-20260608T231304Z`
Base accepted HEAD: `2a117f9ba2effc54e8f915363aa5ed476910dbad`

## Behavior

- Parses `text:list-level-style-image` entries in ODT list styles instead of
  dropping them as unsupported list levels.
- Preserves graphic-bullet metadata from `xlink:href`, `xlink:type`,
  `xlink:show`, `xlink:actuate`, `xlink:title`, `svg:width`, and
  `svg:height`.
- Preserves list-level properties and label-alignment metadata, including
  minimum label width, position-and-space mode, list tab stop, text indent,
  and margin-left.
- Exposes the metadata on list AST attributes and as inert WordPress
  `data-odf-list-*` attributes while keeping Markdown output as a normal
  unordered-list fallback.
- Adds an `imageListStyleCount` import-report counter for graphic-bullet list
  styles observed in content.

## Source Truth

This slice follows the bounded ODF package/content contract already owned by
`OdfReader`: list styles are parsed from `styles.xml`, applied while reading
`text:list`, and surfaced as safe review metadata for WordPress import. The
previous ODF lane note named additional list-style image metadata as the next
non-overlapping package/content gap.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external converter, online service, live provider test,
or live-service provider test was executed.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this slice.
- Prior accepted ODF focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2503 assertions, 0 failures`
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 2540 assertions, 0 failures`
- Coupled ODT reader check:
  `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - `1 test files, 95 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native `OdfReader` DOM package
parsing, existing ODT list-style state, `MarkdownWriter` list fallback,
`WordPressBlockWriter` list output, `ZipPackage` fixtures, and the existing
WordPress ODF handoff example.

## Non-Overlap

This does not repeat accepted ODF text:tab normalization, heading ids,
blockquote styles, table captions, style maps, data-pilot metadata, typed
fields, drop-down fields, settings.xml, draw-layer metadata, form controls,
chart metadata, table row/column visibility, linked/protected sections,
tracked changes, manifest media, encrypted media reporting, ordinary
same-level `text:continue-numbering`, named `text:continue-list`, or database
range subtotal-rule metadata.

## Next

Choose a non-overlapping ODF package/content gap such as manifest-entry
provenance, tracked-change edge metadata, data-pilot metadata, or style-driven
table cell semantics.
