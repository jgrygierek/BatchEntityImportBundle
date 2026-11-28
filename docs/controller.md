# Controller

Create controller with some required code.

This is just an example, depending on your needs you can inject services in different ways.

Class of import configuration is set by `#[AsImportController]` attribute. Controller has to be registered as autoconfigured service (default in Symfony), and configuration has to be registered as a service too. If something is wrong (e.g. configuration is not a service), you will see an error already when container is built.

The attribute has to be added to class of controller itself, it is not inherited from parent class.
Controller which uses `ImportControllerTrait` has to extend `AbstractController`.

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

## Using import handler directly

`ImportControllerTrait` renders templates of the bundle, adds flash messages and redirects. If you need to present results
in other way (e.g. other templates or JSON response), use `ImportHandler` service directly. It handles both steps of import
and returns `ImportResult`, which does not depend on HTTP response:

```php
use App\Model\ImportConfiguration\UserImportConfiguration;
use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Import\ImportStep;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserImportController extends AbstractController
{
    public function __construct(
        private readonly ImportHandler $importHandler,
        private readonly UserImportConfiguration $configuration,
    ) {
    }

    #[Route('/user/import', name: 'user_import')]
    public function import(Request $request): Response
    {
        $result = $this->importHandler->handleFile($request, $this->configuration, $this->generateUrl('user_import_save'));

        foreach ($result->getMessages() as $message) {
            $this->addFlash($message->type, $message->message);
        }

        return match ($result->getStep()) {
            ImportStep::SELECT_FILE => $this->render('import/select_file.html.twig', ['form' => $result->getForm()->createView()]),
            ImportStep::EDIT_MATRIX => $this->render('import/edit_matrix.html.twig', [
                'form' => $result->getForm()->createView(),
                'header_info' => $result->getMatrix()->getHeaderInfo($this->configuration->getEntityClassName(), $this->configuration->getAllowedColumns()),
                'importConfiguration' => $this->configuration,
            ]),
            ImportStep::FINISHED => $this->redirectToRoute('user_import'),
        };
    }
}
```

Action which saves data calls `handleSave()` the same way. `ImportResult::isImported()` tells if data was saved.
Messages are already translated, their type is `ImportMessage::TYPE_SUCCESS` or `ImportMessage::TYPE_ERROR`.

## Events

Events dispatched during import are described in [Events](events.md).

[Back to README](../README.md)
