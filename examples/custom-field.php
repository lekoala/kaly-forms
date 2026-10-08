<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\Field;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;
use Kaly\Forms\Render\HtmlRenderer;

final class MoneyField extends Field
{
    public function __construct(
        string $name,
        ?string $label = null,
        public readonly string $currency = 'EUR',
    ) {
        parent::__construct($name, $label);
    }
}

$renderer = new HtmlRenderer();
$renderer->fieldRenderers()->register(MoneyField::class, static function (
    Field $raw,
    mixed $value,
    FormState $state,
    HtmlRenderer $html,
): Html {
    /** @var MoneyField $field */ $field = $raw;
    return new Html(
        '<span class="money-field"><input'
        . $html->attrs([
            'name' => $field->name,
            'inputmode' => 'decimal',
            'value' => $html->textValue($value),
        ])
        . '><span>'
        . $html->e($field->currency)
        . '</span></span>',
    );
});

$form = (new FormFactory($renderer))->create(
    name: 'price',
    action: '/price',
    fields: [new MoneyField('amount', 'Amount')],
    actions: [new SubmitAction('save', 'Save')],
);

echo $form;
