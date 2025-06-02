<?php

namespace EloquentRelation\Guard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionMethod;

final class ModelRelationScanner
{
    /**
     * Entry method: Get nested HasOne/HasMany related IDs and model structure.
     * @param string $modelClass the class to build the relation tree to,
     * @param array<string> $wantedRelations the relations wanted to be build with,
     * it follows laravel convention e.g 'relation.subRelation'
     * @param int $depth the wanted depth, -1 for infinity
     */
    public function getRelatedTreeWithIds(Model $model, array $wantedRelations = ['*'], int $depth = 1): array
    {
        $relationStructure = $this->buildRelationTree(get_class($model), $wantedRelations, $depth);
        $model->load($relationStructure);
        return $this->collectNestedRelationIds($model, $relationStructure);
    }

    /**
     * Force delete a model and all nested related models.
     * @param Model $model the model that is wanted to be deleted
     * @return int the number of deleted records
     */
    public function forceDelete(Model $model): int
    {
        $relatedTree = $this->getRelatedTreeWithIds($model, depth: -1);
        return $this->forceDeleteFromTree($relatedTree) + $model->delete();
    }

    /**
     * Recursive delete based on collected relation structure.
     * @param array<string, array<string, mixed>> the relation tree:
     * ['relationName' => ['ids' => [],'model' => 'modelClass','nested' => []]]
     * @return int the number of deleted records
     */
    private function forceDeleteFromTree(array $tree): int
    {
        if (empty($tree)) return 0;

        $deletedCount = 0;

        foreach ($tree as $branch) {
            if (empty($branch['ids'])) continue;

            $deletedCount += $this->forceDeleteFromTree($branch['nested']);

            $deletedCount += $branch['model']::whereIn(
                (new $branch['model'])->getKeyName(),
                $branch['ids']
            )->delete();
        }

        return $deletedCount;
    }

    /**
     * Recursively collect nested HasOne/HasMany relation tree.
     * @param string $modelClass the class to build the relation tree to,
     * @param array<string> $wantedRelations the relations wanted to be build with,
     * it follows laravel convention e.g 'relation.subRelation'
     * @param int $depth the wanted depth, -1 for infinity
     */
    private function buildRelationTree(string $modelClass, array $wantedRelations = ['*'], int $depth = 1): array
    {
        $reflection = new ReflectionClass($modelClass);
        $modelInstance = $reflection->newInstanceWithoutConstructor();
        $tree = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->class !== $modelClass ||
                $method->getNumberOfParameters() > 0
            ) continue;

            try {
                $returnType = $method->getReturnType()?->getName();
                if (!$returnType) continue;

                $typeBase = class_basename($returnType);
                $methodName = $method->getName();

                if (in_array($typeBase, ['HasOne', 'HasMany']) && $this->isWantedRelation($methodName, $wantedRelations)) {
                    $subWanted = $this->filterSubRelations($methodName, $wantedRelations);

                    $nestedTree = ($depth > 1 || $depth === -1)
                        ? $this->buildRelationTree(
                            $modelInstance->$methodName()->getModel()::class,
                            $subWanted,
                            $depth === -1 ? -1 : $depth - 1
                        )
                        : [];

                    $tree[$methodName] = $nestedTree;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $tree;
    }

    /**
     * Recursively collect relation IDs and class info based on structure.
     * @param Model $mode
     * @param array $structure
     */
    private function collectNestedRelationIds(Model $model, array $structure): array
    {
        $result = [];

        foreach ($structure as $relation => $nestedStructure) {
            $related = $model->getRelation($relation);

            /** @var Collection $relatedModels */
            $relatedModels = $related instanceof Collection
                ? $related
                : ($related ? collect([$related]) : collect());

            $relatedRelation = $model->$relation()->getRelated();
            $modelClass = $relatedRelation::class;

            $ids = $relatedModels->pluck($relatedRelation->getKeyName())->all();

            $nestedData = [];
            foreach ($relatedModels as $relatedModel) {
                $nestedData[] = $this->collectNestedRelationIds($relatedModel, $nestedStructure);
            }

            $mergedNested = $this->mergeNestedData($nestedData);

            $result[$relation] = [
                'ids' => array_values(array_unique($ids)),
                'model' => $modelClass,
                'nested' => $mergedNested,
            ];
        }

        return $result;
    }

    /**
     * Merge nested result arrays.
     * @param array $nestedData
     * @return array<string, array<string, mixed>>
     */
    private function mergeNestedData(array $nestedData): array
    {
        $merged = [];

        foreach ($nestedData as $entry) {
            foreach ($entry as $key => $data) {
                if (!isset($merged[$key])) {
                    $merged[$key] = ['ids' => [], 'model' => $data['model'], 'nested' => []];
                }

                $merged[$key]['ids'] = array_values(array_unique(array_merge(
                    $merged[$key]['ids'],
                    $data['ids']
                )));

                $merged[$key]['nested'] = $data['nested'];
            }
        }

        return $merged;
    }

    /**
     * Determine if a relation name is among the wanted ones.
     * @param string $relationName
     * @param array<string> $wantedRelations
     * @return bool
     */
    private function isWantedRelation(string $relationName, array $wantedRelations): bool
    {
        foreach ($wantedRelations as $wanted) {
            if ($wanted === '*' || $relationName === explode('.', $wanted, 2)[0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter sub-relations based on a given relation name prefix.
     * @param string $relationName
     * @param array<string> $wantedRelations
     * @return array<string> the subRelations of a relation in $wantedRelations
     */
    private function filterSubRelations(string $relationName, array $wantedRelations): array
    {
        if (in_array('*', $wantedRelations)) {
            return ['*'];
        }

        $filtered = [];

        foreach ($wantedRelations as $relation) {
            $parts = explode('.', $relation, 2);

            if ($parts[0] === $relationName && isset($parts[1])) {
                $filtered[] = $parts[1];
            }
        }

        return $filtered;
    }
}
