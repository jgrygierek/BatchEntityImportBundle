<?php

namespace JG\BatchEntityImportBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Tests\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportControllerTraitTest extends WebTestCase
{
    protected KernelBrowser $client;

    protected ?EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->client        = self::createClient();
        $this->entityManager = self::$kernel->getContainer()->get('doctrine.orm.entity_manager');

        $this->loadDatabaseFixtures();

//        $kernel     = self::$kernel;
//        $aplication = new Application($kernel);
//        $aplication->setAutoExit(false);
//        $output = new NullOutput();
//
//        $input1 = new ArrayInput(['command' => 'doctrine:database:drop', '--env' => 'test', '--force']);
//        $input2 = new ArrayInput(['command' => 'doctrine:database:create', '--env' => 'test']);
//        $input3 = new ArrayInput(['command' => 'doctrine:schema:create', '--env' => 'test']);
//
//        $aplication->run($input1, $output);
//        $aplication->run($input2, $output);
//        $aplication->run($input3, $output);
    }

    protected function loadDatabaseFixtures(): void
    {
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

        $this->assertCount(2, $repository->findAll());

//        /** @var TranslatableEntity|null $item */
//        $item = $repository->find(2);
//
//        $this->assertNotEmpty($item);
//        $this->assertSame('test2', $item->getTestProperty());
//        $this->assertSame('test2_en', $item->translate('en')->getTestTranslationProperty());
//        $this->assertSame('test2_pl', $item->translate('pl')->getTestTranslationProperty());
    }
//
//    public function testViews(): void
//    {
//        $twig   = self::$kernel->getContainer()->get('twig');
//        $loader = $twig->getLoader();
//
//        $this->assertTrue($loader->exists('@BatchEntityImport/edit_matrix.html.twig'));
//        $this->assertTrue($loader->exists('@BatchEntityImport/select_file.html.twig'));
//    }
}
