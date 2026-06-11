# Pandoc CSL direct compact title-family aliases

Slice: plib-zv2mu, Pandoc citation/bibliography CSL core blocker slice 20260611T160443Z.

Current-base implementation on `96af5e2be` teaches the native PHP CSL processor to accept and render compact direct CSL JSON/title variables for `shorttitle`, `maintitle`, `maintitleaddon`, `containertitle`, `containertitleaddon`, `collectiontitle`, `collectiontitleshort`, and `collectionnumber`. It also treats compact short-form title variables such as `volumetitle` as eligible for `form="short"` rendering.

Coverage is in `CitationCslProcessorTest.php` and verifies normalized item metadata, compact-variable CSL citation rendering, bibliography rendering, and WordPress bibliography output. The lane remains native PHP only: no Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Pre-submit focused verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed 1 test file, 4638 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 64017 assertions, 0 failures.
