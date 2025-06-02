<?php

namespace EloquentRelation\Guard\Traits;

use EloquentRelation\Guard\ModelRelationScanner;


/**
 * Trait HasRelationalDependencies
 *
 * Provides useful utilities for handling model relationships such as checking
 * if a model can be deleted safely, getting relation structure, or force-deleting with dependents.
 *
 * Requires the model using this trait to optionally define a `$checkRelations` property,
 * which is an array of relation names to consider.
 */
trait HasRelationalDependencies
{

    private ?ModelRelationScanner $relationScanner = null;

    private function getScanner(): ModelRelationScanner
    {
        return $this->relationScanner ??= new ModelRelationScanner();
    }

    /**
     * Determine if the model can be safely deleted (no related records).
     *
     * @return bool
     */
    public function canBeSafelyDeleted(): bool
    {
        $relations = $this->getScanner()->getRelatedTreeWithIds($this, $this->getScannableRelations(), 1);
        return $this->relationsAreEmpty($relations);
    }

    /**
     * Get full relation tree structure with IDs of all related records.
     *
     * @return array
     */
    public function relationStructure(int $depth = 1): array
    {
        return $this->getScanner()->getRelatedTreeWithIds($this, $this->getScannableRelations(), $depth);
    }

    /**
     * Force delete the model and all nested related models.
     *
     * @return int Number of records deleted (including related)
     */
    public function forceCascadeDelete(): int
    {
        return $this->getScanner()->forceDelete($this);
    }

    /**
     * Check recursively if all relations are empty (no related IDs).
     *
     * @param array $relations
     * @return bool
     */
    private function relationsAreEmpty(array $relations): bool
    {
        foreach ($relations as $relation) {
            if (!empty($relation['ids']) || !$this->relationsAreEmpty($relation['nested'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the relations to check for the current model.
     *
     * Override by defining a `$checkRelations` property in your model.
     *
     * @return array
     */
    private function getScannableRelations(): array
    {
        // return property_exists($this, 'checkRelations') ? $this->checkRelations : ['*'];
        return property_exists(self::class, config('eloquent-relation-guard.relations_array_property')) ?
            self::${config('eloquent-relation-guard.relations_array_property')} :
            ['*'];
    }
}
