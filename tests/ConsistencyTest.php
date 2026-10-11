<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\ChoiceField;
use Kaly\Forms\Field\NumericField;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Fields;
use Kaly\Forms\FormFactory;
use Kaly\Forms\Interaction\RemoteOptions;
use Kaly\Forms\Node\Fieldset;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Render\DefaultTheme;
use Kaly\Forms\Render\NodeRendererRegistry;
use Kaly\Forms\Render\RenderPart;
use Kaly\Forms\Render\RenderProfile;
use Kaly\Forms\Render\ThemeContext;
use Kaly\Forms\Validation\InChoices;
use Kaly\Forms\Validation\StructuralValidator;
use Kaly\Forms\Validation\TemporalRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Browser/server consistency and definition-time guarantees. */
final class ConsistencyTest extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function namesPhpWouldRewrite(): iterable
    {
        yield 'dot' => ['a.b'];
        yield 'space' => ['first name'];
        yield 'tab' => ["a\tb"];
        yield 'bracket' => ['user[email]'];
    }

    #[DataProvider('namesPhpWouldRewrite')]
    public function testNamesPhpWouldRewriteAreRejected(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextField($name);
    }

    public function testAttributeCannotContradictAProjectedRule(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('attribute "min"');
        new NumericField('amount', min: 1, attributes: ['min' => 10]);
    }

    public function testAttributeCannotDuplicateRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextField('nick', required: true, attributes: ['required' => true]);
    }

    public function testUnprojectedAttributesStayFree(): void
    {
        $field = new TextField('nick', maxLength: 10, attributes: ['pattern' => '[a-z]+', 'class' => 'x']);
        $this->assertSame('[a-z]+', $field->attributes['pattern']);
    }

    public function testNumericStepDefaultsToOneOnBothSides(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->numeric('qty', label: 'Qty'),
            $fields->numeric('price', label: 'Price', step: 'any'),
        ]);
        $html = (string) $form;
        $this->assertStringContainsString('step="1"', $html);
        $this->assertStringContainsString('step="any"', $html);

        $state = (new StructuralValidator())->validate($form, ['qty' => '1.5', 'price' => '1.5']);
        $this->assertSame('step', $state->errorsFor('qty')[0]->code ?? null);
        $this->assertSame([], $state->errorsFor('price'));
    }

    public function testStepCheckDoesNotLoosenWithMagnitude(): void
    {
        $step1 = new \Kaly\Forms\Validation\Range(step: 1);
        $this->assertNotNull($step1->validate('n', '1000000000.5', []));
        $this->assertNotNull($step1->validate('n', '123456789012345.5', []));
        $this->assertNull($step1->validate('n', '1000000000', []));
        $this->assertNull($step1->validate('n', '-7', []));

        $cents = new \Kaly\Forms\Validation\Range(step: '0.01');
        $this->assertNull($cents->validate('n', '19.99', []));
        $this->assertNull($cents->validate('n', '1000000000.01', []));
        $this->assertNotNull($cents->validate('n', '1000000000.015', []));

        $this->assertNull((new \Kaly\Forms\Validation\Range(step: 0.1))->validate('n', '0.3', []));
        $this->assertNull((new \Kaly\Forms\Validation\Range(min: '0.5', step: 2))->validate('n', '4.5', []));
        $this->assertNotNull((new \Kaly\Forms\Validation\Range(min: '0.5', step: 2))->validate('n', '4', []));
        // Exponent notation falls back to floats with a fixed tolerance.
        $this->assertNull($step1->validate('n', '1e3', []));
        $this->assertNotNull($step1->validate('n', '1.5e0', []));
    }

    public function testTimeRangeMayWrapAroundMidnight(): void
    {
        $night = new TemporalRange(TemporalRange::TIME, min: '22:00', max: '06:00');
        $this->assertNull($night->validate('t', '23:00', []));
        $this->assertNull($night->validate('t', '02:00', []));
        $this->assertNull($night->validate('t', '22:00', []));
        $this->assertNull($night->validate('t', '06:00:00', []));
        $this->assertSame('range', $night->validate('t', '12:00', [])?->code);
        $this->assertSame('range', $night->validate('t', '06:00:01', [])?->code);
    }

    public function testInactiveBranchCannotBlockSubmissionWithoutJs(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->numeric(
                'n',
                label: 'N',
                required: true,
                min: 1,
                max: 9,
                visibleWhen: \Kaly\Forms\Interaction\Condition::equals('k', 'x'),
            ),
            $fields->text('t', label: 'T', minLength: 2, maxLength: 5, visibleWhen: \Kaly\Forms\Interaction\Condition::equals('k', 'x')),
        ]);
        $html = (string) $form->withState(\Kaly\Forms\FormState::from(['n' => '0']));

        $this->assertDoesNotMatchRegularExpression('/\s(required|min|max|pattern)[\s>=]/', $html);
        $this->assertStringContainsString('data-kf-required data-kf-min="1" data-kf-max="9"', $html);
        // Only edited values are checked against length constraints: kept natively.
        $this->assertStringContainsString('minlength="2" maxlength="5"', $html);
        $this->assertStringContainsString('step="1"', $html);
        $this->assertTrue(
            (new StructuralValidator())
                ->validate($form, ['n' => '0'])
                ->isValid(),
        );
    }

    /** @return iterable<string,array{string}> */
    public static function formOwnedAttributes(): iterable
    {
        yield 'method' => ['method'];
        yield 'action' => ['action'];
        yield 'enctype' => ['enctype'];
        yield 'name' => ['name'];
    }

    #[DataProvider('formOwnedAttributes')]
    public function testFormAttributesCannotOverrideOwnedParameters(string $attribute): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FormFactory())->create(name: 'a', action: '/a', attributes: [$attribute => 'delete']);
    }

    public function testFractionalSecondsCompareByValue(): void
    {
        $rule = new TemporalRange(TemporalRange::TIME, max: '12:00:00.1');
        $this->assertNull($rule->validate('t', '12:00:00.100', []));
        $this->assertNull($rule->validate('t', '12:00:00.1', []));
        $this->assertNotNull($rule->validate('t', '12:00:00.101', []));
        $this->assertNull((new TemporalRange(TemporalRange::TIME, max: '12:00'))->validate('t', '12:00:00.000', []));
        $this->assertNotNull((new TemporalRange(TemporalRange::TIME, max: '12:00'))->validate('t', '12:00:00.001', []));
    }

    public function testChoiceRendersAnEmptyOptionByDefault(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->choice('country', label: 'Country', choices: ['be' => 'Belgium'], required: true),
            $fields->choice('lang', label: 'Lang', choices: ['fr' => 'FR'], emptyLabel: 'Choose…'),
            $fields->choice('size', label: 'Size', choices: ['m' => 'M'], emptyLabel: null),
            $fields->choice('opt', label: 'Opt', choices: ['' => 'None', 'x' => 'X']),
        ]);

        $this->assertMatchesRegularExpression('/name="country"[^>]*required[^>]*><option value=""><\/option><option value="be">/', $html);
        $this->assertStringContainsString('<option value="">Choose…</option><option value="fr">', $html);
        $this->assertMatchesRegularExpression('/name="size"[^>]*><option value="m">/', $html);
        // An explicit '' choice is not doubled.
        $this->assertSame(1, substr_count($html, '<option value="" selected>None</option>'));
        $this->assertMatchesRegularExpression('/name="opt"[^>]*><option value="" selected>None/', $html);
    }

    public function testEmptyChoiceDoesNotSatisfyRequired(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->choice('country', label: 'Country', choices: ['be' => 'Belgium'], required: true),
        ]);
        $this->assertSame(
            'required',
            (new StructuralValidator())
                ->validate($form, ['country' => ''])
                ->errorsFor('country')[0]->code ?? null,
        );
    }

    public function testStrictChoicesIsOptIn(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->choice('loose', label: 'Loose', choices: ['a' => 'A']),
            $fields->choice('strict', label: 'Strict', choices: ['g' => ['a' => 'A']], strictChoices: true),
            $fields->radio('level', label: 'Level', choices: [1 => 'One'], strictChoices: true),
            $fields->checkboxGroup('tags', label: 'Tags', choices: ['x' => 'X', 'y' => 'Y'], strictChoices: true),
            $fields->multipleSelect('langs', label: 'Langs', choices: ['fr' => 'FR'], strictChoices: true),
        ]);
        $validator = new StructuralValidator();

        $state = $validator->validate($form, [
            'loose' => 'zzz',
            'strict' => 'zzz',
            'level' => '2',
            'tags' => ['x', 'nope'],
            'langs' => [['nested']],
        ]);
        $this->assertSame([], $state->errorsFor('loose'));
        foreach (['strict', 'level', 'tags', 'langs'] as $name) {
            $this->assertSame('choice', $state->errorsFor($name)[0]->code ?? null, $name);
        }

        $valid = $validator->validate($form, ['strict' => 'a', 'level' => '1', 'tags' => ['x', 'y'], 'langs' => []]);
        $this->assertTrue($valid->isValid());
    }

    public function testStrictChoicesRefusesRemoteOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChoiceField('city', choices: ['1' => 'Paris'], remote: new RemoteOptions('/cities'), strictChoices: true);
    }

    public function testInChoicesSkipsEmptyValues(): void
    {
        $rule = new InChoices(['a']);
        $this->assertNull($rule->validate('f', '', []));
        $this->assertNull($rule->validate('f', null, []));
        $this->assertNull($rule->validate('f', [], []));
    }

    /** @return iterable<string,array{list<FormNode>,string}> */
    public static function collidingTrees(): iterable
    {
        yield 'same slug' => [[new TextField('a-b'), new TextField('a:b')], 'kf-a-a-b'];
        yield 'explicit vs generated' => [[new TextField('nick'), new TextField('other', attributes: ['id' => 'kf-a-nick'])], 'kf-a-nick'];
        yield 'derived help' => [[new TextField('x'), new TextField('x-help')], 'kf-a-x-help'];
        yield 'derived label' => [[new TextField('x'), new TextField('other', attributes: ['id' => 'kf-a-x-label'])], 'kf-a-x-label'];
        yield 'derived error' => [[new TextField('x'), new TextField('x-error-0')], 'kf-a-x-error-0'];
        yield 'group item' => [
            [
                new \Kaly\Forms\Field\RadioGroupField('r', choices: ['a' => 'A', 'b' => 'B'], attributes: ['id' => 'lvl']),
                new TextField('other', attributes: ['id' => 'lvl-1']),
            ],
            'lvl-1',
        ];
        yield 'nested in fieldset' => [[new TextField('nick'), new Fieldset('F', [new TextField('nick')])], 'kf-a-nick'];
    }

    /** @param list<FormNode> $children */
    #[DataProvider('collidingTrees')]
    public function testIdCollisionsFailAtDefinitionTime(array $children, string $id): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"' . $id . '"');
        (new FormFactory())->create(name: 'a', action: '/a', children: $children);
    }

    public function testDistinctFieldsAndHiddenInputsDoNotCollide(): void
    {
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            new TextField('nick'),
            new TextField('nickname'),
            new \Kaly\Forms\Field\HiddenField('token'),
            new \Kaly\Forms\Field\HiddenField('token2'),
        ]);
        $this->assertCount(4, $form->children());
    }

    public function testThemeDecoratesActionsAndLegends(): void
    {
        $theme = new class extends DefaultTheme {
            public function attributes(RenderPart $part, ?FormNode $node, ThemeContext $context): array
            {
                return match ($part) {
                    RenderPart::Action => ['class' => 'btn'],
                    RenderPart::Legend => ['class' => 'legend'],
                    default => parent::attributes($part, $node, $context),
                };
            }
        };
        $fields = new Fields();
        $html = (string) (new FormFactory(profile: new RenderProfile($theme, NodeRendererRegistry::defaults())))->create(
            name: 'a',
            action: '/a',
            children: [
                new Fieldset('Contact', [$fields->radio('level', label: 'Level', choices: ['a' => 'A'])]),
            ],
            actions: [new SubmitAction('save', 'Save', attributes: ['class' => 'primary'])],
        );

        $this->assertStringContainsString('<legend class="legend">Contact</legend>', $html);
        $this->assertStringContainsString('<legend class="legend">Level</legend>', $html);
        $this->assertStringContainsString('<button class="btn primary" type="submit" name="save" value="1">Save</button>', $html);
    }
}
