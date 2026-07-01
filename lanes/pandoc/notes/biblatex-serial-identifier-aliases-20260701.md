# BibLaTeX Serial Identifier Aliases - 2026-07-01

- Expanded the legacy `BibtexCslProcessor` ISBN/ISSN field aliases to match strict CSL handoff coverage for `isbn13`, `isbn-13`, `isbn10`, `isbn-10`, `eisbn`, `e-isbn`, `electronicisbn`, `electronic-isbn`, `printissn`, `print-issn`, `pissn`, `p-issn`, `eissn`, `e-issn`, `electronicissn`, `electronic-issn`, `onlineissn`, `online-issn`, `issnonline`, and `issn-online`.
- Added focused coverage proving legacy BibLaTeX print/electronic serial identifiers normalize into existing CSL `ISBN`/`ISSN` variables and remain style-visible through citation, bibliography, and WordPress handoff.
- Validation passed `php -l` for `BibtexCslProcessor.php` and `BibtexCslProcessorTest.php`; focused `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` passed with 1 file, 1046 assertions, 0 failures.
- Full `php tools/run-tests.php lanes/pandoc/tests` was attempted on 2026-07-01 and remains baseline-red outside this slice: 534 test files, 142315 assertions, 8912 failures, starting in existing DocBook, HTML/WordPress writer, LaTeX writer, and Markdown raw-block surge coverage.
- No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Node tooling, validators, online services, or live providers were invoked.
