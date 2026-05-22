<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$originalSchema = TableSchema::fromColumns([
    ['name' => 'event_id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'event_count', 'tag' => 2, 'type' => 'int'],
    ['name' => 'payload', 'tag' => 3, 'type' => 'longtext'],
]);

$droppedSchema = TableSchema::fromColumns([
    ['name' => 'event_id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'payload', 'tag' => 3, 'type' => 'longtext'],
]);

$targetSchema = TableSchema::fromColumns([
    ['name' => 'event_id', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
    ['name' => 'event_count', 'tag' => 9, 'type' => 'varchar(20)'],
]);

return [
    'fromCommit' => 'plugin-before-schema-cleanup',
    'toCommit' => 'plugin-after-drop',
    'fromSchema' => $originalSchema,
    'toSchema' => $droppedSchema,
    'targetSchema' => $targetSchema,
    'fromRows' => [
        [
            'event_id' => 10,
            'event_count' => 3,
            'payload' => '{"hook":"save_post"}',
        ],
    ],
    'toRows' => [
        [
            'event_id' => 10,
            'payload' => '{"hook":"save_post"}',
        ],
    ],
];
