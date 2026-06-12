# Direct CSL LangID Language Aliases

Scope: `plib-wh97b` citation/bibliography CSL core blocker slice.

This slice adds one bounded native PHP direct-CSL handoff behavior:

- Direct CSL JSON `langid`, `language-id`, `languageId`, `languageid`, and `hyphenation` now normalize into canonical `language` metadata.
- The normalized scalar language still derives `languageList` when no explicit `language-list` is present.
- CSL rendering exposes the same value through `language`, `langid`, `language-id`, `hyphenation`, `language-list`, and `languagelist`.
- CSL sort keys can sort by those language aliases before citation and bibliography rendering.
- WordPress bibliography review output preserves the rendered alias values.

Accounting:

- `mappedCslDirectLangidAliasCases = 1`
- `cslDirectLangidAliasAssertions = 17`
- `phpPass` moves `3239 -> 3240` in the latest resolved lane status; `phpFail` remains `0`.

Latest completed verification before the target branch advanced again:

```bash
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result on rebased `origin/main daca348385`: `1 test files, 5250 assertions, 0 failures`.
Full lane result on rebased `origin/main daca348385`: `44 test files, 71938 assertions, 0 failures`.

The branch was later rebased through additional moving `origin/main` heads, with
lane status left as full-verification pending for the final non-preverified
submit path.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
