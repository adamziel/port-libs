2026-06-09 UTC - XML/HTML5 DOM orphan table section/column repair

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260609T014306Z`
Base accepted HEAD: `9ab19c9e2380838c7ca01f28e9b3c5ee81262c5f`

Scope

- Added a bounded native HTML5 table insertion-mode repair path for legacy fragments where `caption`, `colgroup`, `col`, `thead`, `tbody`, or `tfoot` appear outside a table before WordPress raw HTML handoff.
- Direct orphan `col` nodes now become generated `table > colgroup > col` packets, orphan table section containers are wrapped into one generated table packet, and direct table `col` children are grouped into generated `colgroup` children instead of being foster-parented before the table.
- Existing orphan `tr`/`td`/`th` repair and invalid-table foster-parenting behavior remains intact.

Non-overlap

- This slice does not repeat the accepted orphan row/cell repair from `xml-html5-dom-core-current-base-20260609T011621Z`.
- It also avoids the accepted foreign-content CDATA, SVG data-image, select/option label, passive link relation, iframe policy metadata, portal/source-set, image-map, datalist, MathML annotation, raw text/plaintext unwrap, and table foster-parenting slices.

Manifest/status delta

- `lane-status.json` `phpPass`: `2072 -> 2073`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2484 -> 2485`.
- `xmlHtmlDomCoreCases`: `8 -> 9`.
- `mappedXmlHtmlDomCoreCases`: `8 -> 9`.
- `xmlHtmlDomCoreAssertions`: `124 -> 154`.
- Added `mappedXmlHtmlDomOrphanTableSectionColumnRepairCases: 1`.

Focused evidence

- Rework notes: `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md'` found no current pandoc rework notes.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1748 assertions, 0 failures`.
- Baseline family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 2099 assertions, 0 failures`.
- Red-first: after adding the section/column repair assertions, `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` failed with `1 test files, 1749 assertions, 1 failures`; orphan `caption`, `col`, `thead`, and `tfoot` nodes stayed outside generated tables, and a direct table `col` node was foster-parented before its table.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `1 test files, 1778 assertions, 0 failures`.
- Final family: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `3 test files, 2129 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test` passed with `html5 dom fragment handoff self-test ok`.

Dependency closure

- No new support component is needed.
- The patch reuses native `Html5DomFragment` sanitizer normalization, `WordPressBlockWriter` raw HTML handoff, focused XML/HTML DOM tests, and the lane-local WordPress HTML5 DOM fragment example.
- Pandoc, Cabal/Haskell runners, browser renderers, external XML/HTML tools, online sanitizers, online services, live provider tests, and live-service provider tests were not run.

Next task

- Choose a non-overlapping XML/HTML5 DOM gap such as remaining table insertion-mode cases beyond orphan row/cell and section/column repair, another foreign-content integration-point edge, or bounded inert metadata handoff.
