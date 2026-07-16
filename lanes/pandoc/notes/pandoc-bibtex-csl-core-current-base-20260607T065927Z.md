# Pandoc BibTeX/CSL Current-Base Original-Language List Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260607T065927Z`
Base accepted HEAD: `d2d9ea88993bebb96d341c8a9132df3b4b90a3ff`

## Behavior

- Added bounded BibLaTeX `origlanguage` literal-list handoff.
- `BibtexCslParser` now splits `origlanguage = {spanish and basque and catalan}` with the same literal-list path used for publisher/place metadata.
- Parsed CSL items retain:
  - scalar `original-language` display text: `spanish; basque; catalan`
  - structured `original-language-list`: `["spanish", "basque", "catalan"]`
  - raw BibLaTeX field text for review provenance.
- `CitationCslProcessor` normalizes direct CSL-like items that provide only `original-language-list`, derives scalar display text, and exposes both `original-language` and `original-language-list` variables to bounded CSL styles.
- The WordPress BibTeX/CSL handoff example now includes a multilingual original-language source and self-test assertions for the structured list plus bibliography rendering.

## Source Truth And Non-Overlap

- Source truth: BibLaTeX datamodel-style `origlanguage` is treated as an original-work language field; in BibLaTeX syntax it can be a literal list separated by `and`.
- This is additive and avoids the recent accepted BibTeX/CSL clusters for entry subtype, library call-number, related/xref records, identifiers/eprint/pubmed/media/report IDs, pagination/bookpagination labels, article-number/eid metadata, event-place lists, institution short parts, custom/user/verbatim fields, reprint/review metadata, and original-title addendum handoff.
- No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 1887 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 1902 assertions, 0 failures`
  - Delta: `+1` PHP PASS case / `+15` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - `wordpress-bibtex-csl-handoff self-test passed`
- Syntax checks passed for:
  - `lanes/pandoc/src/BibtexCslParser.php`
  - `lanes/pandoc/src/CitationCslProcessor.php`
  - `lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`
- Lane whitespace check: `git diff --check -- lanes/pandoc`
  - passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP BibTeX parsing, literal-list splitting, CSL item normalization/rendering, WordPress block bibliography output, and focused PHP tests.

Remaining excluded work: full upstream Pandoc/Haskell runner parity, external citeproc parity, BibTeX/Biber execution, external bibliography-manager validation, locale-language canonicalization, and broader BibLaTeX datamodel alias coverage.

## Follow-Up

Next bounded BibTeX/CSL work should target non-overlapping URL description labels, additional safe BibLaTeX datamodel aliases, name-list annotations, or CSL variable handoff gaps with focused PHP tests.
