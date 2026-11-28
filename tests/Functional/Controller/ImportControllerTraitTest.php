<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityRepository;
use Generator;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Controller\ControllerWithAttribute;
use JG\BatchEntityImportBundle\Tests\Fixtures\Controller\ControllerWithoutAttribute;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Event\ImportEventRecorder;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

class ImportControllerTraitTest extends AbstractWebTestCase
{
    private const int DEFAULT_RECORDS_NUMBER = 20;
    private const int NEW_RECORDS_NUMBER = 30;
    private const string URL = '/jg_batch_entity_import_bundle/import';
    private KernelBrowser $client;
    private ImportEventRecorder $eventRecorder;

    protected function setUp(): void
    {
        $this->client = $this->createCustomClient(self::bootKernel());

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
        $databaseLoader->loadFixtures();

        $this->eventRecorder = self::getEventRecorder();
        $this->eventRecorder->reset();
    }

    public function testInsertNewData(): void
    {
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());

        $newEntityId1 = self::DEFAULT_RECORDS_NUMBER + 1;
        $newEntityId2 = self::DEFAULT_RECORDS_NUMBER + 2;
        $newEntityId3 = self::DEFAULT_RECORDS_NUMBER + 3;
        $newEntityId4 = self::DEFAULT_RECORDS_NUMBER + 4;
        self::assertNull($this->getRepository()->find($newEntityId1));
        self::assertNull($this->getRepository()->find($newEntityId2));
        self::assertNull($this->getRepository()->find($newEntityId3));
        self::assertNull($this->getRepository()->find($newEntityId4));

        $this->submitSelectFileForm(__DIR__ . '/../../Fixtures/Resources/test.csv');
        self::assertStringContainsString('Separator: &quot;|&quot;', $this->client->getResponse()->getContent());

        $this->client->submitForm('btn-submit');

        $this->checkData();
        $this->assertEntityValues(['test1', 'lorem ipsum 1', 'qwerty1', []], $newEntityId1);
        $this->assertEntityValues(['test2', 'lorem ipsum 2', 'qwerty2', ['arr_val_1', 'arr_val_2', 'arr_val_3']], $newEntityId2);
        $this->assertEntityValues(['test3', 'lorem ipsum 3', 'qwerty3', []], $newEntityId3);
        $this->assertEntityValues(['test4', 'lorem ipsum 4', 'qwerty4', ['arr_val_1', '']], $newEntityId4);

