<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Fields;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;

// Same definition as filepond.php and filepond-async.php: only the profile changes.
$csrfToken = $_SERVER['DEMO_CSRF_TOKEN'] ?? '';
$csrfToken = is_string($csrfToken) ? $csrfToken : '';

$fields = new Fields();

$form = (new FormFactory())->create(
    name: 'documents',
    action: '/upload.php',
    children: [
        new HiddenField('_csrf'),
        $fields->file(name: 'documents', label: 'Documents', accept: ['image/*', 'application/pdf'], multiple: true, required: true),
    ],
    actions: [new SubmitAction('send', 'Send')],
);

echo $form->withState(FormState::from(['_csrf' => $csrfToken]));
