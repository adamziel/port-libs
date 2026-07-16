# Pandoc Citation/CSL choose-substitute branch suppression

Micro-slice: `pandoc-citation-csl-core-current-base-20260608T151446Z`
Base accepted HEAD: `9b7dedf8f156ee7a192d9054f47ee79347ca34c8`

## Scope

This patch adds bounded native Citation/CSL support for `cs:choose` inside
`cs:names` substitutes. The rendered substitute branch now determines which CSL
variables are suppressed later in the same rendering context. This keeps a
later URL visible when the title branch rendered, while still suppressing the
later title; URL-only fallbacks suppress the later URL branch as expected.

This intentionally does not repeat the accepted
`20260608T140035Z` basic substitute suppression slice, which covered direct
`cs:text variable="title"` substitutes. This slice owns only the branch-aware
`cs:choose` substitute edge.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL suppresses only rendered bounded csl choose substitute variables
Expected: '(Diaz | https://example.test/named-source | Named Source Packet | 2026; Title Only Packet | https://example.test/title-source | 2025; https://example.test/url-source | 2024)'
Actual:   '(Diaz | https://example.test/named-source | Named Source Packet | 2026; Title Only Packet | 2025; https://example.test/url-source | 2024)'
1 test files, 2580 assertions, 1 failures
```

Green focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2590 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-citation-csl-choose-substitute-handoff.php --self-test
wordpress-citation-csl-choose-substitute-handoff self-test passed
```

## Dependency Closure

No new native PHP support component is needed. The slice reuses
`CitationCslProcessor` style parsing, choose condition evaluation, names
substitutes, Markdown parsing, and WordPress block writing.

Not run or required: Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners,
external bibliography managers, online services, live provider tests, or
live-service provider tests.

## Next

A follow-up Citation/CSL slice should choose a non-overlapping native gap such
as additional substitute edge cases, locale term behavior, abbreviation handoff
metadata, or citeproc disambiguation semantics.
