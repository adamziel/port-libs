# BibTeX/CSL Date-Time Part Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260606T181924Z`
Base accepted HEAD: `3dbd03ad2606ba7aa558ebd5c4e8b990b6b82f2a`

## Source Truth

- The official BibLaTeX manual documents split date part fields for `date`, `origdate`, `eventdate`, and `urldate`, including companion hour/minute/second/timezone families. Source: https://mirrors.ibiblio.org/CTAN/macros/latex/contrib/biblatex/doc/biblatex.pdf
- No local upstream Pandoc checkout was available for this worker, so the slice used the accepted Pandoc lane manifest/status plus bounded native PHP tests as source truth.

## Behavior

- `BibtexCslParser` now preserves bounded date-time part metadata:
  - `hour`, `minute`, `second`, `timezone`, `endhour`, `endminute`, `endsecond`, `endtimezone` for issued dates.
  - `orighour`, `origminute`, `origsecond`, `origtimezone`, `origendhour`, `origendminute`, `origendsecond`, `origendtimezone` for original dates.
  - `eventhour`, `eventminute`, `eventsecond`, `eventtimezone`, `eventendhour`, `eventendminute`, `eventendsecond`, `eventendtimezone` for event dates.
  - `urlhour`, `urlminute`, `urlsecond`, `urltimezone`, `urlendhour`, `urlendminute`, `urlendsecond`, `urlendtimezone` for accessed dates.
- `CitationCslProcessor` normalizes these into review metadata (`time`, `endTime`), renders `Date times: ...` summaries in default bibliography output, and exposes bounded CSL variables such as `issued-time`, `accessed-time`, `event-time`, `event-end-time`, `original-time`, and `date-time-summary`.
- The WordPress BibTeX/CSL example now includes a timestamped source smoke that verifies imported time metadata and rendered bibliography output.

## Red-First Evidence

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result before implementation: `1 test files, 1764 assertions, 1 failures`
- Failing assertion: the new date-time case expected `issued.time` to be `09:15:30+02:00`, but it was `NULL`.

## Final Verification

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 1787 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`
  - `wordpress-bibtex-csl-handoff self-test passed`
- PHP lint for changed PHP files passed.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, the existing WordPress BibTeX/CSL handoff example, and the focused lane test harness.

Excluded by design: Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, external bibliography managers, online services, live provider tests, timezone arithmetic, and full localized date-time formatting.

## Non-Overlap

This slice does not repeat accepted BibTeX/CSL entry-subtype, library call-number, pagination/bookpagination, article-number/eid, event-place list, uncertain-date conditional, institution short-parts, or custom-field handoff work. It owns only bounded BibLaTeX date-time part preservation and CSL review rendering.
