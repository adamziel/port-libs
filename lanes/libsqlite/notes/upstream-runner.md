# libsqlite Upstream Runner Evidence

Date: 2026-05-22

Upstream checkout:

- Git mirror commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- Official manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Cache: `.upstream-cache/libsqlite`

## Cache Hydration

The cache was inspected before changes. It was a clean shallow blobless checkout
with only root files materialized through a root-only sparse checkout.

Hydration command:

```sh
git -C .upstream-cache/libsqlite sparse-checkout set src test tool ext autosetup autoconf mptest
```

This materialized the directories required by SQLite's `configure`,
`testfixture`, and Tcl test runner paths without deleting or resetting the
cache.

## Prerequisites

Installed direct build/test prerequisites with passwordless sudo:

```sh
sudo -n dnf install -y tcl tcl-devel gcc make
```

Verified tools:

- `tclsh`: `/usr/bin/tclsh`, Tcl 9.0.2
- `cc`/`gcc`: GCC 16.1.1-2.fc44
- `make`: GNU Make 4.4.1
- Tcl headers: `/usr/include/tcl.h`

## Build And Tests

Configure:

```sh
mkdir -p .upstream-cache/libsqlite-build-port-libsqlite
cd .upstream-cache/libsqlite-build-port-libsqlite
../libsqlite/configure CFLAGS='-O0 -g'
```

Result: passed. Configure detected Tcl via `/usr/bin/tclsh9.0` and
`/usr/lib64/tclConfig.sh`.

Build:

```sh
make -C .upstream-cache/libsqlite-build-port-libsqlite -j2 testfixture
```

Result: passed.

Focused runner:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick \
  btree*.test pager*.test quick*.test schema*.test rowid*.test table*.test
```

Result: 37 scripts, 0 errors out of 6731 tests in 00:07.

Strongest bounded runner completed in this run:

```sh
cd .upstream-cache/libsqlite-build-port-libsqlite
./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error veryquick
```

Result: 1235 scripts, 0 errors out of 329670 tests in 01:51.

Boundary: SQLite `all` and `release` permutations were not run in this bounded
lane pass because they cover many build configurations and higher-cost suites.
The stale missing `tclsh`/compiler/`make`/Tcl-header blocker is resolved.

## Focused Native Mapping: Table Leaf Overflow

The current PHP slice maps SQLite's table leaf overflow payload placement from
`src/btree.c`: `maxLeaf = usableSize - 35`, `minLeaf =
((usableSize - 12) * 32 / 255) - 23`, and overflow pages store a 4-byte
big-endian next-page pointer followed by up to `usableSize - 4` payload bytes.

Focused upstream fixture boundary:

- `test/corrupt3.test` creates a page-size 1024 table row with one overflow
  page, verifies the first overflow pointer location, and checks malformed
  overflow chains.

The native PHP tests now cover local-payload length calculation, single-page
overflow reads, multi-page chained overflow reads, and premature overflow-chain
termination for WordPress-shaped `wp_options` rows.
