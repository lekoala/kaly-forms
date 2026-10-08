<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Field\CheckboxField;
use Kaly\Forms\Field\CheckboxGroupField;
use Kaly\Forms\Field\ChoiceField;
use Kaly\Forms\Field\CustomElementField;
use Kaly\Forms\Field\DateField;
use Kaly\Forms\Field\DateTimeField;
use Kaly\Forms\Field\EmailField;
use Kaly\Forms\Field\Field;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Field\MultipleSelectField;
use Kaly\Forms\Field\NumericField;
use Kaly\Forms\Field\OptionGroup;
use Kaly\Forms\Field\PasswordField;
use Kaly\Forms\Field\RadioGroupField;
use Kaly\Forms\Field\ReadonlyField;
use Kaly\Forms\Field\TextareaField;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Field\TimeField;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Interaction\RemoteOptions;

/**
 * Composition/extensibility API: which concrete Field a requested concept maps to.
 *
 * Mutable during application composition; treat as read-only once forms are
 * being created. Prefer the Fields facade for authoring form definitions.
 */
final class FieldTypes
{
    /** @var array<class-string<Field>, callable> */
    private array $factories = [];

    public static function defaults(): self
    {
        $types = new self();
        $types->register(TextField::class, self::textField(...));
        $types->register(EmailField::class, self::emailField(...));
        $types->register(PasswordField::class, self::passwordField(...));
        $types->register(TextareaField::class, self::textareaField(...));
        $types->register(ChoiceField::class, self::choiceField(...));
        $types->register(CheckboxField::class, self::checkboxField(...));
        $types->register(RadioGroupField::class, self::radioGroupField(...));
        $types->register(MultipleSelectField::class, self::multipleSelectField(...));
        $types->register(CheckboxGroupField::class, self::checkboxGroupField(...));
        $types->register(HiddenField::class, self::hiddenField(...));
        $types->register(CustomElementField::class, self::customElementField(...));
        $types->register(DateField::class, self::dateField(...));
        $types->register(TimeField::class, self::timeField(...));
        $types->register(DateTimeField::class, self::dateTimeField(...));
        $types->register(NumericField::class, self::numericField(...));
        $types->register(ReadonlyField::class, self::readonlyField(...));
        $types->register(FileField::class, self::fileField(...));
        return $types;
    }

    /** @param class-string<Field> $type */
    public function register(string $type, callable $factory): self
    {
        $this->factories[$type] = $factory;
        return $this;
    }

    /** @param class-string<Field> $type */
    public function has(string $type): bool
    {
        return isset($this->factories[$type]);
    }

    /**
     * @template T of Field
     * @param class-string<T> $type
     * @return T
     */
    public function create(string $type, mixed ...$args): Field
    {
        $factory = $this->factories[$type] ?? throw new \LogicException("No field factory registered for {$type}");
        $field = $factory(...$args);
        if (!$field instanceof $type) {
            throw new \UnexpectedValueException("Field factory for {$type} must return a {$type} instance");
        }
        return $field;
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function textField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $autocomplete = null,
        string $inputType = 'text',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new TextField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            minLength: $minLength,
            maxLength: $maxLength,
            autocomplete: $autocomplete,
            inputType: $inputType,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function emailField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        string $autocomplete = 'email',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new EmailField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            autocomplete: $autocomplete,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function passwordField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        string $autocomplete = 'current-password',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new PasswordField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            minLength: $minLength,
            maxLength: $maxLength,
            autocomplete: $autocomplete,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function textareaField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        int $rows = 4,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new TextareaField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            minLength: $minLength,
            maxLength: $maxLength,
            rows: $rows,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /**
     * @param array<string|int,string|OptionGroup|array<string|int,mixed>> $choices
     * @param array<string,string|int|float|bool|null> $attributes
     */
    private static function choiceField(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        ?RemoteOptions $remote = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new ChoiceField(
            name: $name,
            label: $label,
            choices: $choices,
            help: $help,
            required: $required,
            remote: $remote,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function checkboxField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new CheckboxField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function hiddenField(string $name, array $attributes = []): Field
    {
        return new HiddenField(name: $name, attributes: $attributes);
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function customElementField(
        string $name,
        string $tag,
        ?string $label = null,
        ?string $help = null,
        bool $mirrorHiddenInput = true,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new CustomElementField(
            name: $name,
            tag: $tag,
            label: $label,
            help: $help,
            mirrorHiddenInput: $mirrorHiddenInput,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function dateField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?string $min = null,
        ?string $max = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new DateField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            min: $min,
            max: $max,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /**
     * @param array<string|int,string> $choices
     * @param array<string,string|int|float|bool|null> $attributes
     */
    private static function radioGroupField(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new RadioGroupField(
            name: $name,
            label: $label,
            choices: $choices,
            help: $help,
            required: $required,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /**
     * @param array<string|int,string|OptionGroup|array<string|int,mixed>> $choices
     * @param array<string,string|int|float|bool|null> $attributes
     */
    private static function multipleSelectField(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        ?int $size = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new MultipleSelectField(
            name: $name,
            label: $label,
            choices: $choices,
            help: $help,
            required: $required,
            size: $size,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /**
     * @param array<string|int,string> $choices
     * @param array<string,string|int|float|bool|null> $attributes
     */
    private static function checkboxGroupField(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new CheckboxGroupField(
            name: $name,
            label: $label,
            choices: $choices,
            help: $help,
            required: $required,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function timeField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?string $min = null,
        ?string $max = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new TimeField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            min: $min,
            max: $max,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function dateTimeField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?string $min = null,
        ?string $max = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new DateTimeField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            min: $min,
            max: $max,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function numericField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        int|float|string|null $min = null,
        int|float|string|null $max = null,
        int|float|string|null $step = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new NumericField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            min: $min,
            max: $max,
            step: $step,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    private static function readonlyField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new ReadonlyField(name: $name, label: $label, help: $help, attributes: $attributes, visibleWhen: $visibleWhen);
    }

    /**
     * @param string|list<string>|null $accept
     * @param array<string,string|int|float|bool|null> $attributes
     */
    private static function fileField(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        string|array|null $accept = null,
        bool $multiple = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): Field {
        return new FileField(
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            accept: $accept,
            multiple: $multiple,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }
}
