# pandoc-odf-open-document-core-current-base-20260608T190859Z

- Lane: `pandoc`
- Base accepted HEAD: `40e4afa74effef117e3761e0e7b8018882962824`
- Scope: bounded ODF/OpenDocument `meta.xml` package policy metadata mapping.

## Behavior

`OdfReader` now preserves these native ODF `office:document-meta` values in document metadata and import reports:

- `meta:generator`
- `meta:editing-duration`
- `meta:printed-by`
- `meta:print-date`
- `meta:print-time`
- `meta:template` xlink href/type/title/show/actuate plus `meta:date`
- `meta:auto-reload` xlink href/type/show/actuate plus `meta:delay`
- `meta:hyperlink-behaviour` xlink show plus `office:target-frame-name`

The WordPress ODF handoff example now exercises the same package metadata in its `--self-test`.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php` - passed
- `php -l lanes/pandoc/tests/OdfReaderTest.php` - passed
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` - passed
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` - passed, `1 test files, 2122 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` - passed, `odf open document handoff self-test ok`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json ok\n";'` - passed
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "UPSTREAM_TEST_MANIFEST.json ok\n";'` - passed
- `git diff --check -- lanes/pandoc` - passed

## Dependency Closure

No new native PHP support component is needed. This slice reuses `ZipPackage` XML parts, DOM namespace parsing, `OdfReader` metadata handoff, `MarkdownWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This patch avoids recent ODF content/table/field clusters such as table subtotal rules, data-pilot metadata, named expressions, typed text fields, hidden/conditional fields, dropdown fields, database fields, generated indexes, tracked changes, chart/math/OLE objects, and table style/caption behavior.

Next useful ODF package follow-up: modification metadata (`meta:modification-date` / `meta:modification-time`) or another non-overlapping package/content metadata gap with focused tests.
