# Pandoc XML/HTML5 DOM Core Current Base - Control Base Href

Slice: `pandoc-xml-html5-dom-core-current-base-20260607T072341Z`
Base accepted HEAD: `f59b519bb251aefa4fdb1c3cda61b4eaa10eaee0`

## Behavior Added

- `Html5DomFragment` now normalizes control-separated `<base href>` metadata before trust checks and base URL resolution.
- Control-separated safe schemes such as `h&#9;ttps://...` now resolve reviewer links, image `src`, and `srcset` candidates from the canonical HTTPS base.
- Control-separated unsafe schemes such as `java&#10;script:...` are rejected after normalization and the fragment falls back to the caller-supplied document base.
- The WordPress HTML5 DOM handoff example now self-tests the normalized safe base and unsafe base fallback paths.

## Source Truth And Non-Overlap

- Source truth is the lane-local XML/HTML5 DOM support contract under `lanes/pandoc/src/Html5DomFragment.php` and the existing WordPress raw-HTML handoff example.
- This does not overlap recent XML/HTML5 DOM slices for foreign-content CDATA, SVG data-image resources, select/option label fallback, passive link relation review anchors, iframe policy metadata, JSON visible constraints, or accepted DOCX/BibTeX/current-base work.
- No Pandoc, Cabal, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before implementation.
- Baseline focused test before adding the new case: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 874 assertions, 0 failures`.
- Red-first check after adding the new focused case failed before implementation: `Html5DomFragmentTest.php` had `1 test files, 875 assertions, 1 failures`, with the control-separated HTTPS base treated as a caller-base-relative path.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 894 assertions, 0 failures`.
- DOM family check: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 1167 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test` passed with `wordpress-html5-dom-handoff self-test passed`.
- Focused delta: `+1` PHP PASS case and `+20` focused assertions.
- Status delta: `lane-status.json` `phpPass` moves `1465 -> 1466`; `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves `1883 -> 1884`; `xmlHtmlDomCoreCases` moves `6 -> 7`; `xmlHtmlDomCoreAssertions` moves `89 -> 109`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP DOM/libxml parsing, existing `Html5DomFragment` URL normalization/base resolution helpers, `AstNode`, `WordPressBlockWriter`, and focused PHP tests.

## Follow-Up

Keep XML/HTML5 DOM follow-up bounded to non-overlapping parser/sanitizer edges such as CSP/referrer metadata review, inert microdata/RDFa metadata handoff, URL percent-decoding policy, or XHTML fragment handoff. Full upstream Pandoc/Haskell runner parity, browser HTML5 tree-builder parity, external sanitizer validation, CSP/referrer policy modeling, and recursive XHTML-to-AST conversion remain out of scope for this micro-slice.
