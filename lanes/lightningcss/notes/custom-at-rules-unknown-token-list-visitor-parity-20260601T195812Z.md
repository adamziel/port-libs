# LightningCSS Unknown At-Rule TokenList Visitor Parity

Slice: `lightningcss-custom-at-rules-parser-visitor-parity-20260601T195812Z`

Source truth:
- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `napi/src/at_rule_parser.rs` maps custom parser failures and unregistered names to `UnknownAtRule`.
- `node/ast.d.ts` exposes `UnknownAtRule` with `prelude: TokenOrValue[]`, optional `block: TokenOrValue[]`, and `loc`.
- `src/properties/custom.rs` derives visitor traversal for `TokenList`, so unknown prelude and block token streams participate in value/token visitors before rule visitors observe them.

Pre-change probe:
- `php -r 'require "tools/bootstrap.php"; $seen=[]; $css="@wp-token --wp-gap { 16px draft } .card { color: red; }"; $result=(new PortLibs\LightningCSS\CustomAtRuleTransformer())->transform($css, [], ["Length"=>static function(array $length) use (&$seen): ?array { $seen[]="length:".$length["value"].$length["unit"]; return ["unit"=>"rem","value"=>$length["value"]/16]; }, "Token"=>["ident"=>static function(array $token) use (&$seen): ?string { if (($token["value"]??null)!=="draft") return null; $seen[]="ident:".$token["value"]; return "live"; }], "Rule"=>["unknown"=>["wp-token"=>static function(array $rule) use (&$seen): array { $seen[]="rule:".$rule["prelude"].":".implode(",", array_map(static fn($c)=>$c["type"]??"", $rule["block"]??[])); return []; }]]]); echo $result."\n".json_encode($seen)."\n";'`
- Before this slice, only the unknown rule callback ran for the unknown block token stream: `["rule:--wp-gap:length,token"]`.

Implementation:
- `CustomAtRuleTransformer` now visits unknown at-rule `preludeTokens` and `block` token lists through the existing structured token-list visitor pipeline before dispatching generic or named `Rule.unknown` visitors.
- When a visited unknown block changes, the stale serialized `body` string is discarded so final emission uses the updated structured `block` values.
- The WordPress unknown custom at-rule token-block example now covers visited block lengths, identifiers, and variable fallbacks before the unknown rule callback emits root custom properties.

Verification:
- `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> `1 test files, 498 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-custom-at-rule-unknown-block-tokens.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 9047 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CustomAtRuleTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CustomAtRuleTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-custom-at-rule-unknown-block-tokens.php` -> no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "OK\n";'` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> passed.

Status delta:
- `phpPass` moves `9034 -> 9047` from the full lane run.
- Mapped upstream coverage remains `2439 / 3532`; this deepens the existing custom at-rule visitor/parser cluster rather than adding a new manifest row.

Dependency closure:
- No new support component is needed. The slice reuses the existing custom prelude token-list parser, `Length` visitor, token visitor, variable fallback visitor, and unknown rule serializer.

Non-overlap:
- This is bounded to unknown custom at-rule prelude/block token-list visitor parity. It does not touch bundle/import graph behavior, source maps, CSS Modules, CSSOM, target prefixing, media-query parsing, selector parsing, or property/value prefixing clusters.
- Root harness was not run; this isolated micro-slice used lane-focused verification only.
