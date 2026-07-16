# Pandoc ODF OpenDocument Heading Source ID

Slice: `pandoc-odf-open-document-core-current-base-20260606T192904Z`

Base accepted HEAD: `25ea07f71d9d374a0547131630b25b485b558f60`

## Behavior

Implemented one bounded ODF/OpenDocument heading-anchor cluster in native PHP.
`OdfReader` now preserves explicit source-authored heading ids from:

- `text:h text:id`
- style-derived heading paragraphs with `xml:id`

When no heading bookmark anchor is present, the source id becomes the Pandoc-like
heading `id`, duplicate source ids receive the existing `-1`, `-2`, ... suffixes,
and `odfHeadingAnchor` plus `data-odf-heading-*` metadata records the source
attribute for Markdown review packets. WordPress heading output keeps the source
id on the rendered `<hN>` element.

## Source Truth

The bounded source contract is the pinned Pandoc ODT ContentReader heading-anchor
behavior already recorded in the lane inventory for commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`: source heading anchors should be
applied before constructing Pandoc Header nodes. This slice ports the explicit
ODF id-attribute branch without running Pandoc or an office suite.

## Evidence

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1285 assertions, 0 failures`
- Red-first focused test after adding the case:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1288 assertions, 1 failures`
  - Failure: first heading id was generated as `heading-with-text-id` instead of source `source-review-id`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1309 assertions, 0 failures`
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- PHP lint:
  `php -l lanes/pandoc/src/OdfReader.php && php -l lanes/pandoc/tests/OdfReaderTest.php && php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
  - no syntax errors in all three changed PHP files
- JSON validity:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - passed with no output
- Root harness:
  not run - isolated micro-slice

Expected status movement:

- `phpPass`: `1393 -> 1394`
- `benchmarkDenominator.mapped`: `1806 -> 1807`
- `odfOpenDocumentCoreCases`: `11 -> 12`
- `mappedOdfOpenDocumentCoreCases`: `11 -> 12`
- `odfOpenDocumentCoreAssertions`: `251 -> 275`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OdfReader`,
`AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, `ZipPackage`, and existing
in-process ODT package fixtures.

Full upstream runner parity remains blocked on a hydrated Pandoc checkout and
Cabal/Haskell test executable builds. No Pandoc, Cabal solver/build/test
command, Haskell runner, Word, LibreOffice, `zip`/`unzip`, external converter,
office tool, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat accepted ODF text-tab normalization, generated auto
identifiers, bookmark-derived heading anchors, paragraph blockquote styles,
parent-relative links/images, placeholders, source metadata/page/conditional
fields, table captions, table metadata, linked/protected sections, generated
indexes, form controls, embedded MathML/chart/OLE objects, media manifest
preflight, DOCX/OPC/EPUB behavior, or export-side ODT writing.

## Follow-Up

Keep hidden paragraphs, conditional sections, database fields, richer
generated-index entry layout metadata, tab-stop position metadata, export-side
ODT writing, and hydrated upstream Haskell runner comparison as separate
bounded slices.
