# ODF/OpenDocument Typed Meta User-Defined Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260608T205602Z`
Base: `65a6df3ab5094e251e3a86a2aa20ace8a8f50ea8`

## Behavior

`OdfReader` now preserves typed package-level `meta:user-defined` metadata
from ODT `meta.xml` while keeping the existing simple `metadata.userDefined`
display-value map intact.

The new `metadata.userDefinedDetails` and import-report metadata preserve:

- `meta:value-type` / `office:value-type`
- `office:string-value`
- `office:boolean-value`
- `office:value`
- `office:date-value`
- `office:time-value`
- fallback display values for empty typed metadata elements

The WordPress ODF handoff smoke now includes boolean and numeric
`meta:user-defined` package metadata and verifies that import review packets
can inspect both the display value and typed detail record.

## Source Truth

Pinned Pandoc source: `jgm/pandoc` commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

The upstream ODT entry point reads ODT packages from `content.xml`,
`styles.xml`, and package media, then converts through ContentReader. The
native lane contract extends this bounded support-library surface with
reviewable manifest/meta package metadata, so this slice ports the ODF
`meta:user-defined` typed metadata contract without shelling out to Pandoc or
office tools.

Reference URL:
`https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT.hs`

## Evidence

Baseline focused test before edits:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

- `1 test files, 2268 assertions, 0 failures`

Red-first focused run after adding the typed metadata fixture:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

- `1 test files, 2271 assertions, 1 failures`
- Failure: `userDefinedDetails` was absent and empty typed
  `meta:user-defined` elements produced empty display values.

Final focused test:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

- `1 test files, 2290 assertions, 0 failures`

WordPress ODF smoke:

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

- `odf open document handoff self-test ok`

PHP lint:

`php -l lanes/pandoc/src/OdfReader.php`

- `No syntax errors detected in lanes/pandoc/src/OdfReader.php`

`php -l lanes/pandoc/tests/OdfReaderTest.php`

- `No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php`

`php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`

- `No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`

JSON validation:

`php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`

- `pandoc json ok`

Whitespace check:

`git diff --check -- lanes/pandoc`

- passed with no output

## Dependency Closure

No new support component is needed. This reuses native `OdfReader` DOM package
parsing, `meta.xml` mapping, in-process `ZipPackage` fixtures, and the existing
WordPress ODF handoff example.

No Pandoc binary, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, `zip`, `unzip`, external converter, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This patch only changes package-level `meta:user-defined` metadata. It does
not repeat accepted ODF text-tab normalization, heading ids, table captions,
style maps, content user-defined fields, field formatting metadata, page
variable/statistic fields, database/data-pilot metadata, generated indexes,
embedded objects, chart metadata, sections, tracked changes, manifest media, or
encrypted media reporting.

## Follow-Up

Next ODF/OpenDocument work should choose a non-overlapping package/content
mapping gap such as `settings.xml` metadata, additional manifest-entry
provenance, or style-driven table/list semantics.
