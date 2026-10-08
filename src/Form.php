<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\InlineLayout;
use Kaly\Forms\Node\Layout;
use Kaly\Forms\Render\RendererInterface;
use Kaly\Forms\Render\RenderProfile;

final class Form implements HtmlRenderable
{
    private readonly RenderProfile $profile;

    /**
     * @param list<FormNode> $children
     * @param list<SubmitAction> $actions
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $action,
        public readonly string $method,
        private readonly array $children,
        private readonly array $actions,
        private readonly RendererInterface $renderer,
        private readonly FormState $state = new FormState(),
        public readonly array $attributes = [],
        public readonly Layout $actionsLayout = new InlineLayout(),
        ?RenderProfile $profile = null,
    ) {
        $this->profile = $profile ?? RenderProfile::plain();
    }

    /** @return list<FormNode> */
    public function children(): array
    {
        return $this->children;
    }

    /** @return list<SubmitAction> */
    public function actions(): array
    {
        return $this->actions;
    }

    public function actionsLayout(): Layout
    {
        return $this->actionsLayout;
    }

    public function state(): FormState
    {
        return $this->state;
    }

    public function profile(): RenderProfile
    {
        return $this->profile;
    }

    public function withState(FormState $state): self
    {
        return new self(
            $this->name,
            $this->action,
            $this->method,
            $this->children,
            $this->actions,
            $this->renderer,
            $state,
            $this->attributes,
            $this->actionsLayout,
            $this->profile,
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