        $this->checkDispatchedEvents(self::NEW_RECORDS_NUMBER);
    }

    #[DataProvider('updateRecordDataProvider')]
    public function testUpdateExistingRecord(
        int $updatedEntityId,
        array $expectedDefaultValues,
        string $arrayFieldValue,
        array $expectedValuesAfterChange,
    ): void {
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        $this->assertEntityValues($expectedDefaultValues, $updatedEntityId);

        $this->submitSelectFileForm(__DIR__ . '/../../Fixtures/Resources/test_updated_data.csv');

        $this->assertSame(
            '2',
            $this->client->getCrawler()->filterXpath('//select[@name="matrix[records][0][entity]"]/option[@selected]')->attr('value'),
        );
        $this->assertSame(
            'test',
            $this->client->getCrawler()->filterXpath('//input[@name="matrix[records][0][test_private_property]"]')->attr('value'),
        );
        $this->assertSame(
            'lorem ipsum',
            $this->client->getCrawler()->filterXpath('//input[@name="matrix[records][0][test-private-property2]"]')->attr('value'),
        );
        $this->assertSame(
            'qwerty',
            $this->client->getCrawler()->filterXpath('//input[@name="matrix[records][0][test_public_property]"]')->attr('value'),
        );
        $this->assertSame(
            'arr_val_1000|array_val_1001',
            $this->client->getCrawler()->filterXpath('//input[@name="matrix[records][0][test_array_field]"]')->attr('value'),
        );

        $this->client->submitForm('btn-submit', [
            'matrix' => [
                'records' => [
                    [
                        'entity' => $updatedEntityId,
                        'test_private_property' => 'new_value',
                        'test-private-property2' => 'new_value2',
                        'test_public_property' => 'new_value3',
                        'test_array_field' => $arrayFieldValue,
                    ],
                ],
            ],
        ]);

        $this->checkData(0);
        $this->assertEntityValues($expectedValuesAfterChange, $updatedEntityId);

        $this->checkDispatchedEvents(1);
    }

    public static function updateRecordDataProvider(): Generator
    {
        yield 'record with all fields filled' => [
            10,
            ['abcd_9', '', '', ['arr_val_9', null, 9]],
            'arr_val_A|arr_val_B',
            ['new_value', 'new_value2', 'new_value3', ['arr_val_A', 'arr_val_B']],
        ];
        yield 'record with all fields filled, set empty array' => [
            10,
            ['abcd_9', '', '', ['arr_val_9', null, 9]],
            '',
            ['new_value', 'new_value2', 'new_value3', []],
        ];
        yield 'record with empty array' => [
            11,
            ['abcd_10', '', '', []],
            'arr_val_A|arr_val_B',
            ['new_value', 'new_value2', 'new_value3', ['arr_val_A', 'arr_val_B']],
        ];
    }

    public function testDuplicationFoundInDatabase(): void
    {
        $updatedEntityId = self::DEFAULT_RECORDS_NUMBER + 2;
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        // insert new data
        $this->submitSelectFileForm(__DIR__ . '/../../Fixtures/Resources/test.csv');
        $this->client->submitForm('btn-submit');

        $this->checkData();
        $this->assertEntityValues(['test2', 'lorem ipsum 2', 'qwerty2', ['arr_val_1', 'arr_val_2', 'arr_val_3']], $updatedEntityId);
        $this->checkDispatchedEvents(self::NEW_RECORDS_NUMBER);

        // update existing data
        $this->eventRecorder->reset();
        $this->submitSelectFileForm(__DIR__ . '/../../Fixtures/Resources/test_updated_data.csv');

        $this->client->submitForm('btn-submit', [
            'matrix' => [
                'records' => [
                    [
                        'entity' => $updatedEntityId,
                        'test_private_property' => 'test1',
                        'test_public_property' => 'qwerty1',
                        'test-private-property2' => 'lorem ipsum 1',
                        'test_array_field' => 'arr_val_1|arr_val_2|arr_val_3',
                    ],
                ],
            ],
        ]);

        $response = $this->client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertStringContainsString(
            'Such entity already exists for the same values of fields: test_private_property, test_public_property.',
            $response->getContent(),
        );
        self::assertStringContainsString('Such entity already exists for the same values of fields: test-private-property2.', $response->getContent());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER + self::NEW_RECORDS_NUMBER, $this->getRepository()->findAll());

        $this->checkDispatchedEvents(0);
    }

    /**
     * Records duplicated only within the file are not found by DatabaseEntityUnique (it checks only data in database),
     * so unique constraint of database is violated during saving. Entity manager is closed then,
     * and preview with error has to be still rendered, so user can correct data and send it again.
     */
    public function testUniqueConstraintOfDatabaseViolatedDuringSave(): void
    {
        $this->submitSelectFileForm(__DIR__ . '/../../Fixtures/Resources/test.csv');

        // "test_private_property" is unique in database, the same value is already in the first record
        $this->client->submitForm('btn-submit', ['matrix[records][1][test_private_property]' => 'test1']);

        $response = $this->client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertStringContainsString('save data to database. Data is not unique.', $response->getContent());
        self::assertStringContainsString('id="btn-submit"', $response->getContent());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        $this->checkDispatchedEvents(0);

        // the same preview is sent again with corrected value
        $this->client->submitForm('btn-submit', ['matrix[records][1][test_private_property]' => 'test2']);

        $this->checkData();
        $this->assertEntityValues(['test2', 'lorem ipsum 2', 'qwerty2', ['arr_val_1', 'arr_val_2', 'arr_val_3']], self::DEFAULT_RECORDS_NUMBER + 2);
        $this->checkDispatchedEvents(self::NEW_RECORDS_NUMBER);
    }

    public function testFormWithFileIsSentToRouteWithParameters(): void
    {
        $this->client->request('GET', '/jg_batch_entity_import_bundle/import_with_parameter/users');

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertSame(
            '/jg_batch_entity_import_bundle/import_with_parameter/users',
            $this->client->getCrawler()->filterXPath('//form[@name="file_import"]')->attr('action'),
        );
    }

    public function testImportFileWrongExtension(): void
    {
        $uploadedFile = __DIR__ . '/../../Fixtures/Resources/test_wrong_extension.txt';
        $this->submitSelectFileForm($uploadedFile);

        self::assertStringContainsString('Wrong file extension.', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());

        $this->checkDispatchedEvents(0);
    }

    public function testInvalidDataTypeFlashMessage(): void
    {
        $uploadedFile = __DIR__ . '/../../Fixtures/Resources/test_invalid_field_type.csv';
        $this->submitSelectFileForm($uploadedFile);

        $this->client->submitForm('btn-submit');
        self::assertStringContainsString('Invalid type of data in row 1, column &quot;test_public_property&quot;. Probably missing validation.', $this->client->getResponse()->getContent());

        $this->checkDispatchedEvents(0);
    }

    public function testTruncatedDataIsNotImported(): void
    {
        $limit = (int) ini_get('max_input_vars');
        if ($limit <= 0) {
            self::markTestSkipped('Limit "max_input_vars" is disabled.');
        }

        // number of fields is equal to the limit, so it is not possible to tell if PHP dropped the rest of them
        $records = [];
        for ($i = 0; $i < (int) ceil($limit / 2); ++$i) {
            $records[] = ['test_private_property' => 'private_' . $i, 'test_public_property' => 'public_' . $i];
        }

        $this->client->request('POST', '/jg_batch_entity_import_bundle/import_save', ['matrix' => ['records' => $records]]);

        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));
        $this->client->followRedirect();
        self::assertStringContainsString('Too much data.', $this->client->getResponse()->getContent());
        self::assertStringContainsString((string) $limit, $this->client->getResponse()->getContent());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        $this->checkDispatchedEvents(0);
    }

    public function testUnreadableFileShowsError(): void
    {
        $path = sys_get_temp_dir() . '/batch_entity_import_unreadable.xlsx';
        file_put_contents($path, 'this is not a spreadsheet');

        try {
            $this->submitSelectFileForm($path);
        } finally {
            unlink($path);
        }

        self::assertStringContainsString('File can not be read.', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
    }

    public function testCsvWithUnknownEncodingShowsError(): void
    {
        $path = sys_get_temp_dir() . '/batch_entity_import_unknown_encoding.csv';
        file_put_contents($path, (string) iconv('UTF-8', 'CP1250', "test_private_property,test_public_property\nŻaneta,Łódź\n"));

        try {
            $this->submitSelectFileForm($path);
        } finally {
            unlink($path);
        }

        self::assertStringContainsString('Encoding of file is not recognized.', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
    }

    public function testFileWithDuplicatedHeaderShowsError(): void
    {
        $path = sys_get_temp_dir() . '/batch_entity_import_duplicated_header.csv';
        file_put_contents($path, "test_private_property,test_private_property\na,b\n");

        try {
            $this->submitSelectFileForm($path);
        } finally {
            unlink($path);
        }

        self::assertStringContainsString('Names of columns in header should be unique.', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
    }

    #[DataProvider('invalidPostDataProvider')]
    public function testInvalidPostDataIsNotImported(array $parameters): void
    {
        $this->client->request('POST', '/jg_batch_entity_import_bundle/import_save', $parameters);

        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));
        $this->client->followRedirect();
        self::assertStringContainsString('Data not found', $this->client->getCrawler()->filterXPath('//div[@class="alert alert-danger"]')->text());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        $this->checkDispatchedEvents(0);
    }

    public static function invalidPostDataProvider(): Generator
    {
        yield 'no data' => [[]];
        yield 'matrix is not an array' => [['matrix' => 'abcd']];
        yield 'records are not an array' => [['matrix' => ['records' => 'abcd']]];
        yield 'record is not an array' => [['matrix' => ['records' => ['abcd']]]];
        yield 'value is not scalar' => [['matrix' => ['records' => [['test_private_property' => ['a']]]]]];
        yield 'invalid name of column' => [['matrix' => ['records' => [['test.private' => 'a']]]]];
    }

    public function testRecordsWithoutFirstIndexDoNotCauseError(): void
    {
        $this->client->request('POST', '/jg_batch_entity_import_bundle/import_save', [
            'matrix' => ['records' => [5 => ['test_private_property' => 'a', 'test_public_property' => 'b', 'test-private-property2' => 'c']]],
        ]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        $this->checkDispatchedEvents(0);
    }

    /**
     * Rows of preview always have the same columns, so different columns can be sent only in modified request.
     * Missing value must not cause error of validators (e.g. DatabaseEntityUnique) or misaligned columns in preview.
     */
    public function testRecordsWithDifferentColumnsDoNotCauseError(): void
    {
        $this->client->request('POST', '/jg_batch_entity_import_bundle/import_save', [
            'matrix' => ['records' => [
                ['test_private_property' => 'a', 'test_public_property' => 'b', 'test-private-property2' => 'c'],
                ['test_private_property' => 'd', 'test-private-property2' => 'e'],
                ['test_private_property' => 'f', 'test_public_property' => 'g', 'test-private-property2' => 'h', 'test_array_field' => 'i'],
            ]],
        ]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        // header contains all columns, and each record has field for each of them
        // "entity" column and 4 columns of data
        $rows = $this->client->getCrawler()->filterXPath('//table//tr');
        self::assertSame(5, $rows->first()->filterXPath('.//th')->count());
        self::assertSame([5, 5, 5], $rows->slice(1)->each(static fn (Crawler $row): int => $row->filterXPath('.//td')->count()));
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        $this->checkDispatchedEvents(0);
    }

    public function testControllerUsesSharedConfigurationService(): void
    {
        $controller = self::getContainer()->get(ControllerWithAttribute::class);
        $configuration = new ReflectionMethod($controller, 'getImportConfiguration')->invoke($controller);

        self::assertInstanceOf(BaseConfiguration::class, $configuration);
        self::assertSame(self::getContainer()->get(BaseConfiguration::class), $configuration);
    }

    public function testControllerWithoutAttributeException(): void
    {
        $this->client->catchExceptions(false);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('Import configuration is not set. Add #[AsImportController] attribute to "%s"', ControllerWithoutAttribute::class));
        $this->client->request('GET', '/jg_batch_entity_import_bundle/import_without_attribute');
        $this->client->submitForm('btn-submit', ['file_import[file]' => __DIR__ . '/../../Fixtures/Resources/test.csv']);

        $this->checkDispatchedEvents(0);
    }

    private function submitSelectFileForm(string $uploadedFile): void
    {
        $this->client->request('GET', self::URL);
        self::assertTrue($this->client->getResponse()->isSuccessful());

        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertEquals(self::URL, $this->client->getRequest()->getRequestUri());
    }

    private function checkData(int $newRecordsNumber = self::NEW_RECORDS_NUMBER): void
    {
        $repository = $this->getRepository();
        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));
        $this->client->followRedirect();
        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertStringContainsString('Data has been imported', $this->client->getCrawler()->filterXPath('//div[@class="alert alert-success"]')->text());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER + $newRecordsNumber, $repository->findAll());
    }

    private function assertEntityValues(array $expectedValues, int $entityId): void
    {
        /** @var TestEntity|null $item */
        $item = $this->getRepository()->find($entityId);
        self::assertNotEmpty($item);
        self::assertSame($expectedValues[0], $item->getTestPrivateProperty());
        self::assertSame($expectedValues[1], $item->getTestPrivateProperty2());
        self::assertSame($expectedValues[2], $item->testPublicProperty);
        self::assertSame($expectedValues[3], $item->getTestArrayField());
    }

    private function getRepository(): EntityRepository
    {
        return self::$kernel->getContainer()->get('doctrine.orm.entity_manager')->getRepository(TestEntity::class);
    }

    private function checkDispatchedEvents(int $expectedNumber): void
    {
        $dispatchedEvents = $this->eventRecorder->getEventsFor(RecordImportedSuccessfullyEvent::class);
        self::assertCount($expectedNumber, $dispatchedEvents);
        foreach ($dispatchedEvents as $event) {
            self::assertInstanceOf(RecordImportedSuccessfullyEvent::class, $event);
            self::assertSame(TestEntity::class, $event->class);
            self::assertNotEmpty($event->id);
        }
    }
}
