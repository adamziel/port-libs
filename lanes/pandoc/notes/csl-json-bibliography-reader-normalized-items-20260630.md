# CSL JSON bibliography reader normalized items

## Slice

- Bead: `plib-r4alc`
- Area: Pandoc citations and bibliography CSL core blocker
- Date: 2026-06-30

## Change

`BibliographyReader` now stores processor-normalized `cslItems` for direct `csljson` inputs. The bibliography rendering path already used `CitationCslProcessor::fromItems()`; the document metadata now exposes the same normalized item fields for direct CSL JSON fields such as `publisher-place-list`, `container-title-short`, and `citation-aliases`.

## Direct-format parity

- `bibtex` and `biblatex`: unchanged parser handoff metadata shape.
- `ris`: unchanged parser handoff metadata shape.
- `endnotexml`: unchanged parser handoff metadata shape.
- `csljson`: metadata now matches the processor-normalized direct CSL item shape used for rendering, closing the alias parity gap without shelling out to Pandoc, citeproc, BibTeX, office suites, TeX/browser engines, zip/unzip, Jupyter, Node, or external validators.

## Verification

- `php -l lanes/pandoc/src/BibliographyReader.php`
- `php -l lanes/pandoc/tests/BibliographyReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
