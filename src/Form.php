<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Node\ContainerNode;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\InlineLayout;
use Kaly\Forms\Node\Layout;
use Kaly\Forms\Render\ControlIds;
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
        // Browsers treat any other method (PUT, DELETE...) as GET and would
        // send every field, passwords included, in the query string. Use a
        // hidden `_method` field for framework method spoofing.
        if (!in_array(strtolower($method), ['get', 'post', 'dialog'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported form method "%s": use get, post or dialog (spoof other verbs with a hidden _method field)',
                $method,
            ));
        }
        // Fail at definition time: duplicate ids silently break labels and aria references.
        ControlIds::assertUnique($name, $children);
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

    /**
     * Errors that no rendered field row can display: their field is absent
     * from the tree (renamed field, misnamed application error) or is a
     * HiddenField. Renderers show them with the global errors so an invalid
     * form never comes back without a message.
     *
     * @return list<FormError>
     */
    public function unmatchedErrors(): array
    {
        $names = [];
        self::collectDisplayedNames($this->children, $names);
        return array_values(array_filter(
            $this->state->errors(),
            static fn(FormError $e): bool => $e->field !== null && !isset($names[$e->field]),
        ));
    }

    /**
     * @param list<FormNode> $nodes
     * @param array<string,true> $names
     */
    private static function collectDisplayedNames(array $nodes, array &$names): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Field && !$node instanceof HiddenField) {
                $names[$node->name] = true;
            } elseif ($node instanceof ContainerNode) {
                self::collectDisplayedNames($node->children(), $names);
            }
        }
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
