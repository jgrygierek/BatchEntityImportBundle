# Controller

Create controller with some required code.

This is just an example, depending on your needs you can inject services in different ways.

To enable automatic passing configuration service to your controller, please use `ImportConfigurationAutoInjectInterface` and `ImportConfigurationAutoInjectTrait`.

```php
namespace App\Controller;

use App\Model\ImportConfiguration\UserImportConfiguration;
use JG\BatchEntityImportBundle\Controller\ImportConfigurationAutoInjectInterface;
use JG\BatchEntityImportBundle\Controller\ImportConfigurationAutoInjectTrait;
use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportController extends AbstractController implements ImportConfigurationAutoInjectInterface
{
    use ImportControllerTrait;
    use ImportConfigurationAutoInjectTrait;

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

    protected function getImportConfigurationClassName(): string
    {
       return UserImportConfiguration::class;
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
