<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Node\ContainerNode;
use Kaly\Forms\Node\Fieldset;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\Group;
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
        // These are owned by constructor parameters (and validated there):
        // an attribute would silently override them at render time.
        foreach (['name', 'action', 'method', 'enctype'] as $owned) {
            if (array_key_exists($owned, $attributes)) {
                throw new \InvalidArgumentException(sprintf('Form attribute "%s" must be set through the $%s parameter', $owned, $owned));
            }
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
     * Errors that no visible field row can display: their field is absent
     * from the tree (renamed field, misnamed application error), is a
     * HiddenField, or sits in a branch that is inactive for the state values
     * (own condition or ancestor Group/Fieldset), which an enhancement would
     * hide. Renderers show them with the global errors so an invalid form
     * never comes back without a visible message.
     *
     * @return list<FormError>
     */
    public function unmatchedErrors(): array
    {
        $names = [];
        self::collectDisplayedNames($this->children, $this->state->values(), $names);
        return array_values(array_filter(
            $this->state->errors(),
            static fn(FormError $e): bool => $e->field !== null && !isset($names[$e->field]),
        ));
    }

    /**
     * Same activity rules as StructuralValidator.
     *
     * @param list<FormNode> $nodes
     * @param array<string,mixed> $values
     * @param array<string,true> $names
     */
    private static function collectDisplayedNames(array $nodes, array $values, array &$names): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Field) {
                if (!$node instanceof HiddenField && $node->isActiveFor($values)) {
                    $names[$node->name] = true;
                }
            } elseif ($node instanceof ContainerNode) {
                if (($node instanceof Group || $node instanceof Fieldset) && !$node->isActiveFor($values)) {
                    continue;
                }
                self::collectDisplayedNames($node->children(), $values, $names);
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
