<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Controller;

use Doctrine\ORM\EntityRepository;
use Generator;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class ImportControllerTraitTest extends WebTestCase
{
    private const DEFAULT_RECORDS_NUMBER = 20;
    private const NEW_RECORDS_NUMBER = 12;
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
        $databaseLoader->loadFixtures();
    }

    /**
     * @dataProvider importActionUrlDataProvider
     */
    public function testControllerWorksOk(string $importUrl): void
    {
        $updatedEntityId = self::DEFAULT_RECORDS_NUMBER + 2;
        self::assertCount(self::DEFAULT_RECORDS_NUMBER, $this->getRepository()->findAll());
        // insert new data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test.csv', $importUrl);
        $this->client->submitForm('btn-submit');
        $this->checkData('test2', $updatedEntityId, $importUrl);

        // update existing data
        $this->submitSelectFileForm(__DIR__ . '/../Fixtures/Resources/test_updated_data.csv', $importUrl);
        $this->client->submitForm('btn-submit', [
            'matrix' => [
                'records' => [
                    [
                        'entity' => $updatedEntityId,
                        'test_private_property' => 'new_value',
                    ],
                ],
            ],
        ]);
        $this->checkData('new_value', $updatedEntityId, $importUrl);
    }

    public function importActionUrlDataProvider(): Generator
    {
        yield ['/jg_batch_entity_import_bundle/import'];
        yield ['/jg_batch_entity_import_bundle/subscribed_service/import'];
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

    /**
     * @dataProvider importActionUrlDataProvider2
     */
    public function testImportConfigurationServiceNotFound(string $url): void
    {
        $this->client->catchExceptions(false);
        $this->expectException(ServiceNotFoundException::class);
        $this->client->request('GET', $url);
        $this->client->submitForm('btn-submit', ['file_import[file]' => __DIR__ . '/../Fixtures/Resources/test.csv']);
    }

    public function importActionUrlDataProvider2(): Generator
    {
        yield ['/jg_batch_entity_import_bundle/import_no_service'];
    }

    private function submitSelectFileForm(string $uploadedFile, string $importUrl = '/jg_batch_entity_import_bundle/import'): void
    {
        $this->client->request('GET', $importUrl);
        self::assertTrue($this->client->getResponse()->isSuccessful());
        $this->checkQueriesNumber(0);

        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertEquals($importUrl, $this->client->getRequest()->getRequestUri());
        $this->checkQueriesNumber(1);
    }

    private function checkData(string $expectedValue, int $entityId, string $importUrl = '/jg_batch_entity_import_bundle/import'): void
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
        self::assertSame($expectedValue, $item->getTestPrivateProperty());
    }

    private function getRepository(): EntityRepository
    {
        return self::$kernel->getContainer()->get('doctrine.orm.entity_manager')->getRepository(TranslatableEntity::class);
    }

    private function checkQueriesNumber(int $limit = 1): void
    {
        if ($profile = $this->client->getProfile()) {
            self::assertLessThanOrEqual($limit, $profile->getCollector('db')->getQueryCount());
        }
    }
}
