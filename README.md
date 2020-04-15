BatchImportBundle
=

Bundle adds feature of batch inserting of data provided from different files. 
* Data can be viewed and edited before saving to database.
* Supports translations from KnpLabs Translatable extension.

### Supported extensions:
* CSV
* XLS
* XLSX
* ODS

### Prepare form configuration:

You have to create configuration class. You can add here definitions for dynamic fields loaded from file. 
Field name is the same as column name. If no definition for field will be provided, `TextType` class will be used as default.

```php
namespace App\Configuration;

use App\Entity\User;
use JG\BatchImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchImportBundle\Model\Form\FormFieldDefinition;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UserImportFormConfiguration extends AbstractImportConfiguration implements ImportConfigurationInterface
{
    /**
     * Class of entity used during import process.
     * This is required method.
     *
     * @return string
     */
    public function getEntityClassName(): string
    {
        return User::class;
    }

    /**
     * Used to define field definitions used during process of data editing.
     * If definition for field will not be defined, default definition will be used.
     *
     * @return array|FormFieldDefinition[]
     */
    public function getFieldsDefinitions(): array
    {
        return [
            'age' => new FormFieldDefinition(
                'age', IntegerType::class, [
                    'attr' => [
                        'min' => 0,
                        'max' => 999,
                    ],
                ]
            ),
            'name' => new FormFieldDefinition('name', TextType::class),
            'description' => new FormFieldDefinition(
                'description', TextareaType::class,
                [
                    'attr' => [
                        'rows' => 2,
                    ],
                ]
            ),
        ];
    }
}
```

### Create your controller:

Most part of job is done inside trait, but you still need add some configuration.

```php
namespace App\Controller\Game;

use App\Form\Configuration\UserImportFormConfiguration;
use JG\BatchImportBundle\Controller\ImportControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends AbstractController
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
       return UserImportFormConfiguration::class;
    }
}
```

### Overriding templates:

You can override default templates globally by adding them to directory:

```
templates/budles/BatchImportBundle
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
