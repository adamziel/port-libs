# Pandoc CSL publisher-list compact aliases - 2026-06-11

Bead: plib-krlnb
Base: current main 9c821d42a

## Scope

- Normalized direct CSL JSON `publisherlist` and `publisherplacelist` compact aliases beside existing `publisher-list`/`publisherList` and `publisher-place-list`/`publisherPlaceList`.
- Exposed compact CSL text variables `publisherlist` and `publisherplacelist` through the native style renderer.
- Added one focused `CitationCslProcessorTest` fixture covering array and scalar alias inputs, citation rendering, styled bibliography rendering, default publisher-place bibliography text, and WordPress definition-list handoff.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed 1 test file, 4865 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 66760 assertions, 0 failures.
