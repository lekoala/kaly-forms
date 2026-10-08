<?php

declare(strict_types=1);

namespace Kaly\Forms\Bridge\Twig;

use Kaly\Forms\HtmlRenderable;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\EscaperExtension;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Idiomatic Twig exposure for HtmlRenderable form objects.
 *
 * Templates only need the form variable:
 *
 *     {{ form_html(form) }}
 *     {{ form|form }}
 *     {{ form }}            {# works once FormExtension::register() is used #}
 *
 * Requires twig/twig. Register it with FormExtension::register($twig): it
 * installs the helpers and marks HtmlRenderable as safe for the HTML strategy,
 * so no `|raw` is needed in templates.
 */
final class FormExtension extends AbstractExtension
{
    public static function render(HtmlRenderable $form): string
    {
        return $form->toHtml()->value();
    }

    public static function register(Environment $twig): void
    {
        $twig->addExtension(new self());
        self::markSafe($twig);
    }

    /** @return list<TwigFunction> */
    public function getFunctions(): array
    {
        return [new TwigFunction('form_html', self::render(...), ['is_safe' => ['html']])];
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [new TwigFilter('form', self::render(...), ['is_safe' => ['html']])];
    }

    private static function markSafe(Environment $twig): void
    {
        // Twig >= 3.10 exposes the escaper as a runtime; earlier 3.x uses the extension.
        if (class_exists(EscaperRuntime::class)) {
            $twig->getRuntime(EscaperRuntime::class)->addSafeClass(HtmlRenderable::class, ['html']);
            return;
        }

        $twig->getExtension(EscaperExtension::class)->addSafeClass(HtmlRenderable::class, ['html']);
    }
}
