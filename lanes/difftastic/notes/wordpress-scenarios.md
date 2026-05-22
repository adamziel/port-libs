# difftastic WordPress Scenario

Readable diffs for blocks, render callbacks, templates, theme.json, code snippets, and structured documents.

## Current Native Slice

Native token-level differ that avoids raw line-only comparison, classifies comments separately, carries delimiter open/close anchors, filters comments on request, normalizes trailing commas before closing delimiters, reports recursive changes inside bracketed syntax lists, applies upstream-style word/subword splitting for punctuation, Unicode words, and number boundaries, and renders escaped HTML for token, word/subword, and syntax-list changes.

HTML mode adds upstream-style angle-bracket delimiters without changing default code tokenization, where `<` and `>` remain punctuation/operators. This lets block markup and saved post content diffs report tag-list changes such as class mutations, inserted `id` attributes, and newly inserted inline tags while still escaping the rendered review HTML.

JSON mode aligns object items by their string property key before comparing values. This maps the upstream `sample_files/json_*.json` pair and keeps WordPress `block.json`/`theme.json` metadata reviews focused on changed values and nested arrays instead of whole-object churn.

The current WordPress fixture compares a block render callback where a PHP comment changes at the same time as the escaping API changes from `esc_html` to `wp_kses_post`. With `ignoreComments`, the diff hides the comment-only churn but still reports the security-relevant API change.

The recursive list fixture compares nested `register_block_type` arrays so block support changes such as `html => false` becoming `html => true` and new alignment support show up at the nested array path instead of as a single flattened line replacement.

The subword fixture compares a `register_block_style` slug change from `legacy-cta-v2` to `modern-cta-v3`. The number-aware word diff reports the changed slug words and version number separately, matching the upstream `src/words.rs` behavior used for readable inline changes.

The HTML fixture combines a block-style subword diff with a `theme.json` palette change. It emits escaped `<span>`, `<del>`, and `<ins>` markers with operation and syntax-list path metadata so a WordPress UI can embed reviewable diffs without trusting source text as HTML.

The block-markup fixture compares saved block HTML where a group block gains an `is-style-card` class, a heading gains an `id`, and the paragraph text is wrapped in `<strong>`. The syntax-list renderer reports the tag-level changes and escapes source tags as text for safe embedding in review UIs.

The block.json fixture compares block metadata where the title changes, a `viewScriptModule` is added, HTML support changes, and `full` alignment is added. JSON key alignment keeps the `supports` object matched while reporting nested changes.

The JSON display fixture emits compact machine-readable review data for that same `block.json` path. It mirrors upstream `src/display/json.rs` by returning the language, path, lowercase status, aligned line pairs, and chunks with per-side line numbers and highlighted novel token spans. This gives a WordPress code review or migration UI data it can render itself without trusting source text as HTML.

The theme-variation fixture applies the upstream `slider_at_end` JSON list-deletion shape to `theme.json`. Deprecated button variations are reported as focused deletions while retained variations stay out of the rendered change stream.

Run:

```sh
php lanes/difftastic/examples/wordpress-render-callback-diff.php
php lanes/difftastic/examples/wordpress-subword-diff.php
php lanes/difftastic/examples/wordpress-html-diff.php
php lanes/difftastic/examples/wordpress-block-markup-html-diff.php
php lanes/difftastic/examples/wordpress-block-json-diff.php
php lanes/difftastic/examples/wordpress-block-json-display.php
php lanes/difftastic/examples/wordpress-theme-variation-json-diff.php
```

## Next Task

Map the upstream nested_slider fixture pair to exercise delimiter-sliding behavior, then connect that shape to a WordPress nested block/template array example.
