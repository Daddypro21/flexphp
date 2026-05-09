# ORM Guide — Cycle ORM in FlexPHP

FlexPHP uses [Cycle ORM 2.x](https://cycle-orm.dev/) as its database layer. Cycle ORM is a
data-mapper ORM that maps PHP objects to database rows without extending base classes.

---

## 1. Introduction

Cycle ORM core concepts:

| Concept            | Description                                                      |
|--------------------|------------------------------------------------------------------|
| **Entity**         | A plain PHP class annotated with `#[Entity]` / `#[Column]`      |
| **Repository**     | Reads entities from the database (extends `BaseRepository`)      |
| **EntityManager**  | Persists, updates, and deletes entities                          |
| **Schema**         | The compiled map of entity → table → columns                     |
| **DBAL**           | Database Abstraction Layer — runs raw queries and migrations      |

The `DatabaseManager` class (`src/Database/DatabaseManager.php`) wires everything together.

---

## 2. Defining an Entity with PHP 8 Attributes

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;

#[Entity(table: 'posts')]
#[Index(columns: ['slug'], unique: true)]
class Post
{
    #[Column(type: 'primary')]
    private int $id;

    #[Column(type: 'string', nullable: false)]
    private string $title;

    #[Column(type: 'string', nullable: false)]
    private string $slug;

    #[Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[Column(type: 'boolean', default: false)]
    private bool $published = false;

    #[Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and setters …
    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    // …
}
```

### `#[Entity]` options

| Option       | Description                                    | Default       |
|--------------|------------------------------------------------|---------------|
| `table`      | Database table name                            | Inferred from class name |
| `role`       | ORM role (string alias used internally)        | Class name    |
| `repository` | Custom repository class                        | Auto-generated |
| `mapper`     | Custom mapper class                            | `StdMapper`   |

---

## 3. Available Column Types and Annotations

### `#[Column]` options

| Option      | Type     | Description                                         |
|-------------|----------|-----------------------------------------------------|
| `type`      | string   | Column type (see table below)                       |
| `name`      | string   | Override the database column name                   |
| `nullable`  | bool     | Allow NULL in database (default: `false`)           |
| `default`   | mixed    | Default value                                       |
| `primary`   | bool     | Mark as primary key                                 |
| `unique`    | bool     | Add a unique index on this single column            |
| `unsigned`  | bool     | Unsigned integer (MySQL only)                       |
| `length`    | int      | Column length (for `string`, `char`)                |
| `precision` | int      | Decimal precision                                   |
| `scale`     | int      | Decimal scale                                       |

### Type reference

| Type alias     | PHP type                    | SQL type (MySQL)    |
|----------------|-----------------------------|---------------------|
| `primary`      | `int`                       | INT AUTO_INCREMENT  |
| `bigPrimary`   | `int`                       | BIGINT AUTO_INCREMENT |
| `boolean`      | `bool`                      | TINYINT(1)          |
| `integer`      | `int`                       | INT                 |
| `tinyInteger`  | `int`                       | TINYINT             |
| `bigInteger`   | `int`                       | BIGINT              |
| `string`       | `string`                    | VARCHAR(255)        |
| `char`         | `string`                    | CHAR                |
| `text`         | `string`                    | TEXT                |
| `longText`     | `string`                    | LONGTEXT            |
| `float`        | `float`                     | FLOAT               |
| `double`       | `float`                     | DOUBLE              |
| `decimal`      | `string`                    | DECIMAL             |
| `datetime`     | `\DateTimeImmutable`        | DATETIME            |
| `date`         | `\DateTimeImmutable`        | DATE                |
| `time`         | `string`                    | TIME                |
| `timestamp`    | `\DateTimeImmutable`        | TIMESTAMP           |
| `json`         | `array`                     | JSON / TEXT         |
| `uuid`         | `string`                    | CHAR(36)            |
| `enum`         | `string`                    | ENUM(…)             |
| `binary`       | `string`                    | BLOB                |

---

## 4. Relationships

### HasOne

```php
use Cycle\Annotated\Annotation\Relation\HasOne;

#[Entity(table: 'users')]
class User
{
    #[Column(type: 'primary')]
    private int $id;

    #[HasOne(target: Profile::class)]
    private ?Profile $profile = null;

    public function getProfile(): ?Profile { return $this->profile; }
    public function setProfile(Profile $profile): self { $this->profile = $profile; return $this; }
}
```

### HasMany

```php
use Cycle\Annotated\Annotation\Relation\HasMany;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[Entity(table: 'users')]
class User
{
    #[Column(type: 'primary')]
    private int $id;

    #[HasMany(target: Post::class)]
    private Collection $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    public function getPosts(): Collection { return $this->posts; }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
        }
        return $this;
    }
}
```

### BelongsTo

```php
use Cycle\Annotated\Annotation\Relation\BelongsTo;

#[Entity(table: 'posts')]
class Post
{
    #[Column(type: 'primary')]
    private int $id;

    #[BelongsTo(target: User::class)]
    private User $author;

    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): self { $this->author = $author; return $this; }
}
```

### ManyToMany

```php
use Cycle\Annotated\Annotation\Relation\ManyToMany;
use Cycle\Annotated\Annotation\Relation\Pivoted\PivotedCollection;

#[Entity(table: 'posts')]
class Post
{
    #[Column(type: 'primary')]
    private int $id;

    /** @var PivotedCollection<Tag> */
    #[ManyToMany(target: Tag::class, through: PostTag::class)]
    private PivotedCollection $tags;

    public function __construct()
    {
        $this->tags = new PivotedCollection();
    }

    public function getTags(): PivotedCollection { return $this->tags; }
}

/** Pivot entity */
#[Entity(table: 'post_tag')]
class PostTag
{
    #[Column(type: 'integer')]
    private int $postId;

    #[Column(type: 'integer')]
    private int $tagId;
}
```

---

## 5. Using BaseRepository

Extend `FlexPHP\Database\BaseRepository` for your entities:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;
use FlexPHP\Database\BaseRepository;

/** @extends BaseRepository<Post> */
class PostRepository extends BaseRepository
{
    protected string $entityClass = Post::class;

    /** Custom finder: published posts ordered by date. */
    public function findPublished(int $limit = 20): array
    {
        return $this->select()
            ->where('published', true)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->fetchAll();
    }

    /** Search by title. */
    public function search(string $query): array
    {
        return $this->select()
            ->where('title', 'LIKE', "%{$query}%")
            ->fetchAll();
    }
}
```

### Injecting via DI

Register your repository in a service provider or rely on auto-wiring:

```php
// Auto-wiring works if ORM is bound in the container:
public function __construct(private PostRepository $posts) {}
```

### Using built-in methods

```php
// Find by primary key
$post = $this->posts->findById(1);

// Find all
$all = $this->posts->findAll();

// Find with conditions
$active = $this->posts->findBy(['published' => true]);

// Find one
$post = $this->posts->findOneBy(['slug' => 'hello-world']);

// Count
$total = $this->posts->count(['published' => true]);

// Paginate (page 2, 15 per page)
$page = $this->posts->paginate(2, 15, ['published' => true]);
// Returns: ['data' => [...], 'total' => 100, 'page' => 2, 'per_page' => 15, 'last_page' => 7]

// Save (insert or update)
$post = new Post();
$post->setTitle('Hello World')->setSlug('hello-world');
$this->posts->save($post);

// Delete
$this->posts->delete($post);
```

---

## 6. Raw Queries with the DBAL

Use `DatabaseManager::getDatabase()` to run raw SQL:

```php
$db = $this->databaseManager->getDatabase();

// Simple select
$rows = $db->select()
    ->from('posts')
    ->where('published', true)
    ->orderBy('created_at', 'DESC')
    ->fetchAll();

// Raw query
$count = $db->query('SELECT COUNT(*) FROM posts WHERE published = ?', [1])
    ->fetchColumn();

// Insert
$db->table('posts')->insertOne([
    'title'      => 'New post',
    'slug'       => 'new-post',
    'published'  => false,
    'created_at' => new \DateTimeImmutable(),
]);

// Update
$db->table('posts')
    ->update(['published' => true])
    ->where('id', 5)
    ->run();

// Delete
$db->table('posts')
    ->delete()
    ->where('id', 5)
    ->run();
```

### Transactions

```php
$db->transaction(function (\Cycle\Database\DatabaseInterface $db) {
    $db->table('accounts')->update(['balance' => 900])->where('id', 1)->run();
    $db->table('accounts')->update(['balance' => 1100])->where('id', 2)->run();
});
```

---

## 7. Creating and Running Migrations

### Generate a migration file

```bash
php flex make:migration create_posts_table
# Created: database/migrations/2024_01_15_143022_create_posts_table.php
```

### Migration file structure

```php
<?php

use Cycle\Migrations\Migration;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        $this->table('posts')
            ->addColumn('id',         'primary')
            ->addColumn('title',      'string',  ['nullable' => false])
            ->addColumn('slug',       'string',  ['nullable' => false])
            ->addColumn('body',       'text',    ['nullable' => true])
            ->addColumn('published',  'boolean', ['default'  => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['slug'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('posts')->drop();
    }
}
```

### Run migrations

```bash
php flex migrate
# [2024_01_15_143022] create_posts_table ... done
```

### Check status

```bash
php flex migrate --status
# Pending:  2024_01_15_143022_create_posts_table
# Executed: 2024_01_10_090000_create_users_table
```

### Roll back

```bash
php flex migrate --rollback       # last batch
php flex migrate --rollback --all # all migrations
```

---

## 8. Seeding Data

Create a seeder in `database/seeders/`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use FlexPHP\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setName("User {$i}")
                 ->setEmail("user{$i}@example.com")
                 ->setPassword(password_hash('secret', PASSWORD_BCRYPT));

            $this->orm->getEntityManager()->persist($user);
        }

        $this->orm->getEntityManager()->run();
    }
}
```

### Register in `database/seeders/DatabaseSeeder.php`

```php
public function run(): void
{
    $this->call(UserSeeder::class);
    $this->call(PostSeeder::class);
}
```

### Run seeders

```bash
php flex db:seed
php flex db:seed --class=UserSeeder   # run a specific seeder
```

---

## 9. Multiple Database Connections

Configure additional connections in `config/database.php`:

```php
return [
    'default' => env('DB_DRIVER', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'app'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ],

        'analytics' => [
            'driver'   => 'postgres',
            'host'     => env('ANALYTICS_DB_HOST', '127.0.0.1'),
            'port'     => env('ANALYTICS_DB_PORT', 5432),
            'database' => env('ANALYTICS_DB_DATABASE', 'analytics'),
            'username' => env('ANALYTICS_DB_USERNAME', 'postgres'),
            'password' => env('ANALYTICS_DB_PASSWORD', ''),
        ],

        'cache' => [
            'driver'   => 'sqlite',
            'database' => storage_path('cache/cache.sqlite'),
        ],
    ],

    'entity_dirs' => [
        base_path('app/Models'),
    ],
];
```

### Accessing a specific connection

```php
$analyticsDb = $this->databaseManager->getDatabase('analytics');
$rows = $analyticsDb->select()->from('events')->fetchAll();
```

### Assigning an entity to a specific connection

```php
#[Entity(table: 'events', database: 'analytics')]
class Event
{
    // …
}
```
