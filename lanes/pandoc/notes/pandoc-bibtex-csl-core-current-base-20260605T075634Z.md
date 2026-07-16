# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T075634Z`
Base: `d4e42303eb576f3f2f69bc77e111fb940d5203a7`

## Behavior

- Added bounded BibLaTeX event metadata handoff for `eventtitle`,
  `eventtitleaddon`, `eventtype`, `venue`, and inherited `eventdate`.
- `BibtexCslParser` maps event title/addendum/type/place fields into the
  native CSL item handoff while preserving existing crossref inheritance.
- `CitationCslProcessor` normalizes event metadata, renders bounded CSL
  variables (`event`, `event-title`, `event-title-addon`, `event-type`,
  `event-place`, and `event-date`), and includes compact fallback bibliography
  parts for event review metadata.
- Updated `wordpress-bibtex-csl-handoff.php` so WordPress bibliography review
  packets keep conference/event titles, venues, event types, and event dates
  visible without invoking Pandoc, citeproc, BibTeX, Biber, or bibliography
  managers.

## Source Truth

This follows BibLaTeX event-oriented fields used by conference and proceedings
entries and the lane-local BibTeX/BibLaTeX-to-CSL handoff contract: source
bibliography metadata is preserved in native PHP item structures before local
citation, bibliography, and WordPress block rendering.

The slice is bounded to metadata preservation, CSL variable exposure, crossref
inheritance, and WordPress review output. It does not attempt full citeproc
parity, localized event terms, rich proceedings styles, citation
disambiguation, note-style citations, or external bibliography manager output.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 798 assertions, 0 failures`.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 829 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-handoff self-test passed`.
- Syntax:
  - `php -l lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`
  - Result: no syntax errors detected.
- JSON validation:
  - `lanes/pandoc/lane-status.json valid`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json valid`
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: passed.
- Root harness: not run - isolated micro-slice.

## Mapping Delta

- New focused case:
  `maps bounded biblatex event metadata into csl handoff`.
- Focused Citation/CSL assertions: `798 -> 829` (+31).
- Lane PHP PASS count: `759 -> 760`.
- Manifest mapped count: `1218 -> 1219`.
- BibTeX/CSL sub-counter: `2 cases / 38 assertions -> 3 cases / 69 assertions`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX parsing, TeX accent decoding,
crossref/xdata inheritance generally, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, title/subtitle/title-addon
metadata, publication detail and identifier metadata, main-title/multi-volume
metadata, note/addendum/howpublished metadata, secondary editor role metadata,
name-addon/name-annotation metadata, software/dataset version/pubstate
metadata, CSL style XML/locales, sort keys, name options, macros, choose
conditionals, locator/page label rendering, citation-position conditionals,
PDF engine handoff, EPUB3, DOCX/ODT, table geometry, YAML, ZIP/OPC, archive
compression, charset/Unicode, XML/HTML5 DOM, syntax highlighting, or
upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc/citeproc runner parity remains gated on hydrating
the pinned Pandoc checkout and building the Haskell test executables; that
dependency gate is unchanged by this bounded support-library slice.

## Follow-Up

Keep localized event terms, richer proceedings/conference style behavior,
citation disambiguation, note-style citation output, broader CSL style
catalogs, and full citeproc parity as separate bounded slices.
