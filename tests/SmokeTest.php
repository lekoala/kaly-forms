<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\EmailField;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\FormFactory;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\StructuralValidator;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testStructuralValidationAndRendering(): void
    {
        $form = (new FormFactory())->create(
            name: 'account',
            action: '/account',
            children: [
                new EmailField('email', label: 'Email', required: true),
                new TextField('company', label: 'Company', required: true, visibleWhen: Condition::equals('kind', 'pro')),
                new TextField('kind', label: 'Kind'),
            ],
            actions: [new SubmitAction('save', 'Save')],
        );

        $validator = new StructuralValidator();
        $state = $validator->validate($form, ['email' => 'bad', 'kind' => 'personal', 'company' => '']);

        $this->assertCount(1, $state->errorsFor('email'));
        // hidden condition => structural rule skipped
        $this->assertCount(0, $state->errorsFor('company'));

        $html = (string) $form->withState($state);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('Enter a valid email address', $html);
        $this->assertStringContainsString('data-kf-visible-field="kind"', $html);
        $this->assertStringContainsString('<button', $html);
    }
}
