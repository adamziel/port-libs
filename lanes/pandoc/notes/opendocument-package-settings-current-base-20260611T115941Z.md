# OpenDocument package settings summary slice

- Bead: `plib-s6r7e`
- Required base: `34d367301db334ea34d9b918703e7eccfd4a7b9b`
- Scope: compact ODT/OpenDocument package ingestion in `OpenDocumentPackage`

## Change

`OpenDocumentPackage` now parses manifest-declared `settings.xml` package parts into compact settings metadata:

- top-level `config:config-item-set` summaries;
- typed `config:config-item` values for booleans, integers, and floats;
- named and indexed `config:config-item-map-*` entries;
- `settingsXml` and `settings` fields in package summaries;
- settings metadata on the compact shared AST document attrs.

The parser remains manifest-gated, so undeclared `settings.xml` stays an undeclared package entry, and declared missing or malformed settings parts fail before handoff. `settings.xml` is kept out of media byte exposure.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 file, 271 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 62935 assertions, 0 failures

`lane-status.json` moved `phpPass` from 3054 to 3055.
