## Legacy BibLaTeX Legal And Patent Authority Handoff

Slice: `pandoc-bibtex-csl-legal-patent-authority-20260628`

This slice extends the legacy `BibtexCslProcessor` handoff path for bounded
legal and patent bibliography metadata. Patent and jurisdiction entries now
carry legal numbers, issuing authority/court lists, jurisdiction, patent type,
patent type labels, and status into CSL item metadata, styled CSL variables,
direct bibliography text, and WordPress bibliography output.

The implementation stays inside native PHP `lanes/pandoc` citation support. It
does not invoke Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, external
legal registries, patent lookups, online services, or document converters.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 file, 763 assertions, 0 failures

Accounting:

- `phpPass`: 463 -> 464
- mapped native cases: 2305 -> 2306
- focused BibtexCslProcessor assertions: 740 -> 763
