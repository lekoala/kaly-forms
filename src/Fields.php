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
 * Public authoring API: typed field construction with IDE support.
 *
 * Each method delegates to the injected FieldTypes registry, so the
 * application can substitute implementations without changing definitions.
 */
final class Fields
{
    private readonly FieldTypes $types;

    public function __construct(?FieldTypes $types = null)
    {
        $this->types = $types ?? FieldTypes::defaults();
    }

    public function types(): FieldTypes
    {
        return $this->types;
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    public function text(
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
    ): TextField {
        return $this->types->create(
            TextField::class,
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
    public function email(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        string $autocomplete = 'email',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): EmailField {
        return $this->types->create(
            EmailField::class,
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
    public function password(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        string $autocomplete = 'current-password',
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): PasswordField {
        return $this->types->create(
            PasswordField::class,
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
    public function textarea(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        int $rows = 4,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): TextareaField {
        return $this->types->create(
            TextareaField::class,
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
    public function choice(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        ?RemoteOptions $remote = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): ChoiceField {
        return $this->types->create(
            ChoiceField::class,
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
    public function checkbox(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): CheckboxField {
        return $this->types->create(
            CheckboxField::class,
            name: $name,
            label: $label,
            help: $help,
            required: $required,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    public function hidden(string $name, array $attributes = []): HiddenField
    {
        return $this->types->create(HiddenField::class, name: $name, attributes: $attributes);
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    public function customElement(
        string $name,
        string $tag,
        ?string $label = null,
        ?string $help = null,
        bool $mirrorHiddenInput = true,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): CustomElementField {
        return $this->types->create(
            CustomElementField::class,
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
    public function date(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?string $min = null,
        ?string $max = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): DateField {
        return $this->types->create(
            DateField::class,
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
    public function radio(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): RadioGroupField {
        return $this->types->create(
            RadioGroupField::class,
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
    public function multipleSelect(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        ?int $size = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): MultipleSelectField {
        return $this->types->create(
            MultipleSelectField::class,
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
    public function checkboxGroup(
        string $name,
        ?string $label = null,
        array $choices = [],
        ?string $help = null,
        bool $required = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): CheckboxGroupField {
        return $this->types->create(
            CheckboxGroupField::class,
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
    public function time(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?string $min = null,
        ?string $max = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): TimeField {
        return $this->types->create(
            TimeField::class,
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
    public function dateTime(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?string $min = null,
        ?string $max = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): DateTimeField {
        return $this->types->create(
            DateTimeField::class,
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
    public function numeric(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        int|float|string|null $min = null,
        int|float|string|null $max = null,
        int|float|string|null $step = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): NumericField {
        return $this->types->create(
            NumericField::class,
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
    public function readonly(
        string $name,
        ?string $label = null,
        ?string $help = null,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): ReadonlyField {
        return $this->types->create(
            ReadonlyField::class,
            name: $name,
            label: $label,
            help: $help,
            attributes: $attributes,
            visibleWhen: $visibleWhen,
        );
    }

    /** @param array<string,string|int|float|bool|null> $attributes */
    public function file(
        string $name,
        ?string $label = null,
        ?string $help = null,
        bool $required = false,
        ?string $accept = null,
        bool $multiple = false,
        array $attributes = [],
        ?Condition $visibleWhen = null,
    ): FileField {
        return $this->types->create(
            FileField::class,
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
