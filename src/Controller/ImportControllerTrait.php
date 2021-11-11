<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Form\Type\MatrixType;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

trait ImportControllerTrait
{
    use BaseImportControllerTrait;

    abstract protected function getMatrixSaveActionUrl(): string;

    protected function prepareView(string $view, array $parameters = []): Response
    {
        return $this->render($view, $parameters);
    }

    protected function getSelectFileTemplateName(): string
    {
        return $this->getParameter('batch_entity_import.templates.select_file');
    }

    protected function getMatrixEditTemplateName(): string
    {
        return $this->getParameter('batch_entity_import.templates.edit_matrix');
    }

    protected function createMatrixForm(Matrix $matrix, EntityManagerInterface $em): FormInterface
    {
        $importConfiguration = $this->getImportConfiguration($em);

        return $this->createForm(
            MatrixType::class,
            $matrix,
            [
                'configuration' => $importConfiguration,
                'action' => $this->getMatrixSaveActionUrl(),
                'constraints' => $importConfiguration->getMatrixConstraints(),
            ]
        );
    }
}
