<?php

namespace JG\BatchImportBundle\Controller;

use InvalidArgumentException;
use JG\BatchImportBundle\Form\Type\FileImportType;
use JG\BatchImportBundle\Form\Type\MatrixType;
use JG\BatchImportBundle\Model\FileImport;
use JG\BatchImportBundle\Model\Form\FormConfigurationInterface;
use JG\BatchImportBundle\Model\Matrix;
use JG\BatchImportBundle\Model\MatrixFactory;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

trait BaseImportControllerTrait
{
    private ?FormConfigurationInterface $importConfiguration = null;

    /**
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReaderException
     * @throws \LogicException
     */
    private function doImport(Request $request): Response
    {
        $fileImport = new FileImport();

        /** @var FormInterface $form */
        $form = $this->createForm(FileImportType::class, $fileImport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $matrix = MatrixFactory::createFromUploadedFile($fileImport->getFile());

            return $this->prepareView(
                $this->getMatrixEditTemplateName(),
                [
                    'header' => $matrix->getHeader(),
                    'data'   => $matrix->getRecords(),
                    'form'   => $this->createMatrixForm($matrix)->createView(),
                ]
            );
        }

        $this->setErrors($form);

        return $this->prepareView(
            $this->getSelectFileTemplateName(),
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @throws LogicException
     */
    private function doImportSave(Request $request): Response
    {
        if (!isset($request->get('matrix')['records'])) {
            return $this->redirectToImport();
        }

        $matrix = MatrixFactory::createFromPostData($request->get('matrix')['records']);
        $form   = $this->createMatrixForm($matrix);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            die('TODO');
        }

        $this->setErrors($form);

        return $this->redirectToImport();
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

    /**
     * @return FormConfigurationInterface
     */
    private function getImportConfiguration(): FormConfigurationInterface
    {
        if (!$this->importConfiguration) {
            $class = $this->getImportConfigurationClassName();
            if (!class_exists($class)) {
                throw new UnexpectedValueException('Configuration class not found.');
            }

            $this->importConfiguration = new $class;
        }

        return $this->importConfiguration;
    }

    /**
     * @param FormInterface $form
     */
    private function setErrors(FormInterface $form): void
    {
        $errors = iterator_to_array($form->getErrors());
        if ($errors) {
            $error = reset($errors);
            $this->addFlash('error', $error->getMessage());
        }
    }
}
