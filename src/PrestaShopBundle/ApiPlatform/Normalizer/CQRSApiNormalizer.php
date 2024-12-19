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

namespace PrestaShopBundle\ApiPlatform\Normalizer;

use PrestaShopBundle\ApiPlatform\Exception\LocaleNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use PrestaShopBundle\Entity\Repository\LangRepository;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * This normalizer is based on the Symfony ObjectNormalizer, but it handles some specific normalization for
 * our CQRS <-> ApiPlatform conversion:
 *  - handle getters that match the property without starting by get, has, is
 *  - set appropriate context for the ValueObjectNormalizer for when we don't want a ValueObject but the scalar value to be used
 *  - converts localized values keys in the arrays on properties that have been flagged as LocalizedValue:
 *    - the input is indexed by locale ['fr-FR' => 'Nom de la valeur', 'en-US' => 'Value name']
 *    - the data is normalized and indexed by locale ID [1 => 'Nom de la valeur', 2 => 'Value name']
 *    - reversely localized data indexed by IDs are converted into an array localized by locale during denormalization
 *  - handle setter methods that use multiple parameters
 *  - handle casting of boolean values
 */
#[AutoconfigureTag('prestashop.api.normalizers')]
class CQRSApiNormalizer extends ObjectNormalizer
{
    public const CAST_BOOL = 'cast_bool';

    /**
     * @var array<int, string>
     */
    protected array $localesByID;

    /**
     * @var array<string, int>
     */
    protected array $idsByLocale;

    public function __construct(
        protected LangRepository $languageRepository,
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?NameConverterInterface $nameConverter = null,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        ?callable $objectClassResolver = null,
        array $defaultContext = []
    ) {
        parent::__construct($classMetadataFactory, $nameConverter, $propertyAccessor, $propertyTypeExtractor, $classDiscriminatorResolver, $objectClassResolver, $defaultContext);
    }

    /**
     * This method is overridden because our CQRS objects sometimes have setters with multiple arguments, these are usually used to force specifying arguments that must
     * be defined all together, so they can be validated as a whole. The ObjectNormalizer only deserialize object properties one at a time, so we have to handle this special
     * use case and the best moment to do so is right after the object is instantiated and right before the properties are deserialized.
     */
    protected function instantiateObject(array &$data, string $class, array &$context, ReflectionClass $reflectionClass, bool|array $allowedAttributes, ?string $format = null)
    {
        $this->castBooleanAttributes($data, $context, $reflectionClass);
        $object = parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
        $methodsWithMultipleArguments = $this->findMethodsWithMultipleArguments($reflectionClass, $data);
        $this->executeMethodsWithMultipleArguments($data, $object, $methodsWithMultipleArguments, $context, $format);

        return $object;
    }

    /**
     * This method is only used to denormalize the constructor parameters, the CQRS classes usually expect scalar input values that
     * are converted into ValueObject in the constructor, so only in this phase of the denormalization we disable the ValueObjectNormalizer
     * by specifying the context option ValueObjectNormalizer::VALUE_OBJECT_RETURNED_AS_SCALAR.
     */
    protected function denormalizeParameter(ReflectionClass $class, ReflectionParameter $parameter, string $parameterName, mixed $parameterData, array $context, ?string $format = null): mixed
    {
        if (($context[LocalizedValue::IS_LOCALIZED_VALUE] ?? false) && is_array($parameterData)) {
            $parameterData = $this->updateLanguageIndexesWithLocales($parameterData);
        }

        return parent::denormalizeParameter($class, $parameter, $parameterName, $parameterData, $context + [ValueObjectNormalizer::VALUE_OBJECT_RETURNED_AS_SCALAR => true], $format);
    }

    /**
     * This method is used when normalizing nested children, in nested value we don't want the ValueObject to be returned as arrays but as simple
     * values, so we force the ValueObjectNormalizer::VALUE_OBJECT_RETURNED_AS_SCALAR option. So ValueObject are only normalized as array when they
     * are the root object.
     */
    protected function createChildContext(array $parentContext, string $attribute, ?string $format): array
    {
        $childContext = parent::createChildContext($parentContext, $attribute, $format);

        return $childContext + [ValueObjectNormalizer::VALUE_OBJECT_RETURNED_AS_SCALAR => true];
    }

