# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T041523Z`
Base: `885f79b544126701ac9263486315593117b46de0`

## Behavior

- Added bounded BibTeX/BibLaTeX publication-detail and persistent-identifier
  handoff for `volume`, journal `number`/`issue`, `edition`, `series`,
  `seriesnumber`, `isbn`, `issn`, `eprint`, `archiveprefix`, and
  `eprintclass`.
- `BibtexCslParser` now maps those fields into CSL-compatible item variables:
  `volume`, `issue`, `edition`, `collection-title`, `collection-number`,
  `ISBN`, `ISSN`, `archive`, `archive-place`, and `archive_location`.
- `CitationCslProcessor` normalizes the same variables for direct CSL items,
  exposes them to CSL `<text>` and `<label>` rendering, and includes compact
  fallback bibliography text for WordPress review packets.
- Updated `wordpress-bibtex-csl-handoff.php` so imported `.bib` review packets
  keep journal volume/issue, book series/edition, ISBN/ISSN, and archive/eprint
  metadata visible without invoking Pandoc, citeproc, BibTeX, Biber, or
  bibliography managers.

## Source Truth

- This follows the lane's accepted native PHP BibTeX/BibLaTeX-to-CSL handoff
  model: parsed `.bib` fields are normalized into CSL-style variables before
  native citation and bibliography rendering.
- The slice is bounded to review metadata and fallback rendering. It does not
  attempt full citeproc disambiguation, localized edition ordinals, journal
  abbreviation lookup, eprint resolver policy, or online style/catalog parity.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 519 assertions, 0 failures`.
- Red check:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: failed with 1 focused failure because `BibtexCslParser` did not
    expose `volume` for the new publication-detail test.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 549 assertions, 0 failures`.
- PASS-line count:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php | rg -c '^PASS '`
  - Result: `26`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- New focused test case: `maps bounded biblatex publication details identifiers
  and eprint metadata`.
- Focused Citation/CSL assertions: `519 -> 549`.
- Lane PHP PASS count: `613 -> 614`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX parsing, TeX accent decoding,
crossref inheritance, xdata inheritance, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, title/subtitle/title-addon
metadata, CSL style XML/locales, sort keys, name options, macros, choose
conditionals, locator/page label rendering, citation-position conditionals,
PDF engine handoff, EPUB3, DOCX/ODT, table geometry, YAML, ZIP/OPC,
archive-compression, charset/Unicode, or XML/HTML5 DOM work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc/citeproc runner parity remains gated on hydrating
the pinned Pandoc checkout and building the Haskell test executables; that
dependency gate is unchanged by this bounded support-library slice.

## Follow-Up

- Keep `maintitle`/multi-volume title family handling, localized edition
  ordinal rendering, journal abbreviation lookup, richer BibLaTeX entry
  families, full CSL number rendering, disambiguation, note-style output, and
  full citeproc parity as separate bounded slices.
