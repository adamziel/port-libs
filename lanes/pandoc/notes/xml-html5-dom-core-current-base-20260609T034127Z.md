# XML/HTML5 DOM Image Resource Policy Handoff

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T034127Z`
Base: `6de1d5b33718b9d2dccdce7e31246dedd9031bb9`

## Scope

Added bounded native PHP handling for HTML `<img>` resource policy hints before WordPress raw HTML handoff:

- `loading`: preserves valid `lazy` and `eager` states as `data-pandoc-image-loading`.
- `decoding`: preserves valid `async`, `sync`, and `auto` states as `data-pandoc-image-decoding`.
- `fetchpriority`: preserves valid `high`, `low`, and `auto` states as `data-pandoc-image-fetchpriority`.
- `crossorigin`: preserves valid `anonymous` and `use-credentials` states as `data-pandoc-image-crossorigin`; empty `crossorigin` normalizes to `anonymous`.
- Invalid states and source-owned `data-pandoc-image-*` spoofing are dropped with sanitizer diagnostics.

This does not preserve live browser loading, decoding, priority, or CORS behavior. It converts the source intent into inert reviewer metadata for imported HTML packets.

## Non-Overlap

This slice avoids prior XML/HTML5 DOM coverage for referrer-policy metadata, responsive image `media`/`sizes`, `srcset` candidate filtering, data-image admission, image-map metadata, figure captions, portal/source/iframe policy handoff, and reserved `data-pandoc-*` filtering. It adds a distinct image resource-policy metadata cluster.

## Evidence

- Before editing: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1861 assertions, 0 failures`.
- After editing: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1895 assertions, 0 failures`.
- Adjacent DOM family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 2263 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.

Delta: +1 PHP PASS case and +34 focused assertions. Manifest mapped denominator moved `2648 -> 2649`; XML/HTML5 DOM mapped cases moved `8 -> 9`.

## Dependency Closure

No new support dependency is needed. The slice reuses `Html5DomFragment`, `WordPressBlockWriter`, and the existing HTML5 DOM WordPress handoff example. Full upstream Pandoc HTML reader parity remains a separate upstream-runner task requiring a hydrated pinned Pandoc checkout and Haskell test executables; no Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, office tool, zip/unzip, or external converter was executed.

## Next

Choose a non-overlapping XML/HTML5 DOM follow-up such as richer HTML reader metadata AST projection, malformed active-URL repair diagnostics, or source-position handoff for image/resource metadata.
