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

#[AsImportController(configuration: UserImportConfiguration::class)]
class ImportController extends AbstractController
{
    use ImportControllerTrait;

    #[Route('/user/import', name: 'user_import')]
    public function import(Request $request): Response
    {
        return $this->doImport($request);
    }

    #[Route('/user/import/save', name: 'user_import_save')]
    public function importSave(Request $request): Response
    {
        return $this->doImportSave($request);
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

Events dispatched during import are described in [Events](events.md).

[Back to README](../README.md)
