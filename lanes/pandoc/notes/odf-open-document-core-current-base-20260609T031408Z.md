# ODF OpenDocument Metadata Field Fallback

Slice: `pandoc-odf-open-document-core-current-base-20260609T031408Z`
Base: `915ae6d7e19462f5fae70630857416b816400e62`

## Implementation

Native `OdfReader` now parses `meta.xml` before walking `content.xml` and uses
that package metadata only when an ODF text field has no visible inline
children. Empty source metadata fields are preserved as inert review spans
instead of being dropped:

- `text:title`, `text:subject`, `text:description`, `text:keywords`
- `text:author-name`, `text:initial-creator`, `text:printed-by`
- `text:creation-date`, `text:modification-date`, `text:print-date`
- `text:modification-time`, `text:print-time`
- `text:editing-cycles`, `text:editing-duration`
- `text:template-name`
- typed `text:user-defined text:name="..."`

The fallback records `data-odf-field-metadata-source="meta.xml"` and keeps
typed user-defined values typed. It does not evaluate ODF field formulas or run
office tooling.

## Verification

Focused ODF reader test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2861 assertions, 0 failures
```

WordPress ODF metadata-field smoke:

```text
php lanes/pandoc/examples/wordpress-odf-metadata-field-handoff.php --self-test
odf metadata field handoff self-test ok
```

PHP syntax checks:

```text
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php -l lanes/pandoc/examples/wordpress-odf-metadata-field-handoff.php
```

Result: all reported no syntax errors.

Final JSON and patch hygiene checks:

```text
php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed with no output
```

## Status Delta

- `phpPass`: `2211 -> 2212`
- `benchmarkDenominator.mapped`: `2621 -> 2622`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 331`
- Focused ODF assertions: `2825 -> 2861` (`+36`)

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader` DOM/XML
package parsing, existing `meta.xml` metadata extraction, existing AST
`odf-field` span serialization, `ZipPackage` fixtures, and
`WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF dropdown fields, variable/user field
declaration fallbacks, page-variable/chapter/file/statistic fields,
conditional/hidden fields, DDE/script fields, database ranges, label ranges,
data-pilot metadata, named expressions, table annotations, drawing layers, or
chart/object metadata. It is limited to empty source metadata fields that need
`meta.xml` fallback to remain visible in WordPress review output.

## Root Harness

Root harness not run - isolated micro-slice.

## Follow-Up

Good follow-up ODF slices: calculation settings, print ranges, additional
data-pilot source metadata, or style-driven table/list semantics.
