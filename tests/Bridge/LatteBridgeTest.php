<?php

declare(strict_types=1);

namespace Kaly\Forms\Tests\Bridge;

use Kaly\Forms\Bridge\Latte\FormExtension;
use Kaly\Forms\Field\TextField;
use Kaly\Forms\Form;
use Kaly\Forms\FormFactory;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use PHPUnit\Framework\TestCase;

final class LatteBridgeTest extends TestCase
{
    private function form(): Form
    {
        return (new FormFactory())->create(name: 'demo', action: '/demo', children: [new TextField('name', label: 'Name <b>')]);
    }

    private function latte(string $template, Form $form): string
    {
        $latte = new Engine();
        $latte->setLoader(new StringLoader());
        $latte->addExtension(new FormExtension());

        return $latte->renderToString($template, ['form' => $form]);
    }

    public function testFormFilterOutputsTrustedMarkup(): void
    {
        $html = $this->latte('{$form|form}', $this->form());

        $this->assertStringContainsString('<form', $html);
        $this->assertStringNotContainsString('&lt;form', $html);
        $this->assertStringContainsString('Name &lt;b&gt;', $html);
    }

    public function testFormHtmlFunctionOutputsTrustedMarkup(): void
    {
        $html = $this->latte('{=form_html($form)}', $this->form());

        $this->assertStringContainsString('<form', $html);
        $this->assertStringNotContainsString('&lt;form', $html);
    }
}
