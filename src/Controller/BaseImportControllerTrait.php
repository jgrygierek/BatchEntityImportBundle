<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Form\Type\FileImportType;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\FileImport;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;
use UnexpectedValueException;

trait BaseImportControllerTrait
{
    protected ?ImportConfigurationInterface $importConfiguration = null;

    abstract protected function getImportConfigurationClassName(): string;

    abstract protected function redirectToImport(): RedirectResponse;

    abstract protected function getSelectFileTemplateName(): string;

    abstract protected function getMatrixEditTemplateName(): string;

    abstract protected function prepareView(string $view, array $parameters = []): Response;

    abstract protected function createMatrixForm(Matrix $matrix, EntityManagerInterface $entityManager): FormInterface;

    /**
     * @throws InvalidArgumentException
     * @throws \LogicException
     */
    protected function doImport(Request $request, ValidatorInterface $validator, EntityManagerInterface $entityManager): Response
    {
        $fileImport = new FileImport();

        /** @var FormInterface $form */
        $form = $this->createForm(FileImportType::class, $fileImport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $matrix = MatrixFactory::createFromUploadedFile($fileImport->getFile());

            $errors = $validator->validate($matrix);
            if (0 === $errors->count()) {
                return $this->prepareMatrixEditView($matrix, $entityManager, true);
            }
        } else {
            $errors = $form->getErrors();
        }

        $this->setErrorAsFlash($errors);

        return $this->prepareSelectFileView($form);
    }

    protected function prepareSelectFileView(FormInterface $form): Response
    {
        return $this->prepareView(
            $this->getSelectFileTemplateName(),
            [
                'form' => $form->createView(),
            ]
        );
    }

    protected function prepareMatrixEditView(Matrix $matrix, EntityManagerInterface $entityManager, $manualSubmit = false): Response
    {
        $form = $this->createMatrixForm($matrix, $entityManager);
        if ($manualSubmit) {
            $form->submit(['records' => array_map(static fn (MatrixRecord $record) => $record->getData(), $matrix->getRecords())]);
        }

        return $this->prepareView(
            $this->getMatrixEditTemplateName(),
            [
                'header_info' => $matrix->getHeaderInfo($this->getImportConfiguration($entityManager)->getEntityClassName()),
                'data' => $matrix->getRecords(),
                'form' => $form->createView(),
                'importConfiguration' => $this->getImportConfiguration($entityManager),
            ]
        );
    }

    /**
     * @throws LogicException
     */
    protected function doImportSave(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    {
        if (!isset($request->get('matrix')['records'])) {
            $msg = $translator->trans('error.data.not_found', [], 'BatchEntityImportBundle');
            $this->addFlash('error', $msg);

            return $this->redirectToImport();
        }

        $matrix = MatrixFactory::createFromPostData($request->get('matrix')['records']);
        $form = $this->createMatrixForm($matrix, $entityManager);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getImportConfiguration($entityManager)->import($matrix);

            $msg = $translator->trans('success.import', [], 'BatchEntityImportBundle');
            $this->addFlash('success', $msg);

            return $this->redirectToImport();
        }

        return $this->prepareMatrixEditView($matrix, $entityManager);
    }

    protected function getImportConfiguration(EntityManagerInterface $entityManager): ImportConfigurationInterface
    {
        if (!$this->importConfiguration) {
            $class = $this->getImportConfigurationClassName();
            if (!class_exists($class)) {
                throw new UnexpectedValueException('Configuration class not found.');
            }

            $this->importConfiguration = isset($this->container) && $this->container instanceof ContainerInterface && $this->container->has($class) ? $this->container->get($class) : new $class($entityManager);
        }

        return $this->importConfiguration;
    }

    protected function setErrorAsFlash(Traversable $violations): void
    {
        $errors = iterator_to_array($violations);
        if ($errors) {
            $error = reset($errors);
            $this->addFlash('error', $error->getMessage());
        }
    }
}
