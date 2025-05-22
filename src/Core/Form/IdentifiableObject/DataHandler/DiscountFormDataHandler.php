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

namespace PrestaShop\PrestaShop\Core\Form\IdentifiableObject\DataHandler;

use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Context\LanguageContext;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyException;
use PrestaShop\PrestaShop\Core\Domain\Currency\ValueObject\CurrencyId;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\UpdateDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\DiscountSettings;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Discount\ValueObject\DiscountId;
use PrestaShop\PrestaShop\Core\Domain\Discount\ValueObject\DiscountType;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainConstraintException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class DiscountFormDataHandler implements FormDataHandlerInterface
{
    public const PRODUCT_ID = 1;
    public const COMBINATION_ID = 2;

    public function __construct(
        protected readonly CommandBusInterface $commandBus,
        #[Autowire(service: 'prestashop.default.language.context')]
        protected readonly LanguageContext $defaultLanguageContext,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws DiscountConstraintException
     * @throws DomainConstraintException
     * @throws CurrencyException
     */
    public function create(array $data)
    {
        // For the moment the names are not sent by the form so we continue to generate it as we did later in the method.
        $command = new AddDiscountCommand($data['discount_type'], $data['names'] ?? []);
        switch ($data['discount_type']) {
            case DiscountType::FREE_SHIPPING:
                break;
            case DiscountType::CART_LEVEL:
            case DiscountType::ORDER_LEVEL:
                if ($data['reduction']['type'] === DiscountSettings::AMOUNT) {
                    $command->setAmountDiscount(
                        new DecimalNumber((string) $data['reduction']['value']),
                        new CurrencyId($data['reduction']['currency']),
                        (bool) $data['reduction']['include_tax']
                    );
                } elseif ($data['reduction']['type'] === DiscountSettings::PERCENT) {
                    $command->setPercentDiscount(new DecimalNumber((string) $data['reduction']['value']));
                } else {
                    throw new RuntimeException('Unknown discount value type ' . $data['reduction']['type']);
                }
                break;
            case DiscountType::PRODUCT_LEVEL:
                $command->setPercentDiscount(new DecimalNumber('50'));
                $command->setReductionProduct(1);
                break;
            case DiscountType::FREE_GIFT:
                $command->setProductId(self::PRODUCT_ID);
                $command->setCombinationId(self::COMBINATION_ID);
                break;
            default:
                throw new RuntimeException('Unknown discount type ' . $data['discount_type']);
        }

        $command->setActive(true);

        // Random code based on discount type
        $command->setCode(strtoupper(uniqid($data['discount_type'] . '_')));
        $command->setTotalQuantity(100);

        /** @var DiscountId $discountId */
        $discountId = $this->commandBus->handle($command);

        return $discountId->getValue();
    }

    /**
     * @throws DomainConstraintException
     * @throws DiscountConstraintException
     * @throws CurrencyException
     */
    public function update($id, array $data): void
    {
        $command = new UpdateDiscountCommand($id);
        switch ($data['discount_type']) {
            case DiscountType::FREE_SHIPPING:
            case DiscountType::CART_LEVEL:
            case DiscountType::ORDER_LEVEL:
                if ($data['reduction']['type'] === DiscountSettings::AMOUNT) {
                    $command->setAmountDiscount(
                        new DecimalNumber((string) $data['reduction']['value']),
                        $data['reduction']['currency'],
                        (bool) $data['reduction']['include_tax']
                    );
                } elseif ($data['reduction']['type'] === DiscountSettings::PERCENT) {
                    $command->setPercentDiscount(new DecimalNumber((string) $data['reduction']['value']));
                } else {
                    throw new RuntimeException('Unknown discount value type ' . $data['reduction']['type']);
                }
                break;
            case DiscountType::PRODUCT_LEVEL:
            case DiscountType::FREE_GIFT:
                break;
            default:
                throw new RuntimeException('Unknown discount type ' . $data['discount_type']);
        }
        $command
            ->setLocalizedNames($data['names'])
        ;

        $this->commandBus->handle($command);
    }
}
