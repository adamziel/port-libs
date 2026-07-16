# pandoc-bibtex-csl-core-current-base-20260608T224717Z

Slice: bounded BibTeX/CSL media entry-type alias handoff for `@audio` and `@artwork`.

Accepted base: `c992bb947324f7207d596c6abc6496ba6a35dd32`.

Behavior implemented:
- `BibtexCslParser` now maps BibLaTeX `@audio` entries to CSL `song`.
- `BibtexCslParser` now maps BibLaTeX `@artwork` entries to CSL `graphic`.
- `CitationCslProcessor` keeps the raw BibTeX entry type in `rawBibtex.type` while CSL type conditionals and WordPress bibliography handoff see the normalized media type.

Focused evidence:
- No `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3059 assertions, 0 failures`.
- Red-first: after adding the audio/artwork alias case before implementation, `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed with `1 test files, 3061 assertions, 1 failures` because `@audio` still produced raw type `audio`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3074 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-audio-artwork-type-handoff.php --self-test` passed.
- PHP lint passed for `lanes/pandoc/src/BibtexCslParser.php`, `lanes/pandoc/tests/CitationCslProcessorTest.php`, and `lanes/pandoc/examples/wordpress-bibtex-csl-audio-artwork-type-handoff.php`.

Status delta:
- Adds one focused PHP PASS case and 15 focused assertions in `CitationCslProcessorTest.php`.
- `lane-status.json` `phpPass` moves from `1941` to `1942`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2362` to `2363`.
- `mappedBibtexCslCoreCases` moves from `7` to `8`.
- `bibtexCslCoreAssertions` moves from `121` to `136`.

Non-overlap:
- This does not repeat the accepted media-type mapping for `@movie`, `@video`, `@music`, or `@image`.
- This does not repeat the accepted unpublished/eventtitle speech/manuscript handoff.
- This does not touch event-type subtype labels, event-place lists, article-number/eid, pagination/bookpagination, reviewed-title metadata, or creator-role tests.

Dependency closure:
- No new support component is needed. The slice reuses native `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and `WordPressBlockWriter`.
- No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

Next task:
- A safe follow-up would map another non-overlapping BibLaTeX-to-CSL handoff such as additional entry-type aliases, conference paper publication-state behavior, or creator-role metadata with focused CitationCslProcessor coverage.
