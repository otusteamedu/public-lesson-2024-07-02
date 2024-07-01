<?php

namespace App\Doctrine;

use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class MigrationEventListener
{
    /**
     * @throws SchemaException
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();
        if (!$schema->hasNamespace('public')) {
            $schema->createNamespace('public');
        }
    }
}
