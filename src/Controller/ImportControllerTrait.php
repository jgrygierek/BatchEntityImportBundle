<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use JG\BatchEntityImportBundle\Form\Type\MatrixType;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use LogicException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

trait ImportControllerTrait
{
    use BaseImportControllerTrait;

    abstract protected function getMatrixSaveActionUrl(): string;

    /**
     * @param array<string, mixed> $parameters
     */
    protected function prepareView(string $view, array $parameters = []): Response
    {
        return $this->render($view, $parameters);
    }

    protected function getSelectFileTemplateName(): string
    {
        return $this->getTemplateNameFromConfiguration('select_file');
    }

    protected function getMatrixEditTemplateName(): string
    {
        return $this->getTemplateNameFromConfiguration('edit_matrix');
    }

    /**
     * @throws LogicException
     */
    private function getTemplateNameFromConfiguration(string $name): string
    {
        $template = $this->getParameter('batch_entity_import.templates.' . $name);
        if (!\is_string($template)) {
            throw new LogicException(\sprintf('Template "%s" is not configured.', $name));
        }

        return $template;
    }

    protected function createMatrixForm(Matrix $matrix): FormInterface
    {
        $importConfiguration = $this->getImportConfiguration();

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
