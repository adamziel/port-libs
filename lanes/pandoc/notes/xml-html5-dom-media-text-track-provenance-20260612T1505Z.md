# XML/HTML5 DOM Media Text Track Provenance

Bead: `plib-h6f5r`
Date: 2026-06-12 UTC
Area: Pandoc XML/HTML5 DOM primitives
Base: `aed67bb9`

## Behavior

`XmlHtmlDom` media summaries now preserve bounded text-track reviewer
provenance for `audio` and `video` fragments:

- raw and normalized `kind` values, including invalid-kind fallback to
  `subtitles`;
- raw and canonicalized `srclang` values with invalid-language diagnostics;
- language-required and language-missing state for subtitle/caption tracks;
- aggregate track-kind/language counts and default-track conflict summaries;
- deterministic raw HTML and WordPress raw-block propagation.

The existing compact `tracks` list remains intact for existing consumers. The
new detailed metadata is exposed through `textTracks`, `textTrackIssues`, and
aggregate text-track counters.

No Pandoc, browser renderer, online sanitizer, external validator, online
service, live provider test, or live-service provider test was invoked.

## Accounting

- `phpPass`: `3229 -> 3230`
- `phpFail`: `0`
- mapped denominator: `3249 -> 3250`
- `mappedXmlHtmlDomMediaTextTrackProvenanceCases`: `+1`
- `xmlHtmlDomMediaTextTrackProvenanceAssertions`: `+17`

## Verification

- Red-first `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  failed on missing `textTracks` metadata.
- Focused `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `1 test files, 1509 assertions, 0 failures`.
- Full `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 71847 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for generic audio/video
source summaries, iframe `srcdoc`, ARIA references, details/dialog/popover,
form ownership, image maps, active-content provenance, or sanitizer URL
filtering. It owns only the DOM reviewer provenance for media timed-text track
metadata.
