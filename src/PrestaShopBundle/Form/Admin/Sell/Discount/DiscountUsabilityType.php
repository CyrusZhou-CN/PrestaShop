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

namespace PrestaShopBundle\Form\Admin\Sell\Discount;

use DateTime;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DateRange;
use PrestaShop\PrestaShop\Core\Util\DateTime\DateTime as DateTimeUtil;
use PrestaShopBundle\Form\Admin\Type\CardType;
use PrestaShopBundle\Form\Admin\Type\DateRangeType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

class DiscountUsabilityType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('mode', DiscountUsabilityModeType::class, [
                'label' => $this->trans('Specifiy discount mode', 'Admin.Catalog.Feature'),
                'label_tag_name' => 'h3',
                'required' => false,
            ])
            ->add('customer_eligibility', DiscountCustomerEligibilityType::class, [
                'label' => $this->trans('Select customer eligibility', 'Admin.Catalog.Feature'),
                'label_tag_name' => 'h3',
                'required' => false,
            ])
            ->add('valid_date_range', DateRangeType::class, [
                'label' => $this->trans('Select a Period', 'Admin.Catalog.Feature'),
                'label_tag_name' => 'h3',
                'label_from' => $this->trans('Start date', 'Admin.Catalog.Feature'),
                'label_to' => $this->trans('Expiry date', 'Admin.Catalog.Feature'),
                'required' => false,
                'date_format' => DateRangeType::DEFAULT_DATE_TIME_FORMAT,
                'placeholder' => DateRangeType::DEFAULT_DATE_TIME_FORMAT,
                'default_end_value' => (new DateTime())->modify('+1 month')->setTime(23, 59, 59)->format(DateTimeUtil::DEFAULT_DATETIME_FORMAT),
                'constraints' => [
                    new DateRange([
                        'message' => $this->trans(
                            'The expiration date must be after start date',
                            'Admin.Notifications.Error'
                        ),
                    ]),
                ],
            ])
            ->add('period_never_expires', CheckboxType::class, [
                'label' => $this->trans('Period never expires', 'Admin.Catalog.Feature'),
                'required' => false,
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
        ;
    }

    public function getParent()
    {
        return CardType::class;
    }
}
