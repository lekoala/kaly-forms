<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\Field;
use Kaly\Forms\FormFactory;
use Kaly\Forms\Html;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Render\DefaultTheme;
use Kaly\Forms\Render\NodeRendererRegistry;
use Kaly\Forms\Render\RenderContext;
use Kaly\Forms\Render\RenderProfile;

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

$renderers = NodeRendererRegistry::defaults();
$renderers->register(MoneyField::class, static function (FormNode $node, RenderContext $context): Html {
    /** @var MoneyField $field */ $field = $node;
    $control =
        '<span class="money-field"><input'
        . $context->attrs([
            'name' => $field->name,
            'inputmode' => 'decimal',
            'value' => $context->textValue($context->value($field)),
        ])
        . '><span>'
        . $context->e($field->currency)
        . '</span></span>';
    return $context->fieldRow($field, $control);
});
$profile = new RenderProfile(new DefaultTheme(), $renderers);

$form = (new FormFactory(profile: $profile))->create(
    name: 'price',
    action: '/price',
    children: [new MoneyField('amount', 'Amount')],
    actions: [new SubmitAction('save', 'Save')],
);

echo $form;
