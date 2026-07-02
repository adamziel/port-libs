# Pandoc BibLaTeX Disambiguation Hash Handoff

Slice: `plib-8ffft` citation/bibliography CSL core blocker.

Implemented a bounded native PHP legacy BibLaTeX handoff for disambiguation and bibliography review metadata:

- `pageref` / `page-ref` becomes `biblatex-page-ref`.
- `namehash`, `fullhash`, `bibnamehash`, `labelnamehash`, `authorfullhash`, `author-name-hash`, `editornamehash`, and `sortnamehash` become discrete CSL item metadata fields.
- `biblatex-disambiguation-summary` compacts those values for direct bibliography text, custom CSL style variables, WordPress bibliography output, and citation handoff review packets.

This does not invoke Pandoc, citeproc, BibTeX, Biber, external bibliography managers, online services, office tools, TeX/PDF engines, browser engines, or archive validators.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` passed with 1 file, 1209 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php` passed with 2 files, 7321 assertions, 0 failures.
