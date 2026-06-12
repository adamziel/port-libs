# EPUB3 Collection Role Vocabulary Slice - 2026-06-12

Scope: compact EPUB3 package ingestion now reports OPF `collection` role vocabulary provenance in native PHP package review metadata.

Rebased over current `origin/main` `5e8fce9b58`.

Implemented:
- `EpubPackage::collections()` attaches `roleVocabulary` reports to each OPF collection, reusing package prefix bindings for prefixed role tokens.
- Collection diagnostics now include invalid, duplicate, and unknown-prefix role tokens without aborting package ingestion.
- `EpubPackage::summary()` adds `collectionRoleVocabulary` and mirrors it into the WordPress import review packet.
- Added one focused EPUB package test case with 41 assertions and lane-status counters:
  - `mappedEpubCollectionRoleVocabularyCases`: 1
  - `epubCollectionRoleVocabularyAssertions`: 41

Verification:
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 file, 2188 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 72422 assertions, 0 failures.

No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
