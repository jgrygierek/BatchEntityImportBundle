<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Import;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Event\AfterImportEvent;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Import\ImportMessage;
use JG\BatchEntityImportBundle\Import\ImportResult;
use JG\BatchEntityImportBundle\Import\ImportStep;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use LogicException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Handler is used directly, without controller.
 */
class ImportHandlerTest extends AbstractWebTestCase
{
    private const string SAVE_URL = '/import/save';
    private const string FILES_DIR = __DIR__ . '/../../Fixtures/Resources/';

    private ImportHandler $handler;
    private BaseConfiguration $configuration;

    protected function setUp(): void
    {
        self::bootKernel();

        self::getService(DatabaseLoader::class)->reload();

        // CSRF tokens are stored in session of current request
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getService(RequestStack::class, 'request_stack')->push($request);

        $this->handler = self::getService(ImportHandler::class);
        $this->configuration = self::getService(BaseConfiguration::class);
    }

    public function testFormIsShownBeforeFileIsSent(): void
    {
        $result = $this->handler->handleFile(new Request(), $this->configuration, self::SAVE_URL);

        self::assertSame(ImportStep::SELECT_FILE, $result->getStep());
        self::assertFalse($result->getForm()->isSubmitted());
        self::assertSame([], $result->getMessages());
        self::assertFalse($result->isImported());
    }

    public function testValidFileIsShownAsMatrix(): void
    {
        $result = $this->handleFile('test.csv');

        self::assertSame(ImportStep::EDIT_MATRIX, $result->getStep());
        self::assertCount(30, $result->getMatrix()->getRecords());
        self::assertTrue($result->getForm()->isSubmitted());
        self::assertTrue($result->getForm()->isValid());
        self::assertSame(self::SAVE_URL, $result->getForm()->getConfig()->getAction());
        self::assertSame([], $result->getMessages());
    }

    public function testInvalidFileIsRejected(): void
    {
        $result = $this->handleFile('test_wrong_extension.txt');

        self::assertSame(ImportStep::SELECT_FILE, $result->getStep());
        $this->assertErrorMessage('Wrong file extension.', $result);
    }

    public function testUnreadableFileIsRejected(): void
    {
        $path = sys_get_temp_dir() . '/batch_entity_import_handler_unreadable.xlsx';
        file_put_contents($path, 'this is not a spreadsheet');

        try {
            $result = $this->handleFile($path);
        } finally {
            unlink($path);
        }

        self::assertSame(ImportStep::SELECT_FILE, $result->getStep());
        $this->assertErrorMessage('File can not be read.', $result);
    }

    public function testDataFromPreviewIsImported(): void
    {
        $matrix = $this->handleFile('test.csv')->getMatrix();

        $result = $this->handler->handleSave($this->createSaveRequest(array_map(
            static fn (MatrixRecord $record): array => $record->getData(),
            $matrix->getRecords(),
        )), $this->configuration, self::SAVE_URL);

        self::assertSame(ImportStep::FINISHED, $result->getStep());
        self::assertEquals([ImportMessage::success('Data has been imported')], $result->getMessages());
        self::assertTrue($result->isImported());
        self::assertCount(30, $this->getEntityManager()->getRepository(TestEntity::class)->findAll());
    }

    public function testMissingDataIsNotImported(): void
    {
        $result = $this->handler->handleSave(new Request(), $this->configuration, self::SAVE_URL);

        self::assertSame(ImportStep::FINISHED, $result->getStep());
        $this->assertErrorMessage('Data not found', $result);
        self::assertFalse($result->isImported());
    }

    public function testInvalidDataIsShownAgain(): void
    {
        $records = [['test_private_property' => 'a', 'test_public_property' => 'b', 'test-private-property2' => 'c']];
        self::assertTrue($this->handler->handleSave($this->createSaveRequest($records), $this->configuration, self::SAVE_URL)->isImported());

        // the same values exist already in database
        $result = $this->handler->handleSave($this->createSaveRequest($records), $this->configuration, self::SAVE_URL);

        self::assertSame(ImportStep::EDIT_MATRIX, $result->getStep());
        self::assertCount(1, $result->getMatrix()->getRecords());
        self::assertFalse($result->getForm()->isValid());
        self::assertFalse($result->isImported());
        self::assertCount(1, $this->getEntityManager()->getRepository(TestEntity::class)->findAll());
    }

    public function testEntityFromEntityIdColumnIsSetInPreview(): void
    {
        $entity = $this->importEntity(['test_private_property' => 'a', 'test_public_property' => 'b', 'test-private-property2' => 'c']);

        $result = $this->handleCsv(\sprintf("entity_id,test_private_property,test_public_property,test-private-property2\n%d,a,b,c\n", $entity->getId()));

        self::assertSame(ImportStep::EDIT_MATRIX, $result->getStep());
        self::assertSame($entity, $result->getMatrix()->getRecords()[0]->getEntity());
        // record updates entity with its own values, so it is not a duplicate
        self::assertTrue($result->getForm()->isValid(), (string) $result->getForm()->getErrors(true));
        self::assertSame((string) $entity->getId(), $result->getForm()->createView()['records'][0]['entity']->vars['value']);
    }

