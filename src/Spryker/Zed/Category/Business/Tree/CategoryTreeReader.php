<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Category\Business\Tree;

use Generated\Shared\Transfer\CategoryCriteriaTransfer;
use Generated\Shared\Transfer\CategoryTransfer;
use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\NodeCollectionTransfer;
use Generated\Shared\Transfer\NodeTransfer;
use Orm\Zed\Category\Persistence\SpyCategoryNode;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Category\Business\Tree\Formatter\CategoryTreeFormatter;
use Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface;
use Spryker\Zed\Category\Persistence\CategoryRepositoryInterface;

class CategoryTreeReader implements CategoryTreeReaderInterface
{
    protected const ID = 'id';
    protected const ID_CATEGORY = 'id_category';
    protected const ID_PARENT = 'parent';
    protected const TEXT = 'text';
    protected const IS_ACTIVE = 'is_active';
    protected const IS_MAIN = 'is_main';
    protected const IS_CLICKABLE = 'is_clickable';
    protected const IS_IN_MENU = 'is_in_menu';
    protected const IS_SEARCHABLE = 'is_searchable';
    protected const CATEGORY_TEMPLATE_NAME = 'category_template_name';

    /**
     * @var \Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface
     */
    protected $queryContainer;

    /**
     * @var \Spryker\Zed\Category\Business\Tree\Formatter\CategoryTreeFormatter
     */
    protected $treeFormatter;

    /**
     * @var \Spryker\Zed\Category\Persistence\CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @param \Spryker\Zed\Category\Persistence\CategoryQueryContainerInterface $queryContainer
     * @param \Spryker\Zed\Category\Business\Tree\Formatter\CategoryTreeFormatter $treeFormatter
     * @param \Spryker\Zed\Category\Persistence\CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        CategoryQueryContainerInterface $queryContainer,
        CategoryTreeFormatter $treeFormatter,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->queryContainer = $queryContainer;
        $this->treeFormatter = $treeFormatter;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @param int $idNode
     * @param \Generated\Shared\Transfer\LocaleTransfer $locale
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNode[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getChildren($idNode, LocaleTransfer $locale)
    {
        return $this->queryContainer
            ->queryFirstLevelChildrenByIdLocale($idNode, $locale->getIdLocale())
            ->find();
    }

    /**
     * @param int $idNode
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNode|null
     */
    public function getNodeById($idNode)
    {
        return $this->queryContainer
            ->queryNodeById($idNode)
            ->findOne();
    }

    /**
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNode[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getRootNodes()
    {
        return $this->queryContainer
            ->queryRootNode()
            ->find();
    }

    /**
     * @param int $idCategory
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNode[]|\Propel\Runtime\Collection\ObjectCollection
     */
    public function getAllNodesByIdCategory($idCategory)
    {
        return $this->queryContainer
            ->queryAllNodesByCategoryId($idCategory)
            ->orderByNodeOrder(Criteria::ASC)
            ->find();
    }

    /**
     * @param int $idCategory
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return array
     */
    public function getTree($idCategory, LocaleTransfer $localeTransfer)
    {
        $nodes = $this->getAllNodesByIdCategory($idCategory);
        $categoryNodes = $nodes->getData();
        if ($categoryNodes) {
            return $this->getTreeNodesRecursively($localeTransfer, $categoryNodes[0], true);
        }

        return $this->getTreeNodesRecursively($localeTransfer, null, true);
    }

    /**
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     * @param \Orm\Zed\Category\Persistence\SpyCategoryNode|null $node
     * @param bool $isRoot
     *
     * @return array
     */
    protected function getTreeNodesRecursively(LocaleTransfer $localeTransfer, ?SpyCategoryNode $node = null, $isRoot = false)
    {
        $tree = [];
        if ($node === null) {
            $children = $this->getRootNodes();
        } else {
            $children = $this->getChildren($node->getIdCategoryNode(), $localeTransfer);
        }
        if ($isRoot) {
            $idParent = 0;
        } else {
            $idParent = $node->getIdCategoryNode();
        }

        foreach ($children as $child) {
            $text = $child->getCategory()
                ->getLocalisedAttributes($localeTransfer->getIdLocale())
                ->getFirst()
                ->getName();

            $tree[] = [
                self::ID => $child->getIdCategoryNode(),
                self::ID_CATEGORY => $child->getFkCategory(),
                self::ID_PARENT => $idParent,
                self::TEXT => $text,
                self::IS_MAIN => $child->getIsMain(),
                self::IS_ACTIVE => $child->getCategory()->isActive(),
                self::IS_IN_MENU => $child->getCategory()->getIsInMenu(),
                self::IS_CLICKABLE => $child->getCategory()->getIsClickable(),
                self::IS_SEARCHABLE => $child->getCategory()->getIsSearchable(),
                self::CATEGORY_TEMPLATE_NAME => $child->getCategory()->getCategoryTemplate()->getName(),
            ];
            if ($child->countDescendants() > 0) {
                $tree = array_merge($tree, $this->getTreeNodesRecursively($localeTransfer, $child));
            }
        }

        return $tree;
    }

