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
        return $this->getParameter('batch_entity_import.templates.select_file');
    }

    private function getMatrixEditTemplateName(): string
    {
        return $this->getParameter('batch_entity_import.templates.edit_matrix');
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
