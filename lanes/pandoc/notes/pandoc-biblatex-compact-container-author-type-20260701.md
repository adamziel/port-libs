# Pandoc BibLaTeX Compact Container Author Type

This slice closes a bounded direct CSL/BibLaTeX alias parity gap:

- `BibtexCslParser` now maps compact `containerauthortype` into
  `container-author-type`, matching the legacy processor's existing alias.
- `CitationCslProcessor::fromItems()` accepts direct `containerauthortype`
  payloads as `containerAuthorType`.
- CSL style rendering accepts `<text variable="containerauthortype"/>` as the
  compact alias for `container-author-type`.

Direct-format parity accounting: metadata-only CSL/BibLaTeX handoff work. No
reader/writer format parity counts change, and no external Pandoc, citeproc,
BibTeX, Biber, browser, TeX, office, Node, Jupyter, archive, or validator
tooling was used.

Validation:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorBiblatexCompactContainerAuthorTypeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorBiblatexCompactContainerAuthorTypeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
