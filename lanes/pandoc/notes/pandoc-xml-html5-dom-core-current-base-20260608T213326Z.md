# XML/HTML5 DOM Shadow Accessibility Slice

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T213326Z`

Base accepted HEAD: `17b111d85a0bb4b5cb849a471da21f0b1ab9bf09`

## Behavior

- Added native `Html5DomFragment` handling for declarative shadow-root accessibility metadata.
- Valid `template shadowrootmode="open|closed"` wrappers are still stripped, but generated shadow-root review markers now preserve bounded:
  - `aria-label` as `data-pandoc-shadowroot-aria-label`
  - `aria-description` as `data-pandoc-shadowroot-aria-description`
  - `aria-describedby` as `data-pandoc-shadowroot-aria-describedby`
  - `aria-labelledby` as `data-pandoc-shadowroot-aria-labelledby`
- Malformed label text and unsafe IDREF tokens remain diagnostic-only and do not produce generated metadata.
- Source-owned `data-pandoc-*`, live `aria-*` attributes, `template` wrappers, active descendants, and unsafe URLs remain stripped before WordPress raw HTML handoff.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` notes existed before work began.
- Accepted focused baseline from current lane evidence before this patch:
  - `1 test files, 1480 assertions, 0 failures`
- Red-first check after adding the focused test and before implementation: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1481 assertions, 1 failures`
  - Failure: generated shadow-root marker only contained `data-pandoc-shadowroot-mode`; expected `data-pandoc-shadowroot-aria-*` metadata was absent.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1512 assertions, 0 failures`
  - Delta: `+32` focused assertions and `+1` PHP PASS case over the accepted focused baseline.
- Example smoke: `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test`
  - `xml/html5 dom handoff self-test ok`

## Dependency Closure

No new native PHP support component is needed. This slice reuses `Html5DomFragment`, bounded ARIA metadata normalization helpers, AST raw HTML handoff, `WordPressBlockWriter`, and the existing XML/HTML5 DOM handoff example.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat earlier XML/HTML5 DOM work for unsafe XML declarations, raw text/RCDATA/plaintext, SVG/MathML foreign casing, CDATA, URL/srcset/data-image filtering, base URL/target metadata, iframe srcdoc/source/policy metadata, form/select labels, noscript/template fallback unwrapping, table foster parenting, meta/link metadata, image maps, hidden/inert/details/dialog/popover, microdata/RDFa, time/revision/language metadata, media tracks, ruby annotations, source-line diagnostics, ARIA role/state metadata on normal elements, custom-element provenance, declarative shadow-root mode/flag metadata, slot fallback metadata, quote citation metadata, figure metadata, or reserved `data-pandoc-*` filtering.

Next useful XML/HTML5 DOM follow-up: bounded portal/source-set policy, richer table insertion-mode recovery, or additional accessibility review metadata not already covered by ARIA role/state, declarative shadow-root accessibility, custom elements, forms, iframe policy, media tracks, ruby, figure, and document metadata.
