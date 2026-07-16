<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class FilterRuleSet
{
    /** @var list<array{include: bool, regex: string, glob: string}> */
    private array $rules = [];

    /** @var array<string, true> */
    private array $existing = [];

    public function __construct(private readonly bool $ignoreCase = false)
    {
    }

    /**
     * @param list<string> $rules
     */
    public static function fromRules(array $rules, bool $ignoreCase = false): self
    {
        $set = new self($ignoreCase);
        foreach ($rules as $rule) {
            $set->addRule($rule);
        }

        return $set;
    }

    public function addRule(string $rule): void
    {
        if ($rule === '!') {
            $this->rules = [];
            $this->existing = [];
            return;
        }

        if (!str_starts_with($rule, '+ ') && !str_starts_with($rule, '- ')) {
            throw new \InvalidArgumentException("malformed filter rule {$rule}");
        }

        $this->add(substr($rule, 0, 1) === '+', substr($rule, 2));
    }

    public function add(bool $include, string $glob): void
    {
        $regex = Glob::pathToRegex($glob, $this->ignoreCase);
        $key = ($include ? '+' : '-') . ' ' . $regex;
        if (isset($this->existing[$key])) {
            return;
        }

        $this->rules[] = ['include' => $include, 'regex' => $regex, 'glob' => $glob];
        $this->existing[$key] = true;
    }

    public function includes(string $path): bool
    {
        foreach ($this->rules as $rule) {
            if (Glob::matchesRegex($rule['regex'], $path)) {
                return $rule['include'];
            }
        }

        return true;
    }

    public function includesRemote(string $path): bool
    {
        foreach ($this->rules as $rule) {
            if ($this->matchesRawRegex($rule['regex'], $path)) {
                return $rule['include'];
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function dump(): array
    {
        return array_map(
            static fn (array $rule): string => ($rule['include'] ? '+ ' : '- ') . $rule['regex'],
            $this->rules,
        );
    }

    private function matchesRawRegex(string $regex, string $path): bool
    {
        $result = preg_match('~' . str_replace('~', '\\~', $regex) . '~', preg_replace('#/+#', '/', $path) ?? $path);
        if ($result === false) {
            throw new \InvalidArgumentException("bad regex {$regex}");
        }

        return $result === 1;
    }
}
