# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T131440Z`

Base accepted HEAD: `85d87e5511c95d05f1e827c086a3cd7c854b7f4c`

## Behavior Added

- `Html5DomFragment` now prunes HTML `<picture>` `<source>` branches after
  sanitizer filtering when no `src` or `srcset` candidate remains.
- Safe responsive image candidates still survive candidate-level `srcset`
  filtering, preserve `media`, `type`, and `sizes` metadata, and resolve
  relative URLs through trusted base URL metadata.
- Unsafe `javascript:`, `mailto:`, and other non-fetch picture candidates are
  stripped before WordPress raw HTML handoff, while the fallback `<img>` remains
  import-visible.
- The pruning is scoped to picture sources; existing video/audio source review
  packets that retain metadata after unsafe `src` filtering are not removed.

## Source Truth

Source truth is the lane-local Pandoc XML/HTML5 DOM support contract for safe
HTML reader and WordPress review handoff. Recovered responsive media fragments
may preserve safe reviewer-visible image candidates and fallback images, but
source branches whose fetch candidates were fully removed should not leave
dead media-query branches in raw HTML review blocks.

This is bounded sanitizer/serializer behavior. It is not browser-grade picture
candidate selection, media-query evaluation, `sizes` layout computation,
CSS/media fetching, full HTML5 tree-builder parity, native XHTML-to-AST
conversion, or upstream Pandoc runner parity.

Pre-edit red probe on the accepted base:

```text
Html5DomFragment::fromHtml("<picture><source srcset=\"javascript:alert(1) 1x, /media/safe.webp 2x\" media=\"(min-width: 40em)\" type=\"image/webp\"><source srcset=\"mailto:bad@example.test 1x\" media=\"(max-width: 39em)\"><img src=\"/media/fallback.jpg\" alt=\"Fallback\"></picture>", "https://source.example.test/import/posts/post.html")->serialize()
<picture><source srcset="https://source.example.test/media/safe.webp 2x" media="(min-width: 40em)" type="image/webp"><source media="(max-width: 39em)"><img src="https://source.example.test/media/fallback.jpg" alt="Fallback"></picture>
```

## Evidence

No matching `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.

Baseline focused verification before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 355 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php
2 test files, 226 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 372 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 598 assertions, 0 failures

php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test
html5 dom fragment handoff self-test ok
```

Delta:

- Adds 1 focused PHP PASS case.
- Adds 17 XML/HTML5 DOM assertions.
- Updates `phpPass` from `917` to `918`.
- Updates mapped static inventory coverage from `1375` to `1376`.

## Verification

```text
php -l lanes/pandoc/src/Html5DomFragment.php
No syntax errors detected in lanes/pandoc/src/Html5DomFragment.php

php -l lanes/pandoc/tests/Html5DomFragmentTest.php
No syntax errors detected in lanes/pandoc/tests/Html5DomFragmentTest.php

php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php

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
browser renderer, media player, online sanitizer, external XML/HTML tool,
external template engine, Word, LibreOffice, zip/unzip, TeX/PDF engine, or
online service was executed.

## Non-Overlap

This patch does not repeat accepted XML DTD/entity rejection, processing
instruction rejection, complete HTML document unsafe-declaration preflight,
HTML fragment declaration preflight, raw text `script`/`style` serialization,
RCDATA handling for `title`/`textarea`, obsolete raw-text fallback handling,
plaintext-state source protection, HTML5 void/boolean attribute serialization,
SVG/MathML foreign-content casing, integration-point casing, `href`/`src`/
`srcset` candidate filtering, `srcset` descriptor normalization, extended
`action`/`formaction`/`longdesc`/`background` URL filtering, `ping` side-effect
filtering, control-separated URL normalization, base URL resolution for normal
links/media, SVG local-resource URL policy, obsolete `dynsrc`/`lowsrc` and
local-only `usemap` policy, comment-boundary-safe serialization, visible
form/embed/noscript/template unwrapping, table foster-parenting, charset/
Unicode width handling, Markdown/HTML reader AST coverage, ZIP/OPC package
behavior, DOCX/ODT/EPUB readers, archive compression, math/TeX, PDF handoff,
BibTeX/CSL, YAML, table geometry, syntax highlighting, or legacy DOC/CFB work.
It owns only empty unsafe responsive picture source pruning after existing
candidate URL filtering.

## Follow-Up

Keep full HTML5 tree-builder parity, richer sanitizer policy, CSS/media
resource handling, exact XML namespace declaration placement,
native XHTML-to-AST conversion, browser-grade picture candidate selection,
media-query and sizes evaluation, and upstream Pandoc runner dependency
closure as separate bounded slices.
