<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitMailmap;

$line = static function (string $input): array {
    $results = GitMailmap::parseResults($input);
    if (count($results) !== 1 || $results[0]['entry'] === null || $results[0]['error'] !== null) {
        throw new RuntimeException('Expected a single parsed mailmap entry');
    }

    return $results[0]['entry'];
};

$tryLine = static function (string $input): ?InvalidArgumentException {
    $results = GitMailmap::parseResults($input);
    if (count($results) !== 1) {
        throw new RuntimeException('Expected a single mailmap parse result');
    }

    return $results[0]['error'];
};

$typical = "# from the mailmap docs\n"
    . "\n"
    . "Joe R. Developer <joe@example.com>\n"
    . "Joe R. Developer <joe@example.com> Joe <bugs@example.com>\n"
    . "\n"
    . "Jane Doe <jane@example.com> <jane@laptop.(none)>\n"
    . "Jane Doe <jane@example.com> <jane@desktop.(none)>\n"
    . "Jane Doe <jane@example.com> Jane <bugs@example.com>\n"
    . "<jane@example.com> Jane <Jane@ipad.(none)>\n";

$invalid = "# comment\n"
    . "\n"
    . "<missing closing brace\n"
    . "\n"
    . "just a name\n";

return [
    'line numbers are counted correctly in errors' => static function (TestRunner $t) use ($invalid): void {
        $actual = GitMailmap::parseResults($invalid);
        $t->same(2, count($actual));

        $err = $actual[0]['error'];
        $t->true($err instanceof InvalidArgumentException, 'expected first mailmap error');
        $t->contains('3:', $err->getMessage());

        $err = $actual[1]['error'];
        $t->true($err instanceof InvalidArgumentException, 'expected second mailmap error');
        $t->contains('Line 5', $err->getMessage());
    },

    'a typical mailmap' => static function (TestRunner $t) use ($typical): void {
        $t->same([
            GitMailmap::changeNameByEmail('Joe R. Developer', 'joe@example.com'),
            GitMailmap::changeNameAndEmailByNameAndEmail(
                'Joe R. Developer',
                'joe@example.com',
                'Joe',
                'bugs@example.com',
            ),
            GitMailmap::changeNameAndEmailByEmail('Jane Doe', 'jane@example.com', 'jane@laptop.(none)'),
            GitMailmap::changeNameAndEmailByEmail('Jane Doe', 'jane@example.com', 'jane@desktop.(none)'),
            GitMailmap::changeNameAndEmailByNameAndEmail('Jane Doe', 'jane@example.com', 'Jane', 'bugs@example.com'),
            GitMailmap::changeEmailByNameAndEmail('jane@example.com', 'Jane', 'Jane@ipad.(none)'),
        ], GitMailmap::parse($typical));
    },

    'empty lines and comments are ignored' => static function (TestRunner $t) use ($line): void {
        $t->same([], GitMailmap::parse('# comment'));
        $t->same([], GitMailmap::parse("\n\r\n\t\t   \n"));
        $t->same(
            GitMailmap::changeNameByEmail('# this is a name', 'email'),
            $line(' # this is a name <email>'),
            'whitespace before hashes counts as name though',
        );
    },

    'windows and unix line endings are supported' => static function (TestRunner $t): void {
        $actual = GitMailmap::parse("a <a@example.com>\n<b-new><b-old>\r\nc <c@example.com>");
        $t->same([
            GitMailmap::changeNameByEmail('a', 'a@example.com'),
            GitMailmap::changeEmailByEmail('b-new', 'b-old'),
            GitMailmap::changeNameByEmail('c', 'c@example.com'),
        ], $actual);
    },

    'valid entries' => static function (TestRunner $t) use ($line): void {
        $t->same(
            GitMailmap::changeNameByEmail('proper name', 'commit-email'),
            $line(" \t proper name   <commit-email>"),
        );
        $t->same(
            GitMailmap::changeEmailByEmail('proper email', 'commit-email'),
            $line("  <proper email>   <commit-email>  \t "),
        );
        $t->same(
            GitMailmap::changeNameAndEmailByEmail('proper name', 'proper email', 'commit-email'),
            $line("  proper name \t  <proper email> \t <commit-email>"),
        );
        $t->same(
            GitMailmap::changeEmailByNameAndEmail('proper-email', 'commit name', 'commit-email'),
            $line('<proper-email> commit name <commit-email>'),
        );
    },

    'error if there is just a name' => static function (TestRunner $t) use ($tryLine): void {
        $err = $tryLine('just a name');
        $t->true($err instanceof InvalidArgumentException);
        $t->contains('Line 1', $err->getMessage());
    },

    'error if there is just an email' => static function (TestRunner $t) use ($tryLine): void {
        $err = $tryLine('<email>');
        $t->true($err instanceof InvalidArgumentException);
        $t->contains('1:', $err->getMessage());

        $err = $tryLine("   \t  <email>");
        $t->true($err instanceof InvalidArgumentException);
        $t->contains('1:', $err->getMessage());
    },

    'error if email is empty' => static function (TestRunner $t) use ($tryLine): void {
        $err = $tryLine('hello <');
        $t->true($err instanceof InvalidArgumentException);
        $t->contains('1:', $err->getMessage());

        $err = $tryLine("hello < \t");
        $t->true($err instanceof InvalidArgumentException);
        $t->contains('1:', $err->getMessage());

        $err = $tryLine("hello < \t\r >");
        $t->true($err instanceof InvalidArgumentException);
        $t->contains('1:', $err->getMessage());
    },
];
