# Pandoc BibTeX/CSL Report Authority Fallback Slice

Bead: `plib-qmf28`

Scope:
- Current-base Pandoc citation/bibliography CSL blocker work under `lanes/pandoc`.
- No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Change:
- `CitationCslProcessor` now treats normalized `authority` names as the bounded default creator fallback for `report` items when no author/editor/translator names are present.
- The fallback is limited to reports so existing legislation and legal-case title-led citation labels remain unchanged.

Focused coverage:
- Added `uses bounded biblatex institutional authority as report creator fallback` to `CitationCslProcessorTest.php`.
- The fixture covers a BibTeX `@report` with only `institution`, verifies normalized authority/publisher/raw-field handoff, checks default citation and bibliography rendering, exercises explicit CSL `<names variable="authority"/>`, and confirms `@legislation` remains title-led.
- WordPress bibliography handoff now emits `<dt>Migration Review Institute 2026</dt>` for the report while keeping `<dt>Title Led Import Rule 2025</dt>` for the legal source.

Verification:
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php` passed 2 files / 4524 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 61512 assertions / 0 failures after rebasing on `d4fb5ff1fe8ff76d1c49a05afc01d5604d488677`.
