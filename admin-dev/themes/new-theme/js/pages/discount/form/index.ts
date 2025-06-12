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

import PriceReductionManager from '@components/form/price-reduction-manager';
import DiscountMap from '@pages/discount/discount-map';
import CreateFreeGiftDiscount from '@pages/discount/form/create-free-gift-discount';

$(() => {
  const {eventEmitter} = window.prestashop.instance;

  window.prestashop.component.initComponents(
    [
      'TranslatableInput',
    ],
  );
  new CreateFreeGiftDiscount(eventEmitter);

  new PriceReductionManager(
    DiscountMap.reductionTypeSelect,
    DiscountMap.includeTaxInput,
    DiscountMap.currencySelect,
    DiscountMap.reductionValueSymbol,
    DiscountMap.currencySelectContainer,
  );
  toggleCurrency();
  document.querySelector(DiscountMap.reductionTypeSelect)?.addEventListener('change', toggleCurrency);

  function toggleCurrency(): void {
    if ($(DiscountMap.reductionTypeSelect).val() === 'percentage') {
      $(DiscountMap.currencySelect).fadeOut();
    } else {
      $(DiscountMap.currencySelect).fadeIn();
    }
  }

  const conditionApplicationChoices = document.querySelectorAll('input[name$="[condition_application_type]"]');
  const conditionsSection = document.getElementById('cart-conditions-section');

  function toggleCartConditions() {
    const selectedInput = document.querySelector(
      'input[name$="[condition_application_type]"]:checked',
    ) as HTMLInputElement | null;

    const selectedValue = selectedInput?.value;

    if (conditionsSection) {
      conditionsSection.style.display = selectedValue === 'on_cart' ? 'block' : 'none';
    }
  }

  conditionApplicationChoices.forEach((choice) => {
    choice.addEventListener('change', toggleCartConditions);
  });

  conditionApplicationChoices.forEach((choice) => {
    choice.addEventListener('change', function (this: HTMLInputElement) {
      document.querySelectorAll('.option-card').forEach((card) => {
        card.classList.remove('active');
      });
      this.closest('.option-card')?.classList.add('active');
    });
  });

  const conditionChoices = document.querySelectorAll('input[name$="[condition_type]"]');

  function toggleConditionControls() {
    const selectedInput = document.querySelector('input[name$="[condition_type]"]:checked') as HTMLInputElement | null;
    const selectedValue = selectedInput?.value;

    document.querySelectorAll('.condition-controls').forEach((control) => {
      (control as HTMLElement).style.display = 'none'; // eslint-disable-line
    });

    if (selectedValue === 'minimal_amount') {
      const amountControls = document.getElementById('amount-controls');

      if (amountControls) amountControls.style.display = 'block';
    } else if (selectedValue === 'minimum_quantity') {
      const quantityControls = document.getElementById('quantity-controls');

      if (quantityControls) quantityControls.style.display = 'block';
    }
  }

  conditionChoices.forEach((choice) => {
    choice.addEventListener('change', toggleConditionControls);
  });

  toggleCartConditions();
  toggleConditionControls();
});
