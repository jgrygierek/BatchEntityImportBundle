<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\KnpLabs\Controller;

use Doctrine\ORM\EntityRepository;
use Generator;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Tests\SkippedTestsTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportControllerTraitTest extends WebTestCase
{
    use SkippedTestsTrait;

    private const DEFAULT_RECORDS_NUMBER = 20;
    private const NEW_RECORDS_NUMBER = 30;
    private const URL = '/jg_batch_entity_import_bundle/translatable/import';
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->markKnpLabsTestAsSkipped();

        $this->client = self::createClient();

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
        $databaseLoader->loadFixtures();
    }

    public function testInsertNewData(): void
    {
        $updatedEntityId = self::DEFAULT_RECORDS_NUMBER + 2;
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());

        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test.csv');
        $this->client->submitForm('btn-submit');
        $this->checkData(['test2', 'lorem ipsum 2', 'qwerty2', 'test2_en', 'test2_pl'], $updatedEntityId);
    }

    /**
     * @dataProvider updateRecordDataProvider
     */
    public function testUpdateExistingRecord(int $updatedEntityId, array $expectedDefaultValues, array $expectedValuesAfterChange): void
    {
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        $this->assertEntityValues($expectedDefaultValues, $updatedEntityId);

        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test_updated_data.csv');
        $this->client->submitForm('btn-submit', [
            'matrix' => [
                'records' => [
                    [
                        'entity' => $updatedEntityId,
                        'test_private_property' => 'new_value',
                        'test-private-property2' => 'new_value2',
                        'test_public_property' => 'new_value3',
                        'test-translation-property:en' => 'new_value4',
                        'testTranslationProperty:pl' => 'new_value5',
                    ],
                ],
            ],
        ]);
        $this->checkData($expectedValuesAfterChange, $updatedEntityId, 0);
    }

    public static function updateRecordDataProvider(): Generator
    {
        yield 'record with all fields filled' => [
            10,
            ['abcd_9', '', '', 'qwerty_en_9', 'qwerty_pl_9'],
            ['new_value', 'new_value2', 'new_value3', 'new_value4', 'new_value5'],
        ];

        yield 'record without en field filled' => [
            20,
            ['abcd_19', '', '', '', 'qwerty_pl_19'],
            ['new_value', 'new_value2', 'new_value3', 'new_value4', 'new_value5'],
        ];

        yield 'record without pl field filled' => [
            1,
            ['abcd_0', '', '', 'qwerty_en_0', 'qwerty_en_0'],
            ['new_value', 'new_value2', 'new_value3', 'new_value4', 'new_value5'],
        ];
    }

    public function testDuplicationFoundInDatabase(): void
    {
        $updatedEntityId = self::DEFAULT_RECORDS_NUMBER + 2;
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        // insert new data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test.csv');
        $this->client->submitForm('btn-submit');
        $this->checkData(['test2', 'lorem ipsum 2', 'qwerty2', 'test2_en', 'test2_pl'], $updatedEntityId);

        // update existing data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test_updated_data.csv');

        $this->client->submitForm('btn-submit', [
            'matrix' => [
                'records' => [
                    [
                        'entity' => $updatedEntityId,
                        'test_private_property' => 'test',
                        'test_public_property' => 'qwerty',
                        'test-private-property2' => 'lorem ipsum',
                    ],
                ],
            ],
        ]);

        $response = $this->client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertStringContainsString('Such entity already exists for the same values of fields: test_private_property, test_public_property.', $response->getContent());
        self::assertStringContainsString('Such entity already exists for the same values of fields: test-private-property2.', $response->getContent());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER + self::NEW_RECORDS_NUMBER, $this->getRepository()->findAll());
    }

    public function testInvalidDataTypeFlashMessage(): void
    {
        $uploadedFile = __DIR__ . '/../Fixtures/Resources/test_invalid_field_type.csv';
        $this->submitSelectFileForm($uploadedFile);
        $this->client->submitForm('btn-submit');
        self::assertStringContainsString('Invalid type of data. Probably missing validation.', $this->client->getResponse()->getContent());
    }

    private function submitSelectFileForm(string $uploadedFile): void
    {
        $this->client->request('GET', self::URL);
        self::assertTrue($this->client->getResponse()->isSuccessful());

        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertEquals(self::URL, $this->client->getRequest()->getRequestUri());
    }

    private function checkData(
        array $expectedValues,
        int $entityId,
        int $newRecordsNumber = self::NEW_RECORDS_NUMBER,
    ): void {
        $repository = $this->getRepository();
        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));
        $this->client->followRedirect();
        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertStringContainsString('Data has been imported', $this->client->getResponse()->getContent());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER + $newRecordsNumber, $repository->findAll());

        $this->assertEntityValues($expectedValues, $entityId);
    }

    private function assertEntityValues(array $expectedValues, int $entityId): void
    {
        /** @var TranslatableEntity|null $item */
        $item = $this->getRepository()->find($entityId);
        self::assertNotEmpty($item);
        self::assertSame($expectedValues[0], $item->getTestPrivateProperty());
        self::assertSame($expectedValues[1], $item->getTestPrivateProperty2());
        self::assertSame($expectedValues[2], $item->testPublicProperty);
        self::assertSame($expectedValues[3], $item->translate('en')->getTestTranslationProperty());
        self::assertSame($expectedValues[4], $item->translate('pl')->getTestTranslationProperty());
    }

    private function getRepository(): EntityRepository
    {
        return self::$kernel->getContainer()->get('doctrine.orm.entity_manager')->getRepository(TranslatableEntity::class);
    }
}
