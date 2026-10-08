<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Render\HtmlRenderer;
use Kaly\Forms\Render\RendererInterface;

final readonly class FormFactory
{
    public function __construct(
        private RendererInterface $renderer = new HtmlRenderer(),
    ) {}

    /**
     * @param list<Field> $fields
     * @param list<SubmitAction> $actions
     * @param array<string,string|int|float|bool|null> $attributes
     */
    public function create(
        string $name,
        string $action,
        string $method = 'post',
        array $fields = [],
        array $actions = [],
        array $attributes = [],
    ): Form {
        return new Form($name, $action, $method, $fields, $actions, $this->renderer, attributes: $attributes);
    }
}
