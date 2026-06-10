# EPUB3 OCF metadata links package slice

## Scope

This slice stays inside `lanes/pandoc` and extends the native EPUB3 package preflight path to read optional `META-INF/metadata.xml` link records.

## Implementation

- `EpubPackage::containerLinks()` now exposes OCF metadata `<link>` records as inert package-review metadata.
- Metadata link hrefs resolve from the container root, matching the `META-INF` base-IRI rule used by EPUB OCF.
- Local targets preserve package part names, byte lengths, CRC32 values, and OPF manifest cross-references.
- Remote links remain no-fetch review items, while missing and invalid targets emit explicit diagnostics.
- `remoteResourcePolicy()` now includes `container-link` items alongside OPF metadata links and collection links.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/EpubPackageReaderTest.php`: 3 files, 5022 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 61595 assertions, 0 failures after rebase onto `9cf4cab2e17c59f9eb589f0205e09005b700563b`.

No Pandoc, EPUBCheck, zip/unzip, browser renderer, online service, office suite, Node, Jupyter, TeX/PDF engine, live provider test, or external validator was invoked.

## Status

- `lane-status.json` `phpPass`: `3019 -> 3020` after rebase refresh.
- Focused case added: `summarizes OCF metadata links for EPUB3 package preflight handoff`.
