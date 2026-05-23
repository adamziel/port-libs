<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$schema = TableSchema::fromColumns([
    ['name' => 'attachment_id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'sha256', 'tag' => 2, 'type' => 'varbinary(32)'],
    ['name' => 'preview_sig', 'tag' => 3, 'type' => 'binary(4)'],
]);

return [
    'fromCommit' => 'media-hashes-before',
    'toCommit' => 'media-hashes-after',
    'tables' => [
        [
            'tableName' => 'wp_attachment_hashes',
            'fromSchema' => $schema,
            'toSchema' => $schema,
            'diffRows' => [
                [
                    'diff_type' => 'modified',
                    'from_attachment_id' => 77,
                    'from_sha256' => "\x01\x23\x45",
                    'from_preview_sig' => "\x0a\x0b\x0c\x0d",
                    'to_attachment_id' => 77,
                    'to_sha256' => "\xaa\xbb",
                    'to_preview_sig' => "wp\0\1",
                ],
                [
                    'diff_type' => 'added',
                    'to_attachment_id' => 88,
                    'to_sha256' => "\x01\x23\x45",
                    'to_preview_sig' => "img\0",
                ],
            ],
        ],
    ],
    'expectedStatements' => [
        'UPDATE `wp_attachment_hashes` SET `sha256`=0xaabb,`preview_sig`=0x77700001 WHERE `attachment_id`=77;',
        'INSERT INTO `wp_attachment_hashes` (`attachment_id`,`sha256`,`preview_sig`) VALUES (88,0x012345,0x696d6700);',
    ],
];
