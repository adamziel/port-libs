# XML/HTML5 DOM Punctuation Named References

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T051337Z`
Session: `port-dev-pandoc-xml-html5-dom-20260609T051337Z`
Base: `40ecdbe743809a1f1af99ee730ab306fb571c756`

## Scope

This slice maps one bounded XML/HTML5 DOM behavior cluster: common HTML5 punctuation named character references now decode before DOM reader handoff.

The native prepass now covers punctuation aliases such as `&colon;`, `&semi;`, `&num;`, `&dollar;`, `&commat;`, `&lpar;`, `&rpar;`, bracket/brace aliases, slash, backslash, plus, equals, and punctuation markers. They decode in ordinary text, attributes, and RCDATA review fields before serialization, while raw-text `script` content remains literal.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for unsafe XML/DTD rejection, `NoBreak`/spacing/math named references, RCDATA/template/plaintext protection, SVG/MathML foreign-content casing, CDATA normalization, table insertion-mode repair, base URL/target diagnostics, URL/image/semantic source-line diagnostics, or sanitizer policy metadata. The behavior is only the bounded punctuation named-reference cluster in the shared parser prepass and facade handoff.

## Evidence

- Rework notes: none found under `.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Red-first probe before implementation: `php -r` over `Html5Dom::parseHtmlFragment("<p>&colon; &semi; &lpar; &rpar; &lsqb; &rsqb; &excl; &quest; &num; &dollar; &percnt; &commat;</p>")` serialized those aliases as literal `&amp;...;` text.
- Baseline focused commands before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php` passed with `1 test files, 171 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php` passed with `1 test files, 57 assertions, 0 failures`.
- Focused commands after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php` passed with `1 test files, 188 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php` passed with `1 test files, 65 assertions, 0 failures`.
- Adjacent XML/HTML DOM family command: `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php` passed with `4 test files, 2441 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php --self-test` passed with `xml/html5 dom handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/XmlHtmlDom.php`, `lanes/pandoc/tests/Html5DomTest.php`, `lanes/pandoc/tests/XmlHtml5DomTest.php`, and `lanes/pandoc/examples/wordpress-xml-html5-dom-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Lane diff whitespace check: `git diff --check -- lanes/pandoc` passed.
- Focused delta: 2 new PHP PASS cases and 25 focused assertions.
- Root harness not run: isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2355` to `2357`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2750` to `2751`.
- `xmlHtmlDomCoreCases`: `8` to `9`.
- `mappedXmlHtmlDomCoreCases`: `8` to `9`.
- `xmlHtmlDomCoreAssertions`: `124` to `149`.

## Dependency Closure

No new support component is needed. This reuses native PHP `XmlHtmlDom`, `Html5Dom`, `XmlHtml5Dom`, `WordPressBlockWriter`, the focused PHP test runner, and the existing WordPress XML/HTML5 DOM example. Full upstream Pandoc HTML reader runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

No Pandoc executable, Cabal solver/build/test command, Haskell runner, browser renderer, online sanitizer, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was run.

## Next Task

A useful follow-up is a non-overlapping parser-level HTML reader behavior such as remaining document metadata projection, another source-position diagnostic path outside accepted URL/image/base/semantic metadata, or a separately bounded named-reference cluster with fixture evidence.
