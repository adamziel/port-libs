# XML/HTML5 DOM Template/Noscript Boundary Slice

## Scope

- Added stack-aware HTML5 template end-tag scanning in `XmlHtmlDom` so an outer `<template>` stays inert across nested `<template>` content and raw-text sentinel strings such as `</template>` inside `noscript`/`script` descendants.
- Preserved parsed review provenance for template/noscript/script/p top-level structure, link and image inventories, active descendant buckets, and escaped raw HTML handoff.
- Kept the direct XML/HTML5 DOM reader path local to the existing parser/reviewer conventions without invoking Pandoc or browser renderers.

## Status Delta

- `phpPass`: `3325 -> 3326` after rebasing over current main `7bc8d59244`; `phpFail` remains `0`.
- Mapped denominator: `3284 -> 3285`.
- Added `mappedXmlHtmlDomTemplateNoscriptBoundaryCases = 1`.
- Added `xmlHtmlDomTemplateNoscriptBoundaryAssertions = 30`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/Html5DomTest.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 2 test files, 2084 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 4 test files, 4734 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` -> 45 test files, 74651 assertions, 0 failures after rebasing over `7bc8d59244`.
