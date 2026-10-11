<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Fields;
use Kaly\Forms\FormError;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Node\Group;
use PHPUnit\Framework\TestCase;

/**
 * "Render on error, redirect on success": a failed POST re-renders the same
 * form from FormState, without session or flash storage.
 */
final class RerenderTest extends TestCase
{
    public function testFailedSubmissionRerendersValuesSelectionsAndErrors(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'p', action: '/p', children: [
            new HiddenField('_csrf'),
            $fields->text('name', label: 'Name'),
            $fields->textarea('bio', label: 'Bio'),
            $fields->password('password', label: 'Password'),
            $fields->choice('country', label: 'Country', choices: ['be' => 'BE', 'fr' => 'FR']),
            $fields->radio('plan', label: 'Plan', choices: ['free' => 'Free', 'pro' => 'Pro']),
            $fields->checkbox('news', label: 'News'),
            $fields->checkboxGroup('tags', label: 'Tags', choices: ['a' => 'A', 'b' => 'B']),
            $fields->multipleSelect('langs', label: 'Langs', choices: ['fr' => 'FR', 'nl' => 'NL']),
            $fields->file('avatar', label: 'Avatar'),
        ]);

        $typed = bin2hex(random_bytes(8));
        $html = (string) $form->withState(FormState::from(
            [
                // The application passes the current token, not the submitted one.
                '_csrf' => 'fresh-token',
                'name' => 'Ada <Lovelace>',
                'bio' => "line 1\nline 2",
                'password' => $typed,
                'country' => 'fr',
                'plan' => 'pro',
                'news' => '1',
                'tags' => ['b'],
                'langs' => ['nl'],
                'avatar' => 'C:\fakepath\me.png',
            ],
            [new FormError('Name is taken', 'name'), new FormError('Please retry')],
        ));

        $this->assertStringContainsString('name="_csrf" type="hidden" value="fresh-token"', $html);
        $this->assertStringContainsString('value="Ada &lt;Lovelace&gt;"', $html);
        $this->assertStringContainsString(">line 1\nline 2</textarea>", $html);
        $this->assertStringContainsString('<option value="fr" selected>FR</option>', $html);
        $this->assertMatchesRegularExpression('/value="pro" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="free" checked/', $html);
        $this->assertMatchesRegularExpression('/name="news" type="checkbox" value="1" data-kf-field="news" checked/', $html);
        $this->assertMatchesRegularExpression('/value="b" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="a" checked/', $html);
        $this->assertStringContainsString('<option value="nl" selected>NL</option>', $html);

        // Secrets and uploads are never re-injected.
        $this->assertStringNotContainsString($typed, $html);
        $this->assertStringNotContainsString('fakepath', $html);

        $this->assertStringContainsString('role="alert">Please retry</div>', $html);
        $this->assertStringContainsString('id="kf-p-name-error-0">Name is taken</div>', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
    }

    public function testErrorsInAnInactiveBranchAreShownGlobally(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'p', action: '/p', children: [
            $fields->choice('kind', label: 'Kind', choices: ['private' => 'Private', 'pro' => 'Pro']),
            $fields->text('vat', label: 'VAT', visibleWhen: Condition::equals('kind', 'pro')),
            new Group([$fields->text('company', label: 'Company')], visibleWhen: Condition::equals('kind', 'pro')),
            $fields->text('name', label: 'Name'),
        ]);
        $errors = [
            new FormError('VAT is invalid', 'vat'),
            new FormError('Company is unknown', 'company'),
            new FormError('Name is taken', 'name'),
        ];

        // Branch inactive for the submitted values: an enhancement hides it,
        // so its errors must also appear in the global zone.
        $inactive = $form->withState(FormState::from(['kind' => 'private'], $errors));
        $this->assertSame(
            ['VAT is invalid', 'Company is unknown'],
            array_map(static fn(FormError $e): string => $e->message, $inactive->unmatchedErrors()),
        );
        $html = (string) $inactive;
        // The summary names the field (renderer presentation), the message is untouched.
        $this->assertStringContainsString('role="alert"><strong>VAT</strong>: VAT is invalid</div>', $html);
        $this->assertStringContainsString('role="alert"><strong>Company</strong>: Company is unknown</div>', $html);
        $this->assertStringNotContainsString('</strong>: Name is taken', $html);
        $this->assertSame('VAT is invalid', $inactive->unmatchedErrors()[0]->message);

        // Active branch: errors stay on their rows only.
        $active = $form->withState(FormState::from(['kind' => 'pro'], $errors));
        $this->assertSame([], $active->unmatchedErrors());
    }
}
