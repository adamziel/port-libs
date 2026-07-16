# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T001359Z`

Accepted base: `f937a49921892f866d7c08251cd8ea818c82ae3e`

## Behavior

- Added bounded native TeX accent and special-letter decoding for BibTeX/BibLaTeX text before CSL item normalization.
- The parser now decodes common accent forms such as `{\'E}`, `{\"u}`, `{\`e}`, `{\^u}`, `{\~n}`, `{\c c}`, plus braced/direct variants, and special-letter macros such as `{\o}`, `{\ae}`, `{\oe}`, `{\ss}`, `{\l}`, and related uppercase forms.
- Decoding is applied through the existing `BibtexCslParser::cleanBibtexText()` path so author, editor, title, container-title, publisher, citation labels, bibliography entries, and WordPress blocks receive readable UTF-8 text.
- Unknown or unsupported TeX macros remain deterministic review text rather than shelling out to Pandoc, citeproc, BibTeX, Biber, TeX engines, or online services.
- Updated the WordPress BibTeX handoff smoke so accented `.bib` names and titles stay readable in imported review blocks.

## Red/Green Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 181 assertions, 1 failures`.
  - Failure: the new accent case expected `Étude of Jalapeño Source Packets`; actual value kept literal TeX commands as `\\'Etude of Jalape\\ no Source Packets`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: `1 test files, 193 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`: `wordpress-bibtex-csl-handoff self-test passed`.
  - Root harness: not run - isolated micro-slice.

## Status Delta

- `CitationCslProcessorTest.php` moves from 9 focused cases and 180 assertions to 10 focused cases and 193 assertions.
- Lane status moves from 452 to 453 PHP PASS cases.
- Manifest mapped checks move from 920 to 921.
- BibTeX/CSL mapped core cases move from 2 to 3, and BibTeX/CSL assertions move from 38 to 51.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name metadata, initial BibTeX/BibLaTeX parser coverage, BibTeX crossref inheritance, CSL style XML/locales, simple author-date citation rendering, bracketed citation cluster parsing, missing citation preservation, EPUB3 package handoff, DOCX/ODT package parsing, table geometry, ZIP/OPC package primitives, doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset helpers, PDF handoff planning, XML/HTML5 DOM, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native `BibtexCslParser`, `CitationCslProcessor`, Markdown reader/writer, and WordPress block writer. Remaining bounded citation follow-up work is broader BibLaTeX entry families, remaining TeX macro decoding beyond the common accent/special-letter subset, CSL style XML/locales beyond the bounded style summary already present, citation-position disambiguation, note-style output, and full upstream runner hydration.
