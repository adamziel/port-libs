# Tree Pathspec Negative Wildcard Directory Traversal Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T112251Z`

Base accepted HEAD: `c572f41ab801d8aa51aba64622e775403921afd5`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: `directory_matches_prefix()` returns true for excluded wildcard patterns to prefer traversal false positives over pruning descendants that can still match.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/tests/search/mod.rs`: `directory_matches_prefix_negative_wildcard`, `simplified_search_respects_all_excluded`, and `included_directory_and_excluded_subdir_top_level_with_prefix` cover negative wildcard traversal, all-excluded fallback, and exact directory-only exclusion boundaries.

## Native PHP Delta

- `TreePathspecWalk::breadthFirst()` now continues descending through an excluded tree entry only when the exclusion matched by wildcard and `PathspecSearch::canMatch()` says a descendant can still match.
- Exact directory-only excludes remain authoritative for tree-walk pruning.
- `PathspecTreeWalkTest` adds a WordPress deployment-tree case where `:!wp-content/*-cache` excludes the generated-cache directory itself but still permits an explicitly included manifest below it to be discovered.
- `examples/wordpress-tree-pathspec-walk.php` exposes the same generated-cache manifest selection as a local smoke path.

## Red-First Evidence

Before the implementation, this current-base probe showed the descendant was independently included but the tree walk still returned no paths because the excluded directory was pruned:

```sh
php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\{GitObject,PathspecSearch,Tree,TreeEntry,TreePathspecWalk}; $blobOid=str_repeat("1",40); $objects=[]; $blob=static fn($n)=>new TreeEntry("100644",$n,$blobOid); $tree=static function($n,$t) use (&$objects){$o=$t->toObject(); $objects[$o->oid()]=$o; return new TreeEntry("040000",$n,$o->oid());}; $root=new Tree([$tree("wp-content", new Tree([$tree("generated-cache", new Tree([$blob("index.php")]))]))]); $read=static function($entry,$path) use (&$objects){return $objects[$entry->oid];}; $search=PathspecSearch::fromSpecs(["wp-content/**", ":!wp-content/*-cache", "wp-content/generated-cache/index.php"]); $paths=array_map(fn($r)=>$r->path, TreePathspecWalk::breadthFirst($root,$search,$read,includeTrees:false)); var_export([$search->match("wp-content/generated-cache", true)?->kind, $search->match("wp-content/generated-cache", true)?->isExcluded(), $search->canMatch("wp-content/generated-cache", true), $search->isIncluded("wp-content/generated-cache/index.php", false), $paths]);'
```

Observed state:

```text
directory match kind: wildcard
directory excluded: true
can descend: true
descendant included: true
walk paths: []
```

## Verification

- Syntax:
  - `php -l lanes/gitoxide/src/TreePathspecWalk.php`
  - `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
  - Result: no syntax errors detected in all three files.
- Example smoke:
  - `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; if (($out["negativeWildcardCacheDirectoryExcluded"] ?? null) !== true || ($out["negativeWildcardCacheCanDescend"] ?? null) !== true || !in_array("wp-content/generated-cache", $out["negativeWildcardCacheReadPaths"] ?? [], true) || ($out["negativeWildcardCacheContentPaths"] ?? null) !== ["wp-content/generated-cache/manifest.json"] || ($out["negativeWildcardCacheStaleSkipped"] ?? null) !== true) { fwrite(STDERR, "tree pathspec negative wildcard example failed\n"); exit(1); } echo "tree pathspec negative wildcard example ok\n";'`
  - Result: `tree pathspec negative wildcard example ok`.
- Focused tree/pathspec:
  - `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  - Result: `1 test files, 320 assertions, 0 failures`.
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `40 test files, 8972 assertions, 0 failures`.
- Whitespace:
  - `git diff --check -- lanes/gitoxide`
  - Result: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Full Gitoxide PHP assertions: `8953 -> 8972`.
- Expected mapped denominator: conservatively +1 tree/pathspec behavior cluster; full upstream Cargo workspace was not executed.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP pathspec parser/search and in-memory tree traversal. It does not shell out to Git, run live provider tests, read credentials, or require a shared support-library activation gate.

## Non-Overlap

This extends accepted tree/pathspec walking without repeating default search modes, empty search, empty-pattern prefix bypass, root-dot normalization, absolute-root handling, LF byte wildmatch, whitespace directory fallback, malformed POSIX class fallback, attributes/pathspec filtering, or the prior sparse-checkout negative wildcard traversal slice. The new behavior is limited to applying upstream excluded-wildcard traversal parity inside `TreePathspecWalk`.
