<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class Commit
{
    /**
     * @param list<string> $parents
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly string $tree,
        public readonly array $parents,
        public readonly string $author,
        public readonly string $committer,
        public readonly string $message,
        public readonly array $headers,
        public readonly ?string $encoding = null,
        public readonly array $extraHeaders = [],
    ) {
    }

    public static function parse(string $body, string $algorithm = 'sha1'): self
    {
        [$headerBlock, $message] = array_pad(explode("\n\n", $body, 2), 2, '');
        $headers = [];
        $current = null;

        foreach (explode("\n", $headerBlock) as $line) {
            if ($line === '') {
                continue;
            }
            if ($line[0] === ' ') {
                if ($current === null) {
                    throw new \InvalidArgumentException('Commit continuation line without a header');
                }
                $last = array_key_last($headers[$current]);
                $headers[$current][$last] .= "\n" . substr($line, 1);
                continue;
            }

            $space = strpos($line, ' ');
            if ($space === false) {
                throw new \InvalidArgumentException('Invalid commit header line: ' . $line);
            }
            $current = substr($line, 0, $space);
            $headers[$current][] = substr($line, $space + 1);
        }

        $hashLength = ReferenceTarget::hashHexLength($algorithm);
        foreach (['tree', 'author', 'committer'] as $required) {
            if (($headers[$required] ?? []) === []) {
                throw new \InvalidArgumentException("Commit is missing required {$required} header");
            }
        }

        $tree = strtolower($headers['tree'][0]);
        if (!preg_match('/^[0-9a-f]{' . $hashLength . '}$/', $tree)) {
            throw new \InvalidArgumentException("Commit tree must be a {$hashLength}-character {$algorithm} hex object id");
        }

        $parents = array_map('strtolower', $headers['parent'] ?? []);
        foreach ($parents as $parent) {
            if (!preg_match('/^[0-9a-f]{' . $hashLength . '}$/', $parent)) {
                throw new \InvalidArgumentException("Commit parent must be a {$hashLength}-character {$algorithm} hex object id");
            }
        }

        CommitSignature::parse($headers['author'][0]);
        CommitSignature::parse($headers['committer'][0]);

        $extraHeaders = [];
        foreach ($headers as $name => $values) {
            if (in_array($name, ['tree', 'parent', 'author', 'committer', 'encoding'], true)) {
                continue;
            }
            $extraHeaders[$name] = $values;
        }

        return new self(
            $tree,
            $parents,
            $headers['author'][0],
            $headers['committer'][0],
            $message,
            $headers,
            $headers['encoding'][0] ?? null,
            $extraHeaders,
        );
    }

    public function authorSignature(): CommitSignature
    {
        return CommitSignature::parse($this->author);
    }

    public function committerSignature(): CommitSignature
    {
        return CommitSignature::parse($this->committer);
    }
}
