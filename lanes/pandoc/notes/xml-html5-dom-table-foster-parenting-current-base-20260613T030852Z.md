# XML/HTML5 DOM Table Foster-Parenting Follow-Up

Slice: `pandoc-xml-html5-dom-table-foster-parenting`
Base: origin/main `8d509ca9c0`

## Scope

This follow-up covers one bounded HTML5 tree-construction edge in the `XmlHtmlDom` reader path: misplaced phrasing content inside nested table row-group and row contexts.

The fixture keeps scope to native DOM traversal and deterministic raw HTML handoff. It does not attempt full HTML5 parser parity.

## Behavior

`XmlHtmlDomTest.php` now verifies that:

- row-level `<em>` content inside `<tr>` is foster-parented before the table;
- row-group-level `<span>` content inside `<tbody>` is foster-parented before the table;
- valid `<caption>`, `<tbody>`, `<tr>`, and `<td>` structure remains inside the table;
- table summaries keep the expected table text and body-group/cell metadata;
- text semantic metadata for the fostered `<em>` survives;
- WordPress raw HTML block handoff receives the deterministic serialized fragment.

## Evidence

- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 file, 2133 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 76019 assertions, 0 failures

Counters:

- `phpPass`: 3366 -> 3367
- `phpFail`: 0
- `mappedXmlHtmlDomTableFosterParentingCases`: 1
- `xmlHtmlDomTableFosterParentingAssertions`: 20
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: 3326 -> 3327

No Pandoc binary, Cabal/Haskell runner, browser renderer, Node tooling, online sanitizer, external validator, online service, live provider test, or live-service provider test was invoked.
