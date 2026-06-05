# Pandoc XML HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260605T092626Z`

Base accepted HEAD: `f48306bc245920a0f60018a6db3256e36339fc93`

## Behavior Added

- Added comment-boundary-safe raw HTML serialization for both `XmlHtmlDom` and
  `Html5DomFragment`.
- HTML comments whose text contains `--` now serialize with interior or
  overlapping delimiters split as `- -`.
- HTML comments whose text ends with `-` now get a single padding space before
  the closing delimiter, avoiding invalid `--->` output in WordPress raw HTML
  blocks.
- `Html5DomFragment` now preserves the parsed comment text in normalized nodes
  and applies the safety transform only at serialization time, so reviewer
  audit metadata is not silently rewritten before handoff.
- The WordPress HTML5 DOM fragment smoke now carries a trailing-hyphen review
  comment and rejects serialized `--->` output.

## Source Truth

The full upstream Pandoc runner remains unavailable in this isolated worktree;
there is no hydrated upstream checkout under `/home/claude/port-libs/.upstream-cache/pandoc`.
This slice uses the accepted static manifest and the lane-local XML/HTML5 DOM
support contract: recovered HTML fragments may preserve source comments for raw
HTML handoff, but serialized comments must not emit delimiter-looking comment
content into WordPress blocks.

This is bounded serializer behavior. It is not full HTML5 tree-builder parity,
a browser sanitizer, CSS/media policy, XML schema validation, or XHTML-to-AST
conversion.

## Evidence

Pre-edit DOM family baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 467 assertions, 0 failures
```

Focused probe before the final fix showed the unsafe output:

```text
Html5DomFragment::fromHtml("<!--review---><p>ok</p>")->serialize()
<!--review---><p>ok</p>

XmlHtmlDom::serializeHtmlNode($dom->createComment("review-"))
<!--review--->
```

An intermediate focused run failed until comment text preservation was moved
from normalization to serialization:

```text
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 265 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
1 test files, 279 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php
1 test files, 126 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
3 test files, 495 assertions, 0 failures

php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test
html5 dom fragment handoff self-test ok
```

Delta:

- Adds 2 focused PHP PASS cases.
- Adds 28 focused XML/HTML DOM assertions.
- Updates `phpPass` from `805` to `807`.
- Updates mapped static inventory coverage from `1265` to `1267`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/Html5DomFragment.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP DOM/libxml parsing
with network access disabled plus the existing `XmlHtmlDom`, `Html5DomFragment`,
`AstNode`, and `WordPressBlockWriter` support boundary. No Pandoc, Cabal build,
Haskell runner, browser renderer, online sanitizer, external converter, or
online service was executed.

## Non-Overlap

This does not repeat accepted XML DTD/entity rejection, XML processing
instruction rejection, HTML fragment declaration preflight, raw text
`script`/`style` serialization, RCDATA handling, obsolete raw-text fallback
unwrapping, plaintext-state protection, active form/embed/template/noscript
unwrapping, HTML5 void/boolean attribute serialization, SVG/MathML
foreign-content casing, integration-point casing, URL/srcset filtering, base
URL resolution, or table foster-parenting. It owns only comment-boundary-safe
serialization for preserved raw HTML review comments.

## Follow-Up

Keep complete HTML5 tree-builder parity, broader sanitizer policy, CSS/media
resource handling, XML schema validation, EPUB/XHTML resource handling, and
native XHTML-to-AST conversion as separate bounded slices.
