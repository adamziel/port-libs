# EPUB3 package encrypted binding handler slice 2026-06-10T154219Z

Scope: compact EPUB3 package ingestion now reports encrypted OPF media-type binding handlers in native PHP package review metadata.

Changes:
- `EpubPackage::bindings()` marks binding handler manifest items with `handlerEncrypted`, `handlerCanExposeBytes`, and `handlerEncryption` metadata.
- Encrypted handler entries emit an `encrypted-binding-handler` diagnostic with handler part, media type, review policy, and byte exposure policy.
- WordPress import summary carries the same media-type binding items and diagnostics for review queues.

Verification:
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 file, 773 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 files, 60508 assertions, 0 failures after rebase.

No Pandoc, office suite, TeX/browser engine, zip/unzip, Jupyter, Node tooling, external validator, online service, live provider test, or live-service provider test was executed.
