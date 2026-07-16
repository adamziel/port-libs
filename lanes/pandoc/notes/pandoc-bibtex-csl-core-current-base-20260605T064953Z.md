# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T064953Z`
Base: `346ee12a1740e2d5877ab0e26f367cddd50eae7b`

## Behavior

- Added bounded BibLaTeX name metadata handoff for `nameaddon`,
  `author+an`, and `editor+an`.
- `BibtexCslParser` accepts BibLaTeX `+an` field names, maps `nameaddon` to
  CSL-style `name-addon`, and attaches one-based name annotations to parsed
  author/editor name objects.
- `CitationCslProcessor` normalizes direct and parsed CSL items with
  `nameAddon` and validated name `annotations`, exposes bounded CSL text
  variables `name-addon` and `name-annotation-summary`, and emits compact
  fallback bibliography text for WordPress review queues.
- Updated `wordpress-bibtex-csl-handoff.php` so imported `.bib` review packets
  keep reviewer name annotation metadata visible without invoking Pandoc,
  citeproc, BibTeX, Biber, or bibliography managers.

## Source Truth

- This follows BibLaTeX's `nameaddon` and name annotation field convention and
  the lane-local BibTeX/BibLaTeX-to-CSL handoff model: source bibliography
  metadata is preserved in native PHP item structures before local citation and
  bibliography rendering.
- The slice is bounded to metadata preservation, validation, CSL variable
  exposure, and WordPress review output. It does not attempt full BibLaTeX
  annotation semantics, localized annotation vocabularies, disambiguation,
  note-style citation rendering, or full citeproc parity.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 718 assertions, 0 failures`.
- Red check:
  - Same command after adding the new focused case.
  - Result: `1 test files, 718 assertions, 1 failures`.
  - Failure: parsed BibTeX rejected `author+an` with
    `Expected BibTeX token = at byte 79`.
- Focused green:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 738 assertions, 0 failures`.
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
  `maps bounded biblatex name annotations and name addendum metadata`.
- Focused Citation/CSL assertions: `718 -> 738` (+20).
- Lane PHP PASS count: `721 -> 722`.
- Manifest mapped count: `1181 -> 1182`.
- BibTeX/CSL sub-counter: `2 cases / 38 assertions -> 3 cases / 58 assertions`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, initial BibTeX/BibLaTeX parsing, TeX accent decoding,
crossref inheritance, xdata inheritance, source-file attachment policy,
entry-set/related metadata, translation/original-publication metadata,
legal/patent metadata, date-range metadata, title/subtitle/title-addon
metadata, publication detail and identifier metadata, main-title/multi-volume
metadata, note/addendum/howpublished metadata, secondary editor role metadata,
CSL style XML/locales, sort keys, name options, macros, choose conditionals,
locator/page label rendering, citation-position conditionals, PDF engine
handoff, EPUB3, DOCX/ODT, table geometry, YAML, ZIP/OPC, archive compression,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or upstream-runner
dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader, and WordPress block
writer. Full upstream Pandoc/citeproc runner parity remains gated on hydrating
the pinned Pandoc checkout and building the Haskell test executables; that
dependency gate is unchanged by this bounded support-library slice.

## Follow-Up

Keep full BibLaTeX name-annotation semantics, localized annotation
vocabularies, citation disambiguation, note-style citation output, broader CSL
style catalogs, and full citeproc parity as separate bounded slices.
