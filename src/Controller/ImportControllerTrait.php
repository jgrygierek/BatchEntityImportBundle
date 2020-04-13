<?php

namespace JG\BatchImportBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

trait ImportControllerTrait
{
    use BaseImportControllerTrait;

    private function prepareView(string $view, array $parameters = []): Response
    {
        return $this->render($view, $parameters);
    }

    private function getSelectFileTemplateName(): string
    {
        return '@BatchImport/select_file.html.twig';
    }

    private function getMatrixEditTemplateName(): string
    {
        return '@BatchImport/edit_matrix.html.twig';
    }
}
