<?php
// scripts/make_migration.php
// Usage: composer run make:migration -- "create_users_table"
//        composer run make:migration -- "add_last_login_to_users"

if (!isset($argv[1])) {
    fwrite(STDERR, "Usage: php make_migration.php <name>\n");
    exit(1);
}

$name = trim($argv[1]);
$stamp = date('YmdHis');

$filename = "{$stamp}_" . strtolower(preg_replace('/\s+/', '_', $name)) . ".php";
$path = getcwd() . '/migrations/' . $filename;

// Ensure migrations directory exists
if (!is_dir(dirname($path))) {
    mkdir(dirname($path), 0777, true);
}

// Build class name
$studly = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($name))));
$class  = "M{$stamp}_{$studly}";

// Migration stub (SQL left empty for you to fill)
$stub = <<<PHP
<?php

use Handlr\Database\Migrations\BaseMigration;

class {$class} extends BaseMigration
{
    public function up(): void
    {
        // Write SQL here
        // \$this->exec("...");
    }

    public function down(): void
    {
        // Revert the change
        // \$this->exec("...");
    }
}

PHP;

file_put_contents($path, $stub);

echo "✔ Created migration: migrations/{$filename}\n";
