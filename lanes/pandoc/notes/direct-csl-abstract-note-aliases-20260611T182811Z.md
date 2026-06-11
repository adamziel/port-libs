# Direct CSL Abstract Note Aliases

Bead: `plib-sbv7m`
Base: `dd4bb80d0`

This slice keeps direct CSL JSON bibliography packets self-contained in the native
PHP citation handoff. `CitationCslProcessor` now normalizes compact abstract,
annotation, and note aliases into the canonical CSL metadata used by citation,
bibliography, and WordPress review rendering:

- `abstractNote`, `abstract-note`, `abstractnote` -> `abstract`
- `annotationText`, `annotation-text`, `annotationtext`, `annote` -> `annotation`
- `noteText`, `note-text`, `notetext`, `notes` -> `note`

Verification on 2026-06-11 UTC:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4721 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65071 assertions, 0 failures

No Pandoc, citeproc, bibliography managers, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
