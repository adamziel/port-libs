<?php

declare(strict_types=1);

namespace PortLibs\Pandoc\PlainMath;

use InvalidArgumentException;

final class MathMlWriter
{
    private const MATHML_NAMESPACE = 'http://www.w3.org/1998/Math/MathML';

    public function __construct(
        private readonly MathMlBuilder $builder = new MathMlBuilder(),
    ) {
    }

    /**
     * @param Expression|list<Expression> $expressions
     */
    public function writeDocument(Expression|array $expressions, bool $display = false, string $annotation = ''): string
    {
        $attributes = ['xmlns' => self::MATHML_NAMESPACE];
        if ($display) {
            $attributes['display'] = 'block';
        }

        $semantics = $this->writeFragment($expressions)
            . $this->builder->element(
                'annotation',
                $this->builder->text($annotation),
                ['encoding' => 'application/x-tex']
            );

        return $this->builder->element(
            'math',
            $this->builder->element('semantics', $semantics),
            $attributes
        );
    }

    /**
     * @param Expression|list<Expression> $expressions
     */
    public function writeFragment(Expression|array $expressions): string
    {
        if ($expressions instanceof Expression) {
            return $this->writeExpression($expressions);
        }

        return $this->writeRow($expressions);
    }

    private function writeExpression(Expression $expression): string
    {
        return match ($expression->kind) {
            'number' => $this->tokenElement('mn', $expression),
            'identifier' => $this->tokenElement('mi', $expression),
            'operator' => $this->tokenElement('mo', $expression),
            'mathOperator' => $this->tokenElement('mi', $expression),
            'text' => $this->tokenElement('mtext', $expression),
            'space' => $this->builder->element('mspace', '', $expression->attributes),
            'row' => $this->writeRow($expression->children, (bool) ($expression->attributes['forceMrow'] ?? false)),
            'super' => $this->scriptElement('msup', $expression, 2),
            'sub' => $this->scriptElement('msub', $expression, 2),
            'subsup' => $this->scriptElement('msubsup', $expression, 3),
            'fraction' => $this->fixedArityElement('mfrac', $expression, 2),
            'sqrt' => $this->fixedArityElement('msqrt', $expression, 1),
            'root' => $this->fixedArityElement('mroot', $expression, 2),
            'over' => $this->fixedArityElement('mover', $expression, 2),
            'under' => $this->fixedArityElement('munder', $expression, 2),
            'enclosed' => $this->fixedArityElement('menclose', $expression, 1),
            'style' => $this->fixedArityElement('mstyle', $expression, 1),
            'delimited' => $this->writeDelimited($expression),
            'table' => $this->writeTable($expression),
            'tableRow' => $this->writeTableRow($expression),
            'tableCell' => $this->writeTableCell($expression),
            default => throw new InvalidArgumentException('Unsupported PlainMath expression kind: ' . $expression->kind),
        };
    }

    private function tokenElement(string $element, Expression $expression): string
    {
        return $this->builder->element(
            $element,
            $this->builder->text((string) $expression->value),
            $expression->attributes
        );
    }

    /**
     * @param list<Expression> $children
     */
    private function writeRow(array $children, bool $forceMrow = false): string
    {
        $content = '';
        foreach ($children as $child) {
            $content .= $this->writeExpression($child);
        }

        if (!$forceMrow && count($children) === 1) {
            return $content;
        }

        return $this->builder->element('mrow', $content);
    }

    private function scriptElement(string $element, Expression $expression, int $arity): string
    {
        return $this->fixedArityElement($element, $expression, $arity);
    }

    private function fixedArityElement(string $element, Expression $expression, int $arity): string
    {
        if (count($expression->children) !== $arity) {
            throw new InvalidArgumentException($expression->kind . ' expects ' . $arity . ' child expressions.');
        }

        $content = '';
        foreach ($expression->children as $child) {
            $content .= $this->writeExpression($child);
        }

        return $this->builder->element($element, $content, $expression->attributes);
    }

    private function writeDelimited(Expression $expression): string
    {
        if (count($expression->children) !== 1) {
            throw new InvalidArgumentException('delimited expects one body expression.');
        }

        $content = '';
        if (is_string($expression->attributes['left'] ?? null)) {
            $content .= $this->builder->element(
                'mo',
                $this->builder->text((string) $expression->attributes['left']),
                ['stretchy' => 'true'] + $this->formAttribute($expression, 'leftForm')
            );
        }

        $content .= $this->writeExpression($expression->children[0]);

        if (is_string($expression->attributes['right'] ?? null)) {
            $content .= $this->builder->element(
                'mo',
                $this->builder->text((string) $expression->attributes['right']),
                ['stretchy' => 'true'] + $this->formAttribute($expression, 'rightForm')
            );
        }

        return $this->builder->element('mrow', $content);
    }

    /**
     * @return array<string, string>
     */
    private function formAttribute(Expression $expression, string $key): array
    {
        return is_string($expression->attributes[$key] ?? null)
            ? ['form' => (string) $expression->attributes[$key]]
            : [];
    }

    private function writeTable(Expression $expression): string
    {
        $content = '';
        foreach ($expression->children as $row) {
            $content .= $this->writeExpression($row);
        }

        return $this->builder->element('mtable', $content, $expression->attributes);
    }

    private function writeTableRow(Expression $expression): string
    {
        $content = '';
        foreach ($expression->children as $cell) {
            $content .= $this->writeExpression($cell);
        }

        return $this->builder->element('mtr', $content);
    }

    private function writeTableCell(Expression $expression): string
    {
        if (count($expression->children) !== 1) {
            throw new InvalidArgumentException('tableCell expects one child expression.');
        }

        return $this->builder->element('mtd', $this->writeExpression($expression->children[0]));
    }
}
