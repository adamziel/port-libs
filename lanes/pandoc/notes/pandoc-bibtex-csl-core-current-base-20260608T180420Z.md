# Pandoc BibTeX/CSL Index Title Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T180420Z`
Base accepted HEAD: `1d10c26783e331f072073a9dc0eef297e722aedb`

## Behavior

This slice maps bounded BibLaTeX `indextitle` and `indexsorttitle` metadata into the native CSL handoff path used by WordPress bibliography review output.

- `BibtexCslParser` emits `index-title` and effective `index-sort-title` values.
- If `indexsorttitle` is absent but `indextitle` is present, the effective `index-sort-title` falls back to `indextitle`.
- Crossref child entries inherit parent index metadata through the existing bounded crossref inheritance path.
- `CitationCslProcessor` normalizes `indexTitle` and `indexSortTitle`, renders default bibliography review parts, and exposes CSL text variables `indextitle`, `indexsorttitle`, `index-title`, and `index-sort-title`.
- The WordPress smoke keeps generated source-index labels visible in the rendered bibliography.

## Source Truth

The CTAN BibLaTeX manual documents `indextitle` and `indexsorttitle` fields and includes them in the default cross-reference inheritance set. No external Pandoc, citeproc, BibTeX, Biber, Haskell, Cabal, bibliography-manager, online-service, live-provider, or live-service command was executed.

## Evidence

- Red-first focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed as expected with `1 test files, 2607 assertions, 1 failures` because index-title metadata was absent.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2629 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-index-title-handoff.php --self-test` passed.
- Syntax checks passed for `BibtexCslParser.php`, `CitationCslProcessor.php`, `CitationCslProcessorTest.php`, and `wordpress-bibtex-csl-index-title-handoff.php`.
- JSON validation passed for `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`.
- `git diff --check -- lanes/pandoc` passed.

Status delta: one new focused PHP PASS case, `+36` focused BibTeX/CSL assertions, mapped denominator `2133 -> 2134`, `mappedBibtexCslCoreCases` `7 -> 8`, and `bibtexCslCoreAssertions` `121 -> 157`.

Blocker: none for this bounded handoff.

## Dependency Closure

No new support component is needed. This reuses the existing native `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` support paths.

## Non-Overlap

This does not repeat entry subtype, call-number, pagination/bookpagination, article-number/eid, event-place list, refsection/refsegment, language options, keyword lists, related-entry metadata, or label-field slices. It covers only BibLaTeX `indextitle` and `indexsorttitle` handoff behavior.

## Follow-Up

Next non-overlapping BibTeX/CSL gaps include sort-shorthand/list-shorthand provenance, additional date/name metadata, or another unhandled CSL style variable.
