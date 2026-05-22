<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$lanes = [
    'gitoxide' => [
        'priority' => 1,
        'library' => 'gitoxide',
        'upstream' => 'GitoxideLabs/gitoxide',
        'url' => 'https://github.com/GitoxideLabs/gitoxide',
        'commit' => '87433ed33eee9ba974111d20b854f6acb07cd4a6',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Rust workspace for Git object database, packfiles, refs, protocol, worktree, and plumbing crates.',
        'testSuite' => 'cargo test workspace plus crate-level fixtures; full denominator pending clone/test inventory.',
        'nativeTests' => 3,
        'progress' => 4,
        'phase' => 'seed implementation',
        'currentWork' => 'Native loose Git object storage with canonical object headers and SHA-1 object IDs.',
        'nextTask' => 'Expand loose object store into tree/commit parsing and map gitoxide object tests.',
        'wp' => 'Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.',
    ],
    'lightningcss' => [
        'priority' => 2,
        'library' => 'LightningCSS',
        'upstream' => 'parcel-bundler/lightningcss',
        'url' => 'https://github.com/parcel-bundler/lightningcss',
        'commit' => '22bdda3d190f1cd321d98026225cfc964af64ad9',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Rust CSS parser, transformer, minifier, prefixer, modules, and bundler semantics.',
        'testSuite' => 'Rust tests and fixture snapshots; full denominator pending upstream inventory.',
        'nativeTests' => 3,
        'progress' => 3,
        'phase' => 'seed implementation',
        'currentWork' => 'Native CSS comment/whitespace minifier and declaration block parser.',
        'nextTask' => 'Broaden tokenizer fixtures and map Lightning CSS parser/minifier tests.',
        'wp' => 'Shared-hosting CSS transforms for themes, blocks, Playground, and plugin build-free delivery.',
    ],
    'markerpdf' => [
        'priority' => 3,
        'library' => 'markerPDF',
        'upstream' => 'sddai/markerPDF',
        'url' => 'https://github.com/sddai/markerPDF',
        'commit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'PDF extraction pipeline for structured text/content conversion.',
        'testSuite' => 'Repository tests and sample document fixtures; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 3,
        'phase' => 'seed implementation',
        'currentWork' => 'Native PDF content stream text-run extraction for literal, array, hex, and FlateDecode streams.',
        'nextTask' => 'Add more PDF operators and map markerPDF extraction pipeline fixtures.',
        'wp' => 'PDF import into clean post content and Data Liberation document conversion workflows.',
    ],
    'libsqlite' => [
        'priority' => 4,
        'library' => 'libsqlite',
        'upstream' => 'sqlite/sqlite',
        'url' => 'https://github.com/sqlite/sqlite',
        'commit' => '8f70ec615f4cd247d36f92a22c99f65ebbcc22a7',
        'license' => 'SQLite blessing / public-domain style; verify exact upstream notice before redistribution',
        'architecture' => 'SQLite database file format, b-tree pages, records, varints, journaling, and SQL-visible primitives.',
        'testSuite' => 'SQLite TCL/permutation tests plus file-format fixtures; denominator pending upstream inventory.',
        'nativeTests' => 3,
        'progress' => 4,
        'phase' => 'seed implementation',
        'currentWork' => 'Native SQLite database header parser and SQLite varint decoder.',
        'nextTask' => 'Parse first b-tree page headers and map SQLite file-format tests.',
        'wp' => 'SQLite fallback/read-write tooling for WordPress hosts where the SQLite extension is unavailable.',
    ],
    'readability' => [
        'priority' => 5,
        'library' => 'Readability/content rewrite engine',
        'upstream' => 'mozilla/readability',
        'url' => 'https://github.com/mozilla/readability',
        'commit' => '08be6b4bdb204dd333c9b7a0cfbc0e730b257252',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'DOM scoring/extraction with content cleanup and WordPress block-oriented rewriting.',
        'testSuite' => 'Mozilla Readability fixture corpus; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 3,
        'phase' => 'seed implementation',
        'currentWork' => 'Native DOM extractor removes chrome, scores content containers, and emits simple WordPress blocks.',
        'nextTask' => 'Compare against Mozilla Readability fixture corpus and improve scoring/error behavior.',
        'wp' => 'Migration-aware article cleanup into clean WordPress blocks with link/media/page-builder cleanup.',
    ],
    'pandoc' => [
        'priority' => 6,
        'library' => 'pandoc',
        'upstream' => 'jgm/pandoc',
        'url' => 'https://github.com/jgm/pandoc',
        'commit' => '0640c4c9859aa5a3ede082c190fcd5883c24ac83',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Shared document AST with readers/writers for Markdown, HTML, WXR, EPUB/PDF intermediates, and WordPress blocks.',
        'testSuite' => 'Pandoc golden tests/readers/writers; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 3,
        'phase' => 'seed implementation',
        'currentWork' => 'Native Markdown block reader and WordPress block writer for headings, paragraphs, and lists.',
        'nextTask' => 'Add inline marks/list nesting and map Pandoc native AST tests.',
        'wp' => 'Document conversion kernel for Data Liberation imports and block-oriented output.',
    ],
    'quadrable' => [
        'priority' => 7,
        'library' => 'quadrable',
        'upstream' => 'hoytech/quadrable',
        'url' => 'https://github.com/hoytech/quadrable',
        'commit' => '4f44437dc9b951a91986ad69e2856938387be614',
        'license' => 'BSD-2-Clause',
        'architecture' => 'Header-only C++ sparse binary Merkle tree, compact proofs, sync, copy-on-write LMDB-backed store, MemStore, and quadb CLI.',
        'testSuite' => 'Static inventory of upstream check.cpp and Makefile: 34 top-level scenarios, 29 equivHeads subcases, 136 verify checks, 20 verifyThrow checks.',
        'nativeTests' => 8,
        'progress' => 4,
        'phase' => 'upstream inventory plus key primitive slice',
        'suiteProgress' => 'static upstream check.cpp inventory counted; 8 PHP tests mapped to hashing/key behavior',
        'currentWork' => 'Native hash/key primitives for empty branches, leaf domain separation, path-bit addressing, integer key encoding, and prefix retention.',
        'blocker' => 'C++ runner not executed yet; upstream build requires LMDB, BLAKE2, and initialized submodules. Most tree/proof/sync scenarios remain unported.',
        'nextTask' => 'Port the in-memory sparse tree update/get model for basic put/get, empty heads, batch insert, and deletion scenarios.',
        'wp' => 'Authenticated local-first state sync for Playground snapshots and content databases.',
    ],
    'syncthing' => [
        'priority' => 8,
        'library' => 'syncthing',
        'upstream' => 'syncthing/syncthing',
        'url' => 'https://github.com/syncthing/syncthing',
        'commit' => '3962a237232473c20a44945a6c8ce8c930375360',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Go device/folder model, block exchange, conflict handling, protocol messages, and resumable sync.',
        'testSuite' => 'Go package tests and integration tests; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native content block hashing and verification primitives.',
        'nextTask' => 'Map protocol tests and add block vector/update conflict fixtures.',
        'wp' => 'Resumable media/content synchronization for local-first WordPress and Playground folders.',
    ],
    'difftastic' => [
        'priority' => 9,
        'library' => 'difftastic',
        'upstream' => 'Wilfred/difftastic',
        'url' => 'https://github.com/Wilfred/difftastic',
        'commit' => '7ccfcb315f7e46fd015809416c7d7dffa5be7078',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Rust syntax-aware structural diff with parser integrations and HTML/text output.',
        'testSuite' => 'Rust tests, parser fixtures, sample diffs; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native token-level differ that avoids raw line-only comparison.',
        'nextTask' => 'Map parser fixtures and replace generic tokens with syntax-tree anchors.',
        'wp' => 'Readable diffs for blocks, templates, theme.json, code snippets, and structured documents.',
    ],
    'rclone' => [
        'priority' => 10,
        'library' => 'rclone',
        'upstream' => 'rclone/rclone',
        'url' => 'https://github.com/rclone/rclone',
        'commit' => '28d6b0b7b906da70afdc036ba5bb21f3c86613b8',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Go storage provider abstraction, copy/sync/check semantics, filters, checksums, and resumability.',
        'testSuite' => 'Go backend/unit tests plus integration remotes; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native in-memory provider contract with object metadata, copy, list, and checksum sync plan.',
        'nextTask' => 'Map filter/checksum tests and add filesystem provider contract tests.',
        'wp' => 'Portable backup/import/export sync for shared hosts and cloud storage providers.',
    ],
    'dolt' => [
        'priority' => 11,
        'library' => 'dolt',
        'upstream' => 'dolthub/dolt',
        'url' => 'https://github.com/dolthub/dolt',
        'commit' => 'b2274926e0dcd84aab000ee242df5b5e75689eef',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Git-for-data database versioning, table storage, commits, branches, diffs, merges, and SQL behavior.',
        'testSuite' => 'Go tests/BATS integration tests; denominator pending upstream inventory.',
        'nativeTests' => 1,
        'progress' => 2,
        'phase' => 'deferred',
        'audit' => 'deferred by user direction until non-dolt lanes reach baseline',
        'currentWork' => 'Sidetracked by user direction; no active worker should be assigned until the other lanes reach the required baseline.',
        'blocker' => 'User deferred Dolt until the rest of the portfolio has stronger baselines.',
        'nextTask' => 'Do not schedule Dolt work until the other lanes have real upstream denominators, native slices, passing tests, WordPress scenarios, and dashboard status.',
        'wp' => 'Versioned content/data migrations and inspectable database change sets.',
    ],
    'esbuild' => [
        'priority' => 12,
        'library' => 'esbuild',
        'upstream' => 'evanw/esbuild',
        'url' => 'https://github.com/evanw/esbuild',
        'commit' => '6a794dff68e6a43539f6da671e3080efdf11ca70',
        'license' => 'pending verification from upstream checkout',
        'architecture' => 'Go JS/TS/JSX/CSS parser, transformer, linker, bundler, and minifier.',
        'testSuite' => 'Go parser/bundler/end-to-end tests; denominator pending upstream inventory.',
        'nativeTests' => 2,
        'progress' => 2,
        'phase' => 'seed implementation',
        'currentWork' => 'Native JavaScript lexer for identifiers, numbers, strings, comments, and punctuators.',
        'nextTask' => 'Map parser tests and add JS/TS lexer token coverage.',
        'wp' => 'Node-free asset tooling for shared hosting, Playground, PHAR tools, and browser-backed PHP environments.',
    ],
];

