# pandoc-doctemplates-core-current-base-20260608T115601Z

## Scope

Lane: pandoc
Micro-slice: pandoc-doctemplates-core-current-base-20260608T115601Z
Accepted base: 5dedc386a6e250b7617ad436c30fa6c69d882db9

This slice adds bounded native support for the Pandoc Texinfo default template.
`DocTemplate` now resolves both `templates/default.texinfo` and
`templates/default` for `texinfo` / `texinfo+...` formats without shelling out
to Pandoc or any external template engine.

## Source Truth

- No `port-pandoc` rework note existed for this slice.
- The local pinned Pandoc checkout was absent from
  `/home/claude/port-libs/.upstream-cache/pandoc`.
- Primary source used: the official `jgm/pandoc-templates` repository lists
  `default.texinfo`, and the raw `default.texinfo` template contains the
  bounded `filename`, `title`/`version`, header include, strikeout macro,
  titlepage author/date, TOC, body, include-before, include-after, and `@bye`
  handoff covered here.
  - https://github.com/jgm/pandoc-templates
  - https://raw.githubusercontent.com/jgm/pandoc-templates/master/default.texinfo

## Red-First Evidence

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 724 assertions, 0 failures`.

Red-first probe:

`php -r 'require "tools/bootstrap.php"; $r = new \PortLibs\Pandoc\DocTemplate(); echo $r->renderResource("templates/default", [], ["title" => "Texinfo Review", "body" => "@chapter Body"], null, "texinfo");'`

Result: failed with `UnexpectedValueException: Missing doctemplate resource templates/default`.

## Final Evidence

`php -l lanes/pandoc/src/DocTemplate.php`

Result: no syntax errors.

`php -l lanes/pandoc/tests/DocTemplateTest.php`

Result: no syntax errors.

`php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`

Result: no syntax errors.

`php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`

Result: `1 test files, 748 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`

Result: `OK wordpress doctemplate review packet`.

`php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "ok\n";'`

Result: `ok`.

`git diff --check -- lanes/pandoc`

Result: passed with no output.

Focused delta: +1 PHP PASS case and +24 focused assertions in
`DocTemplateTest.php` (`724` to `748`). The mapped denominator moves from
`2052` to `2053`; lane `phpPass` moves from `1631` to `1632`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
native `DocTemplate` resource resolver, default-template fallback path,
extension-qualified format normalization, conditionals, loops, and WordPress
doctemplate review-packet self-test.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
template engine, TeX/PDF engine, browser renderer, online service, live
provider test, or live-service provider test was run.

## Non-Overlap

This slice does not overlap the accepted doctemplate map-pairs, applied
partial, breakable-space, braced separator, extension-qualified format, Muse,
Org, man, ms, Beamer, or other default-template fallback slices. It owns only
the bounded `default.texinfo` writer fallback and its direct WordPress
review-packet smoke.
