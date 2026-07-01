# Pandoc BibLaTeX Hyphen Medium Status Aliases

Slice: `pandoc-biblatex-hyphen-medium-status-aliases-20260701T203000Z`

## Scope

- `how-published` now maps through both BibTeX/BibLaTeX CSL parsers as CSL `medium`, matching the direct CSL JSON alias that was already supported.
- `pub-state` now maps through both parser paths as CSL `status`, and CSL styles can render the normalized status with the `pub-state` text variable.

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorHyphenMediumStatusAliasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorHyphenMediumStatusAliasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorHyphenMediumStatusAliasTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/CitationCslProcessorBiblatexPrimaryClassArchiveTest.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- `git diff --check`
