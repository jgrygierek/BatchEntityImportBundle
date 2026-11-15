# Overriding templates

## Global templates

You have two ways to override templates globally:

- **Configuration** - just change paths to templates in your configuration file. 
Values in this example are default ones and will be used if nothing will be change.

```yaml
batch_entity_import:
    templates:
        select_file: '@BatchEntityImport/select_file.html.twig'
        edit_matrix: '@BatchEntityImport/edit_matrix.html.twig'
        layout: '@BatchEntityImport/layout.html.twig'
```

- **Bundle directory** - put your templates in this directory:

```
templates/bundles/BatchEntityImportBundle
```

## Controller-specific templates

If you have controller-specific templates, you can override them in controller:

```php
protected function getSelectFileTemplateName(): string
{
    return 'your/path/to/select_file.html.twig';
}

protected function getMatrixEditTemplateName(): string
{
    return 'your/path/to/edit_matrix.html.twig';
}
```

## Main layout

Block name used in templates is `batch_entity_import_content`, so probably there will be need to override it a bit.
You can create a new file with content similar to the given example. Then just use it instead of original layout file.

```twig
{% extends path/to/your/layout.html.twig %}

{% block your_real_block_name %}
    {% block batch_entity_import_content %}{% endblock %}
{% endblock %}
```

Then you just have to override it in bundle directory, or change a path to layout in your configuration.

## Additional data

If you want to add some specific data to the rendered view, just override these methods in your controller:

```php
protected function prepareSelectFileView(FormInterface $form): Response
{
    return $this->prepareView(
        $this->getSelectFileTemplateName(),
        [
            'form' => $form->createView(),
        ]
    );
}

protected function prepareMatrixEditView(FormInterface $form, Matrix $matrix, bool $manualSubmit = false): Response
{
    if ($manualSubmit) {
        $this->manualSubmitMatrixForm($form, $matrix);
    }

    $configuration = $this->getImportConfiguration();

    return $this->prepareView(
        $this->getMatrixEditTemplateName(),
        [
            'header_info' => $matrix->getHeaderInfo($configuration->getEntityClassName(), $configuration->getAllowedColumns()),
            'form' => $form->createView(),
            'importConfiguration' => $configuration,
        ]
    );
}
```

[Back to README](../README.md)
