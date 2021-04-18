<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ImportControllerTraitTest extends WebTestCase
{
    protected KernelBrowser           $client;
    protected ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
    }

    public function testControllerWorksOk(): void
    {
        $repository = $this->entityManager->getRepository(TranslatableEntity::class);
        self::assertEmpty($repository->findAll());

        $this->client->request('GET', '/jg_batch_entity_import_bundle/import');
        self::assertTrue($this->client->getResponse()->isSuccessful());

        $uploadedFile = __DIR__ . '/../Fixtures/Resources/test.csv';
        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertEquals('/jg_batch_entity_import_bundle/import', $this->client->getRequest()->getRequestUri());

        $this->client->submitForm('btn-submit');

        self::assertTrue($this->client->getResponse()->isRedirect('/jg_batch_entity_import_bundle/import'));
        $this->client->followRedirect();
        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertStringContainsString('Data has been imported', $this->client->getResponse()->getContent());
        self::assertCount(2, $repository->findAll());

        /** @var TranslatableEntity|null $item */
        $item = $repository->find(2);

        self::assertNotEmpty($item);
    }

    public function testImportFileWrongExtension(): void
    {
        $this->client->request('GET', '/jg_batch_entity_import_bundle/import');

        $uploadedFile = __DIR__ . '/../Fixtures/Resources/test.txt';
        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertStringContainsString('Wrong file extension.', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
    }
}
