# Controller

Create controller with some required code.

This is just an example, depending on your needs you can inject services in different ways.

Class of import configuration is set by `#[AsImportController]` attribute. Controller has to be registered as autoconfigured service (default in Symfony), and configuration has to be registered as a service too. If something is wrong (e.g. configuration is not a service), you will see an error already when container is built.

The attribute has to be added to class of controller itself, it is not inherited from parent class.

```php
namespace App\Controller;

use App\Model\ImportConfiguration\UserImportConfiguration;
use JG\BatchEntityImportBundle\Attribute\AsImportController;
use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsImportController(configuration: UserImportConfiguration::class)]
class ImportController extends AbstractController
{
    use ImportControllerTrait;

    #[Route('/user/import', name: 'user_import')]
    public function import(Request $request, ValidatorInterface $validator): Response
    {
        return $this->doImport($request, $validator);
    }

    #[Route('/user/import/save', name: 'user_import_save')]
    public function importSave(Request $request, TranslatorInterface $translator): Response
    {
        return $this->doImportSave($request, $translator);
    }

    protected function redirectToImport(): RedirectResponse
    {
       return $this->redirectToRoute('user_import');
    }

    protected function getMatrixSaveActionUrl(): string
    {
       return $this->generateUrl('user_import_save');
    }
}
```

Methods of controller can be overridden to change templates (see [Overriding templates](templates.md))
or encoding of CSV files (see [Import files](files.md#encoding)).

## Events

### RecordImportedSuccessfullyEvent

For each successfully processed record, event `RecordImportedSuccessfullyEvent` is dispatched. This event contains two fields:
- entity class name
- entity id

```php
class RecordImportedSuccessfullyEvent
{
    public function __construct(public readonly string $class, public readonly string $id)
    {
    }
}
```

[Back to README](../README.md)