    /**
     * This method is overridden in order to increase the getters used to fetch attributes, by default the ObjectNormalizer
     * searches for getters start with get/is/has/can, but it ignores getters that matches the properties exactly.
     */
    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        $attributes = parent::extractAttributes($object, $format, $context);
        if ($this->classMetadataFactory) {
            $metadata = $this->classMetadataFactory->getMetadataFor($object);
            $reflClass = $metadata->getReflectionClass();
        } else {
            $reflClass = new ReflectionClass(\is_object($object) ? $object::class : $object);
        }

        foreach ($reflClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflMethod) {
            if (
                0 !== $reflMethod->getNumberOfRequiredParameters()
                || $reflMethod->isStatic()
                || $reflMethod->isConstructor()
                || $reflMethod->isDestructor()
            ) {
                continue;
            }

            $methodName = $reflMethod->name;
            // These type of getters have already been handled by the parent
            if (str_starts_with($methodName, 'get') || str_starts_with($methodName, 'has') || str_starts_with($methodName, 'is') || str_starts_with($methodName, 'can')) {
                continue;
            }

            // Add attributes that match the getter method name exactly
            if ($reflClass->hasProperty($methodName) && $this->isAllowedAttribute($object, $methodName, $format, $context)) {
                $attributes[] = $methodName;
            }
        }

