# Pandoc XML/HTML Language Direction Inheritance - 2026-06-15

## Scope

`XmlHtmlDom` now reports effective `lang`/`xml:lang` and `dir` provenance for nested HTML fragment review packets.

The handoff distinguishes self-owned values from inherited ancestor values, keeps the source element name and ID when available, and treats invalid `dir` tokens as inherited so reviewers can see the nearest valid bidirectional context without claiming full HTML reader parity.

## Accounting

- Base: current main `31c6b73e74`
- `phpPass`: `3712 -> 3713`
- Upstream mapped cases: `3735 -> 3736`
- `mappedXmlHtmlDomLanguageDirectionInheritanceCases`: `0 -> 1`
- `xmlHtmlDomLanguageDirectionInheritanceAssertions`: `0 -> 47`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed `1` file, `4284` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests` passed `46` files, `87960` assertions, `0` failures
- `jq empty` for lane status and upstream manifest JSON
- `git diff --check`
- Conflict-marker scan

No Pandoc binary, browser renderer, online sanitizer, external validator, online service, live provider test, or live-service provider test is part of this slice.
