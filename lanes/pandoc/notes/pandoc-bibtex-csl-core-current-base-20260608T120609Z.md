# BibTeX/CSL Date Addendum Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T120609Z`
Base accepted HEAD: `5f425da1740b76fd38a51b6ce59a09edd9c388d7`

## Behavior

This slice maps bounded BibLaTeX date-addendum metadata into native CSL handoff records:

- `dateaddon` / `date-addon` / `dateaddendum` normalize to CSL `date-addon`.
- `origdateaddon` / `original-date-addon` normalize to `original-date-addon`.
- `eventdateaddon` normalizes to `event-date-addon`.
- `urldateaddon` / `url-date-addon` normalize to `accessed-date-addon`.
- Normalized items expose `dateAddon`, `originalDateAddon`, `eventDateAddon`, and `accessedDateAddon`.
- Default bibliography review output keeps the addenda visible.
- CSL text variables render the addendum slots for custom bibliography styles.
- The WordPress smoke confirms the metadata survives Markdown citation resolution and bibliography block rendering.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2437 assertions, 0 failures`.
- Intermediate red check: the new date-addendum test initially failed because default bibliography output renders the publication year instead of the full issued date in publisher/date details.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2458 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case and `+21` focused assertions in `CitationCslProcessorTest.php`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-date-addon-handoff.php --self-test` passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted BibTeX/CSL entry-subtype, call-number, pagination/bookpagination, article-number/eid, event-place list, gender, thesis-type, or Citation/CSL name/date-marker/date-time/date-season slices. The bounded behavior is specific to BibLaTeX date addenda and CSL text-variable exposure for the imported date qualifiers.
