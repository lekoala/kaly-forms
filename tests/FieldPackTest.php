<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Field\OptionGroup;
use Kaly\Forms\Fields;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;
use Kaly\Forms\Node\Fieldset;
use Kaly\Forms\Node\Group;
use Kaly\Forms\Validation\StructuralValidator;
use PHPUnit\Framework\TestCase;

final class FieldPackTest extends TestCase
{
    public function testRadioGroupRendersStrictStringSelection(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [$fields->radio(
            'level',
            label: 'Level',
            choices: [1 => 'One', 2 => 'Two'],
            required: true,
        )]);

        $html = (string) $form->withState(FormState::from(['level' => '1']));

        $this->assertSame(2, substr_count($html, 'type="radio"'));
        $this->assertSame(2, substr_count($html, 'name="level"'));
        $this->assertStringContainsString('required', $html);
        // String "1" selects int-keyed option 1 through strict string comparison.
        $this->assertSame(1, substr_count($html, 'checked'));
        $this->assertStringContainsString('value="1"', $html);
    }

    public function testMultipleSelectRendersSubsetAndMissingMeansEmpty(): void
    {
        $fields = new Fields();
        $children = [$fields->multipleSelect('tags', label: 'Tags', choices: ['a' => 'A', 'b' => 'B', 'c' => 'C'], size: 3)];

        $filled = (string) (new FormFactory())
            ->create(name: 'a', action: '/a', children: $children)
            ->withState(FormState::from(['tags' => ['a', 'c']]));
        $this->assertStringContainsString('<select', $filled);
        $this->assertStringContainsString('multiple', $filled);
        $this->assertStringContainsString('size="3"', $filled);
        $this->assertSame(2, substr_count($filled, 'selected'));

        $missing = (string) (new FormFactory())
            ->create(name: 'a', action: '/a', children: $children)
            ->withState(FormState::from([]));
        $this->assertStringNotContainsString('selected', $missing);
    }

    public function testCheckboxGroupUsesArrayNamesAndRequiredRule(): void
    {
        $fields = new Fields();
        $children = [$fields->checkboxGroup('interests', label: 'Interests', choices: ['php' => 'PHP', 'js' => 'JS'], required: true)];

        $html = (string) (new FormFactory())
            ->create(name: 'a', action: '/a', children: $children)
            ->withState(FormState::from(['interests' => ['js']]));
        $this->assertSame(2, substr_count($html, 'name="interests[]"'));
        $this->assertSame(1, substr_count($html, 'checked'));

        $validator = new StructuralValidator();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: $children);
        $this->assertCount(1, $validator->validate($form, [])->errorsFor('interests'));
        $this->assertCount(0, $validator->validate($form, ['interests' => ['js']])->errorsFor('interests'));
    }

    public function testTimeAndDateTimeRenderNativeControls(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->time('startsAt', label: 'Starts at', min: '09:00', max: '18:00'),
            $fields->dateTime('deadline', label: 'Deadline'),
        ]);

        $this->assertStringContainsString('type="time"', $html);
        $this->assertStringContainsString('min="09:00"', $html);
        $this->assertStringContainsString('max="18:00"', $html);
        $this->assertStringContainsString('type="datetime-local"', $html);
    }

    public function testNumericRuleKeepsStringValues(): void
    {
        $fields = new Fields();
        $children = [$fields->numeric('price', label: 'Price', required: true, step: '0.01')];

        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: $children);
        $this->assertStringContainsString('type="number"', $html);
        $this->assertStringContainsString('step="0.01"', $html);

        $validator = new StructuralValidator();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: $children);
        $this->assertCount(0, $validator->validate($form, ['price' => '12.50'])->errorsFor('price'));
        $this->assertCount(1, $validator->validate($form, ['price' => 'abc'])->errorsFor('price'));
        // Submitted values stay strings; mapping belongs to the application.
        $this->assertSame('12.50', $validator->validate($form, ['price' => '12.50'])->value('price'));
    }

    public function testOptgroupsRenderSingleLevel(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [
            $fields->choice('lang', label: 'Language', choices: [
                'en' => 'English',
                'Belgium' => ['fr' => 'French', 'nl' => 'Dutch'],
                new OptionGroup('Other', ['de' => 'German']),
            ]),
        ]);

        $this->assertStringContainsString('<optgroup label="Belgium">', $html);
        $this->assertStringContainsString('<optgroup label="Other">', $html);
        $this->assertStringContainsString('value="fr"', $html);
    }

    public function testNestedOptgroupsAreRefused(): void
    {
        $fields = new Fields();

        $this->expectException(\InvalidArgumentException::class);
        $fields->choice('lang', choices: ['outer' => ['inner' => ['deep' => 'Too deep']]]);
    }

    public function testReadonlyRendersStateValueWithoutTrust(): void
    {
        $fields = new Fields();
        $html = (string) (new FormFactory())->create(name: 'a', action: '/a', children: [$fields->readonly(
            'code',
            label: 'Code',
        )])->withState(FormState::from(['code' => '<B1>']));

        $this->assertStringContainsString('<span>&lt;B1&gt;</span>', $html);
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="code"', $html);
    }

    public function testFileNeverRefillsAndSetsMultipart(): void
    {
        $fields = new Fields();
        $children = [$fields->file('attachment', label: 'Attachment', accept: '.pdf', multiple: true)];

        $html = (string) (new FormFactory())
            ->create(name: 'a', action: '/a', children: $children)
            ->withState(FormState::from(['attachment' => 'evil.pdf']));
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('accept=".pdf"', $html);
        $this->assertStringContainsString('multiple', $html);
        $this->assertStringNotContainsString('evil.pdf', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    public function testNestedFileFieldStillSetsMultipart(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            new Group(children: [
                new Fieldset(legend: 'Docs', children: [
                    new Group(children: [$fields->file('attachment', label: 'Attachment')]),
                ]),
            ]),
        ]);

        $this->assertSame('multipart/form-data', $form->enctype());
        $this->assertStringContainsString('enctype="multipart/form-data"', (string) $form);
    }

    public function testEnctypeDefaultsAndOverride(): void
    {
        $fields = new Fields();
        $plain = (new FormFactory())->create(name: 'a', action: '/a', children: [$fields->text('name')]);

        $this->assertSame('application/x-www-form-urlencoded', $plain->enctype());
        $this->assertStringNotContainsString('enctype', (string) $plain);

        $forced = (new FormFactory())->create(
            name: 'a',
            action: '/a',
            children: [$fields->file('attachment')],
            enctype: 'multipart/form-data',
        );
        $this->assertStringContainsString('enctype="multipart/form-data"', (string) $forced);
    }
}
