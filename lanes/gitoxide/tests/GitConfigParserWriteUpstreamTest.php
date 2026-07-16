<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfig;

$roundTripRawValue = static function (string $value): ?string {
    $config = GitConfig::fromString("[a]\nk=c\nk=d");
    $config->setRawValueBy('a', null, 'k', $value);

    return GitConfig::fromString($config->toString())->rawValue('a', null, 'k');
};

$roundTripExistingRawValue = static function (string $value): ?string {
    $config = GitConfig::fromString("[a]k=b\n[a]\nk=c\nk=d");
    $config->setExistingRawValueBy('a', null, 'k', $value);

    return GitConfig::fromString($config->toString())->rawValue('a', null, 'k');
};

return [
    'upstream gix-config parse::empty and whitespace-only inputs yield no sections' => static function (TestRunner $t): void {
        $t->same([], GitConfig::fromString('')->sections());
        $t->same([], GitConfig::fromString("\n   \n \n")->sections());
    },

    'upstream gix-config parse::from_bytes::skips_bom' => static function (TestRunner $t): void {
        $plain = GitConfig::fromString("\n[core]\n\ta = 1\n");
        $bom = GitConfig::fromString("\xEF\xBB\xBF\n[core]\n\ta = 1\n");

        $t->same($plain->sections(), $bom->sections());
        $t->same('1', $bom->value('core', null, 'a'));
    },

    'upstream gix-config parse::from_bytes::complex deterministic values' => static function (TestRunner $t): void {
        $config = GitConfig::fromString(<<<'CFG'
        [user]
                email = code@eddie.sh
                name = Foo Bar
        [core]
                autocrlf = input
        [url "ssh://git@github.com/"]
                insteadOf = "github://"
        [url "ssh://git@git.eddie.sh/edward/"]
                insteadOf = "gitea://"
        [init]
                defaultBranch = master
        CFG);

        $t->same('code@eddie.sh', $config->value('user', null, 'email'));
        $t->same('Foo Bar', $config->value('user', null, 'name'));
        $t->same('input', $config->value('core', null, 'autocrlf'));
        $t->same('github://', $config->value('url', 'ssh://git@github.com/', 'insteadOf'));
        $t->same('gitea://', $config->value('url', 'ssh://git@git.eddie.sh/edward/', 'insteadOf'));
        $t->same('master', $config->value('init', null, 'defaultBranch'));
    },

    'upstream gix-config parse::error invalid section headers and names fail' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn () => GitConfig::fromString('[hello'));
        $t->throws(RuntimeException::class, static fn () => GitConfig::fromString("[a_b]\n c=d"));
        $t->throws(RuntimeException::class, static fn () => GitConfig::fromString("[]A=\\\\\r\\\n\n"));
    },

    'upstream gix-config parse::error invalid value names fail after section lookup' => static function (TestRunner $t): void {
        $t->throws(RuntimeException::class, static fn () => GitConfig::fromString("[core]\n 4a=3"));
        $t->throws(RuntimeException::class, static fn () => GitConfig::fromString("[core] a=b\\\n cd\n[core]\n\n =3"));
    },

    'upstream gix-config parse::section::header write_to escapes subsection bytes' => static function (TestRunner $t): void {
        $config = GitConfig::fromString('');
        $config->setRawValueBy('core', 'a\\b', 'path', 'one');
        $config->setRawValueBy('core', 'a:"b"', 'path', 'two');

        $serialized = $config->toString();
        $t->contains('[core "a\\\\b"]', $serialized);
        $t->contains('[core "a:\"b\""]', $serialized);
        $t->same('one', GitConfig::fromString($serialized)->value('core', 'a\\b', 'path'));
        $t->same('two', GitConfig::fromString($serialized)->value('core', 'a:"b"', 'path'));
    },

    'upstream gix-config parse::section::header rejects invalid subsection bytes' => static function (TestRunner $t): void {
        $config = GitConfig::fromString('');
        $t->throws(InvalidArgumentException::class, static fn () => $config->setRawValueBy('a', "a\nb", 'key', 'value'));
        $t->throws(InvalidArgumentException::class, static fn () => $config->setRawValueBy('a', "a\0b", 'key', 'value'));
    },

    'upstream gix-config parse::section::name and key validation boundaries' => static function (TestRunner $t): void {
        $config = GitConfig::fromString('');

        $config->setRawValueBy('1a', null, 'hello-world', 'ok');
        $t->same('ok', $config->rawValue('1a', null, 'hello-world'));
        $t->throws(InvalidArgumentException::class, static fn () => $config->setRawValueBy('', null, 'a', 'b'));
        $t->throws(InvalidArgumentException::class, static fn () => $config->setRawValueBy('x_y', null, 'a', 'b'));
        $t->throws(InvalidArgumentException::class, static fn () => $config->setRawValueBy('core', null, '', 'b'));
        $t->throws(InvalidArgumentException::class, static fn () => $config->setRawValueBy('core', null, '1a', 'b'));
        $t->throws(InvalidArgumentException::class, static fn () => $config->setRawValueBy('core', null, 'a.2', 'b'));
    },

    'upstream gix-config file::access::raw::raw_value lookup boundaries' => static function (TestRunner $t): void {
        $config = GitConfig::fromString("[core]\na=b\nc=d");
        $t->same('b', $config->rawValue('core', null, 'a'));
        $t->same('d', $config->rawValue('core', null, 'c'));
        $t->same(null, GitConfig::fromString("a=b\n[core]\na=c")->rawValue('', null, 'a'));
        $t->same('d', GitConfig::fromString("[core]\na=b\na=d")->rawValue('core', null, 'a'));
        $t->same('d', GitConfig::fromString("[core]\na=b\n[core]\na=d")->rawValue('core', null, 'a'));
        $t->same(null, $config->rawValue('foo', null, 'a'));
        $t->same(null, $config->rawValue('core', 'a', 'a'));
        $t->same(null, $config->rawValue('core', null, 'aaaaaa'));
    },

    'upstream gix-config file::access::raw subsection must be respected' => static function (TestRunner $t): void {
        $config = GitConfig::fromString("[core]a=b\n[core.a]a=c");

        $t->same('b', $config->rawValue('core', null, 'a'));
        $t->same('c', $config->rawValue('core', 'a', 'a'));
        $t->same(['b'], $config->rawValues('core', null, 'a'));
        $t->same(['c'], $config->rawValues('core', 'a', 'a'));
    },

    'upstream gix-config file::access::raw::raw_multi_value ordered values' => static function (TestRunner $t): void {
        $t->same(['b'], GitConfig::fromString("[core]\na=b\nc=d")->rawValues('core', null, 'a'));
        $t->same(['b', 'c'], GitConfig::fromString("[core]\na=b\na=c")->rawValues('core', null, 'a'));
        $t->same(['b', 'c', 'd'], GitConfig::fromString("[core]\na=b\na=c\n[core]a=d")->rawValues('core', null, 'a'));
        $t->same(['b', 'c', 'd'], GitConfig::fromString("[core]\na=b\na=c\n[core]a=d\n[core]g=g")->rawValues('core', null, 'a'));
    },

    'upstream gix-config file::access::read_only case insensitive section and value names' => static function (TestRunner $t): void {
        $config = GitConfig::fromString("[core] a=true\n[core]\nA=false");

        $t->same(['true', 'false'], $config->values('CORE', null, 'a'));
        $t->same('false', $config->value('core', null, 'A'));
    },

    'upstream gix-config file::access::read_only implicit booleans are not raw strings' => static function (TestRunner $t): void {
        $config = GitConfig::fromString("[core]\na=b\nc");

        $t->same('b', $config->value('core', null, 'a'));
        $t->same('true', $config->value('core', null, 'c'));
        $t->same(null, $config->rawValue('core', null, 'c'));
    },

    'upstream gix-config file::access::read_only multi-line plain and quoted values' => static function (TestRunner $t): void {
        $config = GitConfig::fromString(<<<'CFG'
        [alias]
           save = !git status \
                && git add -A \
                && git commit -m \"$1\" \
                && git push -f \
                && git log -1 \
                && :            # comment
        [core]
           escape-sequence = "hi\nho\n\tthere\bi\\\" \""
        CFG);

        $expectedAlias = '!git status         && git add -A         && git commit -m "$1"         && git push -f         && git log -1         && :';
        $t->same($expectedAlias, $config->rawValue('alias', null, 'save'));
        $t->same("hi\nho\n\tthere\x08i\\\" \"", $config->rawValue('core', null, 'escape-sequence'));
    },

    'upstream gix-config file::access::raw::set_raw_value roundtrips encoded values' => static function (TestRunner $t) use ($roundTripRawValue): void {
        foreach ([
            'hello world',
            "\ta",
            ' a',
            "a\t",
            'a ',
            '"hello"\"there"\\\b\x',
            "a\nb   \n\t   c",
            ';hello ',
            ' # hello',
        ] as $value) {
            $t->same($value, $roundTripRawValue($value), $value);
        }
    },

    'upstream gix-config file::access::raw::set_existing_raw_value roundtrips encoded values' => static function (TestRunner $t) use ($roundTripExistingRawValue): void {
        foreach ([
            'hello world',
            "\ta",
            ' a',
            "a\t",
            'a ',
            '"hello"\"there"\\\b\x',
            "a\nb   \n\t   c",
            ';hello ',
            ' # hello',
        ] as $value) {
            $t->same($value, $roundTripExistingRawValue($value), $value);
        }
    },

    'upstream gix-config file::access::raw::set_raw_value creates missing section and subsection' => static function (TestRunner $t): void {
        $config = GitConfig::fromString('');
        $config->setRawValueBy('new', null, 'key', 'value');
        $config->setRawValueBy('new', 'subsection', 'key', 'subsection-value');

        $roundTrip = GitConfig::fromString($config->toString());
        $t->same('value', $roundTrip->rawValue('new', null, 'key'));
        $t->same('subsection-value', $roundTrip->rawValue('new', 'subsection', 'key'));
    },

    'upstream gix-config file::access::raw::set_existing_raw_value rejects missing values' => static function (TestRunner $t): void {
        $config = GitConfig::fromString('');
        $t->throws(RuntimeException::class, static fn () => $config->setExistingRawValueBy('new', null, 'key', 'value'));
        $t->throws(InvalidArgumentException::class, static fn () => GitConfig::fromString("a=b\n[core]\na=c")->setExistingRawValueBy('', null, 'a', 'd'));
    },

    'upstream gix-config file::write canonical serialization can be parsed again' => static function (TestRunner $t): void {
        $config = GitConfig::fromString('');
        $config->setRawValueBy('core', null, 'repositoryformatversion', '0');
        $config->setRawValueBy('remote', 'origin', 'url', 'git@github.com:GitoxideLabs/gitoxide.git');
        $config->setRawValueBy('test', 'sub-section "special" C:\\root', 'escaped-quoted', "\n\thi\x08");

        $serialized = $config->toString();
        $roundTrip = GitConfig::fromString($serialized);
        $t->same('0', $roundTrip->rawValue('core', null, 'repositoryformatversion'));
        $t->same('git@github.com:GitoxideLabs/gitoxide.git', $roundTrip->rawValue('remote', 'origin', 'url'));
        $t->same("\n\thi\x08", $roundTrip->rawValue('test', 'sub-section "special" C:\\root', 'escaped-quoted'));
    },
];
