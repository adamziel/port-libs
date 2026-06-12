# Pandoc XML/HTML5 DOM Description List Groups

Slice: `plib-88bcw`, XML/HTML5 DOM core blocker.

Implemented bounded native PHP HTML description-list group provenance in `XmlHtmlDom`.

## Behavior

- `summarizeHtmlFragment()` now recognizes `dl`, `dt`, and `dd` as structured description-list nodes instead of generic elements.
- `dl` summaries expose grouped terms/descriptions, group counts, term/detail counts, and leading orphan description counts.
- The grouping is bounded to direct `dt`/`dd` children plus HTML5 direct `div` wrappers, preserving the serialized raw HTML handoff unchanged.
- `dt` and `dd` nodes expose their term/detail text for reviewer-facing DOM handoff.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 test file, 1241 assertions, 0 failures
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 69633 assertions, 0 failures

No Pandoc, office suite, TeX/browser engine, zip/unzip, Jupyter, Node tooling, external validator, online service, or live provider test was run.
