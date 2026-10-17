<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestRelatedEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Event\TestableEventDispatcher;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ImportRelationsControllerTest extends AbstractWebTestCase
{
    private const URL = '/jg_batch_entity_import_bundle/import_relations';
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    /** @var TestableEventDispatcher */
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->client = $this->createCustomClient(self::bootKernel());

        self::getService(DatabaseLoader::class)->reload();

        $this->entityManager = self::getService(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
        $this->entityManager->persist(new TestRelatedEntity('first'));
        $this->entityManager->persist(new TestRelatedEntity('second'));
        $this->entityManager->flush();

        $this->eventDispatcher = self::getTestableEventDispatcher();
        $this->eventDispatcher->resetDispatchedEvents();
    }

    public function testImportRelations(): void
    {
        $this->submitSelectFileForm();

        // preview: select with all related entities and values from file
        self::assertSame(['', '1', '2'], $this->getSelectOptions(0, 'value'));
        self::assertSame(['---', 'Label first', 'Label second'], $this->getSelectOptions(0, 'text'));
        self::assertSame('1', $this->getSelectedOption(0));
        self::assertSame('2', $this->getSelectedOption(1));
        self::assertNull($this->getSelectedOption(2));

        // preview: text field with values from file and validation error for unknown value
        self::assertSame('second', $this->getTextValue(0));
        self::assertEmpty($this->getTextValue(1));
        self::assertSame('unknown', $this->getTextValue(2));
        self::assertStringContainsString('Related entity &quot;unknown&quot; not found.', $this->getResponseContent());

        // saving data with invalid value is not possible
        $this->client->submitForm('btn-submit');

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertStringContainsString('Related entity &quot;unknown&quot; not found.', $this->getResponseContent());
        self::assertEmpty($this->getImportedEntities());
        self::assertFalse($this->eventDispatcher->hasEvent(RecordImportedSuccessfullyEvent::class));

        // fixing value in preview
        $this->client->submitForm('btn-submit', [
            'matrix[records][2][test_second_related_entity]' => 'first',
        ]);

        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));
        $this->client->followRedirect();
        self::assertStringContainsString('Data has been imported', $this->getResponseContent());

        self::assertSame(
            [
                'rel1' => ['first', 'second'],
                'rel2' => ['second', null],
                'rel3' => [null, 'first'],
            ],
            $this->getImportedEntities(),
        );
        self::assertCount(3, $this->eventDispatcher->getEventsFor(RecordImportedSuccessfullyEvent::class));
    }

    public function testChangeRelationsInPreview(): void
    {
        $this->submitSelectFileForm();

        $this->client->submitForm('btn-submit', [
            'matrix[records][0][test_related_entity]' => '',
            'matrix[records][0][test_second_related_entity]' => '',
            'matrix[records][1][test_related_entity]' => '1',
            'matrix[records][2][test_related_entity]' => '2',
            'matrix[records][2][test_second_related_entity]' => 'second',
        ]);

        self::assertTrue($this->client->getResponse()->isRedirect(self::URL));
        self::assertSame(
            [
                'rel1' => [null, null],
                'rel2' => ['first', null],
                'rel3' => ['second', 'second'],
            ],
            $this->getImportedEntities(),
        );
    }

    public function testInvalidSelectValue(): void
    {
        $this->submitSelectFileForm();

        $form = $this->client->getCrawler()->selectButton('btn-submit')->form();
        $form->disableValidation();
        $form['matrix[records][0][test_related_entity]'] = '999';
        $form['matrix[records][2][test_second_related_entity]'] = 'first';
        $this->client->submit($form);

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertFalse($this->client->getResponse()->isRedirect());
        self::assertEmpty($this->getImportedEntities());
        self::assertFalse($this->eventDispatcher->hasEvent(RecordImportedSuccessfullyEvent::class));
    }

    private function submitSelectFileForm(): void
    {
        $this->client->request('GET', self::URL);
        $this->client->submitForm('btn-submit', ['file_import[file]' => __DIR__ . '/../../Fixtures/Resources/test_relations.csv']);

        self::assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * @return array<string>
     */
    private function getSelectOptions(int $row, string $attribute): array
    {
        return $this->client->getCrawler()
            ->filterXPath(\sprintf('//select[@name="matrix[records][%d][test_related_entity]"]/option', $row))
            ->each(static fn ($option): string => 'text' === $attribute ? $option->text() : (string) $option->attr('value'));
    }

    private function getSelectedOption(int $row): ?string
    {
        $option = $this->client->getCrawler()
            ->filterXPath(\sprintf('//select[@name="matrix[records][%d][test_related_entity]"]/option[@selected]', $row));

        return $option->count() ? $option->attr('value') : null;
    }

    private function getTextValue(int $row): ?string
    {
        return $this->client->getCrawler()
            ->filterXPath(\sprintf('//input[@name="matrix[records][%d][test_second_related_entity]"]', $row))
            ->attr('value');
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    private function getImportedEntities(): array
    {
        $this->entityManager->clear();

        $result = [];
        foreach ($this->entityManager->getRepository(TestEntity::class)->findBy([], ['id' => 'ASC']) as $entity) {
            $result[$entity->getTestPrivateProperty()] = [
                $entity->getTestRelatedEntity()?->getName(),
                $entity->getTestSecondRelatedEntity()?->getName(),
            ];
        }

        return $result;
    }

    private function getResponseContent(): string
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        return $content;
    }
}
