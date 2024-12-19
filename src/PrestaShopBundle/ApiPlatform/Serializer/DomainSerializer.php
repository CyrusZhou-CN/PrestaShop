<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace PrestaShopBundle\ApiPlatform\Serializer;

use ArrayObject;
use ReflectionException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Traversable;

class DomainSerializer extends SymfonySerializer
{
    public const NORMALIZATION_MAPPING = 'normalization_mapping';

    protected PropertyAccessorInterface $propertyAccessor;

    /**
     * @param Traversable $denormalizers
     */
    public function __construct(iterable $denormalizers)
    {
        parent::__construct(iterator_to_array($denormalizers));
        // Invalid (or absent) indexes or properties in array/objects are invalid, therefore ignored when checking isReadable
        // which is important for the normalization mapping process
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->enableExceptionOnInvalidPropertyPath()
            ->getPropertyAccessor()
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
    {
        // Before anything perform the mapping if specified
        $this->mapNormalizedData($data, $context);

        return parent::denormalize($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): float|int|bool|ArrayObject|array|string|null
    {
        // Save the mapping because it could be removed by recursive normalize calls on lower levels that also call the mapNormalizedData method
        $normalizationMapping = $context[self::NORMALIZATION_MAPPING] ?? null;
        // Then we clean it, so it will only be saved on the root level which is the purpose since the mapping is defined based on the root level
        unset($context[self::NORMALIZATION_MAPPING]);

        $normalizedData = parent::normalize($data, $format, $context);

        // Data is only mapped on the root level after everything else has been normalized
        if ($normalizationMapping) {
            $context[self::NORMALIZATION_MAPPING] = $normalizationMapping;
            $this->mapNormalizedData($normalizedData, $context);
        }

        return $normalizedData;
    }

    /**
     * Modify the normalized data based on a mapping, basically it copies some values from a path to another, the original
     * path is not modified.
     *
     * @param $normalizedData
     * @param array $context
     */
    protected function mapNormalizedData(&$normalizedData, array &$context): void
    {
        if (empty($context[self::NORMALIZATION_MAPPING]) || null === $normalizedData) {
            return;
        }

        if (!is_object($normalizedData) && !is_array($normalizedData)) {
            return;
        }

        $normalizationMapping = $context[self::NORMALIZATION_MAPPING];
        foreach ($normalizationMapping as $originPath => $targetPath) {
            if ($this->propertyAccessor->isReadable($normalizedData, $originPath) && $this->propertyAccessor->isWritable($normalizedData, $targetPath)) {
                $this->propertyAccessor->setValue($normalizedData, $targetPath, $this->propertyAccessor->getValue($normalizedData, $originPath));
            }
        }

        // Mapping is only done once, so we unset it for next recursive calls
        unset($context[self::NORMALIZATION_MAPPING]);
    }
}
