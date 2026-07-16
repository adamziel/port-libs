# Pandoc CSL subdivision alias spellings

Slice: `plib-hwdsh`

## Change

- Direct CSL JSON item normalization now treats `subDivision`, `sub-division`,
  and `sub_division` as aliases for canonical `division` metadata.
- CSL style variables with the same spelling family render from the canonical
  `division` value.
- BibTeX/BibLaTeX field mapping accepts `sub-division` and `sub_division`
  alongside the existing `subdivision` field.

## Parity

This is citation and bibliography metadata alias work inside the Pandoc lane. It
does not change direct-format parity accounting.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslSubdivisionAliasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslSubdivisionAliasTest.php`
