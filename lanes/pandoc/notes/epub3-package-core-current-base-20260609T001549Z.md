# EPUB3 Manifest Property Vocabulary Handoff

Slice: `pandoc-epub3-package-core-current-base-20260609T001549Z`
Base accepted HEAD: `2db0e80f0d313cd1b86adb66fbde40c6e33a2164`

## Behavior

Implemented bounded native PHP EPUB3 package support for OPF manifest item `properties` vocabulary handoff.

- `EpubReader` now resolves prefixed OPF manifest item property tokens through package `prefix` bindings, including reserved package prefixes such as `rendition`.
- Manifest items expose per-token vocabulary reports with raw property names, prefix/local-name fields, resolved IRIs, counts, and diagnostics for undeclared prefixes.
- `resourceProperties.propertyVocabulary` summarizes all manifest item property tokens by item id and prefix so WordPress/package review can inspect custom Schema.org or house vocabulary terms without changing the existing MathML/SVG/remote/scripted review flags.

## Evidence

Baseline focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3169 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 3204 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`
- `git diff --check -- lanes/pandoc`
- Result: passed

## Dependency Closure

No new support component is needed. This reuses the lane's native PHP ZIP/OPC package reader, OPF XML parser, package prefix resolver, manifest resource-property handoff, and WordPress EPUB package example.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip, Word, LibreOffice, browser renderer, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not touch the already accepted EPUB vendor metadata, accessibility metadata, metadata refinements, metadata-link vocabulary, nav/NCX page-list, XHTML viewport/language/meta-refresh/form/switch/trigger, media-overlay, or asset fallback slices. It is restricted to OPF manifest item `properties` vocabulary provenance and its WordPress resource-property handoff.
