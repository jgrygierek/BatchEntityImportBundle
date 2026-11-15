<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityRepository;
use Generator;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Event\TestableEventDispatcher;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

class ImportControllerTraitTest extends AbstractWebTestCase
{
    private const DEFAULT_RECORDS_NUMBER = 20;
    private const NEW_RECORDS_NUMBER = 30;
    private const URL = '/jg_batch_entity_import_bundle/import';
    private KernelBrowser $client;
    private TestableEventDispatcher|TraceableEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->client = $this->createCustomClient(self::bootKernel());

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
        $databaseLoader->loadFixtures();

        $this->eventDispatcher = self::$kernel->getContainer()->get('event_dispatcher');
        $this->eventDispatcher->resetDispatchedEvents();
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
        $this->eventDispatcher->resetDispatchedEvents();
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
        self::assertStringContainsString('Invalid type of data. Probably missing validation.', $this->client->getResponse()->getContent());

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
        self::assertStringContainsString('Data not found', $this->client->getResponse()->getContent());
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

    public function testImportConfigurationServiceNotFound(): void
    {
        $this->client->catchExceptions(false);
        $this->expectException(ServiceNotFoundException::class);
        $this->client->request('GET', '/jg_batch_entity_import_bundle/import_no_service');
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
        self::assertStringContainsString('Data has been imported', $this->client->getResponse()->getContent());
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
        $dispatchedEvents = $this->eventDispatcher->getEventsFor(RecordImportedSuccessfullyEvent::class);
        self::assertCount($expectedNumber, $dispatchedEvents);
        foreach ($dispatchedEvents as $event) {
            self::assertInstanceOf(RecordImportedSuccessfullyEvent::class, $event);
            self::assertSame(TestEntity::class, $event->class);
            self::assertNotEmpty($event->id);
        }
    }
}
