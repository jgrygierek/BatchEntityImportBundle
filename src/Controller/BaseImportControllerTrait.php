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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;
use UnexpectedValueException;

trait BaseImportControllerTrait
{
    private ?ImportConfigurationInterface $importConfiguration = null;
    private ?TranslatorInterface          $translator = null;
    private ?EntityManagerInterface       $em = null;
    private ?ValidatorInterface           $validator = null;

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * @throws InvalidArgumentException
     * @throws \LogicException
     */
    private function doImport(Request $request): Response
    {
        $this->checkDI();
        $fileImport = new FileImport();

        /** @var FormInterface $form */
        $form = $this->createForm(FileImportType::class, $fileImport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $matrix = MatrixFactory::createFromUploadedFile($fileImport->getFile());

            $errors = $this->validator->validate($matrix);
            if (0 === $errors->count()) {
                return $this->prepareMatrixEditView($matrix);
            }
        } else {
            $errors = $form->getErrors();
        }

        $this->setErrorAsFlash($errors);

        return $this->prepareSelectFileView($form);
    }

    private function prepareSelectFileView(FormInterface $form): Response
    {
        return $this->prepareView(
            $this->getSelectFileTemplateName(),
            [
                'form' => $form->createView(),
            ]
        );
    }

    private function prepareMatrixEditView(Matrix $matrix): Response
    {
        return $this->prepareView(
            $this->getMatrixEditTemplateName(),
            [
                'header_info' => $matrix->getHeaderInfo($this->getImportConfiguration()->getEntityClassName()),
                'data' => $matrix->getRecords(),
                'form' => $this->createMatrixForm($matrix)->createView(),
            ]
        );
    }

    /**
     * @throws LogicException
     */
    private function doImportSave(Request $request): Response
    {
        $this->checkDI();

        if (!isset($request->get('matrix')['records'])) {
            $msg = $this->translator->trans('error.data.not_found', [], 'BatchEntityImportBundle');
            $this->addFlash('error', $msg);

            return $this->redirectToImport();
        }

        $matrix = MatrixFactory::createFromPostData($request->get('matrix')['records']);
        $form = $this->createMatrixForm($matrix);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getImportConfiguration()->import($matrix);

            $msg = $this->translator->trans('success.import', [], 'BatchEntityImportBundle');
            $this->addFlash('success', $msg);
        }

        $this->setErrorAsFlash($form->getErrors());

        return $this->redirectToImport();
    }

    private function getImportConfiguration(): ImportConfigurationInterface
    {
        $this->checkDI();

        if (!$this->importConfiguration) {
            $class = $this->getImportConfigurationClassName();
            if (!class_exists($class)) {
                throw new UnexpectedValueException('Configuration class not found.');
            }

            $this->importConfiguration = new $class($this->em);
        }

        return $this->importConfiguration;
    }

    private function setErrorAsFlash(Traversable $violations): void
    {
        $errors = iterator_to_array($violations);
        if ($errors) {
            $error = reset($errors);
            $this->addFlash('error', $error->getMessage());
        }
    }

    private function checkDI(): void
    {
        if (!$this instanceof ImportControllerInterface) {
            throw new UnexpectedValueException('Controller should implement ' . ImportControllerInterface::class);
        }
    }
}
