<?php

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

    public function setUp(): void
    {
        parent::setUp();

        $this->client        = self::createClient();
        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');

        $databaseLoader = self::$kernel->getContainer()->get(DatabaseLoader::class);
        $databaseLoader->reload();
    }

    public function testControllerWorksOk(): void
    {
        $repository = $this->entityManager->getRepository(TranslatableEntity::class);
        $this->assertEmpty($repository->findAll());

        $this->client->request('GET', '/jg_batch_entity_import_bundle/import');
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $uploadedFile = __DIR__ . '/../Fixtures/Resources/test.csv';
        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertEquals('/jg_batch_entity_import_bundle/import', $this->client->getRequest()->getRequestUri());

        $this->client->submitForm('btn-submit');

        $this->assertTrue($this->client->getResponse()->isRedirect('/jg_batch_entity_import_bundle/import'));
        $this->client->followRedirect();
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertStringContainsString('Data has been imported', $this->client->getResponse()->getContent());
        $this->assertCount(2, $repository->findAll());

        /** @var TranslatableEntity|null $item */
        $item = $repository->find(2);

        $this->assertNotEmpty($item);
    }

    public function testImportFileWrongExtension(): void
    {
        $this->client->request('GET', '/jg_batch_entity_import_bundle/import');

        $uploadedFile = __DIR__ . '/../Fixtures/Resources/test.txt';
        $this->client->submitForm('btn-submit', ['file_import[file]' => $uploadedFile]);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertStringContainsString('Wrong file extension.', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
    }
}
