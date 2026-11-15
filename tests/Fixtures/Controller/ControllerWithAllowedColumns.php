<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Controller;

use JG\BatchEntityImportBundle\Controller\ImportConfigurationAutoInjectInterface;
use JG\BatchEntityImportBundle\Controller\ImportConfigurationAutoInjectTrait;
use JG\BatchEntityImportBundle\Controller\ImportControllerTrait;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\AllowedColumnsConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ControllerWithAllowedColumns extends AbstractController implements ImportConfigurationAutoInjectInterface
{
    use ImportConfigurationAutoInjectTrait;
    use ImportControllerTrait;

    public function import(Request $request, ValidatorInterface $validator): Response
    {
        return $this->doImport($request, $validator);
    }

    public function importSave(Request $request, TranslatorInterface $translator): Response
    {
        return $this->doImportSave($request, $translator);
    }

    protected function redirectToImport(): RedirectResponse
    {
        return $this->redirectToRoute('test_controller.allowed_columns.import');
    }

    protected function getMatrixSaveActionUrl(): string
    {
        return $this->generateUrl('test_controller.allowed_columns.import_save');
    }

    protected function getImportConfigurationClassName(): string
    {
        return AllowedColumnsConfiguration::class;
    }
}
