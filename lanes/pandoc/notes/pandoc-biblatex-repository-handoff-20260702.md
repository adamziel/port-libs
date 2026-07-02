# Pandoc BibLaTeX Repository Handoff Slice - 2026-07-02

Bead: `plib-q7nbz`

Scope: bounded BibTeX/CSL citation metadata. BibLaTeX `repository`,
`repositoryname`, `repository-name`, `depository`, `holdinginstitution`,
`holding-institution`, and `holding_institution` now flow into CSL repository
metadata through `BibtexCslParser`, legacy `BibtexCslProcessor`, and
`CitationCslProcessor`.

Why this is narrow: archive prefix, archive collection, archive location, and
call-number/shelfmark handling already existed. This slice keeps repository as a
separate holding-institution metadata string so CSL styles can render
`repository`/`depository` variables without overloading archive or shelfmark
fields.

Focused fixture: `BibtexCslProcessorRepositoryHandoffTest.php` covers raw
BibTeX provenance, legacy bibliography fallback text, direct CSL item aliases,
custom CSL style rendering, and WordPress bibliography handoff without invoking
Pandoc, citeproc, BibTeX, Biber, bibliography managers, office suites,
TeX/browser engines, Typst, Node, zip/unzip, validators, or live services.

Verification:

```sh
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorRepositoryHandoffTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorRepositoryHandoffTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorRepositoryHandoffTest.php lanes/pandoc/tests/CitationCslProcessorBiblatexPrimaryClassArchiveTest.php lanes/pandoc/tests/BibtexCslProcessorOriginalIdentifierTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Results: focused repository handoff test passed with 1 file, 44 assertions, 0
failures. Adjacent CSL/BibTeX coverage passed with 5 files, 7359 assertions, 0
failures.
