# XML/HTML5 DOM portal/source-set handoff

Slice: `pandoc-xml-html5-dom-core-current-base-20260609T000839Z`
Base accepted HEAD: `35d557737dc1b88c45279aeb585788c53834812d`

## Scope

Implemented a bounded HTML5 DOM sanitizer policy for the remaining portal/source-set gap:

- `<portal src>` is treated as an active browsing-context element and is not serialized live.
- Safe portal sources become inert reviewer links with `data-pandoc-portal-src`, optional `title`, and optional `data-pandoc-portal-referrerpolicy`.
- Portal fallback children remain visible and pass through the existing sanitizer.
- Unsafe portal `src` values are rejected while fallback children remain reviewable.
- `<source>` elements outside an `audio`, `picture`, or `video` source context are dropped after children are normalized, so libxml void-element nesting does not lose following content.
- Existing media/picture source behavior is preserved, including type-only media branches after unsafe `src` filtering and empty picture-branch pruning.

## Evidence

No `port-pandoc-*.needs-lane-rework.md` notes existed for this lane at start.

Red-first command after adding the focused test:

```sh
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
```

Result: `1 test files, 1704 assertions, 1 failures`; the sanitizer still serialized an orphan `<source>` and live `<portal>` markup.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php
```

Result: `1 test files, 1725 assertions, 0 failures`.

DOM family command:

```sh
php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php
```

Result: `3 test files, 2053 assertions, 0 failures`.

Status delta: `lanes/pandoc/lane-status.json` `phpPass` updated from `1999` to `2000` for the new focused portal/source-set test case; `phpFail` remains `0`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test
```

Result: `html5 dom fragment handoff self-test ok`.

PHP lint passed for:

- `lanes/pandoc/src/Html5DomFragment.php`
- `lanes/pandoc/tests/Html5DomFragmentTest.php`
- `lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`

`git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP `Html5DomFragment` sanitizer, existing URL/srcset normalization, referrer-policy metadata handling, and WordPress raw HTML handoff path.

No Pandoc, Cabal solver/build/test command, Haskell runner, browser renderer, external XML/HTML tool, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted iframe policy metadata, media track metadata, picture-source pruning, datalist/form label recovery, ARIA metadata, SVG data-image, MathML annotation, or foreign-content CDATA slices. The change is limited to portal source conversion and orphan source-set dropping.

## Next

A useful non-overlapping follow-up would be richer HTML table insertion-mode recovery, additional parser-level foreign-content edge cases not already covered by SVG/MathML CDATA and data-image resources, or accessibility review metadata outside accepted ARIA/media/portal policy.
