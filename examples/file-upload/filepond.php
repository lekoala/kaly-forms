<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Kaly\Forms\Action\SubmitAction;
use Kaly\Forms\Field\FileField;
use Kaly\Forms\Field\HiddenField;
use Kaly\Forms\Fields;
use Kaly\Forms\FormFactory;
use Kaly\Forms\FormState;
use Kaly\Forms\Html;
use Kaly\Forms\Node\FormNode;
use Kaly\Forms\Render\DefaultTheme;
use Kaly\Forms\Render\NodeRendererRegistry;
use Kaly\Forms\Render\RenderContext;
use Kaly\Forms\Render\RenderProfile;

// Same definition as basic.php: FilePond only enhances, the model is unchanged.
$csrfToken = $_SERVER['DEMO_CSRF_TOKEN'] ?? '';
$csrfToken = is_string($csrfToken) ? $csrfToken : '';

$fields = new Fields();

$renderers = NodeRendererRegistry::defaults();
$renderers->register(FileField::class, static function (FormNode $node, RenderContext $context): Html {
    $inner = NodeRendererRegistry::defaults()->render($node, $context);
    return new Html('<file-pond>' . $inner->value() . '</file-pond>');
});
$profile = new RenderProfile(new DefaultTheme(), $renderers);

$form = (new FormFactory(profile: $profile))->create(
    name: 'documents',
    action: '/upload.php',
    children: [
        new HiddenField('_csrf'),
        $fields->file(name: 'documents', label: 'Documents', accept: ['image/*', 'application/pdf'], multiple: true, required: true),
    ],
    actions: [new SubmitAction('send', 'Send')],
)->withState(FormState::from(['_csrf' => $csrfToken]));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>FilePond sync demo (kaly-forms example integration)</title>
<script type="importmap">
{"imports": {"filepond": "https://unpkg.com/filepond@beta/cdn/index.js", "filepond/": "https://unpkg.com/filepond@beta/cdn/"}}
</script>
</head>
<body>
<?= $form ?>
<script type="module">
import { defineFilePond } from 'filepond';
import { locale } from 'filepond/locales/en-gb.js';

defineFilePond({ locale });
</script>
</body>
</html>
