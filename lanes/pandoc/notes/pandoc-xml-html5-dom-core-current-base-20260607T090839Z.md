# Pandoc XML/HTML5 DOM Core Current Base - Document Policy Metadata

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260607T090839Z`
- Base accepted HEAD: `45057471969b541c83b4a7de143f12f01b0ba6b9`

Implemented one bounded XML/HTML5 DOM support-library behavior cluster: HTML document policy metadata is converted into inert reviewer text before WordPress raw HTML handoff.

## Behavior

- `<meta http-equiv="Content-Security-Policy" content="...">` now becomes an inert reviewer `<span data-pandoc-meta-http-equiv="content-security-policy" data-pandoc-meta-content="...">...`.
- CSP metadata normalization collapses whitespace, strips `report-uri` and `report-to` reporting directives, and rejects directives containing control-separated script schemes or raw markup delimiters.
- `<meta name="referrer" content="...">` now becomes an inert reviewer span only for valid referrer policies.
- Invalid CSP/referrer metadata remains diagnostics-only and is not serialized into the WordPress handoff fragment.

This avoids overlap with the accepted XML/HTML5 DOM iframe-policy slice, passive link relation slice, select-label fallback slice, SVG data-image resource slice, and foreign-content CDATA slice.

## Evidence

- Baseline before the new assertion: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` covered `1 test files, 894 assertions, 0 failures`.
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` failed as expected with `1 test files, 895 assertions, 1 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 911 assertions, 0 failures`.
- Compatibility family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 1184 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for changed PHP files.
- `git diff --check -- lanes/pandoc` passed with no output.

## Dependency Closure

No new support component is needed. The slice reuses native PHP DOM parsing/traversal, `Html5DomFragment`, the existing WordPress HTML fragment handoff example, and the focused pandoc lane test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML parser, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Next

Keep XML/HTML5 DOM follow-up bounded to non-overlapping fragment parser/serializer behavior such as additional inert document metadata, MathML/SVG fragment boundaries, or source-position diagnostics with focused PHP tests.
