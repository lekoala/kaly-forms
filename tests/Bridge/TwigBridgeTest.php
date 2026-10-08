<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests\Bridge;

use Kaly\Forms\Bridge\Twig\FormExtension;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Form;
use Kaly\Forms\FormFactory;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigBridgeTest extends TestCase
{
    private function form(): Form
    {
        return (new FormFactory())->create(name: 'demo', action: '/demo', children: [new TextField('name', label: 'Name <b>')]);
    }

    private function twig(string $template, Form $form): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]));
        FormExtension::register($twig);

        return $twig->render('t', ['form' => $form]);
    }

    public function testFormHtmlFunctionOutputsTrustedMarkup(): void
    {
        $html = $this->twig('{{ form_html(form) }}', $this->form());

        $this->assertStringContainsString('<form', $html);
        $this->assertStringNotContainsString('&lt;form', $html);
        // The label is still escaped by the renderer, never by Twig twice.
        $this->assertStringContainsString('Name &lt;b&gt;', $html);
    }

    public function testFormFilterOutputsTrustedMarkup(): void
    {
        $html = $this->twig('{{ form|form }}', $this->form());

        $this->assertStringContainsString('<form', $html);
        $this->assertStringNotContainsString('&lt;form', $html);
    }

    public function testBareFormVariableIsSafeAfterRegister(): void
    {
        $html = $this->twig('{{ form }}', $this->form());

        $this->assertStringContainsString('<form', $html);
        $this->assertStringNotContainsString('&lt;form', $html);
    }
}
