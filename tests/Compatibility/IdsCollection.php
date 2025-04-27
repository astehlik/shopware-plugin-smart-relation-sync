<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Compatibility;

if (class_exists('Shopware\\Core\\Test\\Stub\\Framework\\IdsCollection')) {
    class IdsCollection extends \Shopware\Core\Test\Stub\Framework\IdsCollection {}
} else {
    class IdsCollection extends \Shopware\Core\Framework\Test\IdsCollection {}
}
