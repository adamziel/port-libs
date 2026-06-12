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
- `phpPass` moves `3243 -> 3244` in the latest resolved lane status; `phpFail` remains `0`.
- Current main already includes the ZIP selected data descriptor review counters, which are preserved alongside this CSL slice:
  `mappedZipSelectedDataDescriptorReviewIssueCases = 1` and
  `zipSelectedDataDescriptorReviewIssueAssertions = 14`.

Latest completed refinery verification after rebase onto `origin/main 0ba14cfa6a`:

```bash
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result on rebased `origin/main 0ba14cfa6a`: `1 test files, 5250 assertions, 0 failures`.
Full lane result on rebased `origin/main 0ba14cfa6a`: `44 test files, 72314 assertions, 0 failures`.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
