# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T124121Z`

Base accepted HEAD: `80358caf3312a42f3e5a37ace947626166339ea9`

## Behavior Added

- `Html5DomFragment` now treats obsolete `dynsrc` and `lowsrc` attributes as
  fetch URL attributes during HTML fragment sanitizer normalization.
- `usemap` is now treated as a local-only image-map reference. Safe values such
  as `#review-map` are preserved and are not expanded through trusted base URL
  metadata; non-local or scheme-bearing values are filtered.
- Safe `dynsrc` and `lowsrc` relative URLs still resolve through trusted base
  URL metadata, matching the existing bounded URL handoff behavior.
- The WordPress HTML5 DOM smoke now proves unsafe obsolete media URLs are
  stripped while safe legacy preview metadata and local image maps survive raw
  HTML block handoff.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for safe
HTML reader and WordPress review handoff: recovered HTML fragments may preserve
reviewer-visible legacy media metadata, but URL-bearing attributes must not pass
scheme-obfuscated or browser-fetching side effects into WordPress raw HTML
blocks. This slice is bounded sanitizer/serializer behavior. It is not full
HTML5 tree-builder parity, browser sanitizer parity, CSS/media fetching, native
XHTML-to-AST conversion, or upstream Pandoc runner parity.

Pre-edit red probe on the accepted base:

```text
Html5DomFragment::fromHtml("<p><img src=\"/media/cover.png\" dynsrc=\"javascript:alert(1)\" lowsrc=\"mailto:cover@example.test\" usemap=\"javascript:alert(1)\" alt=\"Cover\"><img src=\"/media/safe.png\" dynsrc=\"/media/intro.avi\" lowsrc=\"https://cdn.example.test/low.jpg\" usemap=\"#review-map\" alt=\"Safe\"><map name=\"review-map\"><area href=\"/review\" alt=\"Review\"></map></p>")->serialize()
<p><img src="/media/cover.png" dynsrc="javascript:alert(1)" lowsrc="mailto:cover@example.test" usemap="javascript:alert(1)" alt="Cover"><img src="/media/safe.png" dynsrc="/media/intro.avi" lowsrc="https://cdn.example.test/low.jpg" usemap="#review-map" alt="Safe"><map name="review-map"><area href="/review" alt="Review"></map></p>

diagnosticCodes()
[]
```

## Evidence

No current direct `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`; only stale
subdirectory rework notes from 2026-05-24/25 were present.

Baseline focused DOM-family verification before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 567 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 355 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 581 assertions, 0 failures

php lanes/pandoc/examples/wordpress-html5-dom-handoff.php --self-test
wordpress-html5-dom-handoff self-test passed
```

Delta:

- Adds 1 focused PHP PASS case.
- Adds 14 XML/HTML DOM assertions.
- Updates `phpPass` from `899` to `900`.
- Updates mapped static inventory coverage from `1357` to `1358`.

## Verification

```text
php -l lanes/pandoc/src/Html5DomFragment.php
No syntax errors detected in lanes/pandoc/src/Html5DomFragment.php

php -l lanes/pandoc/tests/Html5DomFragmentTest.php
No syntax errors detected in lanes/pandoc/tests/Html5DomFragmentTest.php

php -l lanes/pandoc/examples/wordpress-html5-dom-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-html5-dom-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing,
the existing `Html5DomFragment`, `AstNode`, `WordPressBlockWriter`, and
lane-local manifest/status machinery. No Pandoc, Cabal build, Haskell runner,
browser renderer, online sanitizer, external XML/HTML tool, external template
engine, Word, LibreOffice, zip/unzip, TeX/PDF engine, or online service was
executed.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, processing
instruction rejection, complete HTML document unsafe-declaration preflight,
HTML fragment declaration preflight, raw text `script`/`style` serialization,
RCDATA handling for `title`/`textarea`, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, `href`/`src`/
`srcset` filtering, extended `action`/`formaction`/`longdesc`/`background`
URL filtering, `ping` side-effect filtering, control-separated URL
normalization, base URL resolution for normal links/media, SVG local-resource
URL policy, comment-boundary-safe serialization, visible form/embed/noscript/
template unwrapping, table foster-parenting, charset/Unicode width handling,
Markdown/HTML reader AST coverage, ZIP/OPC package behavior, DOCX/ODT/EPUB
readers, archive compression, math/TeX, PDF handoff, BibTeX/CSL, YAML, table
geometry, syntax highlighting, or legacy DOC/CFB work. It owns only obsolete
`dynsrc`/`lowsrc` fetch URL handling and local-only `usemap` reference policy
inside the HTML fragment sanitizer/handoff layer.

## Follow-Up

Keep full HTML5 tree-builder parity, broader sanitizer policy, CSS/media
resource handling, exact XML namespace declaration placement,
native XHTML-to-AST conversion, and upstream Pandoc runner dependency closure
as separate bounded slices.
