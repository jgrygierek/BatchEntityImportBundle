<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Import;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Form\Type\EntityLookupTextType;
use JG\BatchEntityImportBundle\Form\Type\EntityLookupType;
use JG\BatchEntityImportBundle\Form\Type\MatrixRecordType;
use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Import\ImportResult;
use JG\BatchEntityImportBundle\Import\ImportStep;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\AllTestEntityColumnsAllowedTrait;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Field used to select overridden entity can be replaced by lighter field, which renders only one value.
 */
class OverrideEntityFieldTest extends AbstractWebTestCase
{
    private const string SAVE_URL = '/import/save';

    private ImportHandler $handler;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        self::getService(DatabaseLoader::class)->reload();

        // CSRF tokens are stored in session of current request
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getService(RequestStack::class, 'request_stack')->push($request);

        $this->handler = self::getService(ImportHandler::class);
        $this->entityManager = self::getService(EntityManagerInterface::class, 'doctrine.orm.entity_manager');

        foreach (['first', 'second'] as $name) {
            $entity = new TestEntity();
            $entity->setTestPrivateProperty($name);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function testValueFromFileIsUsedAsLookupValue(): void
    {
        $result = $this->handleFile(
            $this->createConfiguration(EntityLookupTextType::class, ['lookup_field' => 'testPrivateProperty']),
            "entity_id,test_public_property\nsecond,changed\n,new\n",
        );

        self::assertSame(ImportStep::EDIT_MATRIX, $result->getStep());
        self::assertSame('second', $this->getEntityField($result->getForm(), 0)->getViewData());
        self::assertSame('', $this->getEntityField($result->getForm(), 1)->getViewData());
        // only one value is rendered, not all entities
        self::assertArrayNotHasKey('choices', $this->getEntityField($result->getForm(), 0)->createView()->vars);
    }

    public function testEntityFoundByLookupValueIsOverridden(): void
    {
        $configuration = $this->createConfiguration(EntityLookupTextType::class, ['lookup_field' => 'testPrivateProperty']);

        $result = $this->handleSave($configuration, [
            ['entity' => 'second', 'test_public_property' => 'changed'],
            ['entity' => '', 'test_public_property' => 'new'],
        ]);

        self::assertTrue($result->isImported());
        $this->entityManager->clear();
        $repository = $this->entityManager->getRepository(TestEntity::class);
        self::assertCount(3, $repository->findAll());
        self::assertSame('changed', $repository->findOneBy(['testPrivateProperty' => 'second'])?->testPublicProperty);
        self::assertSame('', $repository->findOneBy(['testPrivateProperty' => 'first'])?->testPublicProperty);
    }

    public function testNotExistingEntityIsReported(): void
    {
        $result = $this->handleSave(
            $this->createConfiguration(EntityLookupTextType::class, ['lookup_field' => 'testPrivateProperty']),
            [['entity' => 'unknown', 'test_public_property' => 'changed']],
        );

        self::assertSame(ImportStep::EDIT_MATRIX, $result->getStep());
        self::assertFalse($result->isImported());
        self::assertGreaterThan(0, $this->getEntityField($result->getForm(), 0)->getErrors()->count());
        self::assertCount(2, $this->entityManager->getRepository(TestEntity::class)->findAll());
    }

    public function testSelectWithLookupValues(): void
    {
        $configuration = $this->createConfiguration(EntityLookupType::class, ['label_field' => 'testPrivateProperty']);
        $secondId = $this->entityManager->getRepository(TestEntity::class)->findOneBy(['testPrivateProperty' => 'second'])?->getId();

        $result = $this->handleFile($configuration, "entity_id,test_public_property\n{$secondId},changed\n");

        $field = $this->getEntityField($result->getForm(), 0);
        self::assertSame((string) $secondId, $field->getViewData());
        self::assertSame(['first', 'second'], array_map(static fn (object $choice): string => $choice->label, $field->createView()->vars['choices']));

        $result = $this->handleSave($configuration, [['entity' => (string) $secondId, 'test_public_property' => 'changed']]);

        self::assertTrue($result->isImported());
        $this->entityManager->clear();
        self::assertSame('changed', $this->entityManager->getRepository(TestEntity::class)->find($secondId)?->testPublicProperty);
    }

    public function testLookupValueIsTakenFromEntitySetInRecord(): void
    {
        $entity = $this->entityManager->getRepository(TestEntity::class)->findOneBy(['testPrivateProperty' => 'second']);
        $record = new MatrixRecord(['test_public_property' => 'changed'], 'first');
        $record->setEntity($entity);

        $form = self::getService(FormFactoryInterface::class, 'form.factory')->create(MatrixRecordType::class, $record, [
            'configuration' => $this->createConfiguration(EntityLookupTextType::class, ['lookup_field' => 'testPrivateProperty']),
        ]);

        // entity has priority over value of "entity_id" column
        self::assertSame('second', $form->get('entity')->getViewData());
    }

    public function testInvalidTypeOfFieldException(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException(\sprintf(
            'Field used to override entity has to be "%s" or "%s", "%s" given.',
            EntityLookupType::class,
            EntityLookupTextType::class,
            TextType::class,
        )));

        $this->handleFile($this->createConfiguration(TextType::class, []), "test_public_property\nvalue\n");
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createConfiguration(string $fieldClass, array $options): AbstractImportConfiguration
    {
        return new class($fieldClass, $options) extends AbstractImportConfiguration {
            use AllTestEntityColumnsAllowedTrait;

            /**
             * @param array<string, mixed> $options
             */
            public function __construct(private readonly string $fieldClass, private readonly array $options)
            {
            }

            public function getEntityClassName(): string
            {
                return TestEntity::class;
            }

            public function allowOverrideEntity(): bool
            {
                return true;
            }

            public function getOverrideEntityFieldDefinition(): FormFieldDefinition
            {
                return new FormFieldDefinition($this->fieldClass, $this->options);
            }
        };
    }

    private function handleFile(AbstractImportConfiguration $configuration, string $content): ImportResult
    {
        $path = sys_get_temp_dir() . '/batch_entity_import_override_field.csv';
        file_put_contents($path, $content);

        try {
            $request = Request::create(
                '/import',
                'POST',
                ['file_import' => ['_token' => $this->getCsrfToken('file_import')]],
                [],
                ['file_import' => ['file' => new UploadedFile($path, 'file.csv', null, null, true)]],
            );

            return $this->handler->handleFile($request, $configuration, self::SAVE_URL);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function handleSave(AbstractImportConfiguration $configuration, array $records): ImportResult
    {
        $request = Request::create(self::SAVE_URL, 'POST', ['matrix' => ['records' => $records, '_token' => $this->getCsrfToken('matrix')]]);

        return $this->handler->handleSave($request, $configuration, self::SAVE_URL);
    }

    private function getEntityField(FormInterface $form, int $index): FormInterface
    {
        return $form->get('records')->get((string) $index)->get('entity');
    }

    private function getCsrfToken(string $id): string
    {
        return self::getService(CsrfTokenManagerInterface::class, 'security.csrf.token_manager')->getToken($id)->getValue();
    }
}
