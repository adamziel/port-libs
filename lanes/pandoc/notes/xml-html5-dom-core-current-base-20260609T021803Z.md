# XML/HTML5 DOM current-base responsive image metadata

Session: `port-dev-pandoc-xml-html5-dom-20260609T021803Z`

Base accepted HEAD: `a3acdbf651a3d75d5d84e3bea3aaa5d49ff7e5c6`

## Scope

- Implemented one bounded XML/HTML5 DOM sanitizer behavior cluster for responsive image metadata.
- `<source media>`, `<source sizes>`, and `<img sizes>` now reuse the native bounded CSS-token sanitizer before WordPress raw HTML handoff.
- Unsafe CSS URL/function tokens such as `url(javascript:...)` are removed from responsive image metadata while the otherwise valid `srcset` candidate remains reviewable.
- Safe media queries and sizes lists are normalized the same way accepted `theme-color` media metadata and style review values are normalized.

## Non-overlap

- This slice does not repeat the accepted orphan `<source>`/portal handoff from `xml-html5-dom-core-current-base-20260609T000839Z`.
- It does not repeat picture-source pruning after unsafe `srcset`, orphan row/cell repair, orphan table section/column repair, passive link relations, iframe/portal referrer metadata, SVG presentation URL filtering, image-map conversion, MathML annotations, raw text/plaintext unwrap, or foreign-content integration-point casing.
- The covered gap is specifically unsafe responsive image `media`/`sizes` metadata on otherwise valid picture/media sources and fallback images.

## Evidence

- Rework notes: `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20` found no current pandoc rework notes.
- Baseline focused: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1778 assertions, 0 failures`.
- Red-first behavior probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $f=PortLibs\Pandoc\Html5DomFragment::fromHtml("<picture><source srcset=\"./safe.avif 1x\" media=\"screen and (background:url(javascript:alert(1)))\" sizes=\"(min-width: 40em) calc(50vw + url(javascript:alert(1)))\" type=\"image/avif\"><img src=\"./fallback.jpg\" sizes=\"(min-width: 30em) calc(100vw + url(javascript:alert(1)))\" alt=\"Fallback\"></picture>", "https://source.example.test/import/posts/post.html"); echo $f->serialize(), "\n"; echo json_encode($f->diagnosticCodes()), "\n";'`
  showed unsafe `media` and `sizes` values still serialized.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1797 assertions, 0 failures`.
- DOM family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 2148 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/Html5DomFragment.php`, `lanes/pandoc/tests/Html5DomFragmentTest.php`, and `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`.
- `jq empty lanes/pandoc/lane-status.json && jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/pandoc` passed.

## Status delta

- `lane-status.json` `phpPass`: `2130 -> 2131`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2557 -> 2558`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 143`.
- Added `mappedXmlHtmlDomResponsiveImageMetadataCases: 1`.

## Dependency closure

No new support component is needed. This reuses native PHP `Html5DomFragment` sanitization, the existing CSS-token review-value normalizer, `srcset` URL normalization, `WordPressBlockWriter` raw HTML handoff, and the lane-local WordPress HTML5 DOM fragment example.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external XML/HTML tools, browser renderers, external sanitizers, online services, live provider tests, and live-service provider tests were not run.

## Next task

Choose a non-overlapping XML/HTML5 DOM edge such as another foreign-content integration-point case, a remaining table insertion-mode repair outside accepted orphan row/cell and section/column wrapping, or another bounded inert metadata handoff.