        return $attributes;
    }

    /**
     * This method is overridden in order to dynamically change the localized properties identified by a context or the LocalizedValue
     * helper attribute. The used key that are based on Language's locale are automatically converted to rely on Language's database ID.
     */
    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
        $attributeValue = parent::getAttributeValue($object, $attribute, $format, $context);
        if (($context[LocalizedValue::IS_LOCALIZED_VALUE] ?? false) && is_array($attributeValue)) {
            $attributeValue = $this->updateLanguageIndexesWithIDs($attributeValue);
        }

        return $attributeValue;
    }

    /**
     * This method is overridden in order to dynamically change the localized properties identified by a context or the LocalizedValue
     *  helper attribute. he used key that are based on Language's database ID are automatically converted to rely on Language's locale.
     */
    protected function setAttributeValue(object $object, string $attribute, mixed $value, ?string $format = null, array $context = [])
    {
        if (($context[LocalizedValue::IS_LOCALIZED_VALUE] ?? false) && is_array($value)) {
            $value = $this->updateLanguageIndexesWithLocales($value);
        }

        parent::setAttributeValue($object, $attribute, $value, $format, $context);
    }

    /**
     * Call all the method with multiple arguments and remove the data from the normalized data since it has already been denormalized into
     * the object.
     *
     * @param array $data
     * @param object $object
     * @param array<string, ReflectionMethod> $methodsWithMultipleArguments
     *
     * @return void
     */
    protected function executeMethodsWithMultipleArguments(array &$data, object $object, array $methodsWithMultipleArguments, array $context, ?string $format = null): void
    {
        foreach ($methodsWithMultipleArguments as $attributeName => $reflectionMethod) {
            $methodParameters = $data[$attributeName];
            // denormalize parameters
            foreach ($reflectionMethod->getParameters() as $parameter) {
                $parameterType = $parameter->getType();
                if ($parameterType instanceof ReflectionNamedType && !$parameterType->isBuiltin()) {
                    $childContext = $this->createChildContext($context, $parameter->getName(), $format);
                    if (!$this->serializer instanceof DenormalizerInterface) {
                        throw new LogicException(sprintf('Cannot denormalize parameter "%s" for method "%s" because injected serializer is not a denormalizer.', $parameter->getName(), $reflectionMethod->getName()));
                    }

                    if ($this->serializer->supportsDenormalization($methodParameters[$parameter->getName()], $parameterType->getName(), $format, $childContext)) {
                        $methodParameters[$parameter->getName()] = $this->serializer->denormalize($methodParameters[$parameter->getName()], $parameterType->getName(), $format, $childContext);
                    }
                }
            }

            $reflectionMethod->invoke($object, ...$methodParameters);
            unset($data[$attributeName]);
        }
    }

    /**
     * Force casting boolean properties si that values like (1, 0, true, on, false, ...) are valid, this is useful for
     * data coming from DB where boolean are returned as tiny integers. Requires CAST_BOOL context option to be true.
     *
     * Note: in Symfony 7.1 a new option AbstractNormalizer::FILTER_BOOL has been introduced, when we upgrade our
     * Symfony dependencies our custom CAST_BOOL option (inspired by the Symfony one) can be removed.
     *
     * https://symfony.com/doc/7.1/serializer.html#handling-boolean-values
     */
    protected function castBooleanAttributes(array &$data, array $context, ReflectionClass $reflectionClass): void
    {
        if (!($context[self::CAST_BOOL] ?? false)) {
            return;
        }

        foreach ($data as $attributeName => $value) {
            if ($reflectionClass->hasProperty($attributeName)) {
                $attributeType = $reflectionClass->getProperty($attributeName)->getType();
                if ($attributeType instanceof ReflectionNamedType && $attributeType->isBuiltin() && $attributeType->getName() === 'bool') {
                    $data[$attributeName] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
        }
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param array $normalizedData
     *
     * @return array<string, ReflectionMethod>
     */
    protected function findMethodsWithMultipleArguments(ReflectionClass $reflectionClass, array $normalizedData): array
    {
        $methodsWithMultipleArguments = [];
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            // We only look into public method that can be setters with multiple parameters
            if (
                $reflectionMethod->getNumberOfRequiredParameters() <= 1
                || $reflectionMethod->isStatic()
                || $reflectionMethod->isConstructor()
                || $reflectionMethod->isDestructor()
            ) {
                continue;
            }

            // Remove set/with to get the potential matching property in data (use full method name by default)
            if (str_starts_with($reflectionMethod->getName(), 'set')) {
                $methodPropertyName = lcfirst(substr($reflectionMethod->getName(), 3));
            } elseif (str_starts_with($reflectionMethod->getName(), 'with')) {
                $methodPropertyName = lcfirst(substr($reflectionMethod->getName(), 4));
            } else {
                $methodPropertyName = $reflectionMethod->getName();
            }

            // No data found matching the method so we skip it
            if (empty($normalizedData[$methodPropertyName])) {
                continue;
            }

            $methodParameters = $normalizedData[$methodPropertyName];
            if (!is_array($methodParameters)) {
                throw new InvalidArgumentException(sprintf('Value for method "%s" should be an array', $reflectionMethod->getName()));
            }

            // Now check that all required parameters are present
            foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                if (!$reflectionParameter->isOptional() && !isset($methodParameters[$reflectionParameter->getName()])) {
                    throw new InvalidArgumentException(sprintf('Missing required parameter "%s" for method "%s"', $reflectionParameter->getName(), $reflectionMethod->getName()));
                }
            }
            $methodsWithMultipleArguments[$methodPropertyName] = $reflectionMethod;
        }

        return $methodsWithMultipleArguments;
    }

    /**
     * Return the localized array with keys based on locale string value transformed into integer database IDs.
     *
     * @param array $localizedValue
     *
     * @return array
     *
     * @throws LocaleNotFoundException
     */
    protected function updateLanguageIndexesWithIDs(array $localizedValue): array
    {
        $indexLocalizedValue = [];
        $this->fetchLanguagesMapping();
        foreach ($localizedValue as $localeKey => $localeValue) {
            if (is_string($localeKey)) {
                if (!isset($this->idsByLocale[$localeKey])) {
                    throw new LocaleNotFoundException('Locale "' . $localeKey . '" not found.');
                }

                $indexLocalizedValue[$this->idsByLocale[$localeKey]] = $localeValue;
            }
        }

        return $indexLocalizedValue;
    }

    /**
     * Return the localized array with keys based on integer database IDs transformed into locale string values.
     *
     * @param array $localizedValue
     *
     * @return array
     *
     * @throws LocaleNotFoundException
     */
    protected function updateLanguageIndexesWithLocales(array $localizedValue): array
    {
        $localeLocalizedValue = [];
        $this->fetchLanguagesMapping();
        foreach ($localizedValue as $localeId => $localeValue) {
            if (is_numeric($localeId)) {
                if (!isset($this->localesByID[$localeId])) {
                    throw new LocaleNotFoundException('Locale with ID "' . $localeId . '" not found.');
                }

                $localeLocalizedValue[$this->localesByID[$localeId]] = $localeValue;
            }
        }

        return $localeLocalizedValue;
    }

    /**
     * Fetches the language mapping once and save them in local property for better performance.
     *
     * @return void
     */
    protected function fetchLanguagesMapping(): void
    {
        if (!isset($this->localesByID) || !isset($this->idsByLocale)) {
            $this->localesByID = [];
            $this->idsByLocale = [];
            foreach ($this->languageRepository->getMapping() as $langId => $language) {
                $this->localesByID[(int) $langId] = $language['locale'];
                $this->idsByLocale[$language['locale']] = (int) $langId;
            }
        }
    }

    /**
     * CQRSApiNormalizer must be the last normalizer executed after all the special types normalizers already did their job.
     *
     * @return int
     */
    public static function getNormalizerPriority(): int
    {
        return -1;
    }
}
