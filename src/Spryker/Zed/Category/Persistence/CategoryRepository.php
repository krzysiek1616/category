<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Category\Persistence;

use Generated\Shared\Transfer\CategoryCollectionTransfer;
use Generated\Shared\Transfer\CategoryCriteriaTransfer;
use Generated\Shared\Transfer\CategoryNodeCriteriaTransfer;
use Generated\Shared\Transfer\CategoryNodeUrlCriteriaTransfer;
use Generated\Shared\Transfer\CategoryNodeUrlPathCriteriaTransfer;
use Generated\Shared\Transfer\CategoryTransfer;
use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\NodeCollectionTransfer;
use Generated\Shared\Transfer\NodeTransfer;
use Generated\Shared\Transfer\StoreRelationTransfer;
use Generated\Shared\Transfer\UrlTransfer;
use Orm\Zed\Category\Persistence\Map\SpyCategoryAttributeTableMap;
use Orm\Zed\Category\Persistence\Map\SpyCategoryClosureTableTableMap;
use Orm\Zed\Category\Persistence\Map\SpyCategoryNodeTableMap;
use Orm\Zed\Category\Persistence\Map\SpyCategoryTableMap;
use Orm\Zed\Category\Persistence\SpyCategoryClosureTableQuery;
use Orm\Zed\Category\Persistence\SpyCategoryNodeQuery;
use Orm\Zed\Category\Persistence\SpyCategoryQuery;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;
use Spryker\Zed\PropelOrm\Business\Model\Formatter\PropelArraySetFormatter;
use Spryker\Zed\PropelOrm\Business\Runtime\ActiveQuery\Criteria;

/**
 * @method \Spryker\Zed\Category\Persistence\CategoryPersistenceFactory getFactory()
 */
class CategoryRepository extends AbstractRepository implements CategoryRepositoryInterface
{
    /**
     * @var string
     */
    protected const KEY_FK_CATEGORY = 'fk_category';

    /**
     * @var string
     */
    protected const KEY_ID_CATEGORY_NODE = 'id_category_node';

    /**
     * @var string
     */
    protected const KEY_FK_CATEGORY_NODE_DESCENDANT = 'fk_category_node_descendant';

    /**
     * @var string
     */
    protected const KEY_NAME = 'name';

    /**
     * @var string
     */
    protected const KEY_CATEGORY_KEY = 'category_key';

    /**
     * @var string
     */
    protected const COL_FK_LOCALE = 'fk_locale';

    /**
     * @var string
     */
    protected const KEY_FK_PARENT_CATEGORY_NODE = 'fk_parent_category_node';

    /**
     * @var string
     */
    public const NODE_PATH_GLUE = '/';

    /**
     * @var string
     */
    public const CATEGORY_NODE_PATH_GLUE = ' / ';

    /**
     * @var int|null
     */
    public const NODE_PATH_NULL_DEPTH = null;

    /**
     * @var int
     */
    public const NODE_PATH_ZERO_DEPTH = 0;

    /**
     * @var int
     */
    public const IS_NOT_ROOT_NODE = 0;

    /**
     * @var string
     */
    protected const COL_CATEGORY_NAME = 'name';

    /**
     * @uses \Orm\Zed\Locale\Persistence\Map\SpyLocaleTableMap::COL_LOCALE_NAME
     *
     * @var string
     */
    protected const COL_LOCALE_NAME = 'spy_locale.locale_name';

    /**
     * @var int
     */
    protected const DEPTH_WITH_CHILDREN_RELATIONS = 1;

