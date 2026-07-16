# Attributes/Pathspec Malformed Bracket Parity

Slice: `gitoxide-attributes-pathspec-match-parity-20260601T023809Z`

Source truth:

- Upstream `gix-glob/src/wildmatch.rs` aborts malformed bracket classes such as `[`, `[]`, and `[!]`.
- Upstream `gix-pathspec/src/search/matching.rs` tries wildmatch first, then falls back to verbatim matching when wildcard matching fails.

Port movement:

- `GitAttributes` malformed bracket patterns no longer assign attributes by matching the literal bytes through the regex fallback.
- `PathspecSearch` malformed bracket pathspecs now fall through to verbatim matches, preserving the upstream pathspec boolean result and `verbatim` match kind.
- Valid bracket literals such as `[[]` and negated close-bracket classes such as `[!]]` still use wildcard matching.

Verification:

- `php -l lanes/gitoxide/src/GitAttributes.php`
- `php -l lanes/gitoxide/src/PathspecSearch.php`
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php`
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php`
- `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`: 1 file / 216 assertions / 0 failures.
- `php tools/run-tests.php lanes/gitoxide/tests`: 40 files / 6955 assertions / 0 failures.
- `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php --self-test`: passed.
- `git diff --check -- lanes/gitoxide`: passed.

Dependency closure: no new support component is needed; this reuses the existing native PHP byte-glob/pathspec parser.
