# XML/HTML5 DOM Shadow Template Slot Slice

Slice: `pandoc-xml-html5-dom-core-current-base-20260608T165102Z`

Accepted base: `63e2debc141738e27afa8820a6493fd1cbe7d79e`

## Behavior

- Added native `Html5DomFragment` handling for declarative shadow-root templates.
- Valid `template shadowrootmode="open|closed"` wrappers are still stripped, but the sanitizer now prepends inert reviewer metadata:
  - `data-pandoc-shadowroot-mode`
  - `data-pandoc-shadowroot-delegatesfocus`
  - `data-pandoc-shadowroot-clonable`
  - `data-pandoc-shadowroot-serializable`
- Invalid `shadowrootmode` values stay diagnostics-only and do not produce shadow metadata.
- HTML `slot` elements are converted to inert `span` fallback containers with `data-pandoc-slot-fallback` and bounded `data-pandoc-slot-name` metadata.
- Source-owned `data-pandoc-*`, invalid slot names, active descendants, and unsafe URLs remain stripped before WordPress raw HTML handoff.

## Evidence

- Baseline focused check before patch: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1322 assertions, 0 failures`
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `1 test files, 1365 assertions, 0 failures`
  - Delta: `+43` focused assertions and `+1` PHP PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - `html5 dom fragment handoff self-test ok`

## Dependency Closure

No new native PHP support component is needed. This slice reuses `Html5DomFragment`, AST raw HTML handoff, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat earlier XML/HTML5 DOM work for unsafe XML declarations, raw text/RCDATA/plaintext, SVG/MathML foreign casing, CDATA, URL/srcset/data-image filtering, base URL/target metadata, iframe srcdoc/source/policy metadata, form/select labels, noscript/template fallback unwrapping, table foster parenting, meta/link metadata, image maps, hidden/inert/details/dialog/popover, microdata/RDFa, time/revision/language metadata, media tracks, ruby annotations, source-line diagnostics, or reserved `data-pandoc-*` filtering.

Next useful XML/HTML5 DOM follow-up: ARIA role/state review metadata, custom-element import provenance, or bounded declarative-shadow accessibility metadata.
