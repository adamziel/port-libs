# XML/HTML5 DOM Track Element Review

Bead: `plib-71u2y`
Date: 2026-07-01 UTC
Area: Pandoc XML/HTML5 DOM primitives

## Behavior

`XmlHtmlDom` now exposes per-element text-track review metadata on summarized
HTML `<track>` nodes, matching the kind/language diagnostics already available
through parent `audio`/`video` media aggregates:

- raw and normalized `kind` metadata, including invalid-kind fallback;
- raw and canonicalized `srclang` metadata with invalid-language diagnostics;
- subtitle/caption language-required and language-missing state;
- deterministic per-track issue records, issue codes, counts, validity, and
  media-local sibling index provenance;
- raw HTML and WordPress raw-block handoff preservation.

The existing parent media `tracks` and `textTracks` summaries remain intact.

No Pandoc, browser renderer, online sanitizer, external validator, online
service, live provider test, office suite, TeX engine, Node tool, zip/unzip, or
citeproc/BibTeX/Biber process was invoked.

## Accounting

- focused test file: `XmlHtmlDomTrackElementReviewTest.php`
- focused assertions: `+36`
- mapped XML/HTML5 DOM track-element review case: `+1`

## Verification

- Red-first `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTrackElementReviewTest.php`
  failed on missing `textTrackReviewPolicy` with `1 test files, 2 assertions,
  1 failures`.
- Focused `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTrackElementReviewTest.php`
  passed with `1 test files, 36 assertions, 0 failures`.
- Related `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed
  with `1 test files, 6322 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM media text-track aggregate,
resource URL, iframe, active content, image/media loading, form, table,
template, shadow DOM, or sanitizer-policy work. It owns only per-element
review metadata for summarized HTML `<track>` nodes.
