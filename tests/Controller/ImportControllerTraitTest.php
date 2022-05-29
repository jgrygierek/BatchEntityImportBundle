<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Controller;

use Doctrine\ORM\EntityRepository;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class ImportControllerTraitTest extends WebTestCase
{
    private const DEFAULT_RECORDS_NUMBER = 20;
    private const NEW_RECORDS_NUMBER = 30;
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
        $databaseLoader->loadFixtures();
    }

    public function testControllerWorksOk(): void
    {
        $importUrl = '/jg_batch_entity_import_bundle/import';
        $updatedEntityId = self::DEFAULT_RECORDS_NUMBER + 2;
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        // insert new data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test.csv', $importUrl);
        $this->client->submitForm('btn-submit');
        $this->checkData(['test2', 'lorem ipsum 2', 'qwerty2', 'test2_en', 'test2_pl'], $updatedEntityId, $importUrl);

        // update existing data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test_updated_data.csv', $importUrl);
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
        $this->checkData(['new_value', 'new_value2', 'new_value3', 'new_value4', 'new_value5'], $updatedEntityId, $importUrl);
    }

    public function testDuplicationFoundInDatabase(): void
    {
        $importUrl = '/jg_batch_entity_import_bundle/import';
        $updatedEntityId = self::DEFAULT_RECORDS_NUMBER + 2;
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        // insert new data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test.csv', $importUrl);
        $this->client->submitForm('btn-submit');
        $this->checkData(['test2', 'lorem ipsum 2', 'qwerty2', 'test2_en', 'test2_pl'], $updatedEntityId, $importUrl);

        // update existing data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test_updated_data.csv', $importUrl);

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

    public function testImportFileWrongExtension(): void
    {
        $uploadedFile = __DIR__ . '/../Fixtures/Resources/test.txt';
        $this->submitSelectFileForm($uploadedFile);

        self::assertStringContainsString('Wrong file extension.', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
    }

    public function testInvalidDataTypeFlashMessage(): void
    {
        $uploadedFile = __DIR__ . '/../Fixtures/Resources/test_exception_invalid_type.csv';
        $this->submitSelectFileForm($uploadedFile);
        $this->client->submitForm('btn-submit');
        self::assertStringContainsString('Invalid type of data. Probably missing validation.', $this->client->getResponse()->getContent());
    }

    public function testImportConfigurationServiceNotFound(): void
    {
        $this->client->catchExceptions(false);
        $this->expectException(ServiceNotFoundException::class);
        $this->client->request('GET', '/jg_batch_entity_import_bundle/import_no_service');
        $this->client->submitForm('btn-submit', ['file_import[file]' => __DIR__ . '/../Fixtures/Resources/test.csv']);
    }

    private function submitSelectFileForm(string $uploadedFile, string $importUrl = '/jg_batch_entity_import_bundle/import'): void
    {
        $this->client->request('GET', $importUrl);
        self::assertTrue($this->client->getResponse()->isSuccessful());

        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertEquals($importUrl, $this->client->getRequest()->getRequestUri());
    }

    private function checkData(array $expectedValues, int $entityId, string $importUrl = '/jg_batch_entity_import_bundle/import'): void
    {
        $repository = $this->getRepository();
        self::assertTrue($this->client->getResponse()->isRedirect($importUrl));
        $this->client->followRedirect();
        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertStringContainsString('Data has been imported', $this->client->getResponse()->getContent());
        self::assertCount(self::DEFAULT_RECORDS_NUMBER + self::NEW_RECORDS_NUMBER, $repository->findAll());

        /** @var TranslatableEntity|null $item */
        $item = $repository->find($entityId);
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
