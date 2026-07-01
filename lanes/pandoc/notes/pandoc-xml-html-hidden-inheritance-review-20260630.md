# Pandoc XML/HTML Hidden Inheritance Review - 2026-06-30

Slice: `plib-m9gyq`

`XmlHtmlDom` now carries effective `hidden` state provenance through HTML element summaries. The existing own-attribute fields remain unchanged:

- `hiddenRaw`
- `hiddenKeyword`
- `hiddenState`
- `hiddenValid`
- `hiddenInvalidValueDefaulted`

This slice adds metadata-only effective-state review fields for elements inside hidden subtrees:

- `hiddenReviewPolicy`
- `effectiveHiddenRaw`
- `effectiveHiddenKeyword`
- `effectiveHiddenState`
- `effectiveHidden`
- `effectiveHiddenUntilFound`
- `effectiveHiddenInvalidValueDefaulted`
- `hiddenInherited`
- `hiddenSource`
- `hiddenSourceElement`
- `hiddenSourceElementId`

The review metadata distinguishes `hidden`, `hidden="until-found"`, invalid values defaulting to hidden, and inherited hidden state from ancestor elements. HTML serialization and WordPress raw HTML handoff are unchanged.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomHiddenInheritanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomHiddenInheritanceTest.php` -> 1 file, 48 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 file, 6224 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php` -> 35 files, 7489 assertions, 0 failures
