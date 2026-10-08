<?php

declare(strict_types=1);

namespace Kaly\Forms;

use Kaly\Forms\Field\CheckboxField;
use Kaly\Forms\Field\ChoiceField;
use Kaly\Forms\Field\CustomElementField;
use Kaly\Forms\Field\DateField;
use Kaly\Forms\Field\EmailField;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Field\PasswordField;
use Kaly\Forms\Field\TextareaField;
use Kaly\Forms\Field\TextField;
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
     * @param array<string|int,string> $choices
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
}
