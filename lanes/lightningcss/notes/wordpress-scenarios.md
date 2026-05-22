# LightningCSS WordPress Scenario

Shared-hosting CSS transforms for themes, blocks, Playground, and plugin build-free delivery.

## Current Native Slice

Native CSS comment/whitespace minifier, declaration block parser, CSSOM-style direct declaration plus margin/padding, background, border, and flex-flow shorthand access, value-level color keyword shortening, math-function operator spacing, a small stylesheet parser that distinguishes style rules, at-rule statements/blocks, declarations, selectors, and nested WordPress rules, selector specificity packing/comparison for override ordering, and media query range/feature/list normalization.

`examples/wordpress-stylesheet-parser.php` parses the block-theme CSS fixture and reports the top-level rule count, first selector list, and declaration count. `examples/wordpress-border-cssom.php` models a theme.json-style block border where `border-color` recomposes a full physical border shorthand while side-specific overrides remain guarded. The declaration helper now exposes background color/image/repeat/position/size CSSOM behavior needed by block background controls and theme style migration tools, border shorthand behavior used by block style controls, plus prefixed and unprefixed `flex-flow` direction/wrap behavior used by block layout migrations. The specificity helper covers WordPress override selectors such as block-button hover styles and ID-scoped navigation rules. The media query helper normalizes responsive block-theme breakpoints such as min/max width aliases, comparison ranges, hover/update features, ratios, and simple same-unit calc values. This gives shared-hosting and Playground tooling a native AST boundary before adding prefixing, bundling, or transformer semantics.

## Next Task

Map another upstream `tests/test_cssom.rs` family such as grid-area/grid-template or animation shorthand, or start a custom-media/prefixing transformer slice.
