# Pandoc BibTeX CSL Custom Fields Current Base

Slice: `plib-5uxmy`

## Scope

- Added bounded native PHP legacy BibLaTeX custom-field handoff in `BibtexCslProcessor`.
- Preserves `usera` through `userf` and `verba` through `verbc` as `biblatex-custom-fields`.
- Preserves `lista` through `listf` as `biblatex-custom-lists`.
- Preserves `namea` through `namec` as `biblatex-custom-names`.
- Direct bibliography text, CSL style variables, and WordPress output now expose these review fields.

## Boundary

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external renderer, office suite, TeX/PDF engine, browser, Node tooling, or online service was invoked. This slice only carries source metadata through native PHP review handoff; it does not implement full citeproc parity.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`

Focused test result: `1 test files, 553 assertions, 0 failures`.
