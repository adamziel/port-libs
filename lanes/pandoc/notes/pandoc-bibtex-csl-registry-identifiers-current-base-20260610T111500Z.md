# Pandoc BibTeX/CSL Registry Identifier Handoff

Implemented one bounded native PHP BibTeX/CSL identifier slice:

- `BibtexCslParser` preserves BibLaTeX registry fields `mrnumber`, `mrclass`, `zbl`, `jstor`, `hdl`, `lccn`, and `oclc`.
- `CitationCslProcessor` normalizes those fields for direct CSL JSON-like items and exposes them as CSL text variables.
- Default bibliography review output now keeps MathSciNet, zbMATH, JSTOR, Handle, LCCN, and OCLC identifiers visible.
- Added a WordPress handoff example proving the identifiers survive Markdown citation rendering and bibliography block output.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.

Verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/examples/wordpress-bibtex-csl-registry-identifiers-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php lanes/pandoc/examples/wordpress-bibtex-csl-registry-identifiers-handoff.php --self-test`
- `php tools/run-tests.php lanes/pandoc/tests`

Results:

- Focused `CitationCslProcessorTest.php`: 1 test file, 4227 assertions, 0 failures.
- WordPress registry identifier handoff self-test: passed.
- Full Pandoc PHP lane after rebase onto `origin/main`: 44 test files, 59601 assertions, 0 failures.

Scope boundaries:

This slice does not repeat accepted DOI/ISBN/ISSN/ISAN/ISMN/ISRN/ISWC/PMID/PMCID, eprint/archive, source-file, entryset/related/xref, title hierarchy, creator-role, language, keyword, supplement, or periodical slices. It only owns bounded registry identifier preservation and CSL variable exposure.
