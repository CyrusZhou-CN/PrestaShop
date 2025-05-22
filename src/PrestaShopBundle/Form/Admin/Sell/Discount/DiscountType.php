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

namespace PrestaShopBundle\Form\Admin\Sell\Discount;

use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Discount\DiscountSettings;
use PrestaShop\PrestaShop\Core\Domain\Discount\ValueObject\DiscountType as DiscountTypeVo;
use PrestaShopBundle\Form\Admin\Type\PriceReductionType;
use PrestaShopBundle\Form\Admin\Type\TextPreviewType;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class DiscountType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $discountType = $options['discount_type'];

        $builder
            ->add('discount_type', TextPreviewType::class, [
                'data' => $discountType,
                'label' => $this->trans('Discount Type', 'Admin.Catalog.Feature'),
                'required' => false,
            ])
            ->add('names', TranslatableType::class, [
                'label' => $this->trans('Discount Name', 'Admin.Catalog.Feature'),
                'label_help_box' => $this->trans('This will be displayed in the cart summary, as well as on the invoice.', 'Admin.Catalog.Help'),
                'required' => true,
                'type' => TextType::class,
                'constraints' => [
                    new DefaultLanguage(),
                ],
                'options' => [
                    'constraints' => [
                        new TypedRegex([
                            'type' => 'generic_name',
                        ]),
                        new Length([
                            'max' => DiscountSettings::MAX_NAME_LENGTH,
                            'maxMessage' => $this->trans(
                                'This field cannot be longer than %limit% characters',
                                'Admin.Notifications.Error',
                                ['%limit%' => DiscountSettings::MAX_NAME_LENGTH]
                            ),
                        ]),
                    ],
                ],
            ])
        ;

        if ($discountType === DiscountTypeVo::CART_LEVEL || $discountType === DiscountTypeVo::ORDER_LEVEL) {
            $builder
                ->add('reduction', PriceReductionType::class, [
                    'currency_select' => true,
                    'label' => $this->trans('Discount value', 'Admin.Catalog.Feature'),
                    'label_help_box' => $this->trans('You can choose a minimum amount for the cart either with or without the taxes.', 'Admin.Catalog.Help'),
                    'required' => false,
                    'row_attr' => [
                        'class' => 'discount-container',
                    ],
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'label' => false,
        ]);
        $resolver->setRequired([
            'discount_type',
        ]);
        $resolver->setAllowedTypes('discount_type', ['string']);
    }
}
