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

namespace Tests\Integration\ApiPlatform\Serializer;

use DateTimeImmutable;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\CustomerGroup;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Hook;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\Product;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\ValueObject\CreatedApiClient;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Command\EditCartRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\CartRule\ValueObject\CartRuleAction;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Command\AddCustomerGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Query\GetCustomerGroupForEditing;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\QueryResult\EditableCustomerGroup;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\ValueObject\GroupId;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopCollection;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\ApiPlatform\Serializer\DomainSerializer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Integration\Utility\LanguageTrait;
use Tests\Resources\ApiPlatform\Resources\LocalizedResource;
use Tests\Resources\Resetter\LanguageResetter;

class DomainSerializerTest extends KernelTestCase
{
    use LanguageTrait;

    protected const EN_LANG_ID = 1;

    protected static ?int $frenchLangId = null;

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$frenchLangId = null;
        LanguageResetter::resetLanguages();
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$frenchLangId = null;
        LanguageResetter::resetLanguages();
    }

    protected static function getFrenchId(): int
    {
        if (empty(self::$frenchLangId)) {
            self::$frenchLangId = self::addLanguageByLocale('fr-FR');
        }

        return self::$frenchLangId;
    }

    public function testDenormalize(): void
    {
        $serializer = self::getContainer()->get(DomainSerializer::class);

        // We don't use @dataProvider because the class DB setup was messy, so we do it manually
        foreach ($this->getExpectedDenormalizedData() as $useCase => $denormalizedData) {
            list($dataToDenormalize, $denormalizedObject) = $denormalizedData;
            $normalizationMapping = $denormalizedData[2] ?? [];
            self::assertEquals($denormalizedObject, $serializer->denormalize($dataToDenormalize, get_class($denormalizedObject), null, [DomainSerializer::NORMALIZATION_MAPPING => $normalizationMapping]), $useCase);
        }
    }

    public function testDenormalizeWithEmptyValues(): void
    {
        $serializer = self::getContainer()->get(DomainSerializer::class);
        $value = $serializer->denormalize(null, Product::class, null, []);
        self::assertEquals(new Product(), $value);
    }

    public function getExpectedDenormalizedData()
    {
        $localizedResource = new LocalizedResource([
            'en-US' => 'english link',
            'fr-FR' => 'lien français',
        ]);

        // This property has no context attributes, so it remains indexed by IDs
        $localizedResource->names = [
            self::EN_LANG_ID => 'english name',
            self::getFrenchId() => 'nom français',
        ];
        $localizedResource->descriptions = [
            'en-US' => 'english description',
            'fr-FR' => 'description française',
        ];
        $localizedResource->titles = [
            'en-US' => 'english title',
            'fr-FR' => 'titre français',
        ];

        yield 'api resource with localized properties should have indexes based on locale values instead of integers' => [
            [
                'localizedLinks' => [
                    self::EN_LANG_ID => 'english link',
                    self::getFrenchId() => 'lien français',
                ],
                'names' => [
                    self::EN_LANG_ID => 'english name',
                    self::getFrenchId() => 'nom français',
                ],
                'descriptions' => [
                    self::EN_LANG_ID => 'english description',
                    self::getFrenchId() => 'description française',
                ],
                'titles' => [
                    self::EN_LANG_ID => 'english title',
                    self::getFrenchId() => 'titre français',
                ],
            ],
            $localizedResource,
        ];

        yield 'command with various property types all in constructor' => [
            [
                'localizedNames' => [
                    self::EN_LANG_ID => 'test en',
                    self::getFrenchId() => 'test fr',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => true,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            new AddCustomerGroupCommand(
                [
                    self::EN_LANG_ID => 'test en',
                    self::getFrenchId() => 'test fr',
                ],
                new DecimalNumber('10.3'),
                true,
                true,
                [1]
            ),
        ];

        $editCartRuleCommand = new EditCartRuleCommand(1);
        $editCartRuleCommand->setDescription('test description');
        $editCartRuleCommand->setCode('test code');
        $editCartRuleCommand->setMinimumAmount('10', 1, true, true);
        $editCartRuleCommand->setCustomerId(1);
        $editCartRuleCommand->setLocalizedNames([self::EN_LANG_ID => 'test en', self::getFrenchId() => 'test fr']);
        $editCartRuleCommand->setHighlightInCart(true);
        $editCartRuleCommand->setAllowPartialUse(true);
        $editCartRuleCommand->setPriority(1);
        $editCartRuleCommand->setActive(true);
        $editCartRuleCommand->setValidityDateRange(new DateTimeImmutable('2023-08-23'), new DateTimeImmutable('2023-08-25'));
        $editCartRuleCommand->setTotalQuantity(100);
        $editCartRuleCommand->setQuantityPerUser(1);
        $editCartRuleCommand->setCartRuleAction(new CartRuleAction(true));
        yield 'object with complex setter methods based on multiple properties or sub types' => [
            [
                'cartRuleId' => 1,
                'description' => 'test description',
                'code' => 'test code',
                'minimumAmount' => ['minimumAmount' => '10', 'currencyId' => 1, 'taxIncluded' => true, 'shippingIncluded' => true],
                'customerId' => 1,
                'localizedNames' => [
                    self::EN_LANG_ID => 'test en',
                    self::getFrenchId() => 'test fr',
                ],
                'highlightInCart' => true,
                'allowPartialUse' => true,
                'priority' => 1,
                'active' => true,
                'validityDateRange' => ['validFrom' => '2023-08-23', 'validTo' => '2023-08-25'],
                'totalQuantity' => 100,
                'quantityPerUser' => 1,
                'cartRuleAction' => ['freeShipping' => true],
                // TODO: handle cartRuleAction with complex discount handle by business rules
                // 'cartRuleAction' => ['freeShipping' => true, 'giftProduct' => ['productId': 1], 'discount' => ['amountDiscount' => ['amount' => 10]]]...
            ],
            $editCartRuleCommand,
        ];

        yield 'null value returns an empty object' => [
            null,
            new CustomerGroup(),
        ];

        $customerGroupQuery = new GetCustomerGroupForEditing(51);
        yield 'value object with wrong parameter name converted via mapping' => [
            [
                'groupId' => 51,
            ],
            $customerGroupQuery,
            [
                '[groupId]' => '[customerGroupId]',
            ],
        ];

        $customerGroupQuery = new GetCustomerGroupForEditing(51);
        yield 'value object with proper parameter, extra mapping for normalization should ignore absent data and not override it with null' => [
            [
                'customerGroupId' => 51,
            ],
            $customerGroupQuery,
            [
                '[id]' => '[customerGroupId]',
                '[reduction]' => '[reductionPercent]',
            ],
        ];

        $customerGroupQuery = new GetCustomerGroupForEditing(51);
        yield 'value object with wrong parameter plus extra mapping for normalization' => [
            [
                'groupId' => 51,
            ],
            $customerGroupQuery,
            [
                '[groupId]' => '[customerGroupId]',
                '[id]' => '[customerGroupId]',
                '[reduction]' => '[reductionPercent]',
            ],
        ];

        yield 'single shop constraint' => [
            [
                'shopId' => 42,
            ],
            ShopConstraint::shop(42),
        ];

        yield 'shop group constraint' => [
            [
                'shopGroupId' => 42,
            ],
            ShopConstraint::shopGroup(42),
        ];

        yield 'all shop constraint' => [
            [],
            ShopConstraint::allShops(),
        ];

        yield 'strict shop constraint' => [
            [
                'shopGroupId' => null,
                'shopId' => 51,
                'isStrict' => true,
            ],
            ShopConstraint::shop(51, true),
        ];

        yield 'shop collections' => [
            [
                'shopGroupId' => null,
                'shopId' => null,
                'shopIds' => [3, 4],
                'isStrict' => false,
            ],
            ShopCollection::shops([3, 4]),
        ];

        yield 'shop collections list only specified' => [
            [
                'shopIds' => [3, 4],
            ],
            ShopCollection::shops([3, 4]),
        ];

        yield 'add product command' => [
            [
                'productType' => ProductType::TYPE_STANDARD,
                'shopId' => 51,
            ],
            new AddProductCommand(ProductType::TYPE_STANDARD, 51),
        ];

        yield 'get product query' => [
            [
                'productId' => 42,
                'shopConstraint' => [
                    'shopId' => 2,
                ],
                'displayLanguageId' => 51,
            ],
            new GetProductForEditing(42, ShopConstraint::shop(2), 51),
        ];

        $hook = new Hook();
        $hook->id = 1;
        $hook->active = true;
        $hook->name = 'testHook';
        $hook->title = 'testHookTitle';
        $hook->description = '';
        yield 'denormalize an APIPlatform DTO with specified mapping' => [
            [
                'id_hook' => 1,
                'active' => true,
                'name' => 'testHook',
                'title' => 'testHookTitle',
                'description' => '',
            ],
            $hook,
            ['[id_hook]' => '[id]'],
        ];
    }

    public function testNormalize(): void
    {
        $serializer = self::getContainer()->get(DomainSerializer::class);

        // We don't use @dataProvider because the class DB setup was messy, so we do it manually
        foreach ($this->getNormalizationData() as $useCase => $normalizationData) {
            list($dataToNormalize, $expectedNormalizedData) = $normalizationData;
            $normalizationMapping = $normalizationData[2] ?? [];
            self::assertEquals($expectedNormalizedData, $serializer->normalize($dataToNormalize, null, [DomainSerializer::NORMALIZATION_MAPPING => $normalizationMapping]), $useCase);
        }
    }

    public function testNormalizeWithEmptyValues(): void
    {
        $serializer = self::getContainer()->get(DomainSerializer::class);
        $value = $serializer->normalize(null, null, []);
        self::assertNull($value);
    }

    public function getNormalizationData(): iterable
    {
        $localizedResource = new LocalizedResource([
            'fr-FR' => 'http://mylink.fr',
            'en-US' => 'http://mylink.com',
        ]);
        $localizedResource->names = [
            self::getFrenchId() => 'Nom français',
            self::EN_LANG_ID => 'English name',
        ];
        $localizedResource->descriptions = [
            'fr-FR' => 'Description française',
            'en-US' => 'French description',
        ];
        $localizedResource->titles = [
            'fr-FR' => 'Titre français',
            'en-US' => 'French title',
        ];
        yield 'normalize localized resource' => [
            $localizedResource,
            [
                'names' => [
                    self::getFrenchId() => 'Nom français',
                    self::EN_LANG_ID => 'English name',
                ],
                'descriptions' => [
                    self::getFrenchId() => 'Description française',
                    self::EN_LANG_ID => 'French description',
                ],
                'titles' => [
                    self::getFrenchId() => 'Titre français',
                    self::EN_LANG_ID => 'French title',
                ],
                'localizedLinks' => [
                    self::getFrenchId() => 'http://mylink.fr',
                    self::EN_LANG_ID => 'http://mylink.com',
                ],
            ],
        ];

        $createdApiClient = new CreatedApiClient(42, 'my_secret');
        yield 'normalize command result that contains a ValueObject, returned as an integer not an array' => [
            $createdApiClient,
            [
                'apiClientId' => 42,
                'secret' => 'my_secret',
            ],
        ];

        $groupId = new GroupId(42);
        yield 'normalize GroupId value object returned as array' => [
            $groupId,
            [
                'groupId' => 42,
            ],
        ];

        $productId = new ProductId(42);
        yield 'normalize ProductId value object returned as array' => [
            $productId,
            [
                'productId' => 42,
            ],
        ];

        $editableCustomerGroup = new EditableCustomerGroup(
            42,
            [
                1 => 'Group',
                2 => 'Groupe',
            ],
            new DecimalNumber('10.67'),
            false,
            true,
            [
                1,
            ],
        );
        yield 'normalize object with displayPriceTaxExcluded that is a getter not starting by get' => [
            $editableCustomerGroup,
            [
                'id' => 42,
                'localizedNames' => [
                    1 => 'Group',
                    2 => 'Groupe',
                ],
                'reduction' => 10.67,
                'displayPriceTaxExcluded' => false,
                'showPrice' => true,
                'shopIds' => [
                    1,
                ],
            ],
        ];

        yield 'normalize object with displayPriceTaxExcluded that is a getter not starting by get and with extra mapping' => [
            $editableCustomerGroup,
            [
                'id' => 42,
                'localizedNames' => [
                    1 => 'Group',
                    2 => 'Groupe',
                ],
                'reduction' => 10.67,
                'reductionPercent' => 10.67,
                'displayPriceTaxExcluded' => false,
                'showPrice' => true,
                'shopIds' => [
                    1,
                ],
            ],
            [
                '[reduction]' => '[reductionPercent]',
            ],
        ];

        yield 'normalize single shop constraint' => [
            ShopConstraint::shop(42),
            [
                'shopId' => 42,
                'shopGroupId' => null,
                'shopIds' => null,
                'isStrict' => false,
            ],
        ];

        yield 'normalize group shop constraint' => [
            ShopConstraint::shopGroup(42),
            [
                'shopId' => null,
                'shopGroupId' => 42,
                'shopIds' => null,
                'isStrict' => false,
            ],
        ];

        yield 'normalize all shop constraint' => [
            ShopConstraint::allShops(),
            [
                'shopId' => null,
                'shopGroupId' => null,
                'shopIds' => null,
                'isStrict' => false,
            ],
        ];

        yield 'normalize all shop constraint strict' => [
            ShopConstraint::allShops(true),
            [
                'shopId' => null,
                'shopGroupId' => null,
                'shopIds' => null,
                'isStrict' => true,
            ],
        ];

        yield 'normalize collection shops' => [
            ShopCollection::shops([3, 4]),
            [
                'shopId' => null,
                'shopGroupId' => null,
                'shopIds' => [3, 4],
                'isStrict' => false,
            ],
        ];
    }
}
