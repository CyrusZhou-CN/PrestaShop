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

namespace Tests\Integration\Behaviour\Features\Context\Domain;

use Behat\Gherkin\Node\TableNode;
use Exception;
use PrestaShop\PrestaShop\Core\Domain\Tag\Command\AddTagCommand;
use PrestaShop\PrestaShop\Core\Domain\Tag\Command\EditTagCommand;
use PrestaShop\PrestaShop\Core\Domain\Tag\Exception\DuplicateTagException;
use PrestaShop\PrestaShop\Core\Domain\Tag\Query\GetTagForEditing;
use PrestaShop\PrestaShop\Core\Domain\Tag\QueryResult\EditableTag;
use PrestaShop\PrestaShop\Core\Domain\Tag\ValueObject\TagId;
use RuntimeException;
use Tag;
use Tests\Integration\Behaviour\Features\Context\SharedStorage;

class TagFeatureContext extends AbstractDomainFeatureContext
{
    /**
     * @Given I add a tag :tagReference with specified properties:
     */
    public function addNewTag(string $tagReference, TableNode $node): void
    {
        $data = $node->getRowsHash();

        $language = SharedStorage::getStorage()->get($data['language']);

        $productIds = [];
        foreach (explode(',', $data['products']) as $dataProduct) {
            $productId = SharedStorage::getStorage()->get($dataProduct);
            $productIds[] = $productId;
        }

        $command = new AddTagCommand(
            $data['name'],
            (int) $language->id,
            $productIds
        );

        try {
            /** @var TagId $tagId */
            $tagId = $this->getCommandBus()->handle($command);

            SharedStorage::getStorage()->set($tagReference, $tagId->getValue());
        } catch (Exception $e) {
            $this->setLastException($e);
        }
    }

    /**
     * @When I edit tag :reference with specified properties:
     */
    public function editTagFromSpecifiedProperties(string $reference, TableNode $node): void
    {
        $command = new EditTagCommand((int) SharedStorage::getStorage()->get($reference));

        $data = $node->getRowsHash();
        if (isset($data['name'])) {
            $command->setName($data['name']);
        }
        if (isset($data['language'])) {
            $language = SharedStorage::getStorage()->get($data['language']);

            $command->setLanguageId((int) $language->id);
        }
        if (isset($data['products'])) {
            $productIds = [];
            foreach (explode(',', $data['products']) as $dataProduct) {
                $productIds[] = (int) SharedStorage::getStorage()->get($dataProduct);
            }
            $command->setProductIds($productIds);
        }

        try {
            $this->getCommandBus()->handle($command);
        } catch (Exception $e) {
            $this->setLastException($e);
        }
    }

    /**
     * @Then I should get error that tag is duplicate
     */
    public function assertLastErrorIsDuplicateTag(): void
    {
        $this->assertLastErrorIs(DuplicateTagException::class);
    }

    /**
     * @Then tag :reference name should be :value
     */
    public function assertTagNameMatches(string $reference, string $value): void
    {
        $editableTag = $this->getTagFromReference($reference);

        if ($editableTag->getName() !== $value) {
            throw new RuntimeException(sprintf('Tag name "%s" does not match "%s" name.', $editableTag->getName(), $value));
        }
    }

    /**
     * @Then tag :reference language should be :value
     */
    public function assertTagLanguageMatches(string $reference, string $value): void
    {
        $editableTag = $this->getTagFromReference($reference);

        $language = SharedStorage::getStorage()->get($value);

        if ($editableTag->getLanguageId() !== (int) $language->id) {
            throw new RuntimeException(sprintf('Tag language "%s" does not match "%s" language.', $editableTag->getLanguageId(), $language->id));
        }
    }

    /**
     * @Then tag :reference products should be :value
     */
    public function assertTagProductsMatches(string $reference, string $value): void
    {
        $editableTag = $this->getTagFromReference($reference);
        $valueProductsIds = [];
        foreach ($editableTag->getProducts() as $product) {
            $valueProductsIds[] = $product['id'];
        }
        sort($valueProductsIds);

        $expectedProductsId = [];
        foreach (explode(',', $value) as $expectedProductId) {
            $expectedProductsId[] = SharedStorage::getStorage()->get($expectedProductId);
        }
        sort($expectedProductsId);

        if ($valueProductsIds !== $expectedProductsId) {
            throw new RuntimeException(sprintf(
                'Tag products "%s" does not match "%s" products.',
                implode(',', $valueProductsIds),
                implode(',', $expectedProductsId)
            ));
        }
    }

    protected function getTagFromReference(string $reference): EditableTag
    {
        $idTag = (int) SharedStorage::getStorage()->get($reference);

        /** @var EditableTag $editableTag */
        $editableTag = $this->getQueryBus()->handle(new GetTagForEditing($idTag));

        return $editableTag;
    }
}
