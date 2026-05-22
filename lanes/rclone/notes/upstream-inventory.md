# rclone Upstream Inventory

- Upstream: `https://github.com/rclone/rclone`
- Commit: `28d6b0b7b906da70afdc036ba5bb21f3c86613b8`
- Cache: `.upstream-cache/rclone`
- Method: shallow clone with `--filter=blob:none --depth=1`; denominator counted from `git ls-tree -r --name-only HEAD` plus targeted reads of `Makefile`, `COPYING`, `fs/filter/glob.go`, `fs/filter/filter.go`, `fs/filter/*_test.go`, and `fs/hash/hash_test.go`.
- Focused `lsjson` reads for this slice: `cmd/lsjson/lsjson.go`, `fs/operations/lsjson.go`, and `fs/operations/lsjson_test.go`.

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
