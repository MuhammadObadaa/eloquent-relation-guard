<?php

namespace EloquentRelation\Guard\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RecordTree
 *
 * Visualizes an Eloquent model's relationships and their associated IDs
 * as a nested tree structure in the console.
 */
class RecordTree extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'record:tree
                            {model? : Full class path to the Eloquent model}
                            {id? : Model ID}
                            {depth=1 : Nesting depth (-1 for infinite)}';

    /**
     * The console command description.
     */
    protected $description = 'Visualize an Eloquent model\'s relations and their IDs as a tree.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model') ?? $this->ask('Enter the full model class (e.g., User)');
        $id = $this->argument('id') ?? $this->ask('Enter the model ID');
        $depth = $this->argument('depth');

        $modelClass = "App\\Models\\$modelClass";

        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            $this->error("Class [$modelClass] is not a valid Eloquent model.");
            return 1;
        }

        /** @var Model $model */
        $model = $modelClass::find($id);

        if (!$model) {
            $this->error("No record found for [$modelClass] with ID [$id].");
            return 1;
        }

        if (!method_exists($model, 'relationStructure')) {
            $this->error("The model [$modelClass] does not use the necessary trait providing relationStructure().");
            return 1;
        }

        $this->info("\nRelation Tree for <comment>{$modelClass}</comment> (ID: <fg=cyan>{$id}</>) | Depth: {$depth}");

        $tree = $model->relationStructure($depth);
        $this->displayRelationTree($tree);

        $this->newLine();

        return 0;
    }

    /**
     * Recursively print the relation tree.
     *
     * @param array $tree
     * @param int $level
     * @param string $prefix
     */
    protected function displayRelationTree(array $tree, int $level = 0, string $prefix = ''): void
    {
        $total = count($tree);
        $i = 0;

        foreach ($tree as $relation => $data) {
            $i++;
            $isLast = $i === $total;

            $branch = $isLast ? '└── ' : '├── ';
            $currentLine = $prefix . $branch;

            $modelName = $data['model'] ?? 'unknown';
            $idList = implode(', ', $data['ids'] ?? []);

            $this->line("{$currentLine}<info>{$relation}</info> (<comment>{$modelName}</comment>): [<fg=cyan>{$idList}</>]");

            $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $this->displayRelationTree($data['nested'] ?? [], $level + 1, $childPrefix);
        }
    }
}
