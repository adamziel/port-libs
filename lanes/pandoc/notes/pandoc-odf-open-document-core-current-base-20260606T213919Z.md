# pandoc-odf-open-document-core-current-base-20260606T213919Z

Accepted base: `04ca88552bf0295efe9452d727ef7ee43b7b6a35`

## Scope

Mapped one bounded ODF/OpenDocument package-reader behavior from upstream Pandoc ODT `ContentReader`: `text:tab` is converted to a single Pandoc space, not a literal tab character. Source truth: upstream `Text.Pandoc.Readers.ODT.ContentReader` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` defines `read_tab` as `returnV space`.

Primary source: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT/ContentReader.hs

## Changes

- `lanes/pandoc/src/OdtReader.php`: normalizes `<text:tab/>` to a regular space in inline content.
- `lanes/pandoc/tests/OdtReaderTest.php`: adds package-reader coverage for tabs in headings, generated heading ids, paragraph text, and WordPress block output.
- `lanes/pandoc/examples/wordpress-odt-open-document-handoff.php`: adds a tabbed source paragraph and self-test checks that WordPress output contains a normal space and no literal tab.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: increments mapped ODF/OpenDocument core cases from 11 to 12 and assertions from 251 to 259.
- `lanes/pandoc/lane-status.json`: records the focused PHP pass delta and verification evidence.

## Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php
1 test files, 95 assertions, 0 failures
```

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php
FAIL normalizes ODT tab stops to Pandoc spaces in package reader output
Expected: 'Tabbed heading'
Actual: 'Tabbed	heading'
1 test files, 96 assertions, 1 failures
```

Final:

```text
php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php
1 test files, 103 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odt-open-document-handoff.php --self-test
ODT OpenDocument handoff self-test passed

php -l lanes/pandoc/src/OdtReader.php
No syntax errors detected in lanes/pandoc/src/OdtReader.php

php -l lanes/pandoc/tests/OdtReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdtReaderTest.php

php -l lanes/pandoc/examples/wordpress-odt-open-document-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odt-open-document-handoff.php

git diff --check -- lanes/pandoc
passed with no output
```

## Dependency closure

No new support component is needed. This slice reuses native PHP `OdtReader`, `ZipPackage`, `AstNode`, `WordPressBlockWriter`, DOM/libxml `NONET` parsing, and the focused PHP lane test harness.

Excluded: Pandoc execution, Cabal/Haskell runners, Word, LibreOffice, `zip`/`unzip`, external converters, online services, live provider tests, and live-service provider tests.

## Non-overlap

This complements earlier ODF/OpenDocument work by applying the same upstream `read_tab` contract to the ODT package reader path and WordPress ODT smoke. It does not revisit accepted ODF blockquote, heading source-id, conditional/hidden field, bibliography mark, table caption, object/media, or broader XML/HTML DOM slices.
