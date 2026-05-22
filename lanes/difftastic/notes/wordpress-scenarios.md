# difftastic WordPress Scenario

Readable diffs for blocks, render callbacks, templates, theme.json, code snippets, and structured documents.

## Current Native Slice

Native token-level differ that avoids raw line-only comparison, classifies comments separately, carries delimiter open/close anchors, filters comments on request, and normalizes trailing commas before closing delimiters.

The current WordPress fixture compares a block render callback where a PHP comment changes at the same time as the escaping API changes from `esc_html` to `wp_kses_post`. With `ignoreComments`, the diff hides the comment-only churn but still reports the security-relevant API change.

Run:

```sh
php lanes/difftastic/examples/wordpress-render-callback-diff.php
```

## Next Task

Port a small recursive syntax-list diff for bracketed PHP/JS/CSS structures, then map one upstream `sample_files` pair such as `simple_*.js` or `comma_*.js` into a fixture parity test.
