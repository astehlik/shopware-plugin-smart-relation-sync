<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EntityWriteSubscriber implements EventSubscriberInterface
{
    private bool $inSubscriber = false;

    public function __construct(
        private readonly ObsoleteRelationsDeleter $obsoleteRelationsDeleter,
    ) {}

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents(): array
    {
        return [
            /** @uses onPreWriteValidation() */
            PreWriteValidationEvent::class => 'onPreWriteValidation',
        ];
    }

    public function onPreWriteValidation(PreWriteValidationEvent $event): void
    {
        if ($this->inSubscriber) {
            return;
        }

        $this->inSubscriber = true;

        $this->obsoleteRelationsDeleter->deleteObsoleteRelations($event->getContext(), $event->getWriteContext());

        $this->inSubscriber = false;
    }
}
