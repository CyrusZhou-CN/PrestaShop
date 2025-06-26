# ./vendor/bin/behat -c tests/Integration/Behaviour/behat.yml -s discount --tags update-discount-conditions
@restore-all-tables-before-feature
@restore-languages-after-feature
@update-discount-conditions
Feature: Update discount condition
  PrestaShop allows BO users to update discounts conditions
  As a BO user
  I must be able to update conditions on discounts

  Background:
    Given shop "shop1" with name "test_shop" exists
    Given there is a currency named "usd" with iso code "USD" and exchange rate of 0.92

  Scenario: Create discount with minimum products quantity
    When I create a "free_shipping" discount "discount_with_min_products" with following properties:
      | name[en-US] | Promotion |
    Then discount "discount_with_min_products" should have the following properties:
      | name[en-US] | Promotion     |
      | type        | free_shipping |
    When I update discount "discount_with_min_products" with the condition it requires at least 23 products
    Then discount "discount_with_min_products" should have the following properties:
      | name[en-US]              | Promotion     |
      | type                     | free_shipping |
      | minimum_product_quantity | 23            |
    And discount "discount_with_min_products" should have no product conditions

  Scenario: Create discount with restricted list of products
    Given I add product "beer_product" with following information:
      | name[en-US] | bottle of beer |
      | type        | standard       |
    And I add product "potato_chips_product" with following information:
      | name[en-US] | potato chips |
      | type        | standard     |
    When I create a "free_shipping" discount "discount_with_restricted_products" with following properties:
      | name[en-US] | Promotion |
    Then discount "discount_with_restricted_products" should have the following properties:
      | name[en-US] | Promotion     |
      | type        | free_shipping |
    When I update discount "discount_with_restricted_products" with following conditions matching at least 42 products:
      | condition_type | items                              |
      | products       | beer_product, potato_chips_product |
    Then discount "discount_with_restricted_products" should have the following properties:
      | name[en-US]              | Promotion     |
      | type                     | free_shipping |
      | minimum_product_quantity | 0             |
    Then discount "discount_with_restricted_products" should have the following product conditions matching at least 42 products:
      | condition_type | items                              |
      | products       | beer_product, potato_chips_product |

  Scenario: Create discount with minimum amount
    When I create a "free_shipping" discount "discount_with_min_amount" with following properties:
      | name[en-US] | Promotion |
    Then discount "discount_with_min_amount" should have the following properties:
      | name[en-US] | Promotion     |
      | type        | free_shipping |
    When I update discount "discount_with_min_amount" with the condition of a minimum amount:
      | minimum_amount                   | 12.56 |
      | minimum_amount_currency          | usd   |
      | minimum_amount_tax_included      | true  |
      | minimum_amount_shipping_included | true  |
    Then discount "discount_with_min_amount" should have the following properties:
      | name[en-US]                      | Promotion     |
      | type                             | free_shipping |
      | minimum_product_quantity         | 0             |
      | minimum_amount                   | 12.56         |
      | minimum_amount_currency          | usd           |
      | minimum_amount_tax_included      | true          |
      | minimum_amount_shipping_included | true          |
    And discount "discount_with_min_amount" should have no product conditions
