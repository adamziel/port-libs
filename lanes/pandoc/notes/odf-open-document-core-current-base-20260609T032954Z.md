# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260609T032954Z`

Base accepted HEAD: `507b06f9840603abbb77bf4b360c0377f959830e`

## Summary

Implemented bounded ODF/OpenDocument sender-field settings fallback in native
PHP `OdfReader`.

Empty `text:sender-firstname`, `text:sender-lastname`, `text:sender-email`,
and `text:sender-company` fields now resolve from parsed `settings.xml`
`ooo:user-settings` config items when no visible field text is present. The
resolved values remain inert `odf-field` spans with
`data-odf-field-settings-source`, `data-odf-field-settings-set`, and
`data-odf-field-settings-name` provenance for WordPress review packets.

This stays within OpenDocument package/content/settings XML mapping. It does
not evaluate ODF formulas, inspect office profiles outside the package, or
invoke Pandoc, Haskell runners, Word, LibreOffice, zip/unzip, external
converters, online services, live provider tests, or live-service provider
tests.

## Red/Green Evidence

Red-first focused run after adding the test failed as expected:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
FAIL maps empty ODT sender fields from settings XML into review spans
Expected: 'Sender Maya Editor <desk@example.test> at WordPress Migration Desk remains auditable.'
Actual: 'Sender   <> at  remains auditable.'
1 test files, 2880 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2903 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Syntax/diff checks:

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-open-document-handoff.php

git diff --check -- lanes/pandoc
passed with no output
```

## Status Delta

- Added 1 focused PHP PASS case.
- Added 24 focused assertions in `OdfReaderTest.php`.
- Updated `lane-status.json` `phpPass` from 2233 to 2234.
- Updated `UPSTREAM_TEST_MANIFEST.json` mapped denominator and ODF counters by
  one native support case.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OdfReader` settings parsing, ODF field-span serialization, `MarkdownWriter`,
`WordPressBlockWriter`, focused PHP tests, and the existing WordPress ODF
handoff example.

Full upstream Pandoc ODT runner parity remains a separate upstream-runner
dependency task requiring a hydrated pinned checkout and Haskell test
executables.

## Non-Overlap

This avoids accepted ODF dropdown fields, variable/user field declaration
fallbacks, empty meta.xml metadata-field fallbacks, page-variable get/set
fields, chapter/file/statistic fields, conditional/hidden fields, DDE/script
fields, database ranges/subtotal rules, label ranges, data-pilot metadata,
named expressions, table annotations, drawing layers, chart/object metadata,
visible sender field style metadata, and package manifest/media/encryption
handling. It is limited to settings.xml fallback for empty sender fields.
