# Citation/CSL Standalone BibLaTeX Entry Options

Slice: 2026-06-27 `plib-8k0yp`

The native BibTeX/BibLaTeX reader now lifts bounded standalone BibLaTeX entry-option fields into the existing `biblatex-options` CSL review channel. Covered fields include skip/display switches, name/list disambiguation switches, label-date parts, sort locale, name-use toggles, and name/item count bounds. Explicit standalone fields replace the same option name from `options={...}` so `skipbib={true}` can override `options={skipbib=false}` deterministically.

`dataonly={true}` is handled before item creation, matching `options={dataonly}` behavior. `dataonly={false}` remains visible as provenance on ordinary CSL items.

Focused validation:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 6,031 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php` - 28 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php` - 695 assertions, 0 failures
