<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Attribute;

use Attribute;
use JG\BatchEntityImportBundle\Import\ImportHandler;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Passes import configuration and ImportHandler to controller which uses ImportControllerTrait.
 * Controller has to be registered as autoconfigured service.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsImportController extends Autoconfigure
{
    /**
     * @param class-string<ImportConfigurationInterface> $configuration class of import configuration, it has to be registered as a service
     */
    public function __construct(string $configuration)
    {
        // References are used, so controller gets the same instances as other services,
        // and container can not be built when configuration is not registered as a service.
        parent::__construct(calls: [
            ['setImportConfiguration' => ['@' . $configuration]],
            ['setImportHandler' => ['@' . ImportHandler::class]],
        ]);
    }
}
