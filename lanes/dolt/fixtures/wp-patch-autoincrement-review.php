<?php

declare(strict_types=1);

use PortLibs\Dolt\TableSchema;

$postsSchema = TableSchema::fromColumns([
    ['name' => 'ID', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true, 'autoIncrement' => true],
    ['name' => 'post_title', 'tag' => 2, 'type' => 'varchar(255)'],
    ['name' => 'post_status', 'tag' => 3, 'type' => 'varchar(20)', 'default' => 'draft'],
]);

$postsRows = [
    ['ID' => 1, 'post_title' => 'Imported launch', 'post_status' => 'publish'],
    ['ID' => 2, 'post_title' => 'Imported guide', 'post_status' => 'draft'],
];

return [
    'arguments' => ['HEAD', 'WORKING', 'wp_posts'],
    'options' => [
        'revisionGraph' => [
            ['commit_hash' => 'review-head-hash', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'review-head-hash',
        'knownTables' => ['wp_posts'],
        'revisionSnapshots' => [
            'review-head-hash' => [],
            'WORKING' => [
                [
                    'name' => 'wp_posts',
                    'schema' => $postsSchema,
                    'rows' => $postsRows,
                ],
            ],
        ],
    ],
    'expectedStatements' => [
        "CREATE TABLE `wp_posts` (\n"
        . "  `ID` bigint NOT NULL AUTO_INCREMENT,\n"
        . "  `post_title` varchar(255),\n"
        . "  `post_status` varchar(20) DEFAULT 'draft',\n"
        . "  PRIMARY KEY (`ID`)\n"
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;',
        "INSERT INTO `wp_posts` (`ID`,`post_title`,`post_status`) VALUES (1,'Imported launch','publish');",
        "INSERT INTO `wp_posts` (`ID`,`post_title`,`post_status`) VALUES (2,'Imported guide','draft');",
    ],
    'expectedDiffTypes' => ['schema', 'data', 'data'],
];
