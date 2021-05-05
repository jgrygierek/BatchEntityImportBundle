<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Controller;

use JG\BatchEntityImportBundle\Controller\ImportControllerInterface;
use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\TranslatableEntityBaseConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller extends AbstractController implements ImportControllerInterface
{
    use ImportControllerTrait;

    public function import(Request $request): Response
    {
        return $this->doImport($request);
    }

    public function importSave(Request $request): Response
    {
        return $this->doImportSave($request);
    }

    protected function redirectToImport(): RedirectResponse
    {
        return $this->redirectToRoute('jg.batch_entity_import_bundle.test_controller.import');
    }

    protected function getMatrixSaveActionUrl(): string
    {
        return $this->generateUrl('jg.batch_entity_import_bundle.test_controller.import_save');
    }

    protected function getImportConfigurationClassName(): string
    {
        return TranslatableEntityBaseConfiguration::class;
    }
}
