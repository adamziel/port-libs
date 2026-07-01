# XML/HTML5 DOM Focus Navigation Review

Slice: `plib-5hhdb`
Date: 2026-06-30

## Implementation

- `XmlHtmlDom` now emits aggregate `focusNavigationReviewPolicy`,
  `focusNavigationAttributes`, `focusNavigationIssueCodes`, and
  `focusNavigationValid` metadata whenever `accesskey`, `autofocus`, or
  `tabindex` appears in an imported HTML fragment.
- Access-key summaries now include `accessKeyIssueCodes` for empty token lists,
  invalid shortcut tokens, duplicate shortcut tokens, and document-level
  shortcut conflicts.
- `tabindex` summaries now include `tabIndexIssueCodes` for invalid integer
  payloads.
- The existing document-order autofocus candidate summary is surfaced through
  the aggregate issue list, including multiple candidates and candidates
  suppressed by an earlier autofocus element.

## Evidence

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFocusNavigationReviewTest.php`
  failed with `1 test files, 2 assertions, 1 failures` because
  `focusNavigationReviewPolicy` was absent.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFocusNavigationReviewTest.php`
  passed with `1 test files, 59 assertions, 0 failures`.
- XML/HTML DOM focused family:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  passed with `35 test files, 7520 assertions, 0 failures`.
- Syntax and status checks passed:
  `php -l lanes/pandoc/src/XmlHtmlDom.php`,
  `php -l lanes/pandoc/tests/XmlHtmlDomFocusNavigationReviewTest.php`, and
  lane-status JSON parse.

## Mapping Delta

- `phpPass`: `469 -> 470`.
- Added one focused XML/HTML DOM review case with 59 assertions.
- Direct-format parity accounting remains active in `lane-status.json`; this is
  metadata-only XML/HTML DOM handoff work and does not add a new direct input or
  output format.

## Dependency Closure

No new support component is needed. This reuses native PHP `DOMDocument`,
`XmlHtmlDom`, `AstNode`, `WordPressBlockWriter`, and the lane-local PHP test
harness.

No Pandoc, Cabal/Haskell runner, office suite, TeX engine, browser engine,
Node tooling, `zip`/`unzip`, external XML/HTML validator, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat the accepted `Html5DomFragment` sanitizer conversion
of live focus attributes into inert `data-pandoc-*` attributes, source-line
diagnostics for global metadata, edit-assistance attributes, input hint
attributes, language/direction inheritance, ARIA references, hyperlink
attributionsrc, meta referrer/CSP policy summaries, link fetch policy, media
resource policy, template/noscript boundaries, table repair, iframe policy,
object/param review, popover/dialog/details state, custom-element parts, or
semantic microdata/RDFa metadata.

It owns only the native PHP `XmlHtmlDom` summary contract that lets importer
handoff review focus-navigation hazards without deriving them from several
separate arrays or executing browser focus behavior.
