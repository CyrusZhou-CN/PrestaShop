# ./vendor/bin/behat -c tests/Integration/Behaviour/behat.yml -s discount --tags full-ux-discount-test-free-shipping
@restore-all-tables-before-feature
@full-ux-discount-test-free-shipping

Feature: Full UX discount test
  PrestaShop allows BO users to create discounts
  As a BO user
  I must be able to create discounts using the new discounts

  Background:
    Given I have an empty default cart
    Given shop "shop1" with name "test_shop" exists
    And there is a currency named "usd" with iso code "USD" and exchange rate of 0.92
    And shop configuration for "PS_CART_RULE_FEATURE_ACTIVE" is set to 1
    And there is a product in the catalog named "product1" with a price of 19.812 and 1000 items in stock
    And language with iso code "en" is the default one

  Scenario: Create a complete discount with free shipping using new CQRS
    When I create a free shipping discount "complete_free_shipping_discount" with following properties:
      | name[en-US]       | Promotion              |
      | active            | true                   |
      | valid_from        | 2025-01-01 11:05:00    |
      | valid_to          | 2025-12-01 00:00:00    |
      | code              | PROMO_2025             |
    And discount "complete_free_shipping_discount" should have the following properties:
      | name[en-US]       | Promotion              |
      | active            | true                   |
      | valid_from        | 2025-01-01 11:05:00    |
      | valid_to          | 2025-12-01 00:00:00    |
      | code              | PROMO_2025             |

  Scenario: One product in cart, one discount offering only free shipping
    Given discount "complete_free_shipping_discount" should have the following properties:
      | name[en-US]   | Promotion  |
      | free_shipping | true       |
      | code          | PROMO_2025 |
    And I add 1 items of product "product1" in my cart
    And my cart total shipping fees should be 7.0 tax included
    And my cart total should be 26.812 tax included
    When I apply the voucher code "complete_free_shipping_discount"
    Then my cart total should be 19.812 tax included