    public function testNewRecordWithValuesOfExistingEntityIsDuplicateInPreview(): void
    {
        $this->importEntity(['test_private_property' => 'a', 'test_public_property' => 'b', 'test-private-property2' => 'c']);

        $result = $this->handleCsv("entity_id,test_private_property,test_public_property,test-private-property2\n,a,b,c\n");

        self::assertSame(ImportStep::EDIT_MATRIX, $result->getStep());
        self::assertNull($result->getMatrix()->getRecords()[0]->getEntity());
        self::assertFalse($result->getForm()->isValid());
    }

    public function testNotExistingEntityFromEntityIdColumnIsReportedInPreview(): void
    {
        $result = $this->handleCsv("entity_id,test_private_property,test_public_property,test-private-property2\n999,a,b,c\n");

        self::assertSame(ImportStep::EDIT_MATRIX, $result->getStep());
        self::assertNull($result->getMatrix()->getRecords()[0]->getEntity());
        $entityField = $result->getForm()->get('records')->get('0')->get('entity');
        self::assertFalse($entityField->isValid());
        self::assertSame('999', $entityField->getViewData());
    }

    /**
     * Preview is not shown again, when data is already saved, so user can't save the same data twice.
     */
    public function testExceptionOfThisBundleThrownAfterSaveIsNotShownAsErrorOfImport(): void
    {
        self::getService(EventDispatcherInterface::class, 'event_dispatcher')->addListener(AfterImportEvent::class, static function (): never {
            throw new MatrixRecordInvalidDataTypeException(1, 'test_private_property');
        });
        $records = [['test_private_property' => 'a', 'test_public_property' => 'b', 'test-private-property2' => 'c']];

        try {
            $this->handler->handleSave($this->createSaveRequest($records), $this->configuration, self::SAVE_URL);
            self::fail('Exception was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertInstanceOf(MatrixRecordInvalidDataTypeException::class, $runtimeException->getPrevious());
        }

        self::assertCount(1, $this->getEntityManager()->getRepository(TestEntity::class)->findAll());
    }

    public function testFinishedResultHasNoForm(): void
    {
        $result = ImportResult::finished([]);

        $this->expectExceptionObject(new LogicException('Step "FINISHED" has no form.'));
        $result->getForm();
    }

    public function testSelectFileResultHasNoMatrix(): void
    {
        $result = $this->handler->handleFile(new Request(), $this->configuration, self::SAVE_URL);

        $this->expectExceptionObject(new LogicException('Step "SELECT_FILE" has no matrix.'));
        $result->getMatrix();
    }

    private function handleFile(string $file): ImportResult
    {
        $path = str_contains($file, '/') ? $file : self::FILES_DIR . $file;
        $request = Request::create(
            '/import',
            'POST',
            ['file_import' => ['_token' => $this->getCsrfToken('file_import')]],
            [],
            ['file_import' => ['file' => new UploadedFile($path, basename($path), null, null, true)]],
        );

        return $this->handler->handleFile($request, $this->configuration, self::SAVE_URL);
    }

    private function handleCsv(string $content): ImportResult
    {
        $path = sys_get_temp_dir() . '/batch_entity_import_handler_test.csv';
        file_put_contents($path, $content);

        try {
            return $this->handleFile($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function importEntity(array $values): TestEntity
    {
        self::assertTrue($this->handler->handleSave($this->createSaveRequest([$values]), $this->configuration, self::SAVE_URL)->isImported());

        $entity = $this->getEntityManager()->getRepository(TestEntity::class)->findOneBy(['testPrivateProperty' => $values['test_private_property']]);
        self::assertInstanceOf(TestEntity::class, $entity);

        return $entity;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function createSaveRequest(array $records): Request
    {
        return Request::create(self::SAVE_URL, 'POST', ['matrix' => ['records' => $records, '_token' => $this->getCsrfToken('matrix')]]);
    }

    private function getCsrfToken(string $id): string
    {
        return self::getService(CsrfTokenManagerInterface::class, 'security.csrf.token_manager')->getToken($id)->getValue();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getService(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
    }

    private function assertErrorMessage(string $expected, ImportResult $result): void
    {
        self::assertCount(1, $result->getMessages());
        self::assertSame(ImportMessage::TYPE_ERROR, $result->getMessages()[0]->type);
        self::assertStringContainsString($expected, $result->getMessages()[0]->message);
    }
}
