<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\CheckboxField;
use Kaly\Forms\Field\ChoiceField;
use Kaly\Forms\Field\CustomElementField;
use Kaly\Forms\Field\EmailField;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\FormFactory;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Interaction\RemoteOptions;
use Kaly\Forms\Validation\StructuralValidator;

$forms = new FormFactory();

// The application owns CSRF: the real token is injected at runtime, never hard-coded.
$csrfToken = $_SERVER['CSRF_TOKEN'] ?? '';
$csrfToken = is_string($csrfToken) ? $csrfToken : '';

$form = $forms->create(
    name: 'registration',
    action: '/registrations',
    fields: [
        new HiddenField('_csrf'),
        new TextField('firstName', label: 'First name', required: true, autocomplete: 'given-name'),
        new TextField('lastName', label: 'Last name', required: true, autocomplete: 'family-name'),
        new EmailField('email', label: 'Email', required: true),
        new ChoiceField('country', label: 'Country', choices: ['BE' => 'Belgium', 'FR' => 'France'], required: true),
        new TextField(
            'vatNumber',
            label: 'VAT number',
            help: 'Only requested for Belgian customers',
            visibleWhen: Condition::equals('country', 'BE'),
        ),
        new ChoiceField('city', label: 'City', remote: new RemoteOptions('/cities/suggest', minChars: 2, csrfToken: $csrfToken)),
        new CustomElementField('address', tag: 'address-picker', label: 'Address', attributes: ['data-country-field' => 'country']),
        new CheckboxField('consent', label: 'I accept the terms and conditions', required: true),
    ],
    actions: [new SubmitAction('save', 'Register')],
    attributes: ['data-enhance' => 'form'],
);

$values = [
    '_csrf' => $csrfToken,
    'firstName' => 'Ada',
    'lastName' => '',
    'email' => 'not-an-email',
    'country' => 'BE',
    'vatNumber' => '',
    'city' => '',
    'address' => '',
    'consent' => false,
];

$state = (new StructuralValidator())->validate($form, $values);
$form = $form->withState($state);

// In a plain PHP template, rendering is just: echo $form;
echo $form;
