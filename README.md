<p align="center"><img width="307" height="63" src="/art/logo.jpg" alt="Package logo"></p>
# Eloquent Relation Guard

**Scan, inspect, and (optionally) force‐delete Eloquent models along with their HasOne/HasMany branches—without relying on database‐level cascade or restrict rules.**

> OnDelete, you can walk through a model’s HasOne/HasMany relations (to any depth), get a nested “tree” of related IDs, check whether a model is safe to delete, or forcibly delete the entire sub‐tree in one shot.

---

## Installation

Require the package via Composer:

```bash
composer require mhdobd/eloquent-relation-guard

```

---

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="EloquentRelation\Guard\EloquentRelationGuardServiceProvider" --tag="config"
```
This will copy `config/eloquent-relation-guard.php` into your application’s `config/` directory.

---

## Usage

1. **Use the Trait in Your Model**
   Add the `HasRelationalDependencies` trait to any Eloquent model you want to protect/scan.

   ```php
   use Illuminate\Database\Eloquent\Model;
   use EloquentRelation\Guard\Traits\HasRelationalDependencies;

   class Post extends Model
   {
       use HasRelationalDependencies;

       /**
        * By default, all HasOne/HasMany relations will be scanned.
        * If you want to limit or specify nested branches, define:
        */
       protected array $scanRelations = [
           'comments',             // only scan Post::comments()
           'comments.replies',     // also scan replies under each comment
           'tags',                 // scan Post::tags() (if tags is a HasMany relationship)
           '*'                     // or simply, add all first-level relations
       ];
   }
   ```

2. **Check if a Record Can Be Deleted**

   ```php
   $post = Post::find(42);

   if ($post->canBeSafelyDeleted()) {
       // no related HasOne/HasMany records exist
       $post->delete();
   } else {
       // there are related records—perhaps alert the user
       // so you can alert the user with detailed related relations using $post->relationStructure
   }
   ```

3. **Get the Full Relation “Tree” (with IDs)**

   ```php
   $post = Post::find(42);

   // Pass a depth (integer) to limit how many levels deep to scan.
   // Use -1 for no depth limit, only memory and time one.
   $tree = $post->relationStructure(depth:2);
   // $tree will look like:
   // [
   //   'comments' => [
   //       'ids'   => [10, 11, 12],
   //       'model' => 'App\Models\Comment',
   //       'nested'=> [
   //           'replies' => [
   //               'ids'   => [101, 102],
   //               'model' => 'App\Models\CommentReply',
   //               'nested'=> [ /* … */ ],
   //           ],
   //       ],
   //   ],
   //   'tags' => [
   //       'ids'   => [5, 6],
   //       'model' => 'App\Models\Tag',
   //       'nested'=> [],
   //   ],
   // ]
   ```

4. **Force‐Delete a Model and All Related Records**

   ```php
   $post = Post::find(42);
   $deletedCount = $post->forceCascadeDelete();
   // This will delete all HasOne/HasMany descendants (ignoring DB foreign‐key rules)
   // and then delete the Post itself. $deletedCount is the total number of records removed.
   ```

5. **Console Command: `record:tree`**
   You can visualize a model’s relation tree (with IDs) from the CLI:

   ```bash
   php artisan record:tree Post 42 2
   ```

   * `Post`           : The model class name, it should be located in `app/models/` directory
   * `42`             : ID of the record to inspect
   * `2`              : Depth (how many nested levels to scan; use `-- -1` for unlimited)

   **Example Output:**

   ```
   Relation Tree for App\Models\Post (ID: 42) | Depth: 2
   ├── comments (App\Models\Comment): [10, 11, 12]
   │   └── replies (App\Models\CommentReply): [101, 102]
   └── tags (App\Models\Tag): [5, 6]
   ```

---

## TODO

* [ ] Add support for **soft deletes** (detecting `deleted_at` and deciding whether to include or ignore soft‐deleted records).
* [ ] Allow **configurable thresholds** for maximum nodes/depth before aborting the scan.
* [ ] Add **event hooks** (e.g., `beforeDeleteGuarded`, `afterDeleteGuarded`) so users can tap into the delete process.
* [ ] Provide **Laravel Nova/Filament** integrations for visual “Are you sure?” modals.
* [ ] Mark visited classes when **DFS over them** so it can avoid cyclic database relations.
* [ ] Write **feature tests** and include sample migrations/seeders.
* [ ] Add **Complexity Details** in documentation file.
* [ ] Optimize **Time, Memory, and DB Query** complexity over core package logic implementation.

---

## Contributing

If you discover bugs, have feature requests, or want to contribute code, please use the GitHub issue tracker:

> **Issues & Contributions**:
> [https://github.com/MuhammadObadaa/eloquent-relation-guard/issues](https://github.com/MuhammadObadaa/eloquent-relation-guard/issues)

Feel free to fork the repository, open a PR, and follow these basic guidelines:

1. Fork the repo and create a new branch for your feature/fix.
2. Write at least one test (where applicable) demonstrating the expected behavior.
3. Update `CHANGELOG.md` and `README.md` if you add or change functionality.
4. Submit a pull request—describe what you changed and why.

---

## License

**Eloquent Relation Guard** package is open source, licensed under [MIT](LICENSE). Feel free to use, modify, and redistribute.