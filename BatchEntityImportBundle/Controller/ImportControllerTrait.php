<?php

namespace JG\BatchEntityImportBundle\Controller;

use JG\BatchEntityImportBundle\Form\Type\MatrixType;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use Symfony\Component\Form\FormInterface;
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

    /**
     * @param Matrix $matrix
     *
     * @return FormInterface
     */
    private function createMatrixForm(Matrix $matrix): FormInterface
    {
        return $this->createForm(
            MatrixType::class,
            $matrix,
            [
                'configuration' => $this->getImportConfiguration(),
                'action'        => $this->getMatrixSaveActionUrl(),
            ]
        );
    }
}
