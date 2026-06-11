# ODT settings package inventory role current-base slice

Bead: `plib-yi9y7`
Base: `origin/main` `ba99e7070b699bac1fde42ad212f7d9ab9e116e0`

This slice closes a compact ODF/ODT package-ingestion gap in `OpenDocumentPackage`: manifest-declared `settings.xml` is now classified as an `odf-settings` package role in the ZIP inventory, matching the settings parser and keeping review handoff consumers from treating the settings part as only a generic manifest-declared XML file.

Coverage was added to the existing compact settings package fixture to assert:

- `settings.xml` keeps parsed config item/set/map metadata.
- `settings.xml` appears in `packageInventory.parts` with `["odf-settings", "manifest-declared"]`.
- The inventory preserves manifest path/media-type, declared status, and non-undeclared state.
- `settings.xml` remains excluded from document media byte handoff.

Verification on the refreshed base:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed 1 test file, 457 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 66925 assertions, 0 failures.

No Pandoc binary, office suite, `zip`/`unzip`, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
