BatchEntityImportBundle
=

| Branch | Status | Coverage |
| --- | --- | --- |
| master  | [![Build Status](https://travis-ci.com/jgrygierek/BatchEntityImportBundle.svg?branch=master)](https://travis-ci.com/jgrygierek/BatchEntityImportBundle) | [![codecov](https://codecov.io/gh/jgrygierek/BatchEntityImportBundle/branch/master/graph/badge.svg)](https://codecov.io/gh/jgrygierek/BatchEntityImportBundle) |
| develop | [![Build Status](https://travis-ci.com/jgrygierek/BatchEntityImportBundle.svg?branch=develop)](https://travis-ci.com/jgrygierek/BatchEntityImportBundle) | [![codecov](https://codecov.io/gh/jgrygierek/BatchEntityImportBundle/branch/develop/graph/badge.svg)](https://codecov.io/gh/jgrygierek/BatchEntityImportBundle) |



Bundle adds feature of batch inserting of data provided from different files. 
* Data can be **viewed and edited** before saving to database.
* Supported extensions: **CSV, XLS, XLSX, ODS**
* Supports translations from **KnpLabs Translatable** extension.
* The code is divided into smaller methods that can be easily replaced if you want to change something.
* Columns names are required and should be added as header (first row).
* If column does not have name provided, will be removed from loaded data.

## Documentation
* [Installation](#installation)
* [Basic configuration class](#basic-configuration-class)
* [Creating controller](#creating-controller)
* [Translations](#translations)
* [Fields definitions](#fields-definitions)
* [Overriding templates](#overriding-templates)

## Installation

Install package via composer:

```
composer require jgrygierek/batch-entity-import-bundle
```

Add entry to `bundles.php` file:

```
JG\BatchEntityImportBundle\BatchEntityImportBundle::class => ['all' => true],
```

## Basic configuration class

You have to create configuration class. In the simplest case it will contain only class of used entity.

```php
namespace App\Model\ImportConfiguration;

use App\Entity\User;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;

class UserImportConfiguration extends AbstractImportConfiguration
{
    public function getEntityClassName(): string
    {
        return User::class;
    }
}
```

## Creating controller

Create controller with some required code.

```php
namespace App\Controller;

use App\Model\ImportConfiguration\UserImportConfiguration;
use JG\BatchEntityImportBundle\Controller\ImportControllerInterface;
use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController implements ImportControllerInterface
{
    use ImportControllerTrait;

    /**
     * @Route("/user/import", name="user_import")
     */
    public function import(Request $request): Response
    {
        return $this->doImport($request);
    }

    /**
     * @Route("/user/import/save", name="user_import_save")
     */
    public function importSave(Request $request): Response
    {
        return $this->doImportSave($request);
    }

    private function redirectToImport(): RedirectResponse
    {
       return $this->redirectToRoute('user_import');
    }
    
    private function getMatrixSaveActionUrl(): string
    {
       return $this->generateUrl('user_import_save');
    }
    
    private function getImportConfigurationClassName(): string
    {
       return UserImportConfiguration::class;
    }
}
```

## Translations

This bundle supports KnpLabs Translatable behavior.

To use this feature, every column with translatable values should be suffixed with locale, for example:
* `name:en`
* `description:pl`
* `title:ru`

If suffix will be added to non-translatable entity, field will be skipped.

If suffix will be added to translatable entity, but field will not be found in translation class, field will be skipped.

## Fields definitions

If you want to change types of rendered fields, instead of using default ones,
you have to override method in your import configuration.

```php

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

public function getFieldsDefinitions(): array
{
    return [
        'age' => new FormFieldDefinition(
            IntegerType::class,
            [
                'attr' => [
                    'min' => 0,
                    'max' => 999,
                ],
            ]
        ),
        'name' => new FormFieldDefinition(TextType::class),
        'description' => new FormFieldDefinition(
            TextareaType::class,
            [
                'attr' => [
                    'rows' => 2,
                ],
            ]
        ),
    ];
}
```

## Overriding templates

You can override default templates globally by adding them to directory:

```
templates/bundles/BatchEntityImportBundle
```

If you have controller-specific templates, you can override them in controller:

```php
private function getSelectFileTemplateName(): string
{
    return 'your/path/to/select_file.html.twig';
}

private function getMatrixEditTemplateName(): string
{
    return 'your/path/to/edit_matrix.html.twig';
}
```

If you want add some specific data to the rendered view, just override these methods in controller:

```php
private function prepareSelectFileView(FormInterface $form): Response
{
    return $this->prepareView(
        $this->getSelectFileTemplateName(),
        [
            'form' => $form->createView(),
        ]
    );
}

private function prepareMatrixEditView(Matrix $matrix): Response
{
    return $this->prepareView(
        $this->getMatrixEditTemplateName(),
        [
            'header' => $matrix->getHeader(),
            'data'   => $matrix->getRecords(),
            'form'   => $this->createMatrixForm($matrix)->createView(),
        ]
    );
}
```