    /**
     * @param int $idCategory
     * @param \Generated\Shared\Transfer\LocaleTransfer $locale
     *
     * @return array
     */
    public function getTreeNodeChildren($idCategory, LocaleTransfer $locale)
    {
        $categories = $this->getTree(
            $idCategory,
            $locale
        );

        return $categories;
    }

    /**
     * @param int $idCategory
     * @param \Generated\Shared\Transfer\LocaleTransfer $locale
     *
     * @return array
     */
    public function getTreeNodeChildrenByIdCategoryAndLocale($idCategory, LocaleTransfer $locale)
    {
        $categories = $this->getTreeNodeChildren($idCategory, $locale);

        $this->treeFormatter->setupCategories($categories);

        return $this->treeFormatter->getCategoryTree();
    }

    /**
     * @param int $idCategoryNode
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return array
     */
    public function getSubTree($idCategoryNode, LocaleTransfer $localeTransfer)
    {
        $categoryNodeEntity = $this->getNodeById($idCategoryNode);
        $subTreeCategories = $this->getTreeNodesRecursively($localeTransfer, $categoryNodeEntity, true);
        $this->treeFormatter->setupCategories($subTreeCategories);

        return $this->treeFormatter->getCategoryTree();
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\NodeCollectionTransfer
     */
    public function getCategoryNodeCollectionTree(
        CategoryTransfer $categoryTransfer,
        CategoryCriteriaTransfer $categoryCriteriaTransfer
    ): NodeCollectionTransfer {
        $nodeCollectionTransfer = new NodeCollectionTransfer();
        $categoryNodes = $this->categoryRepository->getCategoryNodeChildNodesCollectionIndexedByParentNodeId(
            $categoryTransfer,
            $categoryCriteriaTransfer
        );

        if ($categoryNodes === []) {
            return $nodeCollectionTransfer;
        }

        $categoryNodeTransfer = $this->buildNodeTree($categoryNodes, $categoryTransfer->getCategoryNode());

        return $nodeCollectionTransfer->addNode($categoryNodeTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\NodeTransfer[][] $categoryNodes
     * @param \Generated\Shared\Transfer\NodeTransfer $parentNodeTransfer
     *
     * @return \Generated\Shared\Transfer\NodeTransfer
     */
    protected function buildNodeTree(array $categoryNodes, NodeTransfer $parentNodeTransfer): NodeTransfer
    {
        $nodeCollectionTransfer = new NodeCollectionTransfer();
        $childrenNodes = $this->findChildrenNodes($categoryNodes, $parentNodeTransfer);
        foreach ($childrenNodes as $childrenNode) {
            $childNodeTransfer = $this->buildNodeTree($categoryNodes, $childrenNode);
            $nodeCollectionTransfer->addNode($childNodeTransfer);
        }

        return $parentNodeTransfer->setChildrenNodes($nodeCollectionTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\NodeTransfer[][] $categoryNodesCollection
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     *
     * @return \Generated\Shared\Transfer\NodeTransfer
     */
    protected function getCategoryParentNode(array $categoryNodesCollection, CategoryTransfer $categoryTransfer): NodeTransfer
    {
        foreach ($categoryNodesCollection as $nodeTransfers) {
            foreach ($nodeTransfers as $nodeTransfer) {
                if ($categoryTransfer->getIdCategory() === $nodeTransfer->getFkCategory()) {
                    return $nodeTransfer;
                }
            }
        }

        return $categoryTransfer->getCategoryNode();
    }

    /**
     * @param \Generated\Shared\Transfer\NodeTransfer[][] $categoryNodesCollection
     * @param \Generated\Shared\Transfer\NodeTransfer $categoryNode
     *
     * @return array
     */
    protected function findChildrenNodes(array $categoryNodesCollection, NodeTransfer $categoryNode): array
    {
        return $categoryNodesCollection[$categoryNode->getIdCategoryNode()] ?? [];
    }
}
