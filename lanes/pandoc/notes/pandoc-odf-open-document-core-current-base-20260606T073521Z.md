# Pandoc ODF OpenDocument Core 2026-06-06

## Scope

Micro-slice: `pandoc-odf-open-document-core-current-base-20260606T073521Z`.

Accepted base: `10c8faa2bd4e18ec06eb4850c4a30e46d6ded63d`.

This is a bounded native PHP ODF/OpenDocument support-library slice. No Pandoc
binary, Cabal solver/build/test command, Haskell test binary, Word,
LibreOffice, `zip`, `unzip`, external office converter, online conversion
service, online sanitizer, live provider test, or other external converter was
executed as progress.

## Source Truth

The pinned upstream Pandoc commit remains
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The relevant upstream source shape
is the ODF `ContentReader` inline-content reader at:

https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT/ContentReader.hs

This slice ports one bounded part of that ODF inline-field contract into the
native PHP reader: source metadata text fields must remain visible inline
content instead of being dropped as unknown `text:*` elements.

The local worktree and upstream cache do not contain a hydrated Pandoc checkout
for Haskell runner comparison, so this slice uses the accepted source inventory
and focused PHP fixtures as evidence.

## Implemented Behavior

`OdfReader::isTextFieldElement()` now admits bounded OpenDocument source
metadata fields into the existing `fieldNode()` handoff path, including:

- `text:title`
- `text:subject`
- `text:description`
- `text:keywords`
- `text:initial-creator`
- `text:creation-date` / `text:creation-time`
- `text:modification-date` / `text:modification-time`
- print/editing metadata fields
- author and sender metadata fields

Those elements now produce `span` AST nodes with `odf-field` classes, preserve
visible fallback text, carry fixed/date/time metadata into `fieldMetadata`, and
render through Markdown and WordPress with `data-odf-field-*` attributes.

## Focused Evidence

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1199 assertions, 0 failures`
- Red-first after adding the focused metadata-field case:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1200 assertions, 1 failures`
  - Failure showed the field text was dropped:
    `Metadata  by  created  at  revised  keywords .`
- PHP lint:
  `php -l lanes/pandoc/src/OdfReader.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - `No syntax errors detected`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1227 assertions, 0 failures`
  - Focused delta from this current-base ODF reader baseline:
    `+1` PASS case / `+28` assertions
- Example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1243 -> 1244`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `benchmarkDenominator.mapped`: `1686 -> 1687`
- `odfOpenDocumentCoreCases`: `10 -> 11`
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`
- `odfOpenDocumentCoreAssertions`: `217 -> 245`

## Dependency Closure

No new native PHP support component is needed. This slice reuses `OdfReader`,
`AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, `ZipPackage`, and the
existing ODF WordPress handoff example.

Full upstream runner parity remains blocked by the missing hydrated Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Cabal
project/package files and Haskell Tasty executable builds.

## Non-Overlap

This patch only covers ODF source metadata text fields routed through the
existing inline field handoff. It deliberately avoids the already accepted ODF
variable/user/page/date field declarations, sequence fields, bibliography
marks, placeholders, form controls, links, annotations, tracked changes,
sections, text:tab normalization, blockquote styles, heading anchors, frame
image/link normalization, frame image dimensions, text-box captions, embedded
objects, table/list mapping, DOCX/OpenXML, EPUB3, ZIP/OPC, XML/HTML5 DOM,
CSL/BibTeX, YAML, table geometry, math/TeX, PDF engine, charset/Unicode, and
upstream-runner audit surfaces.

## Next Activation Gate

Continue ODF/OpenDocument parity in separate bounded slices: database and
conditional fields, richer index-entry layout application, tab-stop position
metadata, export-side ODT writing, or hydrated upstream Haskell runner
comparison once the pinned checkout and Cabal test executables are available.
