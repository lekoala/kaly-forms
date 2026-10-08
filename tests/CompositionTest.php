<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests;

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Fields;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;
use Kaly\Forms\Interaction\Condition;
use Kaly\Forms\Node\ColumnsLayout;
use Kaly\Forms\Node\Fieldset;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Node\Group;
use Kaly\Forms\Node\Heading;
use Kaly\Forms\Node\HtmlBlock;
use Kaly\Forms\Node\Layouts;
use Kaly\Forms\Node\Text;
use Kaly\Forms\Render\DefaultTheme;
use Kaly\Forms\Render\FormTheme;
use Kaly\Forms\Render\NodeRenderer;
use Kaly\Forms\Render\NodeRendererRegistry;
use Kaly\Forms\Render\RenderContext;
use Kaly\Forms\Render\RenderPart;
use Kaly\Forms\Render\RenderProfile;
use Kaly\Forms\Render\ThemeContext;
use Kaly\Forms\Validation\StructuralValidator;
use PHPUnit\Framework\TestCase;

final class CompositionTest extends TestCase
{
    public function testTreeRendersMixedNodes(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(
            name: 'registration',
            action: '/registrations',
            children: [
                new Heading(2, 'Registration'),
                new Group(layout: Layouts::columns(2), children: [
                    $fields->text('firstName', label: 'First name'),
                    $fields->text('lastName', label: 'Last name'),
                ]),
                new Fieldset(legend: 'Contact', children: [$fields->email('email', label: 'Email')]),
                new Text('Fields marked as required must be completed.'),
            ],
            actions: [new SubmitAction('save', 'Register')],
        );

        $html = (string) $form;

        $this->assertStringContainsString('<h2>Registration</h2>', $html);
        $this->assertStringContainsString('data-kf-layout="columns"', $html);
        $this->assertStringContainsString('data-kf-columns="2"', $html);
        $this->assertStringContainsString('<fieldset>', $html);
        $this->assertStringContainsString('<legend>Contact</legend>', $html);
        $this->assertStringContainsString('<p>Fields marked as required must be completed.</p>', $html);
        $this->assertStringContainsString('data-kf-layout="inline"', $html);
    }

    public function testHiddenGroupSkipsRulesButKeepsValues(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'registration', action: '/registrations', children: [
            $fields->text('kind', label: 'Kind'),
            new Group(
                children: [$fields->text('company', label: 'Company', required: true)],
                visibleWhen: Condition::equals('kind', 'pro'),
            ),
        ]);

        $values = ['kind' => 'personal', 'company' => ''];
        $state = (new StructuralValidator())->validate($form, $values);

        // Structural constraints neutralized, submitted data untouched.
        $this->assertCount(0, $state->errorsFor('company'));
        $this->assertSame($values, $state->values());
    }

    public function testHiddenGroupStillRendersWithMetadata(): void
    {
        $fields = new Fields();
        $form = (new FormFactory())->create(name: 'registration', action: '/registrations', children: [
            new Group(children: [$fields->text('company', label: 'Company')], visibleWhen: Condition::equals('kind', 'pro')),
        ]);

        $html = (string) $form->withState(FormState::from(['kind' => 'personal']));

        $this->assertStringContainsString('data-kf-visible-field="kind"', $html);
        $this->assertStringContainsString('name="company"', $html);
    }

    public function testThemeDecoratesWithoutChangingStructure(): void
    {
        $fields = new Fields();
        $children = [$fields->text('firstName', label: 'First name')];
        $plain = (string) (new FormFactory())->create(name: 'a', action: '/a', children: $children);

        $profile = new RenderProfile(new PrefixTheme(), NodeRendererRegistry::defaults());
        $themed = (string) (new FormFactory(profile: $profile))->create(name: 'a', action: '/a', children: $children);

        $this->assertStringContainsString('<input', $plain);
        $this->assertStringNotContainsString('prefixed-control', $plain);
        $this->assertStringContainsString('class="prefixed-control"', $themed);
        // Same structure: label still before control.
        $this->assertLessThan(strpos($themed, '<input'), strpos($themed, '</label>'));
    }

    public function testStructuralOverrideThroughNodeRendererObject(): void
    {
        $fields = new Fields();
        $children = [
            new Group(children: [$fields->text('firstName', label: 'First name')]),
        ];

        $renderers = NodeRendererRegistry::defaults();
        $renderers->register(Group::class, new SectionGroupRenderer());
        $profile = new RenderProfile(new DefaultTheme(), $renderers);

        $html = (string) (new FormFactory(profile: $profile))->create(name: 'a', action: '/a', children: $children);

        $this->assertStringContainsString('<section>', $html);
        $this->assertStringContainsString('name="firstName"', $html);
    }

    public function testHtmlBlockIsVerbatimAndTextIsEscaped(): void
    {
        $form = (new FormFactory())->create(name: 'a', action: '/a', children: [
            new HtmlBlock(new Html('<b>trusted</b>')),
            new Text('<b>untrusted</b>'),
        ]);

        $html = (string) $form;

        $this->assertStringContainsString('<b>trusted</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;untrusted&lt;/b&gt;', $html);
    }

    public function testInvalidModelsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Heading(7, 'Too deep');
    }

    public function testInvalidColumnCountIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ColumnsLayout(0);
    }
}

final class PrefixTheme extends DefaultTheme implements FormTheme
{
    /** @return array<string,string|int|float|bool|null> */
    public function attributes(RenderPart $part, ?FormNode $node, ThemeContext $context): array
    {
        if ($part === RenderPart::Control) {
            return ['class' => 'prefixed-control'];
        }
        return parent::attributes($part, $node, $context);
    }
}

final class SectionGroupRenderer implements NodeRenderer
{
    public function render(FormNode $node, RenderContext $context): Html
    {
        /** @var Group $group */ $group = $node;
        $out = '';
        foreach ($group->children() as $child) {
            $out .= $context->render($child)->value();
        }
        return new Html('<section>' . $out . '</section>');
    }
}
