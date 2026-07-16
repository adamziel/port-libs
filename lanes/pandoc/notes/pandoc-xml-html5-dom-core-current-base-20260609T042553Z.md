# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T042553Z`

Base accepted HEAD: `11fc57ec36d6cc974a7a65f55020cfb9f1af6d59`

## Behavior Added

- Added source-line provenance to `Html5DomFragment` review-state diagnostics:
  - `hidden-content-review`
  - `inert-content-review`
  - `closed-details-review`
  - `dialog-review`
- The sanitized review HTML remains unchanged. This only makes the existing
  diagnostics auditable back to the HTML source line for WordPress review
  packets.
- The WordPress HTML5 DOM fragment smoke now asserts disclosure and hidden-state
  diagnostics carry line metadata.

## Source Truth

- Pandoc HTML reader handoff depends on deterministic source-to-AST review
  decisions. The native XML/HTML5 DOM support layer already preserved line
  metadata for unsafe URLs, blocked tags, image policy metadata, and table
  foster-parenting diagnostics. Review-state diagnostics for hidden/inert
  content, closed disclosures, and dialogs should follow the same provenance
  contract so importer review queues can trace why visible metadata was added.
- Red-first probe on this base showed the diagnostics existed but lacked `line`:
  - `hidden-content-review:{"code":"hidden-content-review","tag":"section","attribute":"hidden","reason":"hidden-content-preserved"}`
  - `closed-details-review:{"code":"closed-details-review","tag":"details","reason":"collapsed-content-preserved"}`
  - `dialog-review:{"code":"dialog-review","tag":"dialog","replacement":"div","state":"open","reason":"dialog-content-preserved","attribute":"open"}`

This is a bounded diagnostics provenance slice. It is not a new sanitizer
policy, full HTML5 tree-builder parity, browser sanitizer parity,
XHTML-to-AST conversion, CSS/media resource handling, or upstream Pandoc runner
parity.

## Evidence

- No current `port-pandoc` rework note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Red-first probe showed missing source lines for hidden, closed details, and
  dialog diagnostics before the patch.
- Focused verification:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `1 test files, 1952 assertions, 0 failures`.
- XML/HTML DOM family verification:
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: `3 test files, 2320 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
  - Result: `html5 dom fragment handoff self-test ok`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2298 -> 2299`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2698 -> 2699`.
- XML/HTML5 DOM core mapped cases: `8 -> 9`.
- XML/HTML5 DOM core focused assertions: `124 -> 129`.
- Focused assertion delta: `+5` assertions in
  `Html5DomFragmentTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing,
`Html5DomFragment` sanitizer diagnostics, `AstNode`,
`WordPressBlockWriter`, the existing focused PHP test runner, and the
lane-local WordPress HTML5 DOM fragment smoke.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer,
online sanitizer, Word, LibreOffice, zip/unzip, external converter, online
service, live provider test, or live-service provider test was executed.

Full upstream Pandoc HTML-reader runner parity remains a separate
upstream-runner dependency task requiring hydrated pinned upstream sources and
Haskell test executables.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, HTML fragment or
complete-document declaration preflight, raw text `script`/`style`
serialization, RCDATA handling, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, URL/srcset
filtering, base URL resolution, image resource-policy source-line diagnostics,
URL repair source-line diagnostics, form/embed/noscript/template/canvas
unwrapping, table foster-parenting, charset/Unicode width handling,
Markdown/HTML reader AST coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB
readers, archive compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table
geometry, syntax highlighting, or legacy DOC/CFB work.

It owns only source-line provenance for existing HTML review-state diagnostics.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS/media
resource handling, EPUB/XHTML package resource resolution, native XHTML-to-AST
conversion, and upstream Pandoc runner dependency closure as separate bounded
slices.
