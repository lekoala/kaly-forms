<?php

declare(strict_types=1);

namespace Kaly\Forms\Render;

use Kaly\Forms\Field\Field;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;

final class FieldRendererRegistry
{
    /** @var array<class-string<Field>, callable(Field,mixed,FormState,HtmlRenderer):Html> */
    private array $renderers = [];

    /** @param class-string<Field> $fieldClass @param callable(Field,mixed,FormState,HtmlRenderer):Html $renderer */
    public function register(string $fieldClass, callable $renderer): self
    {
        $this->renderers[$fieldClass] = $renderer;
        return $this;
    }

    public function render(Field $field, mixed $value, FormState $state, HtmlRenderer $html): Html
    {
        $class = $field::class;
        if (isset($this->renderers[$class])) {
            return $this->renderers[$class]($field, $value, $state, $html);
        }

        foreach ($this->renderers as $registered => $renderer) {
            if ($field instanceof $registered) {
                return $renderer($field, $value, $state, $html);
            }
        }

        throw new \LogicException(sprintf('No renderer registered for field %s', $class));
    }
}
