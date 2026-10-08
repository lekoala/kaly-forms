<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Fields;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Validation\Checked;
use Kaly\Forms\Validation\Email;
use Kaly\Forms\Validation\FilePresence;
use Kaly\Forms\Validation\Length;
use Kaly\Forms\Validation\Numeric;
use Kaly\Forms\Validation\StructuralValidator;
use PHPUnit\Framework\TestCase;

final class ContractsTest extends TestCase
{
    public function testScalarRulesRejectArrays(): void
    {
        $this->assertNotNull((new Email())->validate('email', ['bad'], []));
        $this->assertNotNull((new Numeric())->validate('price', ['bad'], []));
        $this->assertNotNull((new Length(min: 2))->validate('name', ['bad'], []));

        // End-to-end: email[]=bad fails structural validation even when required.
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->email('email', required: true),
            $fields->numeric('price', required: true),
            $fields->text('nick', minLength: 2),
        ]);
        $state = (new StructuralValidator())->validate($form, ['email' => ['bad'], 'price' => ['bad'], 'nick' => ['bad']]);
        $this->assertFalse($state->isValid());
        $this->assertCount(1, $state->errorsFor('email'));
        $this->assertCount(1, $state->errorsFor('price'));
        $this->assertCount(1, $state->errorsFor('nick'));
    }

    public function testMultipleControlsUseBracketNamesWithLogicalIdentity(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->multipleSelect('tags', label: 'Tags', choices: ['a' => 'A']),
            $fields->file('documents', label: 'Docs', multiple: true),
        ]);

        $this->assertStringContainsString('name="tags[]"', $html);
        $this->assertStringContainsString('data-kf-field="tags"', $html);
        $this->assertStringContainsString('name="documents[]"', $html);
        $this->assertStringContainsString('data-kf-field="documents"', $html);

        // Logical name still drives state.
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->multipleSelect('tags', label: 'Tags', choices: ['a' => 'A', 'b' => 'B'], required: true),
        ]);
        $this->assertCount(
            0,
            (new StructuralValidator())
                ->validate($form, ['tags' => ['a']])
                ->errorsFor('tags'),
        );
        $this->assertCount(
            1,
            (new StructuralValidator())
                ->validate($form, [])
                ->errorsFor('tags'),
        );
    }

    public function testConditionalRequiredProjectsNothing(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'reg', action: '/r', children: [
            $fields->text('company', label: 'Company', required: true, visibleWhen: Condition::equals('kind', 'pro')),
        ]);

        $this->assertStringNotContainsString('required', $html);
        $this->assertStringContainsString('data-kf-visible-field="kind"', $html);
    }

    public function testCheckedAcceptsCanonicalValuesOnly(): void
    {
        $rule = new Checked();
        foreach ([true, 1, '1', 'on'] as $ok) {
            $this->assertNull($rule->validate('terms', $ok, []), var_export($ok, true));
        }
        foreach ([false, 0, '0', 'false', 'true', '', null] as $ko) {
            $this->assertNotNull($rule->validate('terms', $ko, []), var_export($ko, true));
        }
        $this->assertSame(['required' => true], $rule->htmlAttributes());
    }

    public function testRadioGroupRequiresOneNativelyCheckboxGroupDoesNot(): void
    {
        $fields = new Fields();
        $radio = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->radio('level', label: 'Level', choices: ['a' => 'A', 'b' => 'B'], required: true),
        ]);
        $this->assertSame(1, substr_count($radio, 'required'));
        $this->assertStringContainsString('<fieldset', $radio);
        $this->assertStringContainsString('<legend>Level</legend>', $radio);

        $group = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->checkboxGroup('interests', label: 'Interests', choices: ['php' => 'PHP', 'js' => 'JS'], required: true),
        ]);
        $this->assertStringNotContainsString('required', $group);
        $this->assertStringContainsString('<fieldset', $group);
        $this->assertStringContainsString('<legend>Interests</legend>', $group);
    }

    public function testConditionStrictness(): void
    {
        $this->assertFalse(Condition::equals('code', '1')->matches(['code' => '01']));
        $this->assertTrue(Condition::equals('code', '1')->matches(['code' => '1']));
        $this->assertTrue(Condition::equals('code', '1')->matches(['code' => 1]));
        // Absent is null, distinct from "".
        $this->assertFalse(Condition::equals('code', '')->matches([]));
        $this->assertTrue(Condition::equals('code', null)->matches([]));
        $this->assertTrue(Condition::notEquals('code', '')->matches([]));
        // Lists: stringify + unique + sort.
        $this->assertTrue(Condition::equals('tags', ['a', 'b'])->matches(['tags' => ['b', 'a', 'a']]));
        $this->assertTrue(Condition::filled('tags')->matches(['tags' => ['a']]));
        $this->assertFalse(Condition::filled('tags')->matches(['tags' => []]));
        $this->assertFalse(Condition::filled('code')->matches([]));
    }

    public function testIdsAreFormScopedAndCustomIdWins(): void
    {
        $fields = new Fields();
        $one = (string) (new FormFactory())->create(name: 'login', action: '/l', children: [$fields->text('email', label: 'Email')]);
        $two = (string) (new FormFactory())->create(name: 'register', action: '/r', children: [$fields->text('email', label: 'Email')]);

        $this->assertStringContainsString('for="kf-login-email"', $one);
        $this->assertStringContainsString('id="kf-login-email"', $one);
        $this->assertStringContainsString('for="kf-register-email"', $two);
        $this->assertStringNotContainsString('kf-login-email', $two);

        $custom = (string) (new FormFactory())->create(name: 'b', action: '/b', children: [
            $fields->text('email', label: 'Email', attributes: ['id' => 'billing-email']),
        ]);
        $this->assertStringContainsString('for="billing-email"', $custom);
        $this->assertStringContainsString('id="billing-email"', $custom);
    }

    public function testDisabledCustomElementDisablesMirrorWithoutRequired(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->customElement('city', tag: 'city-picker', label: 'City', attributes: ['disabled' => true]),
        ]);

        $this->assertStringContainsString('data-kf-mirror-for="kf-a-city"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="hidden"[^>]*disabled[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="hidden"[^>]*required[^>]*>/', $html);
    }

    public function testFileRequiredUsesPresenceAdapter(): void
    {
        $fields = new Fields();
        $children = [$fields->file('cv', label: 'CV', required: true)];
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: $children);

        // No adapter: skipped, application owns the check.
        $this->assertCount(
            0,
            (new StructuralValidator())
                ->validate($form, [])
                ->errorsFor('cv'),
        );

        $absent = new StructuralValidator(new class() implements FilePresence {
            public function has(string $field): bool
            {
                return false;
            }
        });
        $this->assertCount(1, $absent->validate($form, [])->errorsFor('cv'));

        $present = new StructuralValidator(new class() implements FilePresence {
            public function has(string $field): bool
            {
                return $field === 'cv';
            }
        });
        $this->assertCount(0, $present->validate($form, [])->errorsFor('cv'));
    }

    public function testLengthCountsCharacters(): void
    {
        $rule = new Length(min: 2, max: 2);
        $this->assertNull($rule->validate('n', 'éé', []));
        $this->assertNotNull($rule->validate('n', 'é', []));
    }

    public function testStateIsolationBetweenRenders(): void
    {
        $fields = new Fields();
        $children = [$fields->text('name', label: 'Name')];
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: $children);

        $one = (string) $form->withState(FormState::from(['name' => 'Alice']));
        $two = (string) $form->withState(FormState::from(['name' => 'Bob']));

        $this->assertStringContainsString('value="Alice"', $one);
        $this->assertStringNotContainsString('Bob', $one);
        $this->assertStringContainsString('value="Bob"', $two);
        $this->assertStringNotContainsString('Alice', $two);
    }
}
