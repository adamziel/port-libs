# pandoc-epub3-package-core-current-base-20260608T195004Z

Base accepted HEAD: `e33874b6a59046b0ea8a8d0d93a0e5bb2e4b1b0b`

## Scope

Bounded EPUB3 package support-library slice for OPF manifest media-type preflight. This patch teaches `EpubReader` to resolve OPF `fallback` chains for non-core manifest media types and to surface review diagnostics for:

- missing fallback manifest item ids;
- cyclic fallback chains;
- fallback chains that terminate at another unsupported foreign media type;
- foreign resources that have neither a usable manifest fallback nor an XHTML OPF binding handler.

The diagnostics are attached to the manifest media-type report, document metadata, and asset handoff metadata so WordPress import review can retain the source problem without fetching or executing resources.

## Evidence

- Focused previous EPUB3 package evidence from the prior lane note: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> `1 test files, 2542 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> `1 test files, 2586 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` -> `epub3 package handoff self-test ok`.

Delta: +1 focused PHP PASS case and +44 focused assertions for EPUB3 package behavior.

## Dependency Closure

No new native PHP support component is needed. The slice reuses existing `ZipPackage` fixture construction, `EpubReader` OPF manifest parsing, OPF binding handling, and package-part metadata. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat the prior EPUB3 package rows for OCF container parsing, OPF metadata/vendor metadata, spine/nav/NCX targets, XHTML asset scanning, media overlays, CFI fragments, CSS resource/font-face reporting, remote resources, or existing binding-only fallback handlers. It specifically owns manifest `fallback` chain validity and media-type review handoff.

## Root Harness

Not run - isolated micro-slice.
