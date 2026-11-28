<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ImportAllowedColumnsControllerTest extends AbstractWebTestCase
{
    private const string URL = '/jg_batch_entity_import_bundle/import_allowed_columns';
    private const string SAVE_URL = '/jg_batch_entity_import_bundle/import_save_allowed_columns';
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

    /**
     * Updating entities is not allowed by default, so records which should update entities
     * are not silently imported as new entities.
     */
    public function testFileWithEntityIdIsRejectedWhenUpdatingIsNotAllowed(): void
    {
        $this->submitFile(__DIR__ . '/../../Fixtures/Resources/test_updated_data.csv');

        self::assertStringContainsString('Column &quot;entity_id&quot; can not be used', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
        self::assertSame([], $this->getRepository()->findAll());
    }

    public function testFileWithEmptyEntityIdIsAcceptedWhenUpdatingIsNotAllowed(): void
    {
        $this->submitFileWithContent("test_private_property,entity_id\nvalue_1,\nvalue_2,\n");

        self::assertStringContainsString('id="btn-submit"', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
    }

    public function testFileWithoutAllowedColumnsIsRejected(): void
    {
        $this->submitFileWithContent("test_public_property,unknown_column\nvalue_1,value_2\n");

        self::assertStringContainsString('None of the columns of the file can be imported.', $this->client->getResponse()->getContent());
        self::assertStringContainsString('id="file_import_file"', $this->client->getResponse()->getContent());
        self::assertSame([], $this->getRepository()->findAll());
    }

    /**
     * Preview of valid file never sends data without allowed columns, so it can be sent only in modified request.
     * Without the check new entities would be created without any value from file.
     */
    public function testDataWithoutAllowedColumnsSentFromPreviewIsRejected(): void
    {
        // valid file, only to get CSRF token from the preview, so data is rejected because of columns, not because of token
        $this->submitFile(__DIR__ . '/../../Fixtures/Resources/test.csv');
        $token = $this->client->getCrawler()->filterXPath('//input[@name="matrix[_token]"]')->attr('value');
        self::assertNotEmpty($token);

        $this->client->request('POST', self::SAVE_URL, [
            'matrix' => ['records' => [['test_public_property' => 'value_1', 'unknown_column' => 'value_2']], '_token' => $token],
        ]);

        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));
        $this->client->followRedirect();
        self::assertSame(
            'None of the columns of the file can be imported.',
            $this->client->getCrawler()->filterXPath('//div[@class="alert alert-danger"]')->text(),
        );
        self::assertSame([], $this->getRepository()->findAll());
    }

    private function submitFile(string $path): void
    {
        $this->client->request('GET', self::URL);
        $this->client->submitForm('btn-submit', ['file_import[file]' => $path]);
        self::assertTrue($this->client->getResponse()->isSuccessful());
    }

    private function submitFileWithContent(string $content): void
    {
        $path = sys_get_temp_dir() . '/batch_entity_import_allowed_columns.csv';
        file_put_contents($path, $content);

        try {
            $this->submitFile($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * @return EntityRepository<TestEntity>
     */
    private function getRepository(): EntityRepository
    {
        return self::getService(EntityManagerInterface::class, 'doctrine.orm.entity_manager')->getRepository(TestEntity::class);
    }
}
