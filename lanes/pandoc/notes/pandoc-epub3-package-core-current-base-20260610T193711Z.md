# pandoc-epub3-package-core-current-base-20260610T193711Z

Base accepted HEAD: `3866414a3872bc8b19eaf933ca45b4725ec4b2f0`

## Behavior

Implemented compact native PHP EPUB3 package preflight support for OPF
accessibility metadata in `EpubPackage`.

`EpubPackage::accessibility()` now summarizes OPF `schema:` and `a11y:`
metadata from the package document, including access modes, sufficient access
mode token sets, features, hazards, accessibility summary text, certification
fields, `dcterms:conformsTo`, and local linked accessibility records. The
summary exposes the same report through `metadata['accessibility']`,
`summary()['accessibility']`, `wordpressImport['accessibility']`, and
`wordpressImport['metadataDetails']['accessibility']`.

Linked accessibility records remain passive package provenance only: local
targets report resolved package part, byte length, and CRC metadata; remote or
missing link diagnostics continue to use the existing OPF package-link policy.

## Evidence

Focused verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/EpubPackageReaderTest.php`
- Result: `3 test files, 5051 assertions, 0 failures`

Full lane verification:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `44 test files, 61637 assertions, 0 failures`

Accounting:

- `phpPass`: `3021 -> 3022`
- mapped native support: `3167 -> 3168`
- focused delta: `+1` compact EPUB package case / `+29` assertions

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubPackage`,
`ZipPackage`, package-link parsing, DOM/libxml `NONET` XML parsing, and the
lane-local PHP test runner.

No Pandoc, Cabal/Haskell runner, EPUBCheck, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This does not change the richer `EpubReader` accessibility report, XHTML body
conversion, media overlays, encrypted resources, manifest fallback chains,
remote-resource policy, package-link vocabulary validation, or EPUBCheck-style
validation diagnostics. It is restricted to compact `EpubPackage` metadata
handoff for OPF accessibility metadata and linked accessibility records.
