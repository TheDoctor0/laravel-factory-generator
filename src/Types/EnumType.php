<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class EnumType extends Type
{
    public const ENUM = 'enum';

    /**
     * @param array                                     $fieldDeclaration
     * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
     *
     * @return string
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return self::ENUM;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return self::ENUM;
    }
}
