# pandoc-doctemplates-core-current-base-20260608T103659Z

## Scope

Lane: pandoc
Micro-slice: pandoc-doctemplates-core-current-base-20260608T103659Z
Accepted base: 60558650a0743303e68cfdee86308b86651a84b5

This slice adds bounded native support for the Pandoc Org writer default
template resource. `DocTemplate` now resolves both `templates/default.org` and
`templates/default` for `org` / `org+...` formats without shelling out to
Pandoc or any external template engine.

## Source Truth

Upstream behavior was checked against the Pandoc templates default Org writer
template:

https://raw.githubusercontent.com/jgm/pandoc-templates/master/default.org

The supported local fallback covers the bounded template surface used by that
resource: title, author list joining, date, sorted options pairs,
header-includes, abstract block, include-before, body, and include-after.

## Red-First Evidence

Before implementation:

`php -r 'require "tools/bootstrap.php"; $class = "PortLibs\\Pandoc\\DocTemplate"; $r = new $class(); echo $r->renderResource("templates/default", [], ["title" => "Org Review", "body" => "Body"], null, "org");'`

Result: failed with `UnexpectedValueException: Missing doctemplate resource templates/default`.

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 708 assertions, 0 failures`.

## Final Evidence

`php -l lanes/pandoc/src/DocTemplate.php`

Result: no syntax errors.

`php -l lanes/pandoc/tests/DocTemplateTest.php`

Result: no syntax errors.

`php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`

Result: no syntax errors.

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 724 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`

Result: `OK wordpress doctemplate review packet`.

`php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "ok\n";'`

Result: `ok`.

`git diff --check -- lanes/pandoc`

Result: passed with no output.

Focused delta: +1 PHP PASS case and +16 focused assertions in
`DocTemplateTest.php` (`708` to `724`). The lane mapped denominator moves from
`2035` to `2036`; lane `phpPass` moves from `1616` to `1617`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
native `DocTemplate` parser, resource resolver, extension-qualified format
fallback, deterministic map-pairs ordering, and WordPress doctemplate
review-packet self-test.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
template engine, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser
renderer, online service, live provider test, or live-service provider test was
run.

## Non-Overlap

This slice does not overlap the accepted doctemplate Markdown/CommonMark,
extension-qualified output-format, Muse, man, ms, breakable-space, braced
separator, applied-partial, or map-pairs slices. It owns only the missing
`default.org` writer fallback and its direct WordPress review-packet smoke.
