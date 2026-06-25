<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

final class MathMlBuilder
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function element(string $name, string $content = '', array $attributes = []): string
    {
        return '<' . $name . $this->attributes($attributes) . '>' . $content . '</' . $name . '>';
    }

    public function text(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function attributes(array $attributes): string
    {
        $xml = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $xml .= ' ' . $name . '="' . $this->text((string) $value) . '"';
        }

        return $xml;
    }
}
