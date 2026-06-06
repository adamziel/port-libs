## Pandoc Citation CSL Core Current Base 2026-06-06T16:40:29Z

Slice: `pandoc-citation-csl-core-current-base-20260606T164029Z`
Base accepted HEAD: `da1c4a038100b401f46ae08ebc3fd84568db2381`

### Behavior

- Added bounded CSL institution rendering for `institution-parts="short"`, `long-short`, and `short-long`.
- Added bounded `<institution-part name="short">` parsing and formatting.
- Preserved the short institutional literal from CSL name records through native item normalization using `short`, `short-form`, `literal-short`, or `shortLiteral`.
- Missing short institutional literals fall back to the long literal for short-only rendering and are omitted from combined long/short output to avoid duplicate bibliography labels.

### Source Truth And Non-Overlap

This is a bounded Citation/CSL support-library slice. It extends the existing native CSL name/institution handoff only; it does not overlap the accepted nested bibliography display-part slice, date predicates, subsequent-author substitution, et-al use-last, BibTeX/BibLaTeX metadata slices, or charset/Unicode slices.

No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

### Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed before implementation with `CSL citation institution-parts currently supports long`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1763 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-citation-csl-institution-handoff.php --self-test` passed with `wordpress-citation-csl-institution-handoff self-test passed`.
- PHP lint: `php -l` passed for `CitationCslProcessor.php`, `CslStyle.php`, `CitationCslProcessorTest.php`, and `wordpress-citation-csl-institution-handoff.php`.
- Whitespace: `git diff --check -- lanes/pandoc` passed with no output.

### Dependency Closure

No new support component is needed. The slice reuses the native PHP CSL style parser, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and the focused lane PHP harness. Full citeproc parity, locale-dependent institutional abbreviation lookup, external bibliography manager integration, and upstream Pandoc/Haskell runner parity remain separate bounded follow-up work.
