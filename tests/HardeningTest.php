<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Field\CustomElementField;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Fields;
use Kaly\Forms\Form;
use Kaly\Forms\FormError;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Interaction\RemoteOptions;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Render\DefaultTheme;
use Kaly\Forms\Render\NodeRendererRegistry;
use Kaly\Forms\Render\RenderContext;
use Kaly\Forms\Render\RenderProfile;
use Kaly\Forms\Validation\Length;
use Kaly\Forms\Validation\Range;
use Kaly\Forms\Validation\StructuralValidator;
use Kaly\Forms\Validation\TemporalRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HardeningTest extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function invalidTags(): iterable
    {
        yield 'attribute injection' => ['x-a onfocus=alert(1) autofocus'];
        yield 'markup' => ['x-a><script'];
        yield 'uppercase' => ['X-Widget'];
        yield 'no hyphen' => ['widget'];
        yield 'leading digit' => ['1-widget'];
        yield 'reserved' => ['font-face'];
    }

    #[DataProvider('invalidTags')]
    public function testCustomElementTagFollowsTheNameGrammar(string $tag): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CustomElementField('x', tag: $tag);
    }

    public function testValidCustomElementTagIsAccepted(): void
    {
        $this->assertSame('date-picker.v2', (new CustomElementField('x', tag: 'date-picker.v2'))->tag);
    }

    /** @return iterable<string,array{string}> */
    public static function invalidAttributeNames(): iterable
    {
        yield 'space and handler' => ['data-x onclick=alert(1)'];
        yield 'quote' => ['a"b'];
        yield 'closing bracket' => ['a>b'];
        yield 'slash' => ['a/b'];
        yield 'equals' => ['a=b'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidAttributeNames')]
    public function testInvalidAttributeNamesAreRejected(string $name): void
    {
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            new TextField('nick', attributes: [$name => 'v']),
        ]);
        $this->expectException(\InvalidArgumentException::class);
        (string) $form;
    }

    public function testRemoteChoiceKeepsTheSubmittedValueOnRerender(): void
    {
        $fields = new Fields();
        $remote = new RemoteOptions('/cities');
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->choice('city', label: 'City', remote: $remote),
        ]);
        $html = (string) $form->withState(FormState::from(['city' => '42']));
        $this->assertStringContainsString('<option value="42" selected>42</option>', $html);

        // The application can pass the known label: no duplicate option.
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->choice('city', label: 'City', choices: ['42' => 'Paris'], remote: $remote),
        ]);
        $html = (string) $form->withState(FormState::from(['city' => '42']));
        $this->assertSame(1, substr_count($html, '<option'));
        $this->assertStringContainsString('<option value="42" selected>Paris</option>', $html);

        // A fixed select never invents an option for an unknown value.
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->choice('country', label: 'Country', choices: ['be' => 'Belgium']),
        ]);
        $html = (string) $form->withState(FormState::from(['country' => 'xx']));
        $this->assertStringNotContainsString('value="xx"', $html);
    }

    public function testNumericBoundsAreValidatedOnTheServer(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->numeric('n', label: 'N', min: 1, max: 10, step: 0.5),
        ]);
        $html = (string) $form;
        $this->assertSame(1, substr_count($html, 'min="1"'));
        $this->assertSame(1, substr_count($html, 'max="10"'));
        $this->assertSame(1, substr_count($html, 'step="0.5"'));

        $validator = new StructuralValidator();
        $this->assertSame('max', $validator->validate($form, ['n' => '999'])->errorsFor('n')[0]->code ?? null);
        $this->assertSame('min', $validator->validate($form, ['n' => '0'])->errorsFor('n')[0]->code ?? null);
        $this->assertSame('step', $validator->validate($form, ['n' => '1.25'])->errorsFor('n')[0]->code ?? null);
        $this->assertTrue($validator->validate($form, ['n' => '2.5'])->isValid());
        $this->assertTrue($validator->validate($form, ['n' => ''])->isValid());
    }

    public function testRangeStepIsAnchoredOnMinAndToleratesFloats(): void
    {
        $this->assertNull((new Range(min: 1, step: 2))->validate('n', '3', []));
        $this->assertNotNull((new Range(min: 1, step: 2))->validate('n', '4', []));
        $this->assertNull((new Range(step: 0.1))->validate('n', '0.3', []));
        $this->assertNull((new Range(step: 'any'))->validate('n', '0.123', []));
        // Non-numeric input belongs to the Numeric rule.
        $this->assertNull((new Range(max: 1))->validate('n', 'abc', []));
    }

    public function testTemporalFieldsValidateFormatAndBounds(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->date('d', label: 'D', min: '2024-01-01', max: '2024-12-31'),
            $fields->time('t', label: 'T', min: '09:00', max: '18:00'),
            $fields->dateTime('dt', label: 'DT'),
        ]);
        $validator = new StructuralValidator();

        $state = $validator->validate($form, ['d' => '2025-01-01', 't' => '08:59', 'dt' => '2024-05-01 10:00']);
        $this->assertSame('max', $state->errorsFor('d')[0]->code ?? null);
        $this->assertSame('min', $state->errorsFor('t')[0]->code ?? null);
        $this->assertSame('format', $state->errorsFor('dt')[0]->code ?? null);

        $this->assertSame('format', $validator->validate($form, ['d' => '2024-02-30'])->errorsFor('d')[0]->code ?? null);
        $this->assertSame('format', $validator->validate($form, ['t' => '25:00'])->errorsFor('t')[0]->code ?? null);
        $this->assertTrue($validator->validate($form, ['d' => '2024-02-29', 't' => '18:00:00', 'dt' => '2024-05-01T10:00'])->isValid());

        $html = (string) $form;
        $this->assertStringContainsString('min="2024-01-01" max="2024-12-31"', $html);
        $this->assertStringContainsString('min="09:00" max="18:00"', $html);
    }

    public function testInvalidTemporalBoundIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TemporalRange(TemporalRange::DATE, min: '01/01/2024');
    }

    public function testConditionalFieldsOnlyDeferRequired(): void
    {
        $fields = new Fields();
        $kind = Condition::equals('kind', 'pro');
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->text('company', label: 'Company', required: true, maxLength: 20, visibleWhen: $kind),
            $fields->radio('size', label: 'Size', choices: ['s' => 'S', 'l' => 'L'], required: true, visibleWhen: $kind),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\srequired[\s>=]/', $html);
        $this->assertStringContainsString('maxlength="20"', $html);
        $this->assertStringContainsString('data-kf-required', $html);
        // Radio groups defer on the first radio only, like native required.
        $this->assertSame(2, substr_count($html, 'data-kf-required'));
    }

    public function testLengthCountsCrlfAsOneCharacterLikeTheBrowser(): void
    {
        $this->assertNull((new Length(max: 5))->validate('t', "ab\r\ncd", []));
        $this->assertNotNull((new Length(max: 4))->validate('t', "ab\r\ncd", []));
    }

    public function testUnsupportedFormMethodIsRejected(): void
    {
        $this->assertSame('POST', (new FormFactory())->create(name: 'a', action: '/a', method: 'POST')->method);
        $this->expectException(\InvalidArgumentException::class);
        (new FormFactory())->create(name: 'a', action: '/a', method: 'PUT');
    }

    public function testErrorsWithoutAVisibleFieldAreRenderedGlobally(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->text('nick', label: 'Nick'),
            new HiddenField('token'),
        ]);
        $form = $form->withState(FormState::from(errors: [
            new FormError('Nick is taken', 'nick'),
            new FormError('Ghost error', 'renamed'),
            new FormError('Expired token', 'token'),
        ]));

        $this->assertSame(
            ['Ghost error', 'Expired token'],
            array_map(static fn(FormError $e): string => $e->message, $form->unmatchedErrors()),
        );
        $html = (string) $form;
        $this->assertStringContainsString('role="alert">Ghost error</div>', $html);
        $this->assertStringContainsString('role="alert">Expired token</div>', $html);
        $this->assertSame(1, substr_count($html, 'Nick is taken'));
    }

    public function testBracketedFieldNamesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextField('user[email]');
    }

    public function testErrorsAndHelpAreAssociatedWithTheControl(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->text('nick', label: 'Nick', help: 'Public name'),
            $fields->radio('level', label: 'Level', choices: ['a' => 'A', 'b' => 'B'], help: 'Pick one'),
        ]);
        $html = (string) $form->withState(FormState::from(errors: [
            new FormError('Too short', 'nick'),
            new FormError('Taken', 'nick'),
            new FormError('Choose a level', 'level'),
            new FormError('Global problem'),
        ]));

        $this->assertStringContainsString(
            'aria-invalid="true" aria-describedby="kf-a-nick-help kf-a-nick-error-0 kf-a-nick-error-1"',
            $html,
        );
        $this->assertStringContainsString('<div class="form-help" id="kf-a-nick-help">Public name</div>', $html);
        $this->assertStringContainsString('<div class="form-error" id="kf-a-nick-error-1">Taken</div>', $html);
        // Only the global summary is a live region.
        $this->assertSame(1, substr_count($html, 'role="alert"'));

        // Groups: description on the fieldset, invalid state on each box.
        $this->assertStringContainsString('aria-describedby="kf-a-level-help kf-a-level-error-0"><legend>', $html);
        $this->assertSame(2, substr_count($html, 'type="radio" name="level"'));
        $this->assertSame(1, substr_count($html, 'aria-describedby="kf-a-level-help'));

        $valid = (string) $form;
        $this->assertStringNotContainsString('aria-invalid', $valid);
        $this->assertStringContainsString('aria-describedby="kf-a-nick-help"', $valid);
    }

    public function testClosestRegisteredAncestorRendererWins(): void
    {
        $renderers = (new NodeRendererRegistry())
            ->register(BaseProbeField::class, static fn(FormNode $node, RenderContext $context): Html => new Html('base'))
            ->register(MidProbeField::class, static fn(FormNode $node, RenderContext $context): Html => new Html('mid'));
        $form = (new FormFactory(profile: new RenderProfile(new DefaultTheme(), $renderers)))->create(
            name: 'a',
            action: '/a',
            children: [new LeafProbeField('x')],
        );
        $this->assertStringContainsString('<form name="a" method="post" action="/a">mid</form>', (string) $form);
    }

    public function testExplicitAttributesWinOverConvenienceParameters(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->textarea('bio', label: 'Bio', attributes: ['rows' => 10]),
            $fields->email('email', label: 'Email', attributes: ['autocomplete' => 'off']),
            $fields->text('nick', label: 'Nick', autocomplete: 'nickname', attributes: ['autocomplete' => 'off']),
        ]);
        $this->assertStringContainsString('rows="10"', $html);
        $this->assertStringNotContainsString('rows="4"', $html);
        $this->assertSame(2, substr_count($html, 'autocomplete="off"'));
    }

    public function testCustomIdIsNotDuplicatedAcrossGroupItems(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->radio('level', label: 'Level', choices: ['a' => 'A', 'b' => 'B'], attributes: ['id' => 'lvl']),
            $fields->checkboxGroup('tags', label: 'Tags', choices: ['x' => 'X', 'y' => 'Y'], attributes: ['id' => 'tg']),
        ]);
        $this->assertStringNotContainsString('id="lvl"', $html);
        $this->assertStringContainsString('id="lvl-0"', $html);
        $this->assertStringContainsString('id="lvl-1"', $html);
        $this->assertStringContainsString('id="tg-0"', $html);
        $this->assertStringContainsString('id="tg-1"', $html);
    }

    public function testListConditionWithInvalidUtf8FailsLoudly(): void
    {
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            new TextField('x', visibleWhen: Condition::equals('tags', ["\xB1"])),
        ]);
        $this->expectException(\JsonException::class);
        $form->toHtml();
    }

    public function testFormRejectsInvalidMethodWhenBuiltDirectly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Form('a', '/a', 'delete', [], [], new \Kaly\Forms\Render\HtmlRenderer());
    }
}

class BaseProbeField extends Field {}

class MidProbeField extends BaseProbeField {}

final class LeafProbeField extends MidProbeField {}
