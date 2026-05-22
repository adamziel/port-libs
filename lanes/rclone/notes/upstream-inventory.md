# rclone Upstream Inventory

- Upstream: `https://github.com/rclone/rclone`
- Commit: `28d6b0b7b906da70afdc036ba5bb21f3c86613b8`
- Cache: `.upstream-cache/rclone`
- Method: shallow clone with `--filter=blob:none --depth=1`; denominator counted from `git ls-tree -r --name-only HEAD` plus targeted reads of `Makefile`, `COPYING`, `fs/filter/glob.go`, `fs/filter/filter.go`, `fs/filter/*_test.go`, `fs/hash/hash_test.go`, `fs/operations/check.go`, `fs/operations/check_test.go`, and selected command files.
- Focused listing reads: `cmd/lsjson/lsjson.go`, `fs/operations/lsjson.go`, and `fs/operations/lsjson_test.go`, including the `StatJSON` branch that compares directory entries with `strings.EqualFold` when the provider advertises `CaseInsensitive`.
- Focused checksum reads: `cmd/checksum/checksum.go`, `cmd/hashsum/hashsum.go`, `fs/operations/check.go`, and `fs/operations/check_test.go`, including `TestCheckDownload`, `TestCheckEqualReaders`, and `TestCheckSumDownload`.

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
- Focused `lsjson` static inventory: 2 upstream Go test functions (`TestListJSON`, `TestStatJSON`), 24 named/subtest table cases counted from `fs/operations/lsjson_test.go`, and 11 command flags/options declared in `cmd/lsjson/lsjson.go`.
- Focused case-insensitive `StatJSON` rule: 1 implementation branch in `fs/operations/lsjson.go` maps provider `Features().CaseInsensitive` to case-folded directory-entry matching.
- Focused checksum/check static inventory: 9 upstream Go test functions in `fs/operations/check_test.go`, including 9 `ParseSumFile` line samples over LF/CRLF, 14 `CheckSum` named runs across normal and download modes, 7 `CheckDownload` table runs, 12 `CheckEqualReaders` byte/error fixtures, 10 `ApplyTransforms` path-normalization scenarios, and checksum/hashsum command boundaries in `cmd/checksum/checksum.go` and `cmd/hashsum/hashsum.go`.

## Runner Status

The prior missing Go/module/tooling blocker is resolved for a bounded upstream runner. On 2026-05-22, this lane installed Go 1.25.0 under `/home/claude/.local/go-toolchains/go1.25.0`, rebuilt the upstream binary at `28d6b0b`, supplied `rsync` 3.4.1 through a local Fedora RPM extraction and wrapper, and used a temporary mount-namespace overlay for `/etc/mime.types`.

The focused cleanup command passed for the packages that previously exposed local environment gaps:

```text
go test -skip '^TestIntegration' ./backend/huaweidrive ./cmd/gitannex ./cmd/serve/http ./fs/accounting ./fs/logger
```

The broad bounded command then passed for 299 upstream Go packages:

```text
GOFLAGS='-p=1' go test -timeout 20m -skip '^TestIntegration' $(go list ./... | grep -Ev '^github.com/rclone/rclone/cmd/(mount|mount2|serve/docker)$')
```

Environment notes for the passing run:

- `go version go1.25.0 linux/amd64`
- `rclone v1.75.0-beta.1.28d6b0b`, statically linked from the upstream checkout
- `rsync 3.4.1`
- `TZ=UTC`, `CI=true`, `RCLONE_CONFIG=/notfound`
- UID 1001 after dropping namespace capabilities, so permission-denial tests still run as an ordinary user
- temporary `/etc/mime.types` overlay inside the mount namespace only, without modifying the host `/etc`

This is bounded upstream runner evidence, not full provider/mount parity. Live-service provider tests were skipped with `-skip '^TestIntegration'`. `cmd/mount`, `cmd/mount2`, and `cmd/serve/docker` were excluded because they require FUSE/container mount permissions. Full `make quicktest` / `go test ./...` parity and `make test` / `fstest/test_all` provider parity remain open for a later environment that can run the live-service and mount fixtures.

## Mapped Native Slice

The PHP slice maps selected semantics from `fs/filter/glob_test.go`, `fs/filter/filter_test.go`, and existing lane checksum planning:

- Path glob conversion for `*`, `**`, `?`, leading `/`, brace alternation, regexp escapes, and invalid glob detection.
- First-match include/exclude rule ordering with `!` rule reset.
- Case-insensitive matching via the upstream `ignore_case` option.
- Sync planning that skips objects excluded by rclone-style filters.
- WordPress backup planning that includes uploads, WXR exports, and SQL dumps while excluding cache/log/source files.
- `fs/operations` / `cmd/lsjson` list and stat JSON shapes for paths, names, sizes, directory entries, recursive listings, file-only and dir-only modes, omitted modtime/mimetype fields, selected hash output, and metadata.
- Case-insensitive provider `StatJSON` lookup: differently-cased file and directory requests resolve to the provider's canonical object or directory path while still honoring file-only and dir-only filters.
- `fs/operations CheckSum`-style verification of parsed SUM files against provider objects, including match, differ, file-only-in-provider, file-only-in-sum, one-way, filter, duplicate-sum, mixed-case hash, and case-insensitive path transform behavior.
- `fs/operations CheckSum --download` behavior where ordinary checksum mode rejects a provider that does not advertise the requested hash, while download mode hashes object bytes locally and preserves the same match/differ/missing reports.

## Current PHP Verification

- Rclone-only PHP lane check on 2026-05-22: 24 tests, 166 assertions, 0 failures.
- Required root `php tools/run-tests.php` on 2026-05-22: 57 test files, 2,998 assertions, 0 failures against the current shared worktree.
