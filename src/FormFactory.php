<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\InlineLayout;
use Kaly\Forms\Node\Layout;
use Kaly\Forms\Render\HtmlRenderer;
use Kaly\Forms\Render\RendererInterface;
use Kaly\Forms\Render\RenderProfile;

final readonly class FormFactory
{
    public function __construct(
        private RendererInterface $renderer = new HtmlRenderer(),
        private ?RenderProfile $profile = null,
    ) {}

    /**
     * @param list<FormNode> $children
     * @param list<SubmitAction> $actions
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function create(
        string $name,
        string $action,
        string $method = 'post',
        array $children = [],
        array $actions = [],
        array $attributes = [],
        Layout $actionsLayout = new InlineLayout(),
        ?RenderProfile $profile = null,
    ): Form {
        return new Form(
            $name,
            $action,
            $method,
            $children,
            $actions,
            $this->renderer,
            attributes: $attributes,
            actionsLayout: $actionsLayout,
            profile: $profile ?? $this->profile ?? RenderProfile::plain(),
        );
    }
}
