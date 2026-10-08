<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Field\ChoiceField;
use Kaly\Forms\Field\DateField;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Fields;
use Kaly\Forms\FieldTypes;
use Kaly\Forms\FormFactory;
use Kaly\Forms\Html;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Render\DefaultTheme;
use Kaly\Forms\Render\NodeRendererRegistry;
use Kaly\Forms\Render\RenderContext;
use Kaly\Forms\Render\RenderProfile;
use Kaly\Forms\Validation\Length;
use PHPUnit\Framework\TestCase;

final class FieldsTest extends TestCase
{
    public function testDefaultsCreateConcreteTypes(): void
    {
        $fields = new Fields();

        $text = $fields->text('firstName', label: 'First name', minLength: 2);
        $this->assertInstanceOf(TextField::class, $text);
        $this->assertContainsOnlyInstancesOf(Length::class, $text->rules);

        $choice = $fields->choice('country', label: 'Country', choices: ['BE' => 'Belgium']);
        $this->assertInstanceOf(ChoiceField::class, $choice);
        $this->assertSame(['BE' => 'Belgium'], $choice->choices);

        $date = $fields->date('birthDate', label: 'Birth date', min: '1900-01-01', max: '2026-12-31');
        $this->assertInstanceOf(DateField::class, $date);
        $this->assertSame('1900-01-01', $date->min);
        $this->assertSame('2026-12-31', $date->max);
    }

    public function testDateFieldRendersNativeInputByDefault(): void
    {
        $form = (new FormFactory())->create(name: 'details', action: '/details', children: [(new Fields())->date(
            'birthDate',
            min: '1900-01-01',
        )]);
        $html = (string) $form;

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('min="1900-01-01"', $html);
    }

    public function testFieldTypesOverrideSubstitutesTheModel(): void
    {
        $types = FieldTypes::defaults();
        $types->register(
            DateField::class,
            static fn(
                string $name,
                ?string $label = null,
                ?string $help = null,
                bool $required = false,
                ?string $min = null,
                ?string $max = null,
                array $attributes = [],
                ?Condition $visibleWhen = null,
            ): Field => new CalendarDateField(name: $name, label: $label, help: $help, min: $min, max: $max),
        );

        $field = (new Fields($types))->date('birthDate', label: 'Birth date', min: '1900-01-01');

        $this->assertInstanceOf(CalendarDateField::class, $field);
        $this->assertSame('1900-01-01', $field->min);
    }

    public function testRendererSwapKeepsTheModel(): void
    {
        $field = (new Fields())->date('birthDate', label: 'Birth date');

        $renderers = NodeRendererRegistry::defaults();
        $renderers->register(DateField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var DateField $date */ $date = $node;
            return new Html('<calendar-picker name="' . $context->e($date->name) . '"></calendar-picker>');
        });
        $profile = new RenderProfile(new DefaultTheme(), $renderers);

        $default = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [$field]);
        $custom = (string) (new FormFactory(profile: $profile))->create(name: 'a', action: '/a', children: [$field]);

        $this->assertInstanceOf(DateField::class, $field);
        $this->assertStringContainsString('type="date"', $default);
        $this->assertStringContainsString('<calendar-picker', $custom);
        $this->assertStringNotContainsString('type="date"', $custom);
    }

    public function testBothAxesStayIndependent(): void
    {
        $types = FieldTypes::defaults();
        $types->register(
            DirectoryField::class,
            static fn(string $name, ?string $label = null): Field => new DirectoryField($name, $label, ['BE', 'FR']),
        );
        $field = (new Fields($types))
            ->types()
            ->create(DirectoryField::class, name: 'country', label: 'Country');
        $this->assertSame('country', $field->name);

        $renderers = NodeRendererRegistry::defaults();
        $renderers->register(DirectoryField::class, static function (FormNode $node, RenderContext $context): Html {
            /** @var DirectoryField $directory */ $directory = $node;
            return new Html('<directory-picker name="' . $context->e($directory->name) . '"></directory-picker>');
        });
        $profile = new RenderProfile(new DefaultTheme(), $renderers);

        $html = (string) (new FormFactory(profile: $profile))->create(name: 'a', action: '/a', children: [$field]);
        $this->assertStringContainsString('<directory-picker', $html);
    }

    public function testFactoryMayCaptureDependencies(): void
    {
        $countries = ['BE', 'FR'];
        $types = FieldTypes::defaults();
        $types->register(
            DirectoryField::class,
            static fn(string $name, ?string $label = null): Field => new DirectoryField($name, $label, $countries),
        );

        $field = (new Fields($types))
            ->types()
            ->create(DirectoryField::class, name: 'country');

        $this->assertSame(['BE', 'FR'], $field->countries);
    }

    public function testMissingBindingThrows(): void
    {
        $types = new FieldTypes();

        $this->assertFalse($types->has(DateField::class));
        $this->expectException(\LogicException::class);
        $types->create(DateField::class, name: 'birthDate');
    }

    public function testNonFieldFactoryResultThrows(): void
    {
        $types = new FieldTypes();
        $types->register(DateField::class, static function (string $name): mixed {
            return 'not-a-field';
        });

        $this->expectException(\UnexpectedValueException::class);
        $types->create(DateField::class, name: 'birthDate');
    }

    public function testCreatesAreIsolated(): void
    {
        $fields = new Fields();

        $this->assertNotSame($fields->date('a'), $fields->date('a'));
        $this->assertNotSame(FieldTypes::defaults(), FieldTypes::defaults());
    }
}

final class CalendarDateField extends DateField {}

final class DirectoryField extends Field
{
    /** @param list<string> $countries */
    public function __construct(string $name, ?string $label, array $countries)
    {
        parent::__construct($name, $label);
        $this->countries = $countries;
    }

    /** @var list<string> */
    public readonly array $countries;
}
