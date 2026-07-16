# Tree Merge Binary Attribute Fixture Parity

## Source Truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Fixture generator: `gix-merge/tests/fixtures/tree-baseline.sh`
- Generated archive: `gix-merge/tests/fixtures/generated-archives/tree-baseline.tar`
- Case: `both-modify-file-with-binary-attr`

The upstream fixture writes `a/** binary` to `.gitattributes`, then modifies
`a/x.f` differently on both sides. The generated `A-B.merge-info` records a
content conflict with ancestor/ours/theirs index stages while the worktree
content follows the binary fallback, not text conflict markers.

## Native Delta

- `TreeMerge` now collects binary merge patterns from `.gitattributes` beside
  existing `merge=union` patterns.
- Recursive blob merges use `BlobMerge::mergeBinary()` when the full path
  matches a `binary`, `-merge`, or `merge=binary` attribute pattern.
- Attribute matching now handles `**` so the upstream `a/** binary` fixture
  form works through recursive subtree merges.
- `TreeMergeTest.php` now models the upstream fixture with `.gitattributes`
  present and expects the conflicted worktree file to contain the ours blob.
- `wordpress-tree-merge.php` now includes a WordPress upload/media conflict
  smoke that exercises a `wp-content/uploads/** binary` rule.

## Verification

- `php -l lanes/gitoxide/src/TreeMerge.php`
- `php -l lanes/gitoxide/tests/TreeMergeTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-tree-merge.php`
- `php -l lanes/gitoxide/examples/wordpress-tree-merge.php`
- `php tools/run-tests.php lanes/gitoxide/tests/TreeMergeTest.php`
  - `1 test files, 887 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-tree-merge.php`
  - includes `binary-attr-conflicts=1`
  - includes `binary-attr-hero=ours media`
- `git diff --check -- lanes/gitoxide`

## Dependency Closure

No new support component is needed. The slice reuses native `TreeMerge`,
`BlobMerge`, tree/blob object storage, existing `.gitattributes` parsing paths,
and the WordPress tree-merge fixture/example harness.

## Non-Overlap

This deepens one already represented `gix-merge` tree-baseline fixture by
correcting binary-attribute conflict semantics. It does not repeat the accepted
attributes/pathspec parent traversal, quoted pattern, ASCII whitespace, tree
pathspec, transport/protocol, reference-transaction, pack-index, or
merge-base slices.