    /**
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return \Generated\Shared\Transfer\CategoryCollectionTransfer
     */
    public function getAllCategoryCollection(LocaleTransfer $localeTransfer): CategoryCollectionTransfer
    {
        $categoryQuery = SpyCategoryQuery::create();
        $spyCategories = $categoryQuery
            ->joinWithAttribute()
            ->leftJoinNode()
            ->addAnd(
                SpyCategoryAttributeTableMap::COL_FK_LOCALE,
                $localeTransfer->getIdLocale(),
                Criteria::EQUAL,
            )
            ->find();

        return $this->getFactory()
            ->createCategoryMapper()
            ->mapCategoryCollection($spyCategories, new CategoryCollectionTransfer());
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\CategoryCollectionTransfer
     */
    public function getCategoriesByCriteria(CategoryCriteriaTransfer $categoryCriteriaTransfer): CategoryCollectionTransfer
    {
        return $this->getFactory()
            ->createCategoryMapper()
            ->mapCategoryCollection(
                $this->applyCategoryFilters($this->getFactory()->createCategoryQuery(), $categoryCriteriaTransfer)->find(),
                new CategoryCollectionTransfer(),
            );
    }

    /**
     * @param int $idCategoryNode
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return string
     */
    public function getNodePath(int $idCategoryNode, LocaleTransfer $localeTransfer)
    {
        $nodePathQuery = $this->queryNodePathWithRootNode(
            $idCategoryNode,
            $localeTransfer->getIdLocaleOrFail(),
            static::NODE_PATH_ZERO_DEPTH,
        );

        return $this->generateNodePathString($nodePathQuery, static::NODE_PATH_GLUE);
    }

    /**
     * @param int $idNode
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return string
     */
    public function getCategoryNodePath(int $idNode, LocaleTransfer $localeTransfer): string
    {
        $nodePathQuery = $this->queryNodePathWithoutRootNode(
            $idNode,
            $localeTransfer->getIdLocaleOrFail(),
            static::NODE_PATH_NULL_DEPTH,
        );

        return $this->generateNodePathString($nodePathQuery, static::CATEGORY_NODE_PATH_GLUE);
    }

    /**
     * @param \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery $nodePathQuery
     * @param string $glue
     *
     * @return string
     */
    protected function generateNodePathString(SpyCategoryNodeQuery $nodePathQuery, string $glue): string
    {
        $nodePathQuery = $nodePathQuery
            ->clearSelectColumns()
            ->addSelectColumn(static::COL_CATEGORY_NAME);

        /** @var array<string> $pathTokens */
        $pathTokens = $nodePathQuery->find();

        return implode($glue, $pathTokens);
    }

    /**
     * @param int $idNode
     * @param int $idLocale
     * @param int|null $depth
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery
     */
    protected function queryNodePathWithRootNode(
        int $idNode,
        int $idLocale,
        ?int $depth = self::NODE_PATH_NULL_DEPTH
    ): SpyCategoryNodeQuery {
        $categoryNodeQuery = $this->getFactory()->createCategoryNodeQuery();
        $categoryNodeQuery
            ->useClosureTableQuery()
                ->orderByFkCategoryNodeDescendant(Criteria::DESC)
                ->orderByDepth(Criteria::DESC)
                ->filterByFkCategoryNodeDescendant($idNode)
                ->filterByDepth($depth, Criteria::NOT_EQUAL)
            ->endUse()
            ->useCategoryQuery()
                ->useAttributeQuery()
                    ->filterByFkLocale($idLocale)
                ->endUse()
            ->endUse()
            ->setFormatter(new PropelArraySetFormatter());

        return $categoryNodeQuery;
    }

    /**
     * @param int $idNode
     * @param int $idLocale
     * @param int|null $depth
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery
     */
    protected function queryNodePathWithoutRootNode(
        int $idNode,
        int $idLocale,
        ?int $depth = self::NODE_PATH_NULL_DEPTH
    ): SpyCategoryNodeQuery {
        return $this->queryNodePathWithRootNode($idNode, $idLocale, $depth)
            ->filterByIsRoot((string)static::IS_NOT_ROOT_NODE);
    }

    /**
     * @param string $nodeName
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     *
     * @return bool
     */
    public function checkSameLevelCategoryByNameExists(string $nodeName, CategoryTransfer $categoryTransfer): bool
    {
        $categoryNodeQuery = $this->getFactory()->createCategoryNodeQuery();
        $categoryNodeQuery = $this->applyParentCategoryNodeFilter($categoryNodeQuery, $categoryTransfer);

        $categoryNodeQuery->setIgnoreCase(true)
            ->useCategoryQuery()
                ->filterByIdCategory($categoryTransfer->getIdCategory(), Criteria::NOT_EQUAL)
                ->useAttributeQuery()
                    ->filterByName($nodeName)
                ->endUse()
            ->endUse();

        return $categoryNodeQuery->exists();
    }

    /**
     * @param int $idCategory
     *
     * @return \Generated\Shared\Transfer\CategoryTransfer|null
     */
    public function findCategoryById(int $idCategory): ?CategoryTransfer
    {
        $spyCategoryEntity = $this->getFactory()
            ->createCategoryQuery()
            ->leftJoinWithNode()
            ->leftJoinWithAttribute()
            ->findByIdCategory($idCategory)
            ->getFirst();

        if ($spyCategoryEntity === null) {
            return null;
        }

        return $this->getFactory()->createCategoryMapper()->mapCategoryWithRelations(
            $spyCategoryEntity,
            new CategoryTransfer(),
        );
    }

    /**
     * @param int $idCategoryNode
     *
     * @return array<int>
     */
    public function getChildCategoryNodeIdsByCategoryNodeId(int $idCategoryNode): array
    {
        return $this->getFactory()
            ->createCategoryClosureTableQuery()
            ->select(SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE_DESCENDANT)
            ->findByFkCategoryNode($idCategoryNode)
            ->getData();
    }

    /**
     * @param int $idCategoryNode
     *
     * @return array<int>
     */
    public function getParentCategoryNodeIdsByCategoryNodeId(int $idCategoryNode): array
    {
        return $this->getFactory()
            ->createCategoryClosureTableQuery()
            ->select(SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE)
            ->findByFkCategoryNodeDescendant($idCategoryNode)
            ->getData();
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\CategoryTransfer|null
     */
    public function findCategoryByCriteria(CategoryCriteriaTransfer $categoryCriteriaTransfer): ?CategoryTransfer
    {
        $categoryQuery = $this->getFactory()->createCategoryQuery();
        $categoryQuery = $this->applyCategoryFilters($categoryQuery, $categoryCriteriaTransfer);

        $categoryEntity = $categoryQuery->leftJoinWithAttribute()->find()->getFirst();
        if ($categoryEntity === null) {
            return null;
        }

        return $this->getFactory()
            ->createCategoryMapper()
            ->mapCategoryWithRelations($categoryEntity, new CategoryTransfer());
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     *
     * @return int
     */
    public function getCategoryNodeChildCountByParentNodeId(CategoryTransfer $categoryTransfer): int
    {
        return $this->getFactory()
            ->createCategoryClosureTableQuery()
            ->leftJoinWithDescendantNode()
            ->useNodeQuery('node')
                ->filterByFkCategory($categoryTransfer->getIdCategoryOrFail())
            ->endUse()
            ->useDescendantNodeQuery()
                ->leftJoinWithCategory()
            ->endUse()
            ->filterByDepth(0, Criteria::NOT_EQUAL)
            ->count();
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return array<int>
     */
    public function getDescendantCategoryIdsByIdCategory(
        CategoryTransfer $categoryTransfer,
        CategoryCriteriaTransfer $categoryCriteriaTransfer
    ): array {
        $categoryClosureTableQuery = $this->buildCategoryClosureTableQueryByIdCategory(
            $categoryTransfer,
            $categoryCriteriaTransfer,
        );

        $categoryIds = [];
        $categoryClosureTableEntities = $categoryClosureTableQuery->find();

        if (!$categoryClosureTableEntities->count()) {
            return $categoryIds;
        }

        foreach ($categoryClosureTableEntities as $categoryClosureTableEntity) {
            $categoryIds[] = $categoryClosureTableEntity->getDescendantNode()->getCategory()->getIdCategory();
        }

        return $categoryIds;
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return array<int>
     */
    public function getDescendantCategoryNodeIdsByIdCategory(
        CategoryTransfer $categoryTransfer,
        CategoryCriteriaTransfer $categoryCriteriaTransfer
    ): array {
        $categoryClosureTableQuery = $this->buildCategoryClosureTableQueryByIdCategory(
            $categoryTransfer,
            $categoryCriteriaTransfer,
        );

        $categoryNodeIds = [];
        $categoryClosureTableEntities = $categoryClosureTableQuery->find();

        if (!$categoryClosureTableEntities->count()) {
            return $categoryNodeIds;
        }

        foreach ($categoryClosureTableEntities as $categoryClosureTableEntity) {
            $categoryNodeIds[] = $categoryClosureTableEntity->getDescendantNode()->getIdCategoryNode();
        }

        return $categoryNodeIds;
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return array<array<\Generated\Shared\Transfer\NodeTransfer>>
     */
    public function getCategoryNodeChildNodesCollectionIndexedByParentNodeId(
        CategoryTransfer $categoryTransfer,
        CategoryCriteriaTransfer $categoryCriteriaTransfer
    ): array {
        /** @var \Orm\Zed\Category\Persistence\SpyCategoryClosureTableQuery $categoryClosureTableQuery */
        $categoryClosureTableQuery = $this->getFactory()
            ->createCategoryClosureTableQuery()
            ->leftJoinWithDescendantNode()
            ->useNodeQuery('node')
                ->filterByFkCategory($categoryTransfer->getIdCategoryOrFail())
            ->endUse();

        $categoryClosureTableQuery
            ->useDescendantNodeQuery()
                ->leftJoinWithCategory()
                ->orderByNodeOrder(Criteria::DESC)
            ->endUse();

        $this->applyCategoryClosureTableFilters($categoryClosureTableQuery, $categoryCriteriaTransfer);

        $categoryClosureTableEntities = $categoryClosureTableQuery->find();

        if (!$categoryClosureTableEntities->count()) {
            return [];
        }

        $categoryMapper = $this->getFactory()->createCategoryMapper();
        $categoryNodes = [];
        foreach ($categoryClosureTableEntities as $categoryClosureTableEntity) {
            $nodeTransfer = $categoryMapper->mapCategoryNodeEntityToNodeTransferWithCategoryRelation(
                $categoryClosureTableEntity->getDescendantNode(),
                new NodeTransfer(),
            );
            $categoryNodes[$nodeTransfer->getFkParentCategoryNode()][] = $nodeTransfer;
        }

        return $categoryNodes;
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryNodeUrlCriteriaTransfer $categoryNodeUrlCriteriaTransfer
     *
     * @return array<\Generated\Shared\Transfer\UrlTransfer>
     */
    public function getCategoryNodeUrls(CategoryNodeUrlCriteriaTransfer $categoryNodeUrlCriteriaTransfer): array
    {
        $urlQuery = $this->getFactory()
            ->createUrlQuery()
            ->joinSpyLocale()
            ->withColumn(static::COL_LOCALE_NAME);

        if ($categoryNodeUrlCriteriaTransfer->getCategoryNodeIds()) {
            $urlQuery->filterByFkResourceCategorynode_In(array_unique($categoryNodeUrlCriteriaTransfer->getCategoryNodeIds()));
        }

        $urlTransfers = [];

        foreach ($urlQuery->find() as $urlEntity) {
            $urlTransfers[] = (new UrlTransfer())->fromArray($urlEntity->toArray(), true);
        }

        return $urlTransfers;
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryNodeUrlPathCriteriaTransfer $categoryNodeUrlPathCriteriaTransfer
     *
     * @return array
     */
    public function getCategoryNodeUrlPathParts(CategoryNodeUrlPathCriteriaTransfer $categoryNodeUrlPathCriteriaTransfer): array
    {
        $depth = $categoryNodeUrlPathCriteriaTransfer->getOnlyParents() ? 0 : null;

        $nodeQuery = $this->getFactory()->createCategoryNodeQuery();
        if ($categoryNodeUrlPathCriteriaTransfer->getExcludeRootNode()) {
            $nodeQuery->filterByIsRoot(false);
        }

        $nodeQuery
            ->useClosureTableQuery()
                ->orderByFkCategoryNodeDescendant(Criteria::DESC)
                ->orderByDepth(Criteria::DESC)
                ->filterByFkCategoryNodeDescendant($categoryNodeUrlPathCriteriaTransfer->getIdCategoryNodeOrFail())
                ->filterByDepth($depth, Criteria::NOT_EQUAL)
            ->endUse()
            ->useCategoryQuery()
                ->useAttributeQuery()
                    ->filterByFkLocale($categoryNodeUrlPathCriteriaTransfer->getIdLocaleOrFail())
                ->endUse()
            ->endUse()
            ->withColumn(SpyCategoryNodeTableMap::COL_FK_CATEGORY, static::KEY_FK_CATEGORY)
            ->withColumn(SpyCategoryNodeTableMap::COL_ID_CATEGORY_NODE, static::KEY_ID_CATEGORY_NODE)
            ->withColumn(SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE_DESCENDANT, static::KEY_FK_CATEGORY_NODE_DESCENDANT)
            ->withColumn(SpyCategoryAttributeTableMap::COL_NAME, static::KEY_NAME)
            ->withColumn(SpyCategoryTableMap::COL_CATEGORY_KEY, static::KEY_CATEGORY_KEY)
            ->withColumn(SpyCategoryAttributeTableMap::COL_FK_LOCALE, static::COL_FK_LOCALE);

        return $nodeQuery->find()->toArray();
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryNodeUrlPathCriteriaTransfer $categoryNodeUrlPathCriteriaTransfer
     *
     * @return array
     */
    public function getBulkCategoryNodeUrlPathParts(
        CategoryNodeUrlPathCriteriaTransfer $categoryNodeUrlPathCriteriaTransfer
    ): array {
        $categoryNodeQuery = $this->getFactory()->createCategoryNodeQuery();

        if ($categoryNodeUrlPathCriteriaTransfer->getExcludeRootNode()) {
            $categoryNodeQuery->filterByIsRoot(false);
        }

        $categoryNodeQuery
            ->useClosureTableQuery()
                ->orderByFkCategoryNodeDescendant(Criteria::DESC)
                ->orderByDepth(Criteria::DESC)
                ->filterByFkCategoryNodeDescendant_In($categoryNodeUrlPathCriteriaTransfer->getCategoryNodeDescendantIds())
                ->filterByDepth($categoryNodeUrlPathCriteriaTransfer->getOnlyParents() ? 0 : null, Criteria::NOT_EQUAL)
            ->endUse()
            ->useCategoryQuery()
                ->useAttributeQuery()
                    ->filterByFkLocale($categoryNodeUrlPathCriteriaTransfer->getIdLocaleOrFail())
                ->endUse()
            ->endUse()
            ->withColumn(SpyCategoryNodeTableMap::COL_FK_CATEGORY, static::KEY_FK_CATEGORY)
            ->withColumn(SpyCategoryNodeTableMap::COL_ID_CATEGORY_NODE, static::KEY_ID_CATEGORY_NODE)
            ->withColumn(SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE_DESCENDANT, static::KEY_FK_CATEGORY_NODE_DESCENDANT)
            ->withColumn(SpyCategoryAttributeTableMap::COL_NAME, static::KEY_NAME)
            ->withColumn(SpyCategoryTableMap::COL_CATEGORY_KEY, static::KEY_CATEGORY_KEY)
            ->withColumn(SpyCategoryAttributeTableMap::COL_FK_LOCALE, static::COL_FK_LOCALE)
            ->withColumn(SpyCategoryNodeTableMap::COL_FK_PARENT_CATEGORY_NODE, static::KEY_FK_PARENT_CATEGORY_NODE)
            ->select([
                static::KEY_FK_CATEGORY,
                static::KEY_ID_CATEGORY_NODE,
                static::KEY_FK_CATEGORY_NODE_DESCENDANT,
                static::KEY_NAME,
                static::KEY_CATEGORY_KEY,
                static::COL_FK_LOCALE,
                static::KEY_FK_PARENT_CATEGORY_NODE,
            ]);

        return $categoryNodeQuery->find()->toArray(static::KEY_FK_CATEGORY_NODE_DESCENDANT);
    }

    /**
     * @module Locale
     * @module Store
     * @module Url
     *
     * @param \Generated\Shared\Transfer\CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\NodeCollectionTransfer
     */
    public function getCategoryNodesWithRelativeNodes(
        CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
    ): NodeCollectionTransfer {
        $nodeCollectionTransfer = new NodeCollectionTransfer();
        $categoryNodeIds = $categoryNodeCriteriaTransfer->requireCategoryNodeIds()->getCategoryNodeIds();

        if ($categoryNodeIds === []) {
            return $nodeCollectionTransfer;
        }

        $categoryNodeIdsImploded = implode(', ', $categoryNodeIds);

        /** @var \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery $categoryNodeQuery */
        $categoryNodeQuery = $this->getFactory()
            ->createCategoryNodeQuery()
            ->leftJoinClosureTable(SpyCategoryClosureTableTableMap::TABLE_NAME)
            ->addJoinCondition(
                SpyCategoryClosureTableTableMap::TABLE_NAME,
                sprintf('%s = %s', SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE_DESCENDANT, SpyCategoryNodeTableMap::COL_ID_CATEGORY_NODE),
                null,
                Criteria::LOGICAL_OR,
            )
            ->leftJoinWithSpyUrl()
            ->leftJoinWithCategory()
            ->useCategoryQuery(null, Criteria::LEFT_JOIN)
                ->leftJoinWithCategoryTemplate()
                ->leftJoinWithAttribute()
                ->useAttributeQuery(null, Criteria::LEFT_JOIN)
                    ->leftJoinWithLocale()
                ->endUse()
                ->leftJoinSpyCategoryStore()
                ->useSpyCategoryStoreQuery(null, Criteria::LEFT_JOIN)
                    ->leftJoinWithSpyStore()
                ->endUse()
            ->endUse()
            ->where(sprintf('%s IN (%s)', SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE_DESCENDANT, $categoryNodeIdsImploded))
            ->_or()
            ->where(sprintf('%s IN (%s)', SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE, $categoryNodeIdsImploded));

        $categoryNodeQuery
            ->orderByNodeOrder(Criteria::DESC)
            ->distinct();

        if ($categoryNodeCriteriaTransfer->getIsActive() !== null) {
            $categoryNodeQuery->useCategoryQuery(null, Criteria::LEFT_JOIN)
                ->filterByIsActive($categoryNodeCriteriaTransfer->getIsActive())
                ->endUse();
        }

        if ($categoryNodeCriteriaTransfer->getIsInMenu() !== null) {
            $categoryNodeQuery->useCategoryQuery(null, Criteria::LEFT_JOIN)
                ->filterByIsInMenu($categoryNodeCriteriaTransfer->getIsInMenu())
                ->endUse();
        }

        $categoryNodeEntities = $categoryNodeQuery->find()->toKeyIndex();

        if ($categoryNodeEntities === []) {
            return $nodeCollectionTransfer;
        }

        return $this->getFactory()
            ->createCategoryMapper()
            ->mapCategoryNodeEntitiesToNodeCollectionTransfer($categoryNodeEntities, $nodeCollectionTransfer);
    }

    /**
     * @module Locale
     * @module Store
     * @module Url
     *
     * @param \Generated\Shared\Transfer\CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
     *
     * @return \Generated\Shared\Transfer\NodeCollectionTransfer
     */
    public function getCategoryNodes(CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer): NodeCollectionTransfer
    {
        $categoryNodeQuery = $this->getFactory()
            ->createCategoryNodeQuery();

        /** @var \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery $categoryNodeQuery */
        $categoryNodeQuery = $this->setCategoryNodeFilters($categoryNodeQuery, $categoryNodeCriteriaTransfer);

        if (!$categoryNodeCriteriaTransfer->getWithRelations()) {
            return $this->getFactory()
                ->createCategoryNodeMapper()
                ->mapNodeCollection($categoryNodeQuery->find(), new NodeCollectionTransfer());
        }

        $categoryNodeQuery
            ->leftJoinWithSpyUrl()
            ->leftJoinWithCategory()
            ->useCategoryQuery(null, Criteria::LEFT_JOIN)
                ->leftJoinWithCategoryTemplate()
                ->leftJoinWithAttribute()
                ->useAttributeQuery(null, Criteria::LEFT_JOIN)
                    ->leftJoinWithLocale()
                ->endUse()
                ->leftJoinSpyCategoryStore()
                ->useSpyCategoryStoreQuery(null, Criteria::LEFT_JOIN)
                    ->leftJoinWithSpyStore()
                ->endUse()
            ->endUse();

        return $this->getFactory()
            ->createCategoryMapper()
            ->mapCategoryNodeEntitiesToNodeCollectionTransferWithCategoryRelation(
                $categoryNodeQuery->find(),
                new NodeCollectionTransfer(),
            );
    }

    /**
     * @param \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery $categoryNodeQuery
     * @param \Generated\Shared\Transfer\CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery|\Propel\Runtime\ActiveQuery\ModelCriteria
     */
    protected function setCategoryNodeFilters(
        SpyCategoryNodeQuery $categoryNodeQuery,
        CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
    ) {
        if ($categoryNodeCriteriaTransfer->getCategoryNodeIds()) {
            $categoryNodeQuery->filterByIdCategoryNode_In($categoryNodeCriteriaTransfer->getCategoryNodeIds());
        }

        if ($categoryNodeCriteriaTransfer->getIsActive() !== null) {
            $categoryNodeQuery
                ->useCategoryQuery(null, Criteria::LEFT_JOIN)
                    ->filterByIsActive($categoryNodeCriteriaTransfer->getIsActive())
                ->endUse();
        }

        $categoryTemplateIds = $categoryNodeCriteriaTransfer->getCategoryTemplateIds();
        if (count($categoryTemplateIds) !== 0) {
            $categoryNodeQuery
                ->useCategoryQuery()
                    ->filterByFkCategoryTemplate_In($categoryTemplateIds)
                ->endUse();
        }

        if ($categoryNodeCriteriaTransfer->getIsRoot() !== null) {
            $categoryNodeQuery->filterByIsRoot($categoryNodeCriteriaTransfer->getIsRoot());
        }

        if ($categoryNodeCriteriaTransfer->getCategoryIds()) {
            $categoryNodeQuery->filterByFkCategory_In($categoryNodeCriteriaTransfer->getCategoryIds());
        }

        if ($categoryNodeCriteriaTransfer->getIsMain() !== null) {
            $categoryNodeQuery->filterByIsMain($categoryNodeCriteriaTransfer->getIsMain());
        }

        $filterTransfer = $categoryNodeCriteriaTransfer->getFilter();
        if ($filterTransfer !== null) {
            $categoryNodeQuery = $this
                ->buildQueryFromCriteria($categoryNodeQuery, $filterTransfer)
                ->setFormatter(ModelCriteria::FORMAT_OBJECT);
        }

        return $categoryNodeQuery;
    }

    /**
     * @param int $idCategoryNode
     *
     * @return \Generated\Shared\Transfer\StoreRelationTransfer
     */
    public function getCategoryStoreRelationByIdCategoryNode(int $idCategoryNode): StoreRelationTransfer
    {
        $storeRelationTransfer = new StoreRelationTransfer();

        /** @var \Propel\Runtime\Collection\ObjectCollection|\Orm\Zed\Category\Persistence\SpyCategoryStore[] $categoryStoreEntities */
        $categoryStoreEntities = $this->getFactory()
            ->createCategoryStoreQuery()
            ->joinWithSpyCategory()
            ->useSpyCategoryQuery()
                ->joinWithNode()
                ->useNodeQuery()
                    ->filterByIdCategoryNode($idCategoryNode)
                ->endUse()
            ->endUse()
            ->find();

        if (!$categoryStoreEntities->count()) {
            return $storeRelationTransfer;
        }

        return $this->getFactory()
            ->createCategoryStoreRelationMapper()
            ->mapCategoryStoreEntitiesToStoreRelationTransfer(
                $categoryStoreEntities,
                $storeRelationTransfer,
            );
    }

    /**
     * @param int $idCategoryNode
     *
     * @return \Generated\Shared\Transfer\NodeTransfer|null
     */
    public function findCategoryNodeByIdCategoryNode(int $idCategoryNode): ?NodeTransfer
    {
        $categoryNodeEntity = $this->getFactory()
            ->createCategoryNodeQuery()
            ->filterByIdCategoryNode($idCategoryNode)
            ->findOne();

        if (!$categoryNodeEntity) {
            return null;
        }

        return $this->getFactory()
            ->createCategoryNodeMapper()
            ->mapCategoryNode($categoryNodeEntity, new NodeTransfer());
    }

    /**
     * @param \Orm\Zed\Category\Persistence\SpyCategoryQuery $categoryQuery
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryQuery
     */
    protected function applyCategoryFilters(SpyCategoryQuery $categoryQuery, CategoryCriteriaTransfer $categoryCriteriaTransfer): SpyCategoryQuery
    {
        if ($categoryCriteriaTransfer->getIdCategory()) {
            $categoryQuery->filterByIdCategory($categoryCriteriaTransfer->getIdCategory());
        }

        if ($categoryCriteriaTransfer->getIsMain() !== null) {
            $categoryQuery
                ->useNodeQuery('node', Criteria::LEFT_JOIN)
                    ->filterByIsMain($categoryCriteriaTransfer->getIsMain())
                ->endUse();
        }

        if ($categoryCriteriaTransfer->getLocaleName()) {
            $categoryQuery
                ->useAttributeQuery(null, Criteria::LEFT_JOIN)
                    ->useLocaleQuery()
                        ->filterByLocaleName($categoryCriteriaTransfer->getLocaleName())
                    ->endUse()
                ->endUse();
        }

        $idCategoryNode = $categoryCriteriaTransfer->getIdCategoryNode();
        if ($idCategoryNode) {
            $categoryQuery
                ->joinWithNode()
                ->useNodeQuery()
                    ->filterByIdCategoryNode($idCategoryNode)
                ->endUse();
        }

        if ($categoryCriteriaTransfer->getIdLocale()) {
            $categoryQuery
                ->joinWithAttribute()
                ->useAttributeQuery()
                    ->filterByFkLocale($categoryCriteriaTransfer->getIdLocale())
                ->endUse();
        }

        if ($categoryCriteriaTransfer->getWithNodes()) {
            $categoryQuery->leftJoinNode();
        }

        return $categoryQuery;
    }

    /**
     * @param \Orm\Zed\Category\Persistence\SpyCategoryClosureTableQuery $categoryClosureTableQuery
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryClosureTableQuery
     */
    protected function applyCategoryClosureTableFilters(
        SpyCategoryClosureTableQuery $categoryClosureTableQuery,
        CategoryCriteriaTransfer $categoryCriteriaTransfer
    ): SpyCategoryClosureTableQuery {
        if ($categoryCriteriaTransfer->getLocaleName()) {
            $categoryClosureTableQuery
                ->useDescendantNodeQuery()
                    ->useCategoryQuery()
                        ->joinWithAttribute()
                        ->useAttributeQuery()
                            ->useLocaleQuery()
                                ->filterByLocaleName($categoryCriteriaTransfer->getLocaleName())
                            ->endUse()
                        ->endUse()
                    ->endUse()
                ->endUse();
        }

        if ($categoryCriteriaTransfer->getWithChildren()) {
            $categoryClosureTableQuery->filterByDepth(static::DEPTH_WITH_CHILDREN_RELATIONS);
        }

        return $categoryClosureTableQuery;
    }

    /**
     * @param \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery $categoryNodeQuery
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery
     */
    protected function applyParentCategoryNodeFilter(SpyCategoryNodeQuery $categoryNodeQuery, CategoryTransfer $categoryTransfer): SpyCategoryNodeQuery
    {
        $parentCategoryNodeTransfer = $categoryTransfer->getParentCategoryNode();
        if ($parentCategoryNodeTransfer === null) {
            $categoryNodeQuery
                ->filterByFkParentCategoryNode(null)
                ->filterByIsRoot(true);

            return $categoryNodeQuery;
        }

        $categoryNodeQuery->filterByFkParentCategoryNode($parentCategoryNodeTransfer->getIdCategoryNodeOrFail());

        return $categoryNodeQuery;
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryTransfer $categoryTransfer
     * @param \Generated\Shared\Transfer\CategoryCriteriaTransfer $categoryCriteriaTransfer
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryClosureTableQuery
     */
    protected function buildCategoryClosureTableQueryByIdCategory(
        CategoryTransfer $categoryTransfer,
        CategoryCriteriaTransfer $categoryCriteriaTransfer
    ): SpyCategoryClosureTableQuery {
        $categoryClosureTableQuery = $this->getFactory()
            ->createCategoryClosureTableQuery()
            ->leftJoinWithDescendantNode()
            ->useNodeQuery('node')
                ->filterByFkCategory($categoryTransfer->getIdCategoryOrFail())
            ->endUse()
            ->useDescendantNodeQuery()
                ->leftJoinWithCategory()
                ->orderByNodeOrder(Criteria::DESC)
            ->endUse()
            ->filterByDepth(0, Criteria::NOT_EQUAL);

        if ($categoryCriteriaTransfer->getLimit() !== null && $categoryCriteriaTransfer->getOffset() !== null) {
            $categoryClosureTableQuery
                ->limit($categoryCriteriaTransfer->getLimit())
                ->offset($categoryCriteriaTransfer->getOffset());
        }

        return $categoryClosureTableQuery;
    }

    /**
     * @param \Generated\Shared\Transfer\CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
     *
     * @return array<int, array<string, string>>
     */
    public function getAscendantCategoryKeys(CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer): array
    {
        $categoryNodeQuery = $this->getFactory()
            ->createCategoryNodeQuery()
            ->useClosureTableQuery()
                ->orderByFkCategoryNodeDescendant(Criteria::DESC)
                ->orderByDepth(Criteria::DESC)
                ->withColumn(SpyCategoryClosureTableTableMap::COL_FK_CATEGORY_NODE_DESCENDANT, static::KEY_ID_CATEGORY_NODE)
            ->endUse()
            ->joinWithCategory()
            ->withColumn(SpyCategoryTableMap::COL_CATEGORY_KEY, static::KEY_CATEGORY_KEY)
            ->select([
                static::KEY_ID_CATEGORY_NODE,
                static::KEY_CATEGORY_KEY,
            ]);

        $categoryNodeQuery = $this->applyAscendantCategoryKeyFilters($categoryNodeQuery, $categoryNodeCriteriaTransfer);

        return $categoryNodeQuery->find()->toArray();
    }

    /**
     * @param \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery $categoryNodeQuery
     * @param \Generated\Shared\Transfer\CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
     *
     * @return \Orm\Zed\Category\Persistence\SpyCategoryNodeQuery
     */
    protected function applyAscendantCategoryKeyFilters(
        SpyCategoryNodeQuery $categoryNodeQuery,
        CategoryNodeCriteriaTransfer $categoryNodeCriteriaTransfer
    ): SpyCategoryNodeQuery {
        if ($categoryNodeCriteriaTransfer->getCategoryNodeIds()) {
            $categoryNodeQuery
                ->useClosureTableQuery()
                    ->filterByFkCategoryNodeDescendant_In($categoryNodeCriteriaTransfer->getCategoryNodeIds())
                ->endUse();
        }

        return $categoryNodeQuery;
    }
}
