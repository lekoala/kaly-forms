<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Render\RendererInterface;

final class Form implements HtmlRenderable
{
    /**
     * @param list<Field> $fields
     * @param list<SubmitAction> $actions
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $action,
        public readonly string $method,
        private readonly array $fields,
        private readonly array $actions,
        private readonly RendererInterface $renderer,
        private readonly FormState $state = new FormState(),
        public readonly array $attributes = [],
    ) {}

    /** @return list<Field> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return list<SubmitAction> */
    public function actions(): array
    {
        return $this->actions;
    }

    public function state(): FormState
    {
        return $this->state;
    }

    public function withState(FormState $state): self
    {
        return new self(
            $this->name,
            $this->action,
            $this->method,
            $this->fields,
            $this->actions,
            $this->renderer,
            $state,
            $this->attributes,
        );
    }

    public function toHtml(): Html
    {
        return $this->renderer->render($this);
    }

    public function __toString(): string
    {
        return $this->toHtml()->value();
    }
}
