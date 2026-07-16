<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$expectedPaths = static function (int $seed): array {
    $artist = sprintf('artist-%03d', $seed);
    $album = sprintf('album-%03d', $seed);
    $missingAlbum = sprintf('missing-album-%03d', $seed);

    return [
        'source' => 'e_fkey.test e_fkey-28.1..30.1',
        'operation' => 'composite-foreign-key-cardinality-and-null-child',
        'section' => '4.1 Composite Foreign Key Constraints',
        'seed' => $seed,
        'upstream_cases.0' => 'e_fkey-28.1',
        'upstream_cases.6' => 'e_fkey-28.7',
        'upstream_cases.7' => 'e_fkey-28.8',
        'upstream_cases.8' => 'e_fkey-28.9',
        'upstream_cases.9' => 'e_fkey-29.1',
        'upstream_cases.11' => 'e_fkey-29.3',
        'upstream_cases.12' => 'e_fkey-30.1',
        'definition_error_count' => 7,
        'definition_cardinality_checked_at_create_count' => 4,
        'definition_syntax_error_count' => 3,
        'definition_errors.0.case' => 'e_fkey-28.1',
        'definition_errors.0.status' => 'schema-error',
        'definition_errors.0.error' => 'foreign key on jj should reference only one column of table p',
        'definition_errors.0.child_key_width' => 1,
        'definition_errors.0.parent_key_width' => 2,
        'definition_errors.0.detected_at' => 'create-table',
        'definition_errors.1.case' => 'e_fkey-28.2',
        'definition_errors.1.status' => 'syntax-error',
        'definition_errors.1.error' => 'near ")": syntax error',
        'definition_errors.1.parent_column_list_syntax_valid' => false,
        'definition_errors.2.sql' => 'CREATE TABLE c(jj, FOREIGN KEY(jj) REFERENCES p(x, y))',
        'definition_errors.2.error' => 'number of columns in foreign key does not match the number of columns in the referenced table',
        'definition_errors.4.child_key_columns.0' => 'jj',
        'definition_errors.4.child_key_columns.1' => 'ii',
        'definition_errors.5.parent_columns.0' => 'x',
        'definition_errors.5.child_key_width' => 2,
        'definition_errors.5.parent_key_width' => 1,
        'definition_errors.6.parent_columns.2' => 'z',
        'definition_errors.6.cardinality_checked_at_create' => true,
        'implicit_mismatch_count' => 2,
        'implicit_mismatches.0.case' => 'e_fkey-28.8',
        'implicit_mismatches.0.status' => 'foreign-key-mismatch',
        'implicit_mismatches.0.error' => 'foreign key mismatch - "c" referencing "p"',
        'implicit_mismatches.0.detected_at' => 'dml-prepare',
        'implicit_mismatches.0.child_key_width' => 2,
        'implicit_mismatches.0.implicit_parent_key_width' => 1,
        'implicit_mismatches.0.create_table_allowed' => true,
        'implicit_mismatches.1.case' => 'e_fkey-28.9',
        'implicit_mismatches.1.parent_primary_key_columns.1' => 'y',
        'implicit_mismatches.1.child_key_columns.0' => 'a',
        'implicit_mismatches.1.child_key_width' => 1,
        'implicit_mismatches.1.implicit_parent_key_width' => 2,
        'composite_example.case' => 'e_fkey-29.1..30.1',
        'composite_example.parent_table' => 'album',
        'composite_example.child_table' => 'song',
        'composite_example.parent_key_columns.0' => 'albumartist',
        'composite_example.parent_key_columns.1' => 'albumname',
        'composite_example.child_key_columns.0' => 'songartist',
        'composite_example.child_key_columns.1' => 'songalbum',
        'composite_example.album.albumartist' => $artist,
        'composite_example.album.albumname' => $album,
        'composite_example.album.albumcover' => null,
        'composite_example.valid_song.songid' => ($seed * 10) + 1,
        'composite_example.valid_song.songartist' => $artist,
        'composite_example.valid_song.songalbum' => $album,
        'composite_example.valid_song_status' => 'commit-ok',
        'composite_example.missing_song.songid' => ($seed * 10) + 2,
        'composite_example.missing_song.songartist' => $artist,
        'composite_example.missing_song.songalbum' => $missingAlbum,
        'composite_example.missing_song_status' => 'constraint-failed',
        'composite_example.missing_song_error' => 'FOREIGN KEY constraint failed',
        'composite_example.null_album_song.songid' => ($seed * 10) + 3,
        'composite_example.null_album_song.songartist' => $artist,
        'composite_example.null_album_song.songalbum' => null,
        'composite_example.null_album_song_status' => 'commit-ok',
        'composite_example.null_artist_song.songid' => ($seed * 10) + 4,
        'composite_example.null_artist_song.songartist' => null,
        'composite_example.null_artist_song.songalbum' => $missingAlbum,
        'composite_example.null_artist_song_status' => 'commit-ok',
        'composite_example.valid_child_key_matches_parent' => true,
        'composite_example.missing_child_key_matches_parent' => false,
        'composite_example.null_child_key_short_circuit_count' => 2,
        'composite_example.partial_null_child_keys_satisfied' => true,
        'composite_example.null_child_statuses.0.status' => 'commit-ok',
        'composite_example.null_child_statuses.0.satisfied_by_null_child_key' => true,
        'composite_example.null_child_statuses.1.child_key.songartist' => null,
        'composite_example.null_child_statuses.1.child_key.songalbum' => $missingAlbum,
        'composite_example.committed_song_ids.0' => ($seed * 10) + 1,
        'composite_example.committed_song_ids.1' => ($seed * 10) + 3,
        'composite_example.committed_song_ids.2' => ($seed * 10) + 4,
        'composite_example.failed_song_committed' => false,
        'composite_example.committed_song_count' => 3,
        'dependencies.0' => 'sqlite-efkey28-composite-child-and-parent-keys-have-equal-cardinality',
        'dependencies.2' => 'sqlite-efkey28-implicit-parent-primary-key-width-mismatch-surfaces-at-dml-prepare',
        'dependencies.3' => 'sqlite-efkey29-composite-child-key-must-match-composite-parent-key',
        'dependencies.4' => 'sqlite-efkey30-composite-child-key-with-any-null-column-is-satisfied',
    ];
};

$sourceFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test';

$tests = [
    'real upstream e_fkey composite section cites cardinality section' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->contains('### SECTION 4.1: Composite Foreign Key Constraints', $source);
        $t->contains('Parent and child keys must have the same', $source);
        $t->contains('do_test e_fkey-28.$tn [list catchsql $sql] [list 1 $err]', $source);
    },
    'real upstream e_fkey composite section cites implicit primary key mismatch' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->contains('do_test e_fkey-28.8', $source);
        $t->contains('CREATE TABLE c(a, b, FOREIGN KEY(a,b) REFERENCES p);', $source);
        $t->contains('foreign key mismatch - "c" referencing "p"', $source);
    },
    'real upstream e_fkey composite section cites album song example' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->contains('do_test e_fkey-29.1', $source);
        $t->contains('PRIMARY KEY(albumartist, albumname)', $source);
        $t->contains('FOREIGN KEY(songartist, songalbum) REFERENCES album(albumartist,albumname)', $source);
    },
    'real upstream e_fkey composite section cites null child key rule' => static function (TestRunner $t) use ($sourceFile): void {
        $source = file_get_contents($sourceFile);

        $t->true(is_string($source));
        $t->contains('do_test e_fkey-30.1', $source);
        $t->contains('INSERT INTO song VALUES(2, \'Elvis Presley\', NULL, \'Fever\');', $source);
        $t->contains('if any of the child key columns', $source);
    },
];

foreach (range(1, 120) as $seed) {
    foreach ($expectedPaths($seed) as $path => $expected) {
        $tests[sprintf('real upstream e_fkey composite dynamic %03d %s', $seed, $path)] = static function (TestRunner $t) use ($seed, $path, $expected, $value): void {
            $plan = SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyCompositeConstraintPlan($seed);
            $t->same($expected, $value($plan, (string) $path));
        };
    }
}

$tests['real upstream e_fkey composite section rejects zero seed'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyCompositeConstraintPlan(0));
};

return $tests;
