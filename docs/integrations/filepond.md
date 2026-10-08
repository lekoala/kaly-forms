# FilePond integration (example)

FilePond v5 is almost a textbook case for this library: it starts from a native `<input type="file">` wrapped in a `<file-pond>` custom element. Without JS the form stays a classic HTML upload; with FilePond registered it takes over the rich experience. Same `FileField`, no new field type: the model does not change, only rendering and enhancement do.

> FilePond v5 is published as **5.0.0-beta**. This page documents an example integration, not a stable recommendation or dependency of `kaly-forms`.

## Demos

`examples/file-upload/` builds the same definition three ways:

| Demo | What it proves |
|---|---|
| `basic.php` | `FileField` works without JS |
| `filepond.php` | enhancement without model change (sync submit) |
| `filepond-async.php` | separate endpoint, CSRF, upload tokens, restore/revert |

`upload.php` and `temp-upload.php` are minimal plain-PHP endpoints. Serve with `php -S 127.0.0.1:8080 -t examples/file-upload` and set `DEMO_CSRF_TOKEN` to a random value. Their CSRF checks are illustrative; production code uses the framework CSRF service.

## Sync first

The plain profile renders the portable contract:

```html
<label for="documents">Documents</label>
<input id="documents" name="documents" type="file" accept="image/*,application/pdf" multiple>
```

and the form derives `enctype="multipart/form-data"` from the tree. The FilePond profile wraps the same control:

```html
<file-pond>
    <input id="documents" name="documents" type="file" accept="image/*,application/pdf" multiple>
</file-pond>
```

FilePond v5 works synchronously by default: final submit stays a normal multipart POST read through PSR-7 `getUploadedFiles()`. This shows `kaly-forms` does not depend on FilePond at all.

## Async uploads change the protocol, not the field yet

With the `FormPostStore` extension each file is POSTed individually as `multipart/form-data`; the server answers a plain-text upload id, and the final form submits tokens:

```text
browser → POST file → temp endpoint → "01JXYZ..."
final form submits upload ids under the field name (tokens, not files)
```

`FormState` still only carries strings here, and no `UploadedFile` object enters the library. If the canonical value ever really becomes `list<UploadId>` instead of uploaded files, that is exactly when the renderer-vs-FieldTypes rule would justify a specialized type. Until then the enhancement owns the protocol shift behind a clear server contract (`temp-upload.php`: POST store, GET restore, DELETE release).

## CSRF is split in three

Separate requests need their own protection, and each layer owns its part:

```text
kaly-forms   → endpoint metadata (data-upload-url on the field)
application  → CSRF policy (which header, which check)
enhancement  → token transport (resolveRequest adds X-CSRF-Token)
```

The core never sees a token; the FilePond snippet reads `data-csrf` from the input and injects it per request.

## Theme boundary

FilePond v5 embeds its styles (Shadow DOM, custom properties, `::part()`). The `FormTheme` therefore decorates only the surrounding composition (label, wrapper, help, errors) and never reaches inside the component. Third-party components own their internal rendering; the library owns the form around them.
