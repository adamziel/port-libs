# CSL call-number alias current-base slice

Bead: `plib-poqzm`

Base: `e9d25106ae`

Scope:
- Keep the Pandoc citation/bibliography path native-PHP only.
- Normalize compact call-number aliases from BibTeX/BibLaTeX and direct CSL JSON.
- Avoid invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Implemented:
- `BibtexCslParser` and legacy `BibtexCslProcessor` now map `shelfmark`, `shelf-mark`, `callno`, `call-no`, `callnum`, `call-num`, `shelflocation`, and `shelf-location` to canonical `call-number` metadata.
- `CitationCslProcessor` now accepts the matching direct CSL JSON aliases, including camel-case `callNo`, `callNum`, `shelfMark`, and `shelfLocation`.
- CSL text rendering now exposes those aliases through `call-number`-equivalent variables for citation and bibliography layouts.
- The focused fixture covers BibTeX/BibLaTeX import, direct CSL JSON, style rendering, default bibliography text, and WordPress bibliography handoff.

Verification:
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: 1 test file, 4944 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 67307 assertions, 0 failures.
