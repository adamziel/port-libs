# rclone Upstream Inventory

- Upstream: `https://github.com/rclone/rclone`
- Commit: `28d6b0b7b906da70afdc036ba5bb21f3c86613b8`
- Cache: `.upstream-cache/rclone`
- Method: shallow clone with `--filter=blob:none --depth=1`; denominator counted from `git ls-tree -r --name-only HEAD` plus targeted reads of `Makefile`, `COPYING`, `fs/filter/glob.go`, `fs/filter/filter.go`, `fs/filter/*_test.go`, and `fs/hash/hash_test.go`.

## Counted Test-Related Inventory

- Total upstream repository files at the commit: 2,553
- Go test files: 327
- Backend Go test files: 124
- `fs/` Go test files: 91
- `cmd/` Go test files: 47
- `lib/` Go test files: 43
- `vfs/` Go test files: 19
- `cmdtest/` Go test files: 2
- `fstest` paths: 61
- `fstest` Go test files: 1
- Filter Go test files: 2
- Hash Go test files: 1
- Testdata paths: 836
- Test directory paths: 35
- Integration-related paths counted by path: 3
- Script paths and shell helpers: 43

## Runner Status

The full upstream runner was not executed for this lane slice. The `Makefile` quick target runs `RCLONE_CONFIG="/notfound" go test ./...`, while the full `test` target first builds rclone and `fstest/test_all`, then runs provider integration remotes. Executing that would require building the Go module graph and potentially exercising remote/backend integration fixtures, which is outside the modest CPU/network budget for this worker run. The current denominator is therefore a cloned static path inventory, not upstream pass parity.

## Mapped Native Slice

The PHP slice maps selected semantics from `fs/filter/glob_test.go`, `fs/filter/filter_test.go`, and existing lane checksum planning:

- Path glob conversion for `*`, `**`, `?`, leading `/`, brace alternation, regexp escapes, and invalid glob detection.
- First-match include/exclude rule ordering with `!` rule reset.
- Case-insensitive matching via the upstream `ignore_case` option.
- Sync planning that skips objects excluded by rclone-style filters.
- WordPress backup planning that includes uploads, WXR exports, and SQL dumps while excluding cache/log/source files.
