# pandoc-xml-html5-dom-core-current-base-20260609T052746Z

## Slice

Implemented bounded HTML5 media resource policy handoff for `audio` and `video`
fragments. Live playback/resource attributes are converted into inert
`data-pandoc-media-*` reviewer metadata before WordPress raw HTML block handoff:
`autoplay`, `controls`, `loop`, `muted`, `playsinline`, `preload`,
`crossorigin`, `controlslist`, `width`, `height`,
`disablepictureinpicture`, and `disableremoteplayback`.

Safe `src`, `poster`, and child `source` URLs still use the existing URL policy.
Invalid media policy values are dropped with source-line diagnostics instead of
being serialized as live browser controls.

## Source Truth

This is native support-library behavior for Pandoc's HTML reader boundary:
HTML media elements are preserved as reviewable fragment content, while active
browser playback controls and fetch-policy hints are not emitted as live source
attributes in WordPress handoff HTML.

No Pandoc, Cabal/Haskell test runner, browser renderer, office tool, archive
tool, TeX/PDF engine, external converter, validator, online service, or live
provider test was run.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 2040 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed with `1 test files, 2114 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for changed PHP files:
  `lanes/pandoc/src/Html5DomFragment.php`,
  `lanes/pandoc/tests/Html5DomFragmentTest.php`, and
  `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- `lanes/pandoc/lane-status.json` decoded with `JSON_THROW_ON_ERROR`.

## Delta

- Focused PHP PASS delta: `+1` test case.
- Focused assertion delta: `+74` assertions.
- `lane-status.json` `phpPass`: `2375 -> 2376`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`Html5DomFragment` parser/sanitizer, URL policy, diagnostic source-line
metadata, and `WordPressBlockWriter` HTML block handoff.

## Non-Overlap

Avoided accepted XML/HTML5 DOM clusters for DTD/entity rejection, RCDATA/raw
text/plaintext, SVG/MathML foreign-content casing, HTML integration points,
CDATA, URL/srcset filtering, image data fallback, image resource policy
metadata, base URL resolution, passive metadata/link handling, image maps,
forms, fieldsets, datalists, editing/focus/ARIA/revision/time/value/output,
ruby, microdata/RDFa, orphan table repair, picture/source handling, portal
sources, and media track caption metadata.

## Follow-Up

Possible next XML/HTML5 DOM work should stay non-overlapping, for example
bounded embedded-document fallback semantics or parser-level consumption of
these normalized fragments by DOCX/EPUB/ODT readers.
