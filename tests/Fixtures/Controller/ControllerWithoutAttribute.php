<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Controller;

use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ControllerWithoutAttribute extends AbstractController
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
        return $this->redirectToRoute('test_controller.without_attribute.import');
    }

    protected function getMatrixSaveActionUrl(): string
    {
        return $this->generateUrl('test_controller.without_attribute.import_save');
    }
}
