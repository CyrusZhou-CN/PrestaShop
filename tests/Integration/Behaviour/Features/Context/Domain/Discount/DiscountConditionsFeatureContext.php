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

namespace Tests\Integration\Behaviour\Features\Context\Domain\Discount;

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\UpdateDiscountConditionsCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\ProductRule;
use PrestaShop\PrestaShop\Core\Domain\Discount\ProductRuleGroup;
use PrestaShop\PrestaShop\Core\Domain\Discount\ProductRuleType;
use PrestaShop\PrestaShop\Core\Domain\Discount\Query\GetDiscountForEditing;
use PrestaShop\PrestaShop\Core\Domain\Discount\QueryResult\DiscountForEditing;
use Tests\Integration\Behaviour\Features\Context\Domain\AbstractDomainFeatureContext;
use Tests\Integration\Behaviour\Features\Context\Util\PrimitiveUtils;

class DiscountConditionsFeatureContext extends AbstractDomainFeatureContext
{
    /**
     * @When I update discount :discountReference with the condition of a minimum amount:
     *
     * @param string $discountReference
     * @param TableNode $tableNode
     *
     * @return void
     */
    public function updateDiscountConditionMinimalAmount(string $discountReference, TableNode $tableNode): void
    {
        $data = $tableNode->getRowsHash();
        $command = new UpdateDiscountConditionsCommand($this->referenceToId($discountReference));
        $command->setMinimumAmount(
            new DecimalNumber($data['minimum_amount']),
            $this->referenceToId($data['minimum_amount_currency']),
            PrimitiveUtils::castStringBooleanIntoBoolean($data['minimum_amount_tax_included']),
            PrimitiveUtils::castStringBooleanIntoBoolean($data['minimum_amount_shipping_included']),
        );
        $this->getCommandBus()->handle($command);
    }

    /**
     * @When I update discount :discountReference with the condition it requires at least :quantity products
     *
     * @param string $discountReference
     * @param int $quantity
     *
     * @return void
     */
    public function updateDiscountConditionMinimalProductQuantity(string $discountReference, int $quantity): void
    {
        $command = new UpdateDiscountConditionsCommand($this->referenceToId($discountReference));
        $command->setMinimumProductsQuantity($quantity);
        $this->getCommandBus()->handle($command);
    }

    /**
     * @When I update discount :discountReference with following conditions matching at least :quantity products:
     *
     * @param string $discountReference
     * @param int $quantity
     * @param TableNode $tableNode
     *
     * @return void
     */
    public function updateDiscountProductConditions(string $discountReference, int $quantity, TableNode $tableNode): void
    {
        $command = new UpdateDiscountConditionsCommand($this->referenceToId($discountReference));

        $conditions = $tableNode->getColumnsHash();
        $productRules = [];
        foreach ($conditions as $condition) {
            $productRules[] = new ProductRule(
                ProductRuleType::tryFrom($condition['condition_type']),
                $this->referencesToIds($condition['items'])
            );
        }

        // This matches the current business rule for the new form, a discount can only have ONE product rule group
        // (which represent an AND condition), however they can have multiple product rules (which represent and
        // OR condition)
        // If we decide to increase the number of groups later this behat step will need to be refactored but so far
        // it matches our needs.
        $command->setProductConditions([
            new ProductRuleGroup(
                $quantity,
                $productRules,
            ),
        ]);
        $this->getCommandBus()->handle($command);
    }

    /**
     * @Then discount :discountReference should have the following product conditions matching at least :quantity products:
     *
     * @param string $discountReference
     * @param TableNode $tableNode
     *
     * @return void
     */
    public function assertProductConditions(string $discountReference, int $quantity, TableNode $tableNode): void
    {
        /** @var DiscountForEditing $discountForEditing */
        $discountForEditing = $this->getQueryBus()->handle(
            new GetDiscountForEditing($this->getSharedStorage()->get($discountReference))
        );

        $conditionsData = $tableNode->getColumnsHash();
        $productConditions = $discountForEditing->getProductConditions();
        Assert::assertEquals(1, count($productConditions), sprintf('We only handle ONE condition group for now, %d groups were found', count($productConditions)));

        $productRuleGroup = $productConditions[0];
        Assert::assertEquals($quantity, $productRuleGroup->getQuantity(), sprintf('Expected at least %d product quantity but got %d instead', $quantity, $productRuleGroup->getQuantity()));

        $productRules = $productRuleGroup->getRules();
        Assert::assertEquals(count($conditionsData), count($productRules), sprintf('Expected %d rules but got %d instead', count($conditionsData), count($productRules)));

        foreach ($conditionsData as $index => $conditionData) {
            $productRule = $productRules[$index];
            Assert::assertEquals($conditionData['condition_type'], $productRule->getType()->value);
            $expectedItemIds = $this->referencesToIds($conditionData['items']);
            Assert::assertEquals($expectedItemIds, $productRule->getItemIds(), 'The expected items do not match');
        }
    }

    /**
     * @Then discount :discountReference should have no product conditions
     *
     * @param string $discountReference
     *
     * @return void
     */
    public function assertNoProductConditions(string $discountReference): void
    {
        /** @var DiscountForEditing $discountForEditing */
        $discountForEditing = $this->getQueryBus()->handle(
            new GetDiscountForEditing($this->getSharedStorage()->get($discountReference))
        );

        Assert::assertEmpty($discountForEditing->getProductConditions(), 'Product conditions were found when none is expected');
    }
}
