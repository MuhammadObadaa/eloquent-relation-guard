# Eloquent Relation Guard — Full Documentation

This document covers everything you need to know to install, configure, and use **Eloquent Relation Guard** in your Laravel application. It also explains the available APIs, the console command, and how to contribute.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Setting Up Your Models](#setting-up-your-models)
   - [Adding the Trait](#adding-the-trait)
   - [Defining the `$scanRelations` Array](#defining-the-scanrelations-array)
   - [Type‐Hinting HasOne/HasMany Relations](#type-hinting-hasonemany-relations)
5. [API Reference](#api-reference)
   1. [`canBeSafelyDeleted()`](#canBeSafelyDeleted-bool)
   2. [`relationStructure(int $depth = 1): array`](#relationstructureint-depth--1-array)
   3. [`forceCascadeDelete(): int`](#forcecascadedelete-int)
6. [Console Command: `record:tree`](#console-command-recordtree)
7. [Advanced Configuration Options](#advanced-configuration-options)
8. [Troubleshooting & Limitations](#troubleshooting--limitations)
9. [Contributing & Issue Tracker](#contributing--issue-tracker)
10. [License](#license)

---

## Introduction

**Eloquent Relation Guard** helps you safely inspect and manage deep HasOne/HasMany relationships on your Laravel models:

- **Scan a model’s relationship branches** to any depth.  
- **Generate a nested “relation tree”** (array) containing every HasOne/HasMany relation name, related class, and a list of related model IDs.  
- **Check if a model can be deleted** (i.e., it has no related child records).  
- **Force‐delete a model and all its related child records** in one go—regardless of database‐level foreign‐key cascade or restrict rules.

You no longer need to rely solely on `ON DELETE CASCADE` (or worry about `ON DELETE RESTRICT`) at the database level. This package ensures any unwanted child records are discovered (or removed) within Laravel itself.

---

## Installation

1. **Require the package via Composer**  
```bash
   composer require mhdobd/eloquent-relation-guard
```

> Adjust the vendor/package name if you chose something different.

2. **Publish the configuration**

   ```bash
   php artisan vendor:publish --provider="mhdobd\EloquentRelationGuard\RelationGuardServiceProvider" --tag="config"
   ```

   This will copy `config/eloquent-relation-guard.php` into your application’s `config/` folder.

3. **Verify your environment**

   * PHP ≥ 8.0
   * Laravel version above 8.x
   * No additional PHP extensions are required beyond what Laravel itself needs (e.g., `ext-mbstring`, `ext-pdo`).

---

## Configuration

Open `config/eloquent-relation-guard.php`. By default, you’ll see:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Model Relations Declaration Property
    |--------------------------------------------------------------------------
    |
    | This property name tells the package where to look for the list of
    | relations you want to scan in your Eloquent models. By default:
    |
    |   protected array $scanRelations = [ '*' ];
    |
    | If you rename this (e.g. to "checkRelations"), update this config key.
    |
    */
    'relations_array_property' => 'scanRelations',

];
```

* **`relations_array_property`**: The name of the property on your model that holds an array of “dot‐notation” relations to scan.

  * If you leave it as `"scanRelations"`, your models must define a `protected array $scanRelations = […]`.
  * If you prefer `protected array $checkRelations = […]`, then set this value to `"checkRelations"`.

---

## Setting Up Your Models

### Adding the Trait

1. In any Eloquent model you want to protect/scan, include the trait:

   ```php
   use Illuminate\Database\Eloquent\Model;
   use EloquentRelation\Guard\Traits\HasRelationalDependencies;

   class Post extends Model
   {
       use HasRelationalDependencies;

       // … other model code …
   }
   ```

2. The trait will automatically instantiate the internal scanner and expose three APIs (`canBeSafelyDeleted()`, `relationStructure()`, `forceCascadeDelete()`).

---

### Defining the `$scanRelations` Array

* By default, if your model does **not** define any `$scanRelations`, the package assumes you want to scan **all** HasOne/HasMany relations (equivalent to `['*']`).

* To explicitly restrict or customize what relations to include (and how deep), add a protected array property with the same name as in your config (default: `scanRelations`):

  ```php
  class Post extends Model
  {
      use HasRelationalDependencies;

      /**
       * Only scan:
       *  - Post::comments()
       *  - Post::metaData() (HasOne)
       *  - Then scan nested replies under each comment
       *  - Or add every first-level relation using '*'
       */
      protected array $scanRelations = [
          'comments',
          'comments.replies',
          'metaData',
          '*',
      ];
  }
  ```

* **Dot‐notation**:

  * `'comments.replies'` means: first traverse the `comments` relation, then for each `Comment` model, traverse its `replies` relation.
  * If you use `'*'`, it will discover all HasOne/HasMany methods on the model via reflection.

---

### Type‐Hinting HasOne/HasMany Relations

Your model’s relation methods must return proper Eloquent relation objects, otherwise the scanner can’t detect them. For example:

```php
class Post extends Model
{
    use HasRelationalDependencies;

    protected array $scanRelations = [
        'comments.replies',
        'metaData',
    ];

    // A one‐to‐many (HasMany) relation to Comment
    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // A one‐to‐one (HasOne) relation to PostMeta
    public function metaData(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PostMeta::class);
    }
}

class Comment extends Model
{
    use HasRelationalDependencies;

    protected array $scanRelations = [
        'replies',       // Only look at replies under each comment
    ];

    // A one‐to‐many (HasMany) relation to CommentReply
    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CommentReply::class);
    }
}
```

* If you forget to return the correct relation type, the scanner won’t pick up that method.
* Any method returning a type other than `HasOne` or `HasMany` is ignored automatically.

---

## API Reference

After you’ve added the `HasRelationalDependencies` trait and (optionally) defined `$scanRelations`, your model now has these three public methods:

### `canBeSafelyDeleted(): bool`

* **What it does**:

  * Scans only the **first level** (depth = 1) of HasOne/HasMany relations defined in `$scanRelations`.
  * Returns `true` if none of those relations has any related record.
  * Returns `false` if there is at least one related record found.

* **Usage**:

  ```php
  $post = Post::find(42);

  if ($post->canBeSafelyDeleted()) {
      // No immediate child records—safe to delete
      $post->delete();
  } else {
      // There are related records—take appropriate action
      // so you can alert the user with detailed related relations using $post->relationStructure
  }
  ```

<!-- * **Complexity**:

  * Reflection on the model’s methods → O(M·W).
  * Eager‐load level‐1 relations → O(B) queries + O(I\_level1) memory.
  * Check if any IDs exist → O(I\_level1). -->

---

### `relationStructure(int $depth = 1): array`

* **What it does**:

  * Builds a **nested array** of all HasOne/HasMany relations (as specified by `$scanRelations`) up to the given `$depth`.
  * Loads (via `load()`) all related models needed to gather IDs.
  * Returns a tree structure where each node is:

    ```php
    [
      'ids'   => [ /* list of related IDs */ ],
      'model' => 'App/Model/ClassName',
      'nested'=> [ /* recursive same structure for deeper relations */ ],
    ]
    ```
  * Use `depth = -1` for “unlimited” scanning down every level that eventually contains a HasOne/HasMany relation, but take memory, time and query complexity in mind.

* **Usage**:

  ```php
  $post = Post::find(42);

  // Scan two levels deep
  $tree = $post->relationStructure(2);

  // Scan unlimited depth
  $fullTree = $post->relationStructure(-1);
  ```

* **Example Return Value** (depth = 2):

  ```php
  [
      'comments' => [
          'ids'   => [10, 11, 12],
          'model' => 'App\Models\Comment',
          'nested'=> [
              'replies' => [
                  'ids'   => [101, 102],
                  'model' => 'App\Models\CommentReply',
                  'nested'=> [
                      // (depth = 2 stops here; nested = [])
                  ],
              ],
          ],
      ],
      'metaData' => [
          'ids'   => [55],  // only one PostMeta record
          'model' => 'App\Models\PostMeta',
          'nested'=> [],
      ],
  ]
  ```

<!-- * **Complexity**:

  * Recursively reflect on up to `B^depth` relation‐methods.
  * Eager‐loads potentially many related rows (O(I) in memory).
  * Returns a full nested array of size O(I). -->

---

### `forceCascadeDelete(): int`

* **What it does**:

  1. Calls `relationStructure(-1)` to discover **all** HasOne/HasMany descendants (no depth limit).
  2. Recursively issues `Model::whereIn(primaryKey, [IDS])->delete()` on each related branch, beginning at the deepest level.
  3. Deletes the original model record.
  4. Returns the **total count** of deleted records (sum of all forced child deletions + the parent).

* **Usage**:

  ```php
  $post = Post::find(42);

  // This will delete all child comments → child replies → child replies of replies, etc.,
  // and finally delete Post #42 itself.
  $deletedCount = $post->forceCascadeDelete();

  echo "Deleted {$deletedCount} total records (including related child records).";
  ```

* **Important**:

  * Ignores any database‐level `ON DELETE` constraints; it always “forces” Laravel‐level deletes.
  * If any related model has its own deletion hooks (e.g., model events or cascading soft deletes), those will still fire in the usual Laravel manner.
  * Observers will only be triggered over the model object that call the `forceCascadeDelete()` method.

---

## Console Command: `record:tree`

This Artisan command gives you a **tree‐style, interactive visualization** of a model’s relation structure (with IDs):

```bash
php artisan record:tree {modelClass?} {id?} {depth=1}
```

* **Arguments**:

  * `modelClass` (optional): Model class name (e.g., `Post`). If omitted, you’ll be prompted.
  * `id`         (optional): The record’s primary key. If omitted, you’ll be prompted.
  * `depth`      (optional, default = 1): How many nested levels to scan (`-- -1` for unlimited).

**Example:**

```bash
php artisan record:tree Post 42 2
```

**Expected Output:**

```
Relation Tree for App\Models\Post (ID: 42) | Depth: 2
├── comments (App\Models\Comment): [10, 11, 12]
│   └── replies (App\Models\CommentReply): [101, 102]
└── metaData (App\Models\PostMeta): [55]
```

* **Use Cases**:

  * Quickly inspect which child records exist.
  * Debug complex relationship graphs.
  * Verify that `canBeSafelyDeleted()` is accurate before permanently deleting in production.

---

## Advanced Configuration Options

At present, your package only offers a single config key:

```php
// config/eloquent-relation-guard.php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Relations Declaration Property
    |--------------------------------------------------------------------------
    |
    | Change this key if you prefer a different property name on your models.
    |
    */
    'relations_array_property' => 'scanRelations',
];
```

### Possible Future Keys (not yet implemented)

* `default_scan_depth` (int)
  Default number of levels to scan when `relationStructure()` is called without arguments.

* `max_nodes_threshold` (int)
  Maximum allowed number of nodes (relations + records) before aborting and throwing a “TooManyRelationsException.”

* `include_soft_deleted` (bool)
  Whether to include soft‐deleted models in the scan or ignore them.

* `event_hooks` (array)
  Classes or callables to run before/after each delete step.

---

## Troubleshooting & Limitations

* **Database Memory/Timeouts**:
  If one model has thousands of related children across many nested levels, eager loading everything at once may exceed memory or cause timeouts. Future versions may allow you to chunk or limit scans.

* **Cycles & Self‐References**:
  Version 1 does **not** handle cyclic relationships (A → B → A). You must ensure your DAG is acyclic, or else the scanner could enter an infinite loop. A future release will detect and break cycles.

* **Missing Type Hints**:
  If your relation method does not explicitly return `HasOne` or `HasMany`, the package will ignore that method. For example, a method returning a raw query builder (no type hint) won’t be discovered. Always use the correct Eloquent return type:

  ```php
  public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
  {
      return $this->hasMany(Comment::class);
  }
  ```

---

## Contributing & Issue Tracker

Found a bug? Have a feature request? Want to contribute enhancements? Please use the GitHub issue tracker:

**Issues / Pull Requests**:
[https://github.com/MuhammadObadaa/eloquent-relation-guard/issues](https://github.com/MuhammadObadaa/eloquent-relation-guard/issues)

### How to Contribute

1. **Fork** the repository.
2. **Create a new branch** (`feature/awesome-thing` or `bugfix/some-bug`).
3. **Write tests** (where appropriate) to cover your changes.
4. **Update Documentation** if you add features or change behavior.
5. **Submit a Pull Request** and reference any related issues.

---

## License

**Eloquent Relation Guard** package is open source, licensed under [MIT](LICENSE). Feel free to use, modify, and redistribute.

---

> *Happy deleting—safely!*