foreach ($lanes as $slug => $lane) {
    $dir = "{$root}/lanes/{$slug}";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $manifest = [
        'schemaVersion' => 1,
        'lane' => $slug,
        'priority' => $lane['priority'],
        'upstream' => [
            'name' => $lane['upstream'],
            'url' => $lane['url'],
            'commit' => $lane['commit'],
            'license' => $lane['license'],
            'architecture' => $lane['architecture'],
        ],
        'benchmarkDenominator' => [
            'status' => isset($lane['suiteProgress']) ? 'static-upstream-inventory' : 'static-seed',
            'total' => isset($lane['suiteProgress']) && $slug === 'quadrable'
                ? '34 top-level check.cpp scenarios; 29 equivHeads subcases; 136 verify checks; 20 verifyThrow checks'
                : 'pending full upstream inventory',
            'mapped' => $lane['nativeTests'],
            'source' => $lane['testSuite'],
            'warning' => 'This is a defensible seed manifest, not a completed upstream denominator. A worker must clone/count the upstream suite before this lane can claim real parity progress.',
        ],
        'nativeImplementation' => [
            'language' => 'PHP',
            'shellOutsAllowedForProgress' => false,
            'currentSlice' => $lane['currentWork'],
        ],
        'wordpressScenario' => $lane['wp'],
        'nextTask' => $lane['nextTask'],
    ];
    if ($slug === 'quadrable') {
        $manifest['benchmarkDenominator']['runner'] = 'make test';
        $manifest['benchmarkDenominator']['runnerStatus'] = 'not executed in this lane slice; upstream runner requires LMDB, BLAKE2, and initialized submodules';
        $manifest['benchmarkDenominator']['inventoryPath'] = 'lanes/quadrable/notes/upstream-inventory.md';
        $manifest['benchmarkDenominator']['warning'] = 'The denominator is counted from upstream sources, but parity is still partial. Native PHP tests only map a small subset of hashing/key behavior.';
    }
    if (!empty($lane['phase']) && $lane['phase'] === 'deferred') {
        $manifest['deferred'] = [
            'status' => true,
            'reason' => 'User requested this lane be sidetracked until the rest of the portfolio is established.',
        ];
    }

    $status = [
        'schemaVersion' => 1,
        'library' => $lane['library'],
        'estimatedProgress' => $lane['progress'],
        'suiteProgress' => $lane['suiteProgress'] ?? 'static manifest seed; upstream suite not yet executed',
        'phpPass' => $lane['nativeTests'],
        'phpFail' => 0,
        'wordpressScenarios' => $lane['wp'],
        'phase' => $lane['phase'],
        'audit' => $lane['audit'] ?? 'needs independent auditor review',
        'currentWork' => $lane['currentWork'],
        'blocker' => $lane['blocker'] ?? 'Full upstream benchmark denominator still needs cloned/tested inventory.',
        'latestCommit' => 'pending first repository commit',
        'nextTask' => $lane['nextTask'],
    ];

    file_put_contents($dir . '/UPSTREAM_TEST_MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents($dir . '/lane-status.json', json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $notes = "# {$lane['library']} WordPress Scenario\n\n"
        . $lane['wp'] . "\n\n"
        . "## Current Native Slice\n\n{$lane['currentWork']}\n\n"
        . "## Next Task\n\n{$lane['nextTask']}\n";
    file_put_contents($dir . '/notes/wordpress-scenarios.md', $notes);
}

fwrite(STDOUT, 'Seeded metadata for ' . count($lanes) . " lanes\n");
