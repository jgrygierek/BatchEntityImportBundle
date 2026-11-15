<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ImportAllowedColumnsControllerTest extends AbstractWebTestCase
{
    private const string URL = '/jg_batch_entity_import_bundle/import_allowed_columns';
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createCustomClient(self::bootKernel());

        self::getService(DatabaseLoader::class)->reload();
    }

    public function testNotAllowedColumnsAreMarkedInPreviewAndNotImported(): void
    {
        $this->client->request('GET', self::URL);
        $this->client->submitForm('btn-submit', ['file_import[file]' => __DIR__ . '/../../Fixtures/Resources/test.csv']);
        self::assertTrue($this->client->getResponse()->isSuccessful());

        // only "test_private_property" is allowed, other columns are marked the same way as unknown columns
        $headers = $this->client->getCrawler()->filterXpath('//table//th')->each(
            static fn ($node): string => (string) preg_replace('/\s+/', ' ', trim($node->text())),
        );
        self::assertSame(
            [
                'Override record',
                'test_private_property',
                'Unknown field test-private-property2',
                'Unknown field test_public_property',
                'Unknown field test_array_field',
            ],
            $headers,
        );

        $this->client->submitForm('btn-submit');
        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));

        $items = self::getService(EntityManagerInterface::class, 'doctrine.orm.entity_manager')
            ->getRepository(TestEntity::class)
            ->findBy([], ['id' => 'ASC']);
        self::assertCount(30, $items);
        self::assertSame('test1', $items[0]->getTestPrivateProperty());
        self::assertSame('test2', $items[1]->getTestPrivateProperty());
        foreach ($items as $item) {
            // values of not allowed columns are present in file, but they are not imported
            self::assertSame('', $item->testPublicProperty);
            self::assertSame('', $item->getTestPrivateProperty2());
            self::assertSame([], $item->getTestArrayField());
        }
    }
}
