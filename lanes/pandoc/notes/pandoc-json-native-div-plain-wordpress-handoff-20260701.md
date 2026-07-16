# Pandoc JSON/native Div Plain WordPress handoff

Slice: `plib-63j70` JSON/native AST constructor completeness.

Pandoc JSON/native readers already hydrate `Div` children as block payloads,
including adjacent `Plain` constructors. The remaining handoff gap was
WordPress HTML rendering: nested HTML containers rendered `Plain` as inline
text, so adjacent `Plain` blocks merged and lost constructor boundaries.

`WordPressBlockWriter` now lets HTML block containers render nested `Plain`
children as paragraph-like HTML while keeping the default inline `Plain`
behavior for definition/list contexts that already depend on it.

No Pandoc binary, office suite, browser engine, external validator, or external
converter was invoked.

Focused validation:

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeDivPlainHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeDivPlainHandoffTest.php`
  passed with 1 file, 8 assertions, 0 failures.
- A 12-file JSON/native focused gate including the new regression passed with
  316 assertions and 0 failures.
- `PandocJsonNativeAstTest.php` remains baseline-red with six unrelated
  failures after the targeted Div plain-boundary failure was removed.
