<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Node\ContainerNode;
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
        public readonly ?string $enctype = null,
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

    /**
     * Explicit enctype wins; otherwise multipart when the static tree contains
     * a FileField anywhere, even inside a hidden group.
     */
    public function enctype(): string
    {
        if ($this->enctype !== null) {
            return $this->enctype;
        }
        return self::containsFileField($this->children) ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
    }

    /** @param list<FormNode> $nodes */
    private static function containsFileField(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof FileField) {
                return true;
            }
            if ($node instanceof ContainerNode && self::containsFileField($node->children())) {
                return true;
            }
        }
        return false;
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
            $this->enctype,
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
