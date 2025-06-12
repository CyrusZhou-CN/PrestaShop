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

use PrestaShopBundle\Form\Admin\Type\CardType;
use PrestaShopBundle\Form\Admin\Type\PriceReductionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DiscountConditionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('condition_application_type', ChoiceType::class, [
                'choices' => [
                    'None' => 'none',
                    'On cart' => 'on_cart',
                    'On delivery' => 'on_delivery',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'on_cart',
                'label' => 'Apply free shipping',
                'choice_attr' => function ($choice, $key, $value) {
                    return ['class' => 'shipping-choice'];
                },
                'row_attr' => ['class' => 'shipping-section-row'],
            ])
            ->add('condition_type', ChoiceType::class, [
                'choices' => [
                    'Specific products' => 'specific_products',
                    'Product segment' => 'product_segment',
                    'Minimum purchase amount' => 'minimal_amount',
                    'Minimum product quantity' => 'minimum_quantity',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'minimal_amount',
                'label' => 'Cart conditions',
                'choice_attr' => function ($choice, $key, $value) {
                    return ['class' => 'cart-condition-choice'];
                },
                'row_attr' => ['class' => 'cart-conditions-row'],
            ])
            ->add('minimal_amount', PriceReductionType::class, [
                'currency_select' => true,
                'label' => false,
                'required' => false,
                'row_attr' => [
                    'class' => 'discount-container minimum-amount-container',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'form_theme' => '@PrestaShop/Admin/Sell/Catalog/Discount/FormTheme/conditions_form_theme.html.twig',
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->children['condition_application_type']->vars['attr']['class'] = 'shipping-section';
        $view->children['condition_type']->vars['attr']['class'] = 'cart-conditions-section';
    }

    public function getParent()
    {
        return CardType::class;
    }
}